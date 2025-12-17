<?php
/*
* Kokonotsuba! includes/require file.
*/

/* Constants */
require __DIR__.'/constants.php';

// interfaces
require __DIR__ . '/code/interfaces/IBoard.php';
require __DIR__ . '/code/interfaces/ILogger.php';
require __DIR__ . '/code/interfaces/IModule.php';
require __DIR__ . '/code/interfaces/MethodInterceptor.php';

// pmclibrary
require __DIR__ . '/code/pmclibrary.php';

// account
require __DIR__ . '/code/account/accountRepository.php';
require __DIR__ . '/code/account/accountService.php';
require __DIR__ . '/code/account/staffAccount.php';
require __DIR__ . '/code/account/staffAccountSession.php';

// action_log
require __DIR__ . '/code/action_log/actionLoggerRepository.php';
require __DIR__ . '/code/action_log/actionLoggerService.php';
require __DIR__ . '/code/action_log/loggedActionEntry.php';

// board
require __DIR__ . '/code/board/board.php';
require __DIR__ . '/code/board/boardCreator.php';
require __DIR__ . '/code/board/boardData.php';
require __DIR__ . '/code/board/boardPostNumbers.php';
require __DIR__ . '/code/board/boardRebuilder.php';
require __DIR__ . '/code/board/boardRepository.php';
require __DIR__ . '/code/board/boardService.php';
require __DIR__ . '/code/board/boardStoredFile.php';
require __DIR__ . '/code/board/import/abstractBoardImporter.php';
require __DIR__ . '/code/board/import/pixmicatBoardImporter.php';
require __DIR__ . '/code/board/import/vichanBoardImporter.php';

// cache/path_cache
require __DIR__ . '/code/cache/path_cache/boardPathRepository.php';
require __DIR__ . '/code/cache/path_cache/boardPathService.php';
require __DIR__ . '/code/cache/path_cache/cachedBoardPath.php';

// cache/thread_cache
require __DIR__ . '/code/cache/thread_cache/threadCache.php';
require __DIR__ . '/code/cache/thread_cache/threadCacheSingleton.php';

// capcode backend classes
require __DIR__ . '/code/capcode_backend/capcodeRepository.php';
require __DIR__ . '/code/capcode_backend/capcodeService.php';

// containers
require __DIR__ . '/code/containers/boardDiContainer.php';
require __DIR__ . '/code/containers/moduleEngineContext.php';
require __DIR__ . '/code/containers/routeDiContainer.php';

// database
require __DIR__ . '/code/database/database.php';
require __DIR__ . '/code/database/transactionManager.php';

// error
require __DIR__ . '/code/error/softErrorHandler.php';
require __DIR__ . '/code/error/BoardException.php';

// file
require __DIR__ . '/code/file/file.php';
require __DIR__ . '/code/file/fileFromUpload.php';
require __DIR__ . '/code/file/postFileUploadController.php';
require __DIR__ . '/code/file/thumbnail.php';

// html
require __DIR__ . '/code/html/boardList.php';
require __DIR__ . '/code/html/filterForms.php';
require __DIR__ . '/code/html/formAndLayout.php';
require __DIR__ . '/code/html/helperHtmlFunctions.php';
require __DIR__ . '/code/html/miscPartials.php';
require __DIR__ . '/code/html/moduleHtmlFunctions.php';
require __DIR__ . '/code/html/pagers.php';
require __DIR__ . '/code/html/postHtmlFunctions.php';
require __DIR__ . '/code/html/post_html/postWidget.php';
require __DIR__ . '/code/html/post_html/attachmentRenderer.php';
require __DIR__ . '/code/html/post_html/postDataPreparer.php';
require __DIR__ . '/code/html/post_html/postElementGenerator.php';
require __DIR__ . '/code/html/post_html/postRenderer.php';
require __DIR__ . '/code/html/post_html/postTemplateBinder.php';
require __DIR__ . '/code/html/threadRenderer.php';

// ip
require __DIR__ . '/code/ip/IPAddress.php';
require __DIR__ . '/code/ip/IPValidator.php';

// lang (only LanguageLoader.php)
require __DIR__ . '/code/lang/LanguageLoader.php';

// libraries
require __DIR__ . '/code/libraries/lib_admin.php';
require __DIR__ . '/code/libraries/lib_cache.php';
require __DIR__ . '/code/libraries/lib_common.php';
require __DIR__ . '/code/libraries/lib_compatible.php';
require __DIR__ . '/code/libraries/lib_database.php';
require __DIR__ . '/code/libraries/lib_errorhandler.php';
require __DIR__ . '/code/libraries/lib_external.php';
require __DIR__ . '/code/libraries/lib_file.php';
require __DIR__ . '/code/libraries/lib_filter.php';
require __DIR__ . '/code/libraries/lib_post.php';
require __DIR__ . '/code/libraries/lib_rebuild.php';
require __DIR__ . '/code/libraries/lib_json.php';
require __DIR__ . '/code/libraries/lib_template.php';
require __DIR__ . '/code/libraries/lib_query.php';
require __DIR__ . '/code/libraries/lib_attachment.php';

// log_in
require __DIR__ . '/code/log_in/adminLoginController.php';
require __DIR__ . '/code/log_in/authenticate.php';
require __DIR__ . '/code/log_in/loginHandler.php';

// logger
require __DIR__ . '/code/logger/LoggerInterceptor.php';
require __DIR__ . '/code/logger/NopLogger.php';
require __DIR__ . '/code/logger/SimpleLogger.php';

// module_classes
require __DIR__ . '/code/module_classes/abstractModule.php';
require __DIR__ . '/code/module_classes/abstractModuleMain.php';
require __DIR__ . '/code/module_classes/abstractModuleJavascript.php';
require __DIR__ . '/code/module_classes/abstractModuleAdmin.php';
require __DIR__ . '/code/module_classes/moduleContext.php';
require __DIR__ . '/code/module_classes/moduleEngine.php';
require __DIR__ . '/code/module_classes/hookDispatcher.php';

// overboard
require __DIR__ . '/code/overboard.php';

// post
require __DIR__ . '/code/post/FlagHelper.php';
require __DIR__ . '/code/post/attachment/attachment.php';
require __DIR__ . '/code/post/attachment/fileEntry.php';
require __DIR__ . '/code/post/attachment/fileRepository.php';
require __DIR__ . '/code/post/attachment/fileService.php';
require __DIR__ . '/code/post/helper/agingHandler.php';
require __DIR__ . '/code/post/helper/defaultTextFillter.php';
require __DIR__ . '/code/post/helper/fortuneGenerator.php';
require __DIR__ . '/code/post/helper/postDateFormatter.php';
require __DIR__ . '/code/post/helper/postFilterApplier.php';
require __DIR__ . '/code/post/helper/thumbnailCreator.php';
require __DIR__ . '/code/post/helper/webhookDispatcher.php';
require __DIR__ . '/code/post/postRedirectRepository.php';
require __DIR__ . '/code/post/postRedirectService.php';
require __DIR__ . '/code/post/postRegistData.php';
require __DIR__ . '/code/post/postRepository.php';
require __DIR__ . '/code/post/postSearchRepository.php';
require __DIR__ . '/code/post/postSearchService.php';
require __DIR__ . '/code/post/postService.php';
require __DIR__ . '/code/post/postValidator.php';
require __DIR__ . '/code/post/deletion/deletedPostsRepository.php';
require __DIR__ . '/code/post/deletion/deletedPostsService.php';

// quote_link
require __DIR__ . '/code/quote_link/quoteLink.php';
require __DIR__ . '/code/quote_link/quoteLinkRepository.php';
require __DIR__ . '/code/quote_link/quoteLinkService.php';

// routers
require __DIR__ . '/code/routers/modeHandler.php';
require __DIR__ . '/code/routers/routes/accountRoute.php';
require __DIR__ . '/code/routers/routes/actionLogRoute.php';
require __DIR__ . '/code/routers/routes/adminRoute.php';
require __DIR__ . '/code/routers/routes/boardsRoute.php';
require __DIR__ . '/code/routers/routes/defaultRoute.php';
require __DIR__ . '/code/routers/routes/handleAccountActionRoute.php';
require __DIR__ . '/code/routers/routes/handleBoardRequestsRoute.php';
require __DIR__ . '/code/routers/routes/managePostsRoute.php';
require __DIR__ . '/code/routers/routes/moduleRoute.php';
require __DIR__ . '/code/routers/routes/moduleloadedRoute.php';
require __DIR__ . '/code/routers/routes/overboardRoute.php';
require __DIR__ . '/code/routers/routes/rebuildRoute.php';
require __DIR__ . '/code/routers/routes/registRoute.php';
require __DIR__ . '/code/routers/routes/statusRoute.php';
require __DIR__ . '/code/routers/routes/usrdelRoute.php';
require __DIR__ . '/code/routers/routes/jsonApiRoute.php';

// template
require __DIR__ . '/code/template/pageRenderer.php';
require __DIR__ . '/code/template/templateEngine.php';

// thread
require __DIR__ . '/code/thread/threadRedirect.php';
require __DIR__ . '/code/thread/threadRepository.php';
require __DIR__ . '/code/thread/threadService.php';

// policies
require __DIR__ . '/code/policy/postPolicy.php';

// api
require __DIR__ . '/code/api/boardApi.php';
require __DIR__ . '/code/api/threadApi.php';