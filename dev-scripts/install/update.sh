#!/bin/bash

# Script to update Drupal in the docker container.
cd /var/www/html/;

drush -y cr
drush -y updatedb
drush -y entup
drush -y fra --bundle=social
