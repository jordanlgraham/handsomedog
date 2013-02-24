<?php

@ini_set("default_charset", "utf-8");
@ini_set("display_errors", 0);

class Comcure {

    protected $config = array();
    private $compression = array(
        'type' => false
    );
    private $decompression = array(
        'gzip' => 'gzdecode',
        'bzip' => 'bzdecompress'
    );

    protected function __construct($config) {
        $this->config = $config;
        if(get_magic_quotes_gpc()) {
	    $_GET = array_map(array($this, '_stripslashes'), $_GET);
	    $_POST = array_map(array($this, '_stripslashes'), $_POST);
	}
        $this->environment();
        register_tick_function(array(&$this, 'tick_handler'), true);
    }

    protected function _stripslashes($value){
	if(is_array($value)){
	    return array_map(array($this, '_stripslashes'), $value);
	}else{
	    return stripslashes($value);
	}
    }

    private function environment() {
        if (version_compare(PHP_VERSION, '5.1.0', '<')) {
            print $this->encode(
                            array(
                                "error" => "PHP version - " . PHP_VERSION,
                            )
                    );
            exit;
        }
        if (!extension_loaded($this->config['extension'])) {
            print $this->encode(
                            array(
                                "error" => "PHP installation appears to be missing the `{$this->config['extension']}` extension.",
                            )
                    );
            exit;
        }
        if (!@ini_get('safe_mode') && function_exists('set_time_limit')
                && strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
            @set_time_limit($this->config['time']);
        } elseif (@ini_get('max_execution_time') < $this->config['time']) {
            @ini_set('max_execution_time', $this->config['time']);
        }

        if ($this->config['compress'] == 'gzip') {
            if (!function_exists('gzencode')) {
                print $this->encode(
                                array(
                                    "error" => "The {$this->config['compress']} skipping compression (gzencode unavailable)",
                                )
                        );
                exit;
            }
            $this->compression['type'] = 'gzencode';
        }

        if ($this->config['compress'] == 'bzip') {
            if (!function_exists('bzcompress')) {
                print $this->encode(
                                array(
                                    "error" => "The {$this->config['compress']} skipping compression (bzcompress unavailable)",
                                )
                        );
                exit;
            }
            $this->compression['type'] = 'bzcompress';
        }
        return true;
    }

    protected function checkSum() {
        return md5(md5_file(basename(__FILE__)) . md5_file("config.php"));
    }

    private function getMemorySize() {
        $value = @ini_get('memory_limit');
        $last = strtolower($value[strlen($value) - 1]);
        switch ($last) {
            case 'g':
                $value = substr($value, 0, (strlen($value) - 1));
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value = substr($value, 0, (strlen($value) - 1));
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value = substr($value, 0, (strlen($value) - 1));
                $value *= 1024;
        }
        return $value;
    }

    public function tick_handler() {
        $current = @memory_get_usage();
        $value = $this->getMemorySize();
        $delta = (($value - $current) / $value) * 100;
        $threshold = 98;
        if ($delta < $threshold) {
            $inc = $value * 1.2;
            $total = $inc + $value;
            @ini_set('memory_limit', (int) $total);
        }
    }

    private function compress($buffer) {
        return $this->compression['type'] ? $this->compression['type']($buffer) : $buffer;
    }

    public function encode($buffer) {
        return urlencode($this->compress(serialize($buffer)));
    }

    protected function sysGetTempDir() {
        if (!function_exists('sys_get_temp_dir')) {
            $temp = tempnam(__FILE__, '');
            if (file_exists($temp)) {
                unlink($temp);
                return dirname($temp);
            }
        }
        return sys_get_temp_dir();
    }

    private function gzdecode($data) {
        $temp = tempnam($this->sysGetTempDir(), 'gz');
        file_put_contents($temp, $data);
        ob_start();
        readgzfile($temp);
        $data = ob_get_clean();
        unlink($temp);
        return $data;
    }

    protected function decompress($buffer) {
        if ($this->config['compress'] == 'gzip') {
            if (!function_exists('gzdecode')) {
                return $this->gzdecode($buffer);
            }
            return gzdecode($buffer);
        }
        return empty($this->config['compress']) ? $buffer : $this->decompression[$this->config['compress']]($buffer);
    }

    protected function toInteger($version) {
        return preg_match("/^(\d+)\.(\d+)\.(\d+)/", $version, $matches) ? sprintf("%d%02d%02d", $matches[1], $matches[2], $matches[3]) : 0;
    }

}

class Mysql extends Comcure {

    private $link = null;
    private $functions = array(
        "connect" => "mysql_connect",
        "pconnect" => "mysql_pconnect",
    );
    private $result = null;

    protected function __construct($config) {
        parent::__construct($config);
        $this->connect();
    }

    private function connect() {
        $connect = $this->functions['connect'];
        if ($this->config['connection']['persistent']) {
            $connect = $this->functions['pconnect'];
        }

        try {
            $this->link = @$connect("{$this->config['host']}:{$this->config['port']}", $this->config['user'], $this->config['password']);
            if ($this->link == null) {
                print $this->encode(
                                array(
                                    "error" => $this->error()
                                )
                        );
                exit;
            }
            $database = @mysql_select_db($this->config['database'], $this->link);
            if (!$database) {
                print $this->encode(
                                array(
                                    "error" => $this->error()
                                )
                        );
                exit;
            }
        } catch (Exception $e) {
            print $this->encode(
                            array(
                                "error" => $e->getMessage()
                            )
                    );
            exit;
        }
        return $this->link;
    }

    protected function clientEncoding() {
        return mysql_client_encoding($this->link);
    }

    protected function collation($charset) {
        if (function_exists('mysql_set_charset')) {
            mysql_set_charset($charset, $this->link);
        } else {
            $this->query("SET NAMES `$charset`");
        }
    }

    protected function query($query, $mode = "store", $skipError = false) {
        if ($mode == "unbuffered") {
            $this->result = @mysql_unbuffered_query($query, $this->link);
        } else {
            $this->result = @mysql_query($query, $this->link);
        }
        if ($this->result == null && $skipError == false) {
            print $this->encode(
                            array(
                                "error" => $this->error()
                            )
                    );
            exit;
        }
    }

    private function error() {
        return mysql_error();
    }

    protected function record() {
        return ($this->result) ? mysql_fetch_object($this->result) : false;
    }

    protected function fetch() {
        return ($this->result) ? mysql_fetch_row($this->result) : false;
    }

    protected function freeResult() {
        return ($this->result) ? mysql_free_result($this->result) : false;
    }

    protected function numFields() {
        return ($this->result) ? mysql_num_fields($this->result) : 0;
    }

    protected function fieldFlags($offset) {
        return ($this->result) ? mysql_field_flags($this->result, $offset) : false;
    }

    protected function fetchField($num) {
        return ($this->result) ? mysql_fetch_field($this->result, $num) : false;
    }

    protected function getAllowedPacketSize() {
        $this->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        return $this->record()->Value;
    }

    protected function version() {
        return $this->toInteger(mysql_get_server_info());
    }

}

class Dump extends Mysql {

    private $info = array();
    private $buffer = array();
    private $args;

    public function __construct($config) {
        parent::__construct($config);
        if (count($_POST)) {
            $values = array_values($_POST);
            $name = array_shift($values);
            if (isset($this->config['token'])
                    && $this->config['token'] != md5(basename(dirname(__FILE__)) . urldecode($_POST['key']))) {
                print $this->encode(
                                array(
                                    'error' => "Invalid token."
                                )
                        );
                exit;
            }
            $checkSum = $this->checkSum();
            if ($_POST['md5'] != $checkSum) {
                print $this->encode(
                                array(
                                    'error' => "Invalid checksum($checkSum) for package Comcure."
                                )
                        );
                exit;
            }
            if (method_exists($this, (string) $name)) {
                $method = new ReflectionMethod($this, $name);
                if ($method->isPublic()) {
                    if (isset($_POST['args'])) {
                        $this->args = unserialize($_POST['args']);
                    }
                    call_user_func(array($this, $name));
                }
            }
        }
    }

    public function allowedPacketSize() {
        print $this->getAllowedPacketSize();
    }

    public function encoding() {
        return $this->clientEncoding();
    }

    public function structure() {
        $this->query("SHOW TABLE STATUS", "store", $this->args->skipError);
        while ($row = $this->record()) {
            $this->info[] = $row;
        }
        return $this;
    }

    private function getRoutines($name) {
        $result = array();
        $name = strtoupper($name);
        $this->query("SHOW $name STATUS");
        while ($row = $this->record()) {
            if ($this->config['database'] == $row->Db
                    && $row->Type == $name) {
                $result[] = $row->Name;
            }
        }
        return $result;
    }

    public function routines() {
        if ($this->version() < 50114) {
            return;
        }
        foreach (array('PROCEDURE', 'FUNCTION') as $routine) {
            $info = $this->getRoutines($routine);
            foreach ($info as $name) {
                $this->query("SHOW CREATE $routine `$name`");
                while ($row = $this->record()) {
                    $this->buffer[] = array(
                        'name' => $name,
                        'type' => $routine,
                        'create' => $routine == 'FUNCTION' ? $row->{'Create Function'} : $row->{'Create Procedure'},
                    );
                }
            }
        }
        if (count($this->buffer)) {
            print $this->encode($this->buffer);
        }
    }

    public function events() {
        if ($this->version() > 50100) {
            $result = array();
            $this->query("SHOW EVENTS WHERE `Db`='{$this->config['database']}'");
            while ($row = $this->record()) {
                $result[] = $row->Name;
            }
            if (count($result)) {
                foreach ($result as $event) {
                    $this->query("SHOW CREATE EVENT $event");
                    $this->buffer[] = array(
                        'name' => $event,
                        'create' => $this->record()->{"Create Event"},
                    );
                }
                print $this->encode($this->buffer);
            }
        }
    }

    public function triggers() {
        if ($this->version() > 50114) {
            foreach ($this->structure()->info as $row) {
                $this->query("SHOW TRIGGERS FROM `{$this->config['database']}` LIKE '$row->Name'");
                while ($item = $this->record()) {
                    $this->query("SHOW CREATE TRIGGER `$item->Trigger`");
                    $this->buffer[] = array(
                        'name' => $row->Name,
                        'create' => $this->record()->{"SQL Original Statement"},
                    );
                }
            }
            if (count($this->buffer)) {
                print $this->encode($this->buffer);
            }
        }
    }

    public function scheme() {
        $scheme = array();
        $info = $this->structure()->info;
        if($this->args->skipError) {
            print $this->encode(
                            array(
                                "info" => $info,
                            )
                    );
        }
        if (is_array($info)) {
            foreach ($info as $row) {
                $this->query("SHOW CREATE TABLE `$row->Name`");
                $scheme[] = array(
                    $row->Name => $row->Comment == "VIEW" ? $this->record()->{"Create View"} : $this->record()->{"Create Table"}
                );
            }
            print $this->encode(
                            array(
                                "info" => $info,
                                "scheme" => $scheme,
                            )
                    );
        }
    }

    public function columns() {
        $columns = array();
        $this->query("SHOW COLUMNS FROM `{$this->args->table}`");
        $j = 0;
        while ($column = $this->record()) {
            $columns[$this->args->table][$j] = $column->Field;
            $j++;
        }
        print $this->encode($columns);
    }

    public function count() {
        $this->query("SELECT COUNT(*) AS `count` FROM `{$this->args->table}`");
        print serialize(
                        array(
                            "limit" => $this->args->limit,
                            "table" => array(
                                $this->args->table => $this->record()->count,
                            )
                        )
                );
    }

    private function meta() {
        $fields = array();
        $num = $this->numFields();
        for ($i = 0; $i < $num; $i++) {
            $fields[$i] = $this->fetchField($i);
        }
        return $fields;
    }

    public function rows() {
        $values = array();
        $flags = array();
        $this->collation($this->args->charset);
        $this->query("SELECT * FROM `{$this->args->table}` LIMIT {$this->args->offset}, {$this->args->limit}", "unbuffered");
        $meta = $this->meta();
        for ($j = 0; $j < $this->numFields(); $j++) {
            $flags[$j] = $this->fieldFlags($j);
        }
        declare (ticks = 1) {
            if ($this->args->count) {
                $i = 0;
                while ($item = $this->fetch()) {
                    $values[$i++] = $item;
                    unset($item);
                }
            }
            $this->freeResult();
            print $this->encode(
                            array(
                                $this->args->table => array(
                                    'values' => $values,
                                    'flags' => $flags,
                                    'meta' => $meta,
                                )
                            )
                    );
        }
    }

    public function restore() {
        $temp = null;
        if (isset($this->args->sql['charset'])) {
            $this->collation($this->args->sql['charset']);
        }
        $query = $this->decompress(base64_decode($this->args->sql['query']));
        if (isset($this->args->sql['file'])) {
            $temp = $this->sysGetTempDir() . "/" . $this->args->sql['file'];
        }
        if (isset($this->args->sql['action']) && $this->args->sql['action'] == 'read') {
            if (file_exists($temp)) {
                $query = file_get_contents($temp);
                unlink($temp);
            }
        }
        if (isset($this->args->sql['action']) && $this->args->sql['action'] == 'write') {
            file_put_contents($temp, $query, FILE_APPEND);
        } else {
            $this->query($query);
        }
        print $this->encode(
                        array(
                            'length' => strlen($query)
                        )
                );
        unset($query);
    }

    public function check() {
        print 1;
    }

}

$config = @include "config.php";
if (!isset($config) || !is_array($config)) {
    print serialize(
                    array(
                        "error" => "The configuration file config.php is missing or corrupt!"
                    )
            );
    exit;
}
new Dump($config);