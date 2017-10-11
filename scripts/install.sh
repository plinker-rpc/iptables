#!/bin/bash

#
# Post installs, Nginx+PHP-fpm, Plinker server and tasks runner.
#

#
set -e
export DEBIAN_FRONTEND=noninteractive
export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'
export HOME='/root'

#
# Generate plinker keys
plinker_pub=$(date +%s%N | sha256sum | base64 | head -c 32 ; echo)
sleep 1
plinker_pri=$(date +%s%N | sha256sum | base64 | head -c 32 ; echo)

# System Dependencys
setup_system() {
    #
    # set timezone
    echo "Europe/London" > /etc/timezone
    dpkg-reconfigure -f noninteractive tzdata >/dev/null 2>/dev/null
    #
    # Update System
    sudo apt-get update
    sudo apt-get -yq upgrade
    #
    # Install system packages
    sudo apt-get -yq install curl wget
    sudo apt-get -yq install unzip
    sudo apt-get -yq install git
    sudo apt-get -yq install nano
    sudo apt-get -yq install htop
}

# Nginx
install_nginx() {
    #
    # Install Apache2
    sudo apt-get -yq install nginx openssl
    #
    # Empty the webroot
    if [ -f /var/www/html/index.html ] ; then
        sudo rm /var/www/html/index.html
    fi
    #
    if [ -f /var/www/html/index.nginx-debian.html ] ; then
        sudo rm /var/www/html/index.nginx-debian.html
    fi
    #
    # setup initial config, will be overwritten by task
    echo -e "
server {
    listen 88 default_server;
    listen [::]:88 default_server;
    root /var/www/html;
    #
    index index.php index.html index.htm;
    #
    server_name _;
    #
    location / {
        try_files \$uri \$uri/ =404;
	}
    #
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.0-fpm.sock;
    }
    #
    location ~ /\.ht {
        deny all;
    }
    #
    location ~ /.*\.db {
        deny all;
    }
}
" > /etc/nginx/sites-available/default
    #
    nginx -s reload
}

# Install PHP
install_php() {
    #
    # Import distibution variables 
    . /etc/lsb-release
    #
    # Is PHP5?
    if [ $DISTRIB_RELEASE = "12.04" ] || [ $DISTRIB_RELEASE = "14.04" ] || [ $DISTRIB_RELEASE = "15.04" ]; then
        phpver="5"
    fi
    #
    # Is PHP7?
    if [ $DISTRIB_RELEASE = "16.04" ] || [ $DISTRIB_RELEASE = "16.10" ] || [ $DISTRIB_RELEASE = "17.04" ] || [ $DISTRIB_RELEASE = "17.10" ]; then
        phpver="7"
    fi
    #
    # Install PHP5
    if [ $phpver = "5" ]; then
        #
        echo "Installing PHP5.5.9"
        sudo apt-get -yq install php5 php5-cli
        sudo apt-get -yq install php5-{curl,gd,mcrypt,json,mysql,sqlite,zip,opcache}
        
        sudo apt-get -yq install php5-fpm
        #
        # enable mods
        sudo php5enmod mcrypt
    fi
    #
    # Install PHP7
    if [ $phpver = "7" ]; then
        #
        echo "Installing PHP7.1"
        sudo apt-get -yq install php7.1 php7.1-cli
        sudo apt-get -yq install php7.1-{mbstring,curl,gd,mcrypt,json,xml,mysql,sqlite,zip,opcache}
        sudo apt-get -yq install php7.1-fpm
    fi
}

# Install composer (globally)
install_composer() {
    #
    # Install composer
    sudo curl -sS https://getcomposer.org/installer | sudo php
    sudo mv composer.phar /usr/bin/composer
}

# Install Application
install_project() {
    #
    cd /var/www/html
    #
    composer require plinker/nginx
    #
    echo -e "<?php
require 'vendor/autoload.php';

/**
 * Plinker Server
 */
if (\$_SERVER['REQUEST_METHOD'] == 'POST') {

    /**
     * Plinker Config
     */
    \$plinker = [
        'public_key'  => '$plinker_pub',
        'private_key' => '$plinker_pri'
    ];
    
    /**
     * Plinker server listener
     */
    if (isset(\$_POST['data']) &&
        isset(\$_POST['token']) &&
        isset(\$_POST['public_key'])
    ) {
        // test its encrypted
        file_put_contents('./encryption-proof.txt', print_r(\$_POST, true));
        //
        \$server = new \Plinker\Core\Server(
            \$_POST,
            hash('sha256', gmdate('h').\$plinker['public_key']),
            hash('sha256', gmdate('h').\$plinker['private_key'])
        );
        exit(\$server->execute());
    }
}
" > /var/www/html/index.php
    #
    echo -e "<?php
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Forbidden: CLI script');
}

require 'vendor/autoload.php';

\$task = new Plinker\Tasks\Runner([
    'database' => [
        'dsn'      => 'sqlite:../database.db',
        'host'     => '',
        'name'     => '',
        'username' => '',
        'password' => '',
        'freeze'   => false,
        'debug'    => false,
    ],
    'debug'        => true,
    'sleep_time'   => 5,
    'pid_path'     => './pids'
]);

\$task->daemon('Queue', [
    'sleep_time' => 5
]);
" > /var/www/html/task.php
    #
    crontab -l | { cat; echo "@reboot while sleep 1; do cd /var/www/html && /usr/bin/php task.php ; done >/dev/null 2>&1"; } | crontab -
}

#
# Main 
#
main() {
    #
    setup_system
    #
    install_php
    #
    install_composer
    #
    install_nginx
    #
    install_project
    #
    echo "Install finished."
}

main
