<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\interfaces\IBoard;

/**
 * Decides which entries the post and attachment menus show.
 *
 * Every entry names an action ('delete', 'purgeDeletedFile', ...), and every action is a board
 * config toggle — so an option is turned off by unticking it in the board config editor, per
 * board, instead of editing the module that adds it.
 *
 * A module declares the entries it adds in its own module/{name}/config.php, as PostMenu.{action}
 * / AttachmentMenu.{action}; the toggles then sit beside that module's other settings in the
 * editor. The same paths at the top level (PostMenu.* / AttachmentMenu.*) apply to any entry with
 * no module of its own. Both are merged here, and where two modules add the same action, either
 * one turning it off turns it off.
 *
 * An action with no toggle declared is shown, so a module's new entry appears without needing a
 * config key first. Hiding an entry only hides it: the role checks behind the action are what
 * actually deny it.
 */
class widgetMenuPolicy {
	/** Config keys the toggles live under, and the menu names used to look them up. */
	public const MENU_POST = 'PostMenu';
	public const MENU_ATTACHMENT = 'AttachmentMenu';

	/**
	 * @param array<string, array<string, bool>> $menus Menu name => [action => enabled].
	 */
	public function __construct(private readonly array $menus = []) {}

	/** Collect both menus' toggles out of a board's config. */
	public static function fromBoard(IBoard $board): self {
		return self::fromConfig($board->loadBoardConfig());
	}

	/**
	 * @param array $config A board's resolved config, module namespaces included.
	 */
	public static function fromConfig(array $config): self {
		$moduleConfigs = (array)($config['modules'] ?? []);
		$menus = [];

		foreach ([self::MENU_POST, self::MENU_ATTACHMENT] as $menu) {
			// entries with no module of their own
			$toggles = (array)($config[$menu] ?? []);

			// then each module's own declarations
			foreach ($moduleConfigs as $moduleConfig) {
				foreach ((array)($moduleConfig[$menu] ?? []) as $action => $enabled) {
					// same action declared by two modules: off anywhere is off
					$toggles[$action] = ($toggles[$action] ?? true) && $enabled;
				}
			}

			$menus[$menu] = $toggles;
		}

		return new self($menus);
	}

	/**
	 * @param string $menu   One of the MENU_* constants.
	 * @param string $action The entry's action name.
	 */
	public function isEnabled(string $menu, string $action): bool {
		// an entry with no action can't be addressed by a toggle
		if ($action === '') {
			return true;
		}

		$toggles = $this->menus[$menu] ?? [];

		// undeclared actions are on
		if (!array_key_exists($action, $toggles)) {
			return true;
		}

		return (bool)$toggles[$action];
	}

	/**
	 * Drop the disabled entries from a menu's widget array.
	 *
	 * @param string $menu    One of the MENU_* constants.
	 * @param array  $widgets Widget entries as built by abstractModule::buildWidgetEntry().
	 * @return array The entries that stay, renumbered.
	 */
	public function filter(string $menu, array $widgets): array {
		return array_values(array_filter(
			$widgets,
			fn(array $widget) => $this->isEnabled($menu, (string)($widget['action'] ?? ''))
		));
	}
}
