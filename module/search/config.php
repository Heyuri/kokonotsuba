<?php
/**
 * Config schema for the search module (namespace: modules.search.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, templateField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Search',

	'SEARCH_POSTS_PER_PAGE' => intField('Search results per page', 50, 'config_desc_modules.search.SEARCH_POSTS_PER_PAGE'),
	'SEARCH_TEMPLATE' => templateField('Search template', 'kokoimg', 'config_desc_modules.search.SEARCH_TEMPLATE'),
	'DISPLAY_THREADED_FORMAT' => boolField('Threaded search format', false, 'config_desc_modules.search.DISPLAY_THREADED_FORMAT'),
];
