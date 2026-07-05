<?php
/**
 * Config schema for the search module (namespace: modules.search.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, templateField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Search',

	'SEARCH_POSTS_PER_PAGE' => intField('Search results per page', 50, 'Number of search results shown per page.'),
	'SEARCH_TEMPLATE' => templateField('Search template', 'kokoimg', 'Template used to render search results.'),
	'DISPLAY_THREADED_FORMAT' => boolField('Threaded search format', false, 'Display search results in a threaded format.'),
];
