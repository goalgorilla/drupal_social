FROM drupal:8
MAINTAINER devel@goalgorilla.com

# Install packages.
RUN apt-get update && apt-get install -y \
  php-pclzip \
  mysql-client \
  git \
  ssmtp \
  nano \
  vim && \
  apt-get clean

ADD docker_build/prod/mailcatcher-ssmtp.conf /etc/ssmtp/ssmtp.conf

# Dockerhub currently runs on docker 1.8 and does not support the ARG command.
# Reset the logic after the dockerhub is updated.
# https://docs.docker.com/v1.8/reference/builder/
# ARG hostname=goalgorilla.com

RUN echo "hostname=goalgorilla.com" >> /etc/ssmtp/ssmtp.conf
RUN echo 'sendmail_path = "/usr/sbin/ssmtp -t"' > /usr/local/etc/php/conf.d/mail.ini

ADD docker_build/prod/php.ini /usr/local/etc/php/php.ini

# Install extensions
RUN docker-php-ext-install zip
RUN docker-php-ext-install bcmath

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

# Install Open Social via composer.
RUN rm -f /var/www/composer.lock
RUN rm -rf /root/.composer

ADD composer.json /var/www/composer.json
WORKDIR /var/www/
RUN composer install --prefer-dist --no-interaction --no-dev

WORKDIR /var/www/html/
RUN chown -R www-data:www-data *

# Unfortunately, adding the composer vendor dir to the PATH doesn't seem to work. So:
RUN ln -s /var/www/vendor/bin/drush /usr/local/bin/drush

RUN php -r 'opcache_reset();'

# Fix shell.
RUN echo "export TERM=xterm" >> ~/.bashrc
