<?php
/**
 * Config schema for the segregator module (namespace: modules.segregator.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{stringField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Segregator',

	'SEGREGATOR_SUB_DOMAIN' => stringField('Segregator subdomain', '', 'Subdomain prefix prepended to the file host (empty = disabled).'),
	'SEGREGATOR_COOKIE_NAME' => stringField('Segregator cookie name', 'viewAllContent', 'Name of the access cookie checked by nginx.'),
	'SEGREGATOR_COOKIE_DOMAIN' => stringField('Segregator cookie domain', '', 'Cookie domain scope (empty = current host only).'),
];
