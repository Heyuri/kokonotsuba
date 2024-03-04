<?php
return [
    /*
     * this is where you put in the creds for you main data base
     */
    'mysqlDB' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'username' => 'user',
        'password' => 'password',
        'databaseName' => 'kotatsuba', 
    ],
    /*
     * This is the directory to store your logs. OpenBSD runs httpd in a chroot at /var/www/ 
     * if you are not running OpenBSD then change this to something like.
     * /var/www/logs
     */
    'logDir' => '/logs', 
];