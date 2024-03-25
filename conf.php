<?php
return [
    /*
     * this is where you put in the creds for you main data base
     */
    'mysqlDB' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'username' => 'kodomo',
        'password' => 'kodomo',
        'databaseName' => 'boarddb', 
    ],
    /*
     * its best to keep logs just outside of the web path.
     * so a place like this. /var/www/logs
     */
    'timeZone' => 'UTC',
    'logDir' => '../logs',
    'rootPath' => './',
    'auditLog' => 'auditlog.txt',
    'passwordSalt'=> 'abc123!?_',

    /*
     * This section should end up in its own board config file.
     */
    'fileConf' =>[
        'allowedMimeTypes'=> [
            'image/jpeg', 
            'image/png', 
            'image/gif'
        ],
        'maxFileSize'=> 5242880, // 5mb
        'maxFiles'=> 3,
    ],
    'cookieExpireTime'=> time()+7*24*3600, // 7days from the curent time
    'allowQuoteLinking'=> false, // link to post and post on other boards.
    'autoEmbedLinks'=> true, // links will be turned into hyperlinks
    'defualtName'=> '',
    'defaultEmail'=> '',
    'defaultSubject'=> '',
    'defaultComment'=> '',

];