#!/bin/sh
if [ $(id -u) -ne 0 ]
then
	echo "Please run the script as root"
	exit 1;
fi

# User interaction required. Should we printf "y\n"?
pkg update
pkg install mariadb104-server mariadb104-client nginx-lite bind914 \
php73 php73-curl php73-extension php73-mysqli php73-mysqli php73-json php73-xml

sysrc mysql_enable="YES"
sysrc nginx_enable="YES"
sysrc named_enable="YES"
sysrc php_fpm_enable="YES"
