<?php
/**
 * Config schema for the segregator module (namespace: modules.segregator.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{stringField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Segregator',

	'SEGREGATOR_SUB_DOMAIN' => stringField('Segregator subdomain', '', 'config_desc_modules.segregator.SEGREGATOR_SUB_DOMAIN'),
	'SEGREGATOR_COOKIE_NAME' => stringField('Segregator cookie name', 'viewAllContent', 'config_desc_modules.segregator.SEGREGATOR_COOKIE_NAME'),
	'SEGREGATOR_COOKIE_DOMAIN' => stringField('Segregator cookie domain', '', 'config_desc_modules.segregator.SEGREGATOR_COOKIE_DOMAIN'),
];
