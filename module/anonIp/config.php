<?php
/**
 * Config schema for the anonIp module (namespace: modules.anonIp.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 *
 * Who may run the anonymizer is not configured here - that is a role, so it lives in
 * $config['AuthLevels'] as CAN_ANONYMIZE_IPS.
 *
 * How often is the only question a standing schedule answers: a scheduled run covers every
 * record. Narrowing one to an age window is a per-run choice, made from the anonymizer page.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Moderation',
	'_module' => 'IP Anonymizer',

	'AUTO_ANONYMIZE_DAYS' => intField('config_label_modules.anonIp.AUTO_ANONYMIZE_DAYS', 0, 'config_desc_modules.anonIp.AUTO_ANONYMIZE_DAYS', min: 0),
];
