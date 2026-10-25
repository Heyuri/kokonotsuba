<?php

namespace Kokonotsuba\action_log;

/**
 * A string-backed enum of action types, the built-ins (actionType) or a feature's own.
 *
 * A module keeps its types in an enum of its own implementing this, registers it with
 * actionLoggerService::registerTypes() during initialize(), and passes its cases to logAction().
 * The backing value is the key stored in actionlog.action_type.
 */
interface actionTypeDefinition extends \BackedEnum {
	/** Label shown next to the checkbox on the filter form and in the log table. */
	public function label(): string;

	/** Section this type sits under on the filter form. */
	public function group(): actionTypeGroup;

	/** Whether the filter form ticks this by default. */
	public function isDefault(): bool;
}
