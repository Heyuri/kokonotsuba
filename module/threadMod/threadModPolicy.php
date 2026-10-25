<?php

namespace Kokonotsuba\Modules\threadMod;

/**
 * What a thread's owner and mods may do. Pure: every fact about the thread and the target post
 * is handed in, so it has unit tests and no database.
 *
 * Staff override is not decided here. Staff act through moduleAdmin, gated on CAN_DELETE_POST.
 */
final class threadModPolicy {
	/** Capcode keys stored in the post's capcode column. */
	public const CAPCODE_OWNER = 'threadOwner';
	public const CAPCODE_MOD = 'threadMod';

	/** @param int $maxDeletionsPerThread 0 means no cap. */
	public function __construct(private readonly int $maxDeletionsPerThread = 0) {}

	/** A stripped thread has no OP tools at all. */
	public function canAct(threadRole $actor, bool $stripped): bool {
		return !$stripped && $actor->isAtLeastMod();
	}

	public function capReached(int $deletionsSoFar): bool {
		return $this->maxDeletionsPerThread > 0 && $deletionsSoFar >= $this->maxDeletionsPerThread;
	}

	/**
	 * @param threadRole $targetAuthor The target post's author as the thread knows them: owner,
	 *                                 mod or nobody in particular.
	 */
	public function canDelete(
		threadRole $actor,
		bool $stripped,
		bool $targetIsOp,
		bool $targetHasStaffCapcode,
		threadRole $targetAuthor,
		bool $targetAlreadyDeleted,
		int $deletionsSoFar
	): bool {
		if (!$this->canAct($actor, $stripped) || $targetIsOp || $targetHasStaffCapcode || $targetAlreadyDeleted) {
			return false;
		}

		// a mod does not get to hide the owner
		if (!$actor->isOwner() && $targetAuthor->isOwner()) {
			return false;
		}

		return !$this->capReached($deletionsSoFar);
	}

	/** The owner undoes anything; a mod only what they hid themselves. */
	public function canUndo(threadRole $actor, bool $stripped, threadRole $deletedBy, string $deletedByHash, string $actorHash): bool {
		if (!$this->canAct($actor, $stripped) || !$deletedBy->isAtLeastMod()) {
			return false;
		}

		if ($actor->isOwner()) {
			return true;
		}

		// a mod does not overrule the owner
		if ($deletedBy->isOwner()) {
			return false;
		}

		return $actorHash !== '' && hash_equals($deletedByHash, $actorHash);
	}

	/** Only the owner grants, to a post with a recorded browser that is neither the OP nor themselves. */
	public function canGrant(threadRole $actor, bool $stripped, bool $targetIsOp, string $targetHash, string $actorHash): bool {
		return $this->canAct($actor, $stripped)
			&& $actor->isOwner()
			&& !$targetIsOp
			&& $targetHash !== ''
			&& !hash_equals($targetHash, $actorHash);
	}

	/** The owner revokes anyone; a mod may only step down. */
	public function canRevoke(threadRole $actor, bool $stripped, string $targetHash, string $actorHash): bool {
		if (!$this->canAct($actor, $stripped) || $targetHash === '') {
			return false;
		}

		$self = $actorHash !== '' && hash_equals($targetHash, $actorHash);

		return $actor->isOwner() ? !$self : $self;
	}

	public function canTransfer(threadRole $actor, bool $stripped, bool $targetIsOp, string $targetHash, string $actorHash): bool {
		return $this->canGrant($actor, $stripped, $targetIsOp, $targetHash, $actorHash);
	}

	/** The capcode a poster of this role may sign with, null when none. */
	public function capcodeFor(threadRole $actor, bool $stripped): ?string {
		if (!$this->canAct($actor, $stripped)) {
			return null;
		}

		return $actor->isOwner() ? self::CAPCODE_OWNER : self::CAPCODE_MOD;
	}

	public static function isThreadCapcode(string $capcode): bool {
		return $capcode === self::CAPCODE_OWNER || $capcode === self::CAPCODE_MOD;
	}

	/** The role a stored thread capcode stands for. */
	public static function roleOfCapcode(string $capcode): threadRole {
		return match ($capcode) {
			self::CAPCODE_OWNER => threadRole::OWNER,
			self::CAPCODE_MOD => threadRole::MOD,
			default => threadRole::NONE,
		};
	}
}
