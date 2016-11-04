#!/bin/bash

# Install script for in the docker container.
cd /var/www/html/;

LOCAL=$1
NFS=$2
DEV=$3

fn_sleep() {
  if [[ $LOCAL != "nopause" ]]
  then
     sleep 5
  fi
}

# Set the correct settings.php requires dev-scripts folder to be mounted in /var/www/dev-scripts/.
chmod 777 /var/www/html/sites/default

if [ -f /var/www/html/sites/default/settings.php ]; then
  chmod 777 /var/www/html/sites/default/settings.php
  rm /var/www/html/sites/default/settings.php
fi

if [ -f /var/www/html/sites/default/default.settings.php ]; then
  chmod 777 /var/www/html/sites/default/default.settings.php
  rm /var/www/html/sites/default/default.settings.php
fi

cp /var/www/dev-scripts/install/default.settings.php /var/www/html/sites/default/default.settings.php

drush -y site-install social --db-url=mysql://root:root@db:3306/social --account-pass=admin install_configure_form.update_status_module='array(FALSE,FALSE)';
fn_sleep
echo "installed drupal"
if [[ $NFS != "nfs" ]]
  then
    chown -R www-data:www-data /var/www/html/
    fn_sleep
    echo "set the correct owner"
  fi

php -r 'opcache_reset();';
fn_sleep
echo "opcache reset"
chmod 444 sites/default/settings.php

# Create private files directory.
if [ ! -d /var/www/files_private ]; then
  mkdir /var/www/files_private;
fi
chmod 777 -R /var/www/files_private;
chmod 777 -R sites/default/files

fn_sleep
echo "settings.php and files directory permissions"
drush pm-enable social_demo -y
fn_sleep
echo "enabled module"
drush cc drush
drush sda file user group topic event eventenrollment post comment # Add the demo content
#drush sdr eventenrollment topic event post comment group user file # Remove the demo content
drush pm-uninstall social_demo -y
fn_sleep
echo "flush image caches"
drush cc drush
drush image-flush --all
fn_sleep
echo "Run activity queues"
drush queue-run activity_logger_message
drush queue-run activity_creator_logger
drush queue-run activity_creator_activities
fn_sleep
echo "Rebuild node access"
drush php-eval 'node_access_rebuild()';

# Add 'dev; to your install script as third argument to enable
# development modules e.g. pause nfs dev.
if [[ $DEV == "dev" ]]
then
  drush en social_devel -y
fi
