FROM drupal:7.43-apache
MAINTAINER jordan@handsomedogstudio.com

RUN yes | pecl install xdebug \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_autostart=off" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_connect_back=on" >> /usr/local/etc/php/conf.d/xdebug.ini

RUN \
    curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    ln -s /usr/local/bin/composer /usr/bin/composer && \
    # echo "deb http://httpredir.debian.org/debian jessie main contrib" >> /etc/apt/sources.list && \
    apt-get update && \
    apt-get install git -y && \
    git clone https://github.com/drush-ops/drush.git /usr/local/src/drush && \
    ln -s /usr/local/src/drush/drush /usr/bin/drush && \
    cd /usr/local/src/drush && \
    git fetch origin && git checkout 8.x && composer install

RUN docker-php-ext-install opcache

RUN apt-get -y install mysql-client

RUN apt-get -y install libz-dev libmemcached-dev libmemcached11 libmemcachedutil2 build-essential \
    && pecl install memcached-2.2.0 \
    && echo extension=memcached.so >> /usr/local/etc/php/conf.d/memcached.ini \
    && apt-get remove -y build-essential libmemcached-dev libz-dev \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /tmp/pear

EXPOSE 22