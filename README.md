# RedditCache

RedditCache utilizes Nginx, PHP 7, MariaDB, and Bind9 to MITM the reddit mobile app and store commonly-consumed media. It is built to be run as a lightweight FreeBSD container.

As a MITM cache, we do need control over both the client's DNS server and install a self-signed certificate authority. However, after the initial installation it should be relatively maintenance free, as it the program will automatically purge unused media to minimize wasted space.

## Installation 

* todo, eventually will be a simple install.sh script

