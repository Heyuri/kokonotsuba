<?php
/**
 * Config schema for the adminDel module (namespace: modules.adminDel.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, stringField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Janitor / delete',

	'JANIMUTE_LENGTH' => intField('Janitor mute length (min)', 20, 'Janitor mute duration in minutes.'),
	'JANIMUTE_REASON' => stringField('Janitor mute reason', 'You have been muted temporarily!', 'Reason shown for a janitor mute.'),
];
