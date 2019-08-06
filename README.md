# RedditCache

RedditCache utilizes Nginx, PHP 7, MariaDB, and Bind9 to MITM the reddit mobile app and store commonly-consumed media. It is built to be run as a lightweight FreeBSD container.

As a MITM cache, we do need control over both the client's DNS server and install a self-signed certificate authority. However, after the initial installation it should be relatively maintenance free, as it the program will automatically purge unused media to minimize wasted space.

## Installation 

* curl -O https://raw.githubusercontent.com/Candunc/RedditCache/master/install.sh
* chmod +x install.sh
* ./install.sh

That will automagically install RedditCache and start up all the services. Set up the clients DNS to point to the server and navigate to http://<SERVER_IP>/myCA.pem and install the root certificate. Then, browse reddit as normal. Videos will take longer to initially load, as it waits for the entire file to cache rather than just requesting the initial range, but subsequent reloads will be instantaneous. 
