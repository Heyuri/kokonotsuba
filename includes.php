<?php
/*
* Kokonotsuba! includes/require file.
*/

/* Interfaces */
require __DIR__.'/code/interfaces/IBoard.php'; // board interface
require __DIR__.'/code/interfaces/IFileIO.php'; // FileIO inteface
require __DIR__.'/code/interfaces/ILogger.php'; // Logger interface
require __DIR__.'/code/interfaces/IModule.php'; // ModuleHelper interface
require __DIR__.'/code/interfaces/IPIO.php'; // postSingleton interface
require __DIR__.'/code/interfaces/IPIOCondition.php'; // pio condition interface
require __DIR__.'/code/interfaces/MethodInterceptor.php'; // method interceptor interface

/* Loggers */
require __DIR__.'/code/logger/SimpleLogger.php'; // simple logger class
require __DIR__.'/code/logger/LoggerInterceptor.php'; // logger incterceptor class
require __DIR__.'/code/logger/NopLogger.php'; // no logging class

/* Language */
require __DIR__.'/code/LanguageLoader.php';

/* FileIO */
require __DIR__.'/code/abstract fileio/AbstractFileIO.php';
require __DIR__.'/code/abstract fileio/AbstractIfsFileIO.php';
require __DIR__.'/code/fileio/fileio.local.php';


/* PMC library (singleton factory) */
require __DIR__.'/code/pmclibrary.php'; // factory

/* Libraries */
require __DIR__.'/code/libraries/lib_admin.php'; // admin panel functions
require __DIR__.'/code/libraries/lib_template.php'; // template library
require __DIR__.'/code/libraries/lib_rebuild.php'; // rebuild library
require __DIR__.'/code/libraries/lib_errorhandler.php'; // error library
require __DIR__.'/code/libraries/lib_compatible.php'; // compatability library
require __DIR__.'/code/libraries/lib_common.php'; // general-purpose functions
require __DIR__.'/code/libraries/lib_post.php'; // post-related functions

/* Sensors */
require __DIR__.'/code/sensor/PIOSensor.php'; // PIO senssor
require __DIR__.'/code/sensor/postConditions.php'; // post/thread conditions classes

/* Templating */
require __DIR__.'/code/template/templateEngine.php'; // post and thread functions
require __DIR__.'/code/template/pageRenderer.php'; // page renderer

/* html */
require __DIR__.'/code/html/globalHTML.php'; // html class

/* Handle soft error pages */
require __DIR__.'/code/error/softErrorHandler.php';

/* Module related */
require __DIR__. '/code/module classes/moduleEngine.php'; // module manager class
require __DIR__. '/code/module classes/moduleHelper.php'; // module helper class

/* Path caching */
require __DIR__.'/code/path cache/boardPathCachingIO.php';
require __DIR__.'/code/path cache/cachedBoardPath.php';


/* Database singleton */
require __DIR__.'/code/database/database.php';

/* Overboard */
require __DIR__.'/code/overboard.php';

/* Post objects and singletons */
require __DIR__.'/code/post/FlagHelper.php';
require __DIR__.'/code/post/postValidator.php';
require __DIR__.'/code/post/postSingleton.php';
require __DIR__.'/code/post/postRedirectIO.php';
require __DIR__.'/code/post/threadRedirect.php';

/* Routers */
require __DIR__.'/code/routers/adminPageHandler.php';
require __DIR__.'/code/routers/modeHandler.php';

/* Account Related */
require __DIR__.'/code/account/accountClass.php';
require __DIR__.'/code/account/accountIO.php';
require __DIR__.'/code/account/accountRequestHandler.php';
require __DIR__.'/code/account/staffAccountSession.php';

/* Log in */
require __DIR__.'/code/log in/authenticate.php';
require __DIR__.'/code/log in/loginHandler.php';

/* Action log */
require __DIR__.'/code/action log/actionClass.php';
require __DIR__.'/code/action log/actionLoggerSingleton.php';

/* Board classes and singleton */
require __DIR__.'/code/board/boardClass.php';
require __DIR__.'/code/board/boardRebuilder.php';
require __DIR__.'/code/board/boardSingleton.php';
require __DIR__.'/code/board/boardStoredFile.php';

/* IP */
require __DIR__.'/code/ip/IPAddress.php';
require __DIR__.'/code/ip/IPValidator.php';