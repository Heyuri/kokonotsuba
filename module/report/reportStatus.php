<?php

namespace Kokonotsuba\Modules\report;

use function Kokonotsuba\libraries\_T;

/**
 * Lifecycle of a single report row.
 *
 * A report starts PENDING. Approving it deletes the reported post; dismissing it leaves the
 * post alone. Both are terminal — the row keeps its mod action metadata so the reporter can
 * see that something happened and staff can see who did it.
 */
enum reportStatus: int {
	case PENDING = 0;
	case APPROVED = 1;
	case DISMISSED = 2;

	/** Coerce a raw database value into a status, falling back to PENDING for unknown values. */
	public static function fromValue(mixed $value): self {
		return self::tryFrom((int) $value) ?? self::PENDING;
	}

	public function isPending(): bool {
		return $this === self::PENDING;
	}

	/** Human-readable state shown in the report tables. */
	public function label(): string {
		return match ($this) {
			self::PENDING => _T('report_status_pending'),
			self::APPROVED => _T('report_status_approved'),
			self::DISMISSED => _T('report_status_dismissed'),
		};
	}

	/**
	 * CSS class applied to the row so approved rows read green and dismissed rows read red.
	 * The actual (faded) colours live in static/css/module/report.css.
	 */
	public function rowCssClass(): string {
		return match ($this) {
			self::PENDING => 'reportRowPending',
			self::APPROVED => 'reportRowApproved',
			self::DISMISSED => 'reportRowDismissed',
		};
	}
}
