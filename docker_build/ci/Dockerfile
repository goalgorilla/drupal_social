FROM goalgorilla/drupal_social:latest
MAINTAINER devel@goalgorilla.com

ADD php.ini /usr/local/etc/php/php.ini

### CI SPECIFIC - START ###

# Install composer dependencies.
RUN composer install --prefer-dist --no-interaction

# Unfortunately, adding the composer vendor dir to the PATH doesn't seem to work. So:
RUN ln -s /root/.composer/vendor/bin/behat /usr/local/bin/behat
RUN ln -s /root/.composer/vendor/bin/phpunit /usr/local/bin/phpunit
RUN ln -s /var/www/vendor/bin/drupal /usr/local/bin/drupal

### CI SPECIFIC - END ###

RUN php -r "opcache_reset();"
