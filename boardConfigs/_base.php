<?php
// this is the defualt configs. new board configs will shadow this config file. 
// so if new board config is missing something, it will have to be in here.
// deleting a config from this file could break the system.

return [

    'boardTitle' => "no title",
    'boardSubTitle' => "no discription",
    'boardLogoPath' => "", //leave blank for no logo.

    'navLinksLeft'=> [
        'cgi' => 'https://example.net/cgi-bin/',
        'upload' => 'https://up.example.net/',
    ],
    'navLinksRight'=> [
        'wiki' => 'https://wiki.example.net/'
    ],

    'fileConf' =>[
        'allowedMimeTypes'=> [
            'image/jpeg', 
            'image/png', 
            'image/gif'
        ],
        'maxFileSize'=> 5242880, // 5mb
        'maxFiles'=> 3,
    ],
    
    'allowRuffle' => true,
    'cookieExpireTime'=> time()+7*24*3600, // 7days from the curent time
    'allowQuoteLinking'=> false, // link to post and post on other boards.
    'autoEmbedLinks'=> true, // links will be turned into hyperlinks
    'defualtName'=> '',
    'defaultEmail'=> '',
    'defaultSubject'=> '',
    'defaultComment'=> '',
    'canTripcode' => true,
    'canFortune' => true,
    'threadsPerPage' => 15,
    'maxActiveThreads' => 150,
    'defaultCSS' => '/static/css/kotatsu.css',
];