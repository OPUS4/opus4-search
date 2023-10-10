# -*- mode: ruby -*-
# vi: set ft=ruby :

$software = <<SCRIPT
# Downgrade to PHP 8.1
apt-add-repository -y ppa:ondrej/php
apt-get -yq update
apt-get -yq install php8.1

# Install MYSQL
debconf-set-selections <<< "mysql-server mysql-server/root_password password root"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password root"
apt-get -yq install mysql-server

# Install required PHP packages
apt-get -yq install php8.1-dom
apt-get -yq install php8.1-mbstring
apt-get -yq install php8.1-intl
apt-get -yq install php8.1-gd
apt-get -yq install php8.1-mcrypt
apt-get -yq install php8.1-curl
apt-get -yq install php8.1-zip
apt-get -yq install php8.1-mysql

# Install Java
apt-get -yq install openjdk-11-jdk

# Install required tools
apt-get -yq install libxml2-utils
apt-get -yq install ant
SCRIPT

$composer = <<SCRIPT
cd /vagrant
bin/install-composer.sh
bin/composer update
SCRIPT

$solr = <<SCRIPT
cd /home/vagrant
mkdir -p "downloads"
cd downloads
SOLR_TAR="solr-$SOLR_VERSION.tgz"
if test ! -f "$SOLR_TAR"; then
  wget -q "https://dlcdn.apache.org/solr/solr/$SOLR_VERSION/$SOLR_TAR"
fi
tar xfz "$SOLR_TAR" -C /home/vagrant
cd /home/vagrant/solr-$SOLR_VERSION
mkdir -p server/solr/opus4/conf
echo name=opus4 > server/solr/opus4/core.properties
cd server/solr/opus4/conf/
if test ! -f "schema.xml"; then
  ln -s /vagrant/conf/schema.xml schema.xml
fi
if test ! -f "solrconfig.xml"; then
  ln -s /vagrant/conf/solrconfig.xml solrconfig.xml
fi
SCRIPT

$database = <<SCRIPT
/vagrant/vendor/bin/opus4db --adminpwd root --userpwd root --sqlpwd root
SCRIPT

$workspace = <<SCRIPT
cd /vagrant
ant prepare-workspace prepare-config
SCRIPT

$environment = <<SCRIPT
if ! grep "cd /vagrant" /home/vagrant/.profile > /dev/null; then
  echo "cd /vagrant" >> /home/vagrant/.profile
fi
if ! grep "PATH=/vagrant/bin" /home/vagrant/.bashrc > /dev/null; then
  echo "export PATH=/vagrant/bin:$PATH" >> /home/vagrant/.bashrc
fi
# Increase limits for Apache Solr
if ! grep "vagrant hard" /etc/security/limits.conf > /dev/null; then
  echo "vagrant hard nofile 65535" >> /etc/security/limits.conf
  echo "vagrant soft nofile 65535" >> /etc/security/limits.conf
  echo "vagrant hard nproc 65535" >> /etc/security/limits.conf
  echo "vagrant soft nproc 65535" >> /etc/security/limits.conf
fi
SCRIPT

$start = <<SCRIPT
cd /home/vagrant/solr-$SOLR_VERSION
./bin/solr start
SCRIPT

$help = <<SCRIPT
echo "Log into the VM using 'vagrant ssh' and logout using 'logout'."
echo "In VM use:"
echo "  'composer test' for running tests"
echo "  'composer update' to update dependencies"
echo "  'composer cs-check' to check coding style"
echo "  'composer cs-fix' to automatically fix basic style problems"
echo "You can access Solr in your browser."
echo "  http://localhost:9983"
SCRIPT

Vagrant.configure("2") do |config|
  config.vm.box = "bento/ubuntu-22.04"

  config.vm.network "forwarded_port", guest: 8983, host: 9983, host_ip: "127.0.0.1"

  ENV['SOLR_VERSION']="9.3.0"

  config.vm.provision "Install required software...", type: "shell", inline: $software
  config.vm.provision "Install Apache Solr...", type: "shell", privileged: false, inline: $solr, env: {"SOLR_VERSION" => ENV['SOLR_VERSION']}
  config.vm.provision "Setup environment...", type: "shell", inline: $environment
  config.vm.provision "Install Composer dependencies...", type: "shell", privileged: false, inline: $composer
  config.vm.provision "Prepare workspace...", type: "shell", privileged: false, inline: $workspace
  config.vm.provision "Create database...", type: "shell", inline: $database
  config.vm.provision "Start services...", type: "shell", privileged: false, run: "always", inline: $start, env: {"SOLR_VERSION" => ENV['SOLR_VERSION']}
  config.vm.provision "Information", type: "shell", privileged: false, run: "always", inline: $help
end
