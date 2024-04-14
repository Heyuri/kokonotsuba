<?php
/*
 * This is the base config file. new boards created will have this a default.
 */

return [
    'boardID' => -1, // do not change this.
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
    'staticPath' => "/static/",
    'unlisted' => false,
    'timeZone' => 'UTC',
    'allowRuffle' => true,
    'cookieExpireTime'=> time()+7*24*3600, // 7days from the curent time
    'allowQuoteLinking'=> false, // link to post and post on other boards.
    'autoEmbedLinks'=> true,
    'allowBlankName' => false,
    'allowBlankEmail' => false,
    'allowBlankSubject' => true,
    'allowBlankComment' => true,
    'defualtName'=> 'anon',
    'defaultEmail'=> '',
    'defaultSubject'=> '',
    'defaultComment'=> 'kita',
    'canTripcode' => true,
    'tripcodeSalt'=> 'abc123!?_',
    'canFortune' => true,
    'fortunes' => ['Very bad luck', 'Bad luck','Average luck','Good luck','Godly luck'],
    'threadsPerPage' => 15,
    'maxActiveThreads' => 150,
    'defaultCSS' => '/static/css/kotatsu.css',
];