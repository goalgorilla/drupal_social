#!/usr/bin/env bash

PROJECT_FOLDER=/var/www/html/profiles/contrib/social/tests/behat

/var/www/vendor/bin/behat --version

echo $PROJECT_FOLDER/config/behat.yml;

/var/www/vendor/bin/behat $PROJECT_FOLDER --config $PROJECT_FOLDER/config/behat.yml --tags "stability"
