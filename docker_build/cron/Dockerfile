FROM goalgorilla/drupal_social:latest
MAINTAINER devel@goalgorilla.com

# Install development related packages.
RUN apt-get update && apt-get install -y \
nano \
cron \
vim
# Install crontab
RUN echo '* * * * * /root/dev-scripts/cron/cron.sh >> /var/log/cron.log 2>>/var/log/cron.log' | crontab -

RUN chmod 777 /root/dev-scripts/cron/cron.sh
RUN chmod 777 /root/dev-scripts/cron/run_cron
# Add output file
RUN touch /var/log/cron.log
# Start cron daemon
CMD cron && tail -f /var/log/cron.log
