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
     * its best to keep it outside of the project path.
     * /var/www/logs
     */
    'timeZone' => 'UTC',
    'logDir' => '../logs', //also known as storage path
    'rootPath' => './', //dumb config
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