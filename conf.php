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
     * This is the directory to store your logs. 
     * its best to keep it outside of the web path
     * /var/www/logs
     */
    'timeZone' => '0', // Timezones, 0 is UTC. Example: '-4' for New York, or '9' for Japan
    'logDir' => '../logs', //also known as storage path
    'rootPath' => './', //dumb config
    'auditLog' => 'auditlog.txt',
    

];