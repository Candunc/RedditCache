#!/bin/sh
if [ $(id -u) -ne 0 ]
then
	echo "Please run the script as root"
	exit 1
fi

echo "This script will install RedditCache without further user input."
echo "Please read install.sh before confirming."

# Credits to Myrddin Emrys, ferhtgoldaraz
# https://stackoverflow.com/a/226724
# https://stackoverflow.com/a/15696250
read -p $'Do you wish to continue? [y/N]\n> ' yn
case $yn in
	[Yy]* ) break;;
*) exit 1;;
esac

# Prevent further prompts from pkg
export ASSUME_ALWAYS_YES=yes

# Ensure pkg is initialized and up to date, and install dependancies
pkg update
pkg install apg git mariadb104-server mariadb104-client nginx-lite bind914 \
php73 php73-curl php73-extensions php73-mysqli php73-json php73-xml

git clone https://github.com/Candunc/RedditCache.git /tmp/RedditCache
cd /tmp/RedditCache

# Credits to Juha Nurmela for the command
# https://forums.FreeBSD.org/threads/how-to-get-hostname-ip-address.54923/post-310136

IPv4=`ifconfig | awk '$1 == "inet" { print $2 }' | head -n 1`
IPv6=`ifconfig | awk '$1 == "inet6" { print $2 }' | head -n 2 | tail -n 1`

if [ $IPv6 != "fe80::1%lo" ]
then
	sed -i "" "s/#DISABLE_IPv6//g" ./named.hijack.db
	sed -i "" "s/{IPv6}/$IPv6/g" ./namedb/hijack.db
fi

sed -i "" "s/{IPv4}/$IPv4/g" ./namedb/hijack.db


# Patch Bind9 conf file
mv ./namedb/hijack.db /usr/local/etc/namedb/master/
mv /usr/local/etc/namedb/named.conf ./
patch < namedb/named.conf.patch
mv named.conf /usr/local/etc/namedb/

# Patch nginx conf file
mkdir /usr/local/etc/nginx/conf.d/
cp ./nginx/conf.d/* /usr/local/etc/nginx/conf.d/
mkdir /usr/local/etc/nginx/snippets/
cp ./nginx/snippets/self-signed.conf /usr/local/etc/nginx/snippets/
mv /usr/local/etc/nginx/nginx.conf ./
patch < ./nginx/nginx.conf.patch
mv ./nginx.conf /usr/local/etc/nginx/

# Patch php-fpm conf file
mv /usr/local/etc/php-fpm.d/www.conf ./
patch < ./php-fpm.d/www.conf.patch
mv ./www.conf /usr/local/etc/php-fpm.d/

# Set up our PHP files
mkdir /var/www/
mkdir /var/www/redd.it/
cp ./php/* /var/www/redd.it/

# Enable required services
sysrc mysql_enable="YES"
sysrc nginx_enable="YES"
sysrc named_enable="YES"
sysrc php_fpm_enable="YES"

# Set up database
service mysql-server start
pass=`apg -MCLN -a 1 -m 16 -n 1`
sed -i "" "s/{password}/$pass/g" mysql/setup.sql
mysql < ./mysql/setup.sql

mv /var/www/redd.it/config.example.php /var/www/redd.it/config.php
sed -i "" "s/username/php/g" /var/www/redd.it/config.php
sed -i "" "s/password/$pass/g" /var/www/redd.it/config.php

# We are now done with the git repository.
cd $HOME
rm -r /tmp/RedditCache

# Set up MITM certs

mkdir /usr/local/etc/ssl/myCA
chmod 700 /usr/local/etc/ssl/myCA
cd /usr/local/etc/ssl/myCA

# Generate MITM Certificate

# https://superuser.com/a/226229
openssl req -new -newkey rsa:4096 -days 3650 \
	-nodes -x509 -subj "/C=CA/ST=BC/L=Kelowna/O=RedditCache/CN=RedditCache" \
	-keyout myCA.key -out myCA.crt

openssl req -new -newkey rsa:4096 -days 3650 \
	-nodes -subj "/C=CA/ST=BC/L=Kelowna/O=RedditCache/CN=*.redd.it" \
	-keyout redd.it.key -out redd.it.csr

openssl x509 -req -in redd.it.csr -CA myCA.crt -CAkey myCA.key -CAcreateserial \
	-out redd.it.crt -days 3650 -sha256

mkdir /var/www/cert
cp myCA.crt /var/www/cert/myCA.pem
cp redd.it.crt redd.it.key /usr/local/etc/ssl/

# We should be complete!
service php-fpm start
service named start
service nginx start
exit 0
