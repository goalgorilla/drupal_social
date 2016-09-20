#!/usr/bin/env bash
#
# Run the cron from this script
# This will be installed in your crontab during installation
#
# Expand path
PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/sbin
# Some vars
CRON_ROOT=/root/dev-scripts/cron
DRUPAL_ROOT=/var/www/html
DRUSH=`which drush`
# Current implementation of the cron. Can be another (EG: elysia-cron)
CRON=core-cron
# drush  must be installed.
if [ ! -f $DRUSH ]
then
  echo `date`" - DRUSH NOT FOUND: SKIPPING CRON"
  exit
fi

# the run_cron file must be there.
if [ ! -f $CRON_ROOT/run_cron ]
then
  echo `date`" - CRON TURNED OFF: SKIPPING CRON"
  exit
fi

# Run the cron.
echo `date`" - ALL GOOD: RUNNING CRON"

$DRUSH --root=$DRUPAL_ROOT $CRON
