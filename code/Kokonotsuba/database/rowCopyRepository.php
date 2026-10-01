<?php

namespace Kokonotsuba\database;

/**
 * Re-inserts a table's rows under a new key.
 *
 * For data that hangs off a post or a thread and has to follow it when the row it belongs to is
 * duplicated rather than moved. The table must not carry generated columns, since every column
 * the read hands back is written straight to the copy.
 */
class rowCopyRepository extends baseRepository {
	/**
	 * Copy every row whose key appears in the map, rewriting the key to its mapped value.
	 * A new key that already owns rows is skipped, so an entry the destination already has wins.
	 *
	 * @param string $keyColumn  Column holding the key being remapped.
	 * @param array  $keyMap     Old key => new key.
	 * @param string $autoColumn Auto-increment column, dropped from the copies.
	 * @return void
	 */
	public function copyRowsToNewKeys(string $keyColumn, array $keyMap, string $autoColumn): void {
		if (empty($keyMap)) {
			return;
		}

		// the driver may hand keys back as strings, so index the map the same way
		$byOldKey = [];
		foreach ($keyMap as $oldKey => $newKey) {
			$byOldKey[(string)$oldKey] = $newKey;
		}

		$grouped = [];
		foreach ($this->findAllWhereIn($keyColumn, array_keys($byOldKey)) as $row) {
			$newKey = $byOldKey[(string)$row[$keyColumn]] ?? null;

			if ($newKey !== null) {
				$grouped[$newKey][] = $row;
			}
		}

		foreach ($grouped as $newKey => $rows) {
			if ($this->exists($keyColumn, $newKey)) {
				continue;
			}

			foreach ($rows as $row) {
				unset($row[$autoColumn]);
				$row[$keyColumn] = $newKey;
				$this->insert($row);
			}
		}
	}
}
