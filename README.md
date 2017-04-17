## Get up and running

### Requirements for local development

- Docker Toolbox
- Git
- Entry in /etc/hosts pointing docker.vm at 192.168.99.100
- Creation of a docker-bash file as outlined here:

### Get the project running

1. Clone this repository to a local directory under your home directory
2. Open "Docker Quickstart Terminal" to start the Docker Machine
3. Using the Docker Machine shell "cd" into your project directory root from step 1
4. Type "docker-compose up -d" to start the configured containers
5. Assuming the IP address of your VM is 192.168.99.100 (default for Docker Toolbox) then you should be able to access
the site using this IP in your browser

### Initial setup

Setup Notes:
The Drupal files directories are mapped to ./app/default/files and ./app/default/files-private. If you need to pull in
files from a canonical source, put them in those folders and they will be mapped into the container in the correct
location.
If you run into permission problems with files directories in Drupal, run the prep.sh file in this repository.
The MySQL data directory is stored outside the container in ./mysql-data. This folder is git-ignored and not in the
repository. On initial checkout it will not exist. Once you start the docker containers you will need to import the
canonical DB in with the following commands:

    docker exec -i -t handsomedog_mysql_1 bash
    mysql -uroot -pnegus drupal < /var/lib/mysql-dump/default.sql

Once your site is up and running, killing and starting the containers will not remove any data and files. Only on fresh
install, or the import of another db will you need to do the steps above.

If you wish to export your db, run the following comamand:

    mysqldump -uroot -pnegus drupal > /var/lib/mysql-dump/mydbbackup.sql

It will be placed in the ./mysql-dump folder, but do not commit it to the repository.

### Configure PHPStorm for debugging

- Under File > Settings > Languages and Frameworks > Debug make sure the debug port is set to 9000 and "can accept
incoming connections" is enabled
- Under File > Settings > Languages and Frameworks > Debug > DBGp Proxy make sure the host is configured for the bridged
host of the docker setup (172.17.0.1 by default) and the port is set to 9000
- Under File > Settings > Languages and Frameworks > Servers setup a new server configured with the host (192.168.99.100
for Docker Machine by default) with port 80, make sure XDebug is setup as the debugger.
- Make sure "use path mappings is checked" and you map the themes and modules directory to their corresponding
directories in the container
    - app/default -> /var/www/html/sites/default
    - app/libraries -> /var/www/html/libraries
    - app/modules -> /var/www/html/modules
    - app/themes -> /var/www/html/themes
    - drupal8-src -> /var/www/html
- Start the server with the debugging parameter specified by clicking the "Run" menu and then "Debug [project]" (or on
PC Shift + F9).
- Make sure PHPStorm is listening for remote debugging connections by clicking Run > Start Listening for PHP Debug
Connections (you don't need to do this if you use the step above).

### Development workflow

Please follow the Github Flow workflow for all feature development. Checkins directly to master are a no-no.
