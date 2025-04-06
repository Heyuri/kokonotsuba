<?php
/*
* Kokonotsuba! includes/require file.
*/


/* Libraries */
require __DIR__.'/lib/interfaces.php';
require __DIR__.'/lib/lib_simplelogger.php';
require __DIR__.'/lib/lib_loggerinterceptor.php';
require __DIR__.'/lib/lib_admin.php'; // Admin panel functions
require __DIR__.'/lib/lib_template.php'; // Template library
require __DIR__.'/lib/templateEngine.php'; // Post and thread functions
require __DIR__.'/lib/pageRenderer.php'; // Renderer for pages
require __DIR__.'/lib/lib_post.php';
require __DIR__.'/lib/lib_pio.php';
require __DIR__.'/lib/lib_pio.cond.php';
require __DIR__.'/lib/lib_common.php'; // Introduce common function archives
require __DIR__.'/lib/pmclibrary.php'; // Ingest libraries
require __DIR__.'/lib/lib_errorhandler.php'; // Introduce global error capture
require __DIR__.'/lib/lib_compatible.php'; // Introduce compatible libraries

/* Module related */
require __DIR__. '/lib/moduleEngine.php';
require __DIR__. '/lib/moduleHelper.php';

/* Caching */
require __DIR__.'/lib/boardPathCachingIO.php';
require __DIR__.'/lib/cachedBoardPath.php';

/* Database singleton */
require __DIR__.'/lib/database.php';

/* Handle soft error pages */
require __DIR__.'/lib/softErrorHandler.php';

/* HTML output */
require __DIR__.'/lib/globalHTML.php';

/* Main output */
require __DIR__.'/lib/modeHandler.php';

/* Overboard */
require __DIR__.'/lib/overboard.php';

/* Post objects and singletons */
require __DIR__.'/lib/postValidator.php';
require __DIR__.'/lib/postSingleton.php';
require __DIR__.'/lib/postRedirectIO.php';
require __DIR__.'/lib/threadRedirect.php';

/* Admin page selector */
require __DIR__.'/lib/adminPageHandler.php';

/* Account Related */
require __DIR__.'/lib/accountIO.php';
require __DIR__.'/lib/accountClass.php';
require __DIR__.'/lib/accountRequestHandler.php';
require __DIR__.'/lib/staffAccountSession.php';
require __DIR__.'/lib/loginHandler.php';
require __DIR__.'/lib/authenticate.php';

/* Action log */
require __DIR__.'/lib/actionClass.php';
require __DIR__.'/lib/actionLoggerSingleton.php';


/* Board classes and singleton */
require __DIR__.'/lib/boardClass.php';
require __DIR__.'/lib/boardRebuilder.php';
require __DIR__.'/lib/boardSingleton.php';
require __DIR__.'/lib/boardStoredFile.php';

/* IP */
require __DIR__.'/lib/IPAddress.php';
require __DIR__.'/lib/IPValidator.php';
