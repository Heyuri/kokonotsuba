<?php
/**
 * Config schema for the report module (namespace: modules.report.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 *
 * Who may see, approve, dismiss and clear reports is not configured here — those are roles,
 * so they live in $config['AuthLevels'] alongside every other capability:
 * CAN_VIEW_REPORTS, CAN_APPROVE_REPORT, CAN_DISMISS_REPORT, CAN_CLEAR_POST_REPORTS.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, templateField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Report',

	'REASON_MAX_LENGTH' => intField('config_label_modules.report.REASON_MAX_LENGTH', 1000, 'config_desc_modules.report.REASON_MAX_LENGTH', min: 1),
	'ENABLE_NOTIFICATIONS' => boolField('config_label_modules.report.ENABLE_NOTIFICATIONS', true, 'config_desc_modules.report.ENABLE_NOTIFICATIONS'),
	'NOTIFICATION_WINDOW_MINUTES' => intField('config_label_modules.report.NOTIFICATION_WINDOW_MINUTES', 60, 'config_desc_modules.report.NOTIFICATION_WINDOW_MINUTES', min: 1),
	'NOTIFICATION_POLL_SECONDS' => intField('config_label_modules.report.NOTIFICATION_POLL_SECONDS', 60, 'config_desc_modules.report.NOTIFICATION_POLL_SECONDS', min: 10),
	'REPORT_POST_TEMPLATE' => templateField('config_label_modules.report.REPORT_POST_TEMPLATE', 'kokoimg', 'config_desc_modules.report.REPORT_POST_TEMPLATE'),

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.viewReports' => boolField('config_label_modules.report.PostMenu.viewReports', true),
	'PostMenu.reportPost'  => boolField('config_label_modules.report.PostMenu.reportPost', true),
];
