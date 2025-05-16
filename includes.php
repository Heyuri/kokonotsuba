<?php
/*
* Kokonotsuba! includes/require file.
*/

/* Constants */
require __DIR__.'/constants.php';

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
require __DIR__.'/code/lang/LanguageLoader.php';

/* FileIO */
require __DIR__.'/code/abstract_fileio/AbstractFileIO.php';
require __DIR__.'/code/abstract_fileio/AbstractIfsFileIO.php';
require __DIR__.'/code/fileio/fileio.local.php';


/* PMC library (singleton factory) */
require __DIR__.'/code/pmclibrary.php'; // factory

/* Libraries */
require __DIR__.'/code/libraries/lib_admin.php'; // admin panel functions
require __DIR__.'/code/libraries/lib_template.php'; // template library
require __DIR__.'/code/libraries/lib_rebuild.php'; // rebuild library
require __DIR__.'/code/libraries/lib_errorhandler.php'; // error library
require __DIR__.'/code/libraries/lib_compatible.php'; // compatability library
require __DIR__.'/code/libraries/lib_file.php'; // file I/O library
require __DIR__.'/code/libraries/lib_common.php'; // general-purpose functions
require __DIR__.'/code/libraries/lib_cache.php'; // caching library
require __DIR__.'/code/libraries/lib_post.php'; // post-related functions
require __DIR__.'/code/libraries/lib_filter.php'; // filter-related functions

/* Sensors */
require __DIR__.'/code/sensor/PIOSensor.php'; // PIO senssor
require __DIR__.'/code/sensor/postConditions.php'; // post/thread conditions classes

/* Templating */
require __DIR__.'/code/template/templateEngine.php'; // post and thread functions
require __DIR__.'/code/template/pageRenderer.php'; // page renderer

/* html */
require __DIR__.'/code/html/globalHTML.php'; // html class
require __DIR__.'/code/html/threadRenderer.php'; // thread rendering class
require __DIR__.'/code/html/postRenderer.php'; // post rendering class
require __DIR__.'/code/html/postHtmlFunctions.php'; // post html library
require __DIR__.'/code/html/helperHtmlFunctions.php'; // html and string manip library

/* Handle soft error pages */
require __DIR__.'/code/error/softErrorHandler.php';

/* Module related */
require __DIR__. '/code/module_classes/moduleEngine.php'; // module manager class
require __DIR__. '/code/module_classes/moduleHelper.php'; // module helper class

/* Caching */
require __DIR__.'/code/cache/path_cache/boardPathCachingIO.php';
require __DIR__.'/code/cache/path_cache/cachedBoardPath.php';
require __DIR__.'/code/cache/thread_cache/threadCacheSingleton.php';
require __DIR__.'/code/cache/thread_cache/threadCache.php';


/* Database singleton */
require __DIR__.'/code/database/database.php';

/* Overboard */
require __DIR__.'/code/overboard.php';

/* Post objects and singletons */
require __DIR__.'/code/post/FlagHelper.php';
require __DIR__.'/code/post/postValidator.php';
require __DIR__.'/code/post/postSingleton.php';
require __DIR__.'/code/post/threadSingleton.php';
require __DIR__.'/code/post/postRedirectIO.php';
require __DIR__.'/code/post/threadRedirect.php';

/* files */
require __DIR__.'/code/file/file.php';
require __DIR__.'/code/file/thumbnail.php';
require __DIR__.'/code/file/fileFromUpload.php';
require __DIR__.'/code/file/postFileUploadController.php';

/* regist/post helper classes */
require __DIR__.'/code/post/helper/agingHandler.php';
require __DIR__.'/code/post/helper/defaultTextFillter.php';
require __DIR__.'/code/post/helper/fortuneGenerator.php';
require __DIR__.'/code/post/helper/postDateFormatter.php';
require __DIR__.'/code/post/helper/postFilterApplier.php';
require __DIR__.'/code/post/helper/postIDGenerator.php';
require __DIR__.'/code/post/helper/thumbnailCreator.php';
require __DIR__.'/code/post/helper/tripcodeProcessor.php';
require __DIR__.'/code/post/helper/webhookDispatcher.php';

/* quote link */
require __DIR__.'/code/quote_link/quoteLinkSingleton.php';
require __DIR__.'/code/quote_link/quoteLink.php';
require __DIR__.'/code/quote_link/quoteLinkFunctions.php';


/* Routers */
require __DIR__.'/code/routers/modeHandler.php';

/* Routes */
require __DIR__.'/code/routers/routes/accountRoute.php';
require __DIR__.'/code/routers/routes/adminRoute.php';
require __DIR__.'/code/routers/routes/managePostsRoute.php';
require __DIR__.'/code/routers/routes/actionLogRoute.php';
require __DIR__.'/code/routers/routes/boardsRoute.php';
require __DIR__.'/code/routers/routes/defaultRoute.php';
require __DIR__.'/code/routers/routes/handleAccountActionRoute.php';
require __DIR__.'/code/routers/routes/handleBoardRequestsRoute.php';
require __DIR__.'/code/routers/routes/moduleloadedRoute.php';
require __DIR__.'/code/routers/routes/moduleRoute.php';
require __DIR__.'/code/routers/routes/overboardRoute.php';
require __DIR__.'/code/routers/routes/rebuildRoute.php';
require __DIR__.'/code/routers/routes/registRoute.php';
require __DIR__.'/code/routers/routes/statusRoute.php';
require __DIR__.'/code/routers/routes/usrdelRoute.php';

/* Account Related */
require __DIR__.'/code/account/accountClass.php';
require __DIR__.'/code/account/accountIO.php';
require __DIR__.'/code/account/accountRequestHandler.php';
require __DIR__.'/code/account/staffAccountSession.php';

/* Log in */
require __DIR__.'/code/log_in/authenticate.php';
require __DIR__.'/code/log_in/loginHandler.php';
require __DIR__.'/code/log_in/adminLoginController.php';

/* Action log */
require __DIR__.'/code/action_log/actionClass.php';
require __DIR__.'/code/action_log/actionLoggerSingleton.php';

/* Board classes and singleton */
require __DIR__.'/code/board/boardClass.php';
require __DIR__.'/code/board/boardRebuilder.php';
require __DIR__.'/code/board/boardSingleton.php';
require __DIR__.'/code/board/boardStoredFile.php';
require __DIR__.'/code/board/boardCreator.php';
// board import
require __DIR__.'/code/board/import/abstractBoardImporter.php';
require __DIR__.'/code/board/import/pixmicatBoardImporter.php';
require __DIR__.'/code/board/import/vichanBoardImporter.php';

/* IP */
require __DIR__.'/code/ip/IPAddress.php';
require __DIR__.'/code/ip/IPValidator.php';
