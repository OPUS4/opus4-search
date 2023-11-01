#!/usr/bin/env bash

SOLR_VERSION="9.4.0"
wget -q "https://www.apache.org/dyn/closer.lua/solr/solr/$SOLR_VERSION/solr-$SOLR_VERSION.tgz?action=download" -O - | tar -xz
cd solr-$SOLR_VERSION
./bin/solr start -force
./bin/solr create -c opus4 -force
cd server/solr/opus4/conf/
rm -f managed-schema schema.xml solrconfig.xml
ln -s ../../../../../conf/schema.xml schema.xml
ln -s ../../../../../conf/solrconfig.xml solrconfig.xml
cd ../../../../
./bin/solr restart -force
cd ..