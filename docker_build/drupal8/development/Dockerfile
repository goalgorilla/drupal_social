FROM goalgorilla/drupal_social:extended-dev-ci
MAINTAINER devel@goalgorilla.com

ADD php.ini /usr/local/etc/php/php.ini

# Xdebug.
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN sed -i '1 a xdebug.remote_autostart=true' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_mode=req' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_handler=dbgp' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_connect_back=1 ' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_port=9000' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_host=127.0.0.1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN sed -i '1 a xdebug.remote_enable=1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Contains the Mac hack to get the permissions to work for development.
# Set user 1000 and group staff to www-data, enables write permission.
# https://github.com/boot2docker/boot2docker/issues/581#issuecomment-114804894
RUN usermod -u 1000 www-data
RUN usermod -G staff www-data
