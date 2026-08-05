<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;

/**
 * Build the staff-only [Moderate] control for the post checkboxes.
 *
 * Modules contribute entries through the MassModerateTools hook (see MassModerateListenerTrait);
 * everything they return is rendered here into a single <template>, so opening the window is a
 * clone rather than a fetch. Only listeners the viewer's role passes are registered, so the list
 * is already what this account is allowed to do.
 */
function generateMassModerateHtml(templateEngine $templateEngine, moduleEngine $moduleEngine, array $config): string {
	$tools = [];
	$moduleEngine->dispatch('MassModerateTools', [&$tools]);

	if (!$tools) {
		return '';
	}

	// higher priority first, matching hook listener ordering
	usort($tools, fn(array $a, array $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

	// Entries are filed under the area of moderation they belong to, so a list several modules
	// long still reads as a handful of short ones. A group ranks by its strongest entry.
	$groups = [];
	foreach ($tools as $tool) {
		$groupLabel = (string)($tool['group'] ?? '');

		if (!isset($groups[$groupLabel])) {
			$groups[$groupLabel] = [
				'priority' => $tool['priority'] ?? 0,
				'{$MM_GROUP_LABEL}' => htmlspecialchars($groupLabel),
				'{$MM_GROUP_ITEMS}' => [],
			];
		}

		$groups[$groupLabel]['{$MM_GROUP_ITEMS}'][] = [
			'{$MM_ACTION}'    => htmlspecialchars((string)($tool['action'] ?? '')),
			'{$MM_LABEL}'     => htmlspecialchars((string)($tool['label'] ?? '')),
			'{$MM_URL}'       => htmlspecialchars((string)($tool['url'] ?? '')),
			'{$MM_SCOPE}'     => htmlspecialchars((string)($tool['scope'] ?? 'post')),
			'{$MM_EFFECT}'    => htmlspecialchars((string)($tool['effect'] ?? 'none')),
			'{$MM_REQUIRES}'  => htmlspecialchars((string)($tool['requires'] ?? '')),
			'{$MM_INDICATOR}' => htmlspecialchars((string)($tool['indicator'] ?? '')),
			'{$MM_FORM}'      => htmlspecialchars((string)($tool['form'] ?? '')),
			'{$MM_CONFIRM}'   => htmlspecialchars((string)($tool['confirm'] ?? '')),
			'{$MM_PARAMS}'    => htmlspecialchars(json_encode((object)($tool['params'] ?? []))),
		];
	}

	$groups = array_values($groups);
	usort($groups, fn(array $a, array $b) => $b['priority'] <=> $a['priority']);

	$windowHtml = $templateEngine->ParseBlock('MASS_MODERATE', [
		'{$MM_OPEN_LABEL}'  => _T('mass_moderate_open'),
		'{$MM_BACK_LABEL}'  => _T('mass_moderate_back'),
		'{$MM_APPLY_LABEL}' => _T('mass_moderate_apply'),
		'{$MM_STATUS}'      => _T(
			'mass_moderate_status',
			'<span class="massModerateCount">0</span>',
			'<span class="massModerateThreadCount">0</span>'
		),
		'{$MM_GROUPS}'      => $groups,
	]);

	$scriptUrl = ($config['STATIC_URL'] ?? '') . 'js/massModerate.js';

	// The window's own wording travels with the template so the script holds no English of its own.
	$strings = [
		'data-none-selected' => _T('mass_moderate_none_selected'),
		'data-no-threads'    => _T('mass_moderate_no_threads'),
		'data-done'          => _T('mass_moderate_done'),
		'data-failed'        => _T('mass_moderate_failed'),
		'data-title'         => _T('mass_moderate_title'),
	];

	$stringAttributes = '';
	foreach ($strings as $attribute => $value) {
		$stringAttributes .= ' ' . $attribute . '="' . htmlspecialchars($value) . '"';
	}

	return '<template id="massModerateTemplate"' . $stringAttributes . '>' . $windowHtml . '</template>'
		. '<script src="' . htmlspecialchars($scriptUrl) . '" defer></script>';
}
