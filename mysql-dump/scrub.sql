TRUNCATE TABLE cache;
UPDATE system SET STATUS = 1 WHERE name = 'stage_file_proxy';
REPLACE INTO variable SET name = 'stage_file_proxy_hotlink', value = 'i:1;';
REPLACE INTO variable SET name = 'stage_file_proxy_origin', value = 's:23:"http://jordangraham.com";';