FROM solr:6

#COPY solr.xml /opt/solr/server/solr/configsets
RUN mkdir -p /opt/solr/server/solr/configsets/social/conf
COPY socialSite /opt/solr/server/solr/configsets/socialconf

CMD solr-precreate social /opt/solr/server/solr/configsets/social