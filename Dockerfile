FROM ubuntu:16.04

# Update Ubuntu
RUN apt-get update \

# Install system-packages
&& apt-get install -y debconf-utils\
    composer\
    wget\
    unzip\
    ant\
    sudo\
    curl\

# Install PHP
&& apt-get install -y php\
    php-cli\
    php-dev\
    php-curl\
    php-mysql

RUN useradd opus4
