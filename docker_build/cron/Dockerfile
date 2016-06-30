FROM goalgorilla/drupal_social:latest
MAINTAINER devel@goalgorilla.com

# Install development related packages.
RUN apt-get update && apt-get install -y \
nano \
cron \
vim

# Install crontab
ADD cron.sh /root/dev-scripts/cron/cron.sh
ADD run_cron /root/dev-scripts/cron/run_cron
RUN chmod 777 /root/dev-scripts/cron/cron.sh
RUN chmod 777 /root/dev-scripts/cron/run_cron

RUN echo '* * * * * /root/dev-scripts/cron/cron.sh >> /var/log/cron.log 2>>/var/log/cron.log' | crontab -

# Add output file
RUN touch /var/log/cron.log
# Start cron daemon
CMD cron && tail -f /var/log/cron.log
