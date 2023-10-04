#!/usr/bin/env bash

ant download-solr -DsolrVersion=9.3.0 -DdownloadDir=./downloads
cd solr-9.3.0
./bin/solr start -force
./bin/solr create -c opus4 -force
cd server/solr/opus4/conf/
rm -f managed-schema schema.xml solrconfig.xml
ln -s ../../../../../conf/schema.xml schema.xml
ln -s ../../../../../conf/solrconfig.xml solrconfig.xml
cd ../../../../
./bin/solr restart -force
cd ..