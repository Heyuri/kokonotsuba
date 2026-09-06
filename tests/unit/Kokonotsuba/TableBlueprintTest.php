<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use InvalidArgumentException;
use Koko\Tests\Framework\TestCase;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\sqlRunner;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * SQL emission and identifier validation for the declarative table definition.
 *
 * sqlRunner is only used here to resolve logical table keys, so a null connection is enough —
 * nothing in these tests executes.
 */
final class TableBlueprintTest extends TestCase {

	private function runner(): sqlRunner {
		$connection = new class extends databaseConnection {
			public function __construct() {}
		};

		return new sqlRunner(
			$connection,
			['POST_TABLE' => 'posts', 'BOARD_TABLE' => 'boards'],
			true,
			static function (string $statement): void {}
		);
	}

	public function testEmitsColumnsPrimaryKeyAndEngine(): void {
		$blueprint = (new tableBlueprint())
			->column('id', 'INT UNSIGNED NOT NULL AUTO_INCREMENT')
			->column('name', 'VARCHAR(64) NOT NULL')
			->primary('id');

		$sql = $blueprint->toCreateSql('widgets', $this->runner());

		$this->assertStringContains('CREATE TABLE IF NOT EXISTS `widgets`', $sql);
		$this->assertStringContains('`id` INT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
		$this->assertStringContains('PRIMARY KEY (`id`)', $sql);
		$this->assertStringContains('ENGINE=InnoDB', $sql);
	}

	public function testIndexColumnsMayCarryPrefixLengthAndDirection(): void {
		$blueprint = (new tableBlueprint())
			->column('tripcode', 'TEXT')
			->column('is_op', 'TINYINT(1) NOT NULL')
			->index('idx_tripcode', ['tripcode(10)'])
			->index('idx_rank', ['is_op DESC']);

		$sql = $blueprint->toCreateSql('posts', $this->runner());

		$this->assertStringContains('KEY `idx_tripcode` (`tripcode`(10))', $sql);
		$this->assertStringContains('KEY `idx_rank` (`is_op` DESC)', $sql);
	}

	public function testUniqueAndFulltextKeywords(): void {
		$blueprint = (new tableBlueprint())
			->column('com', 'MEDIUMTEXT NOT NULL')
			->column('no', 'INT NOT NULL')
			->unique('uq_no', ['no'])
			->fulltext('ft_com', ['com']);

		$sql = $blueprint->toCreateSql('posts', $this->runner());

		$this->assertStringContains('UNIQUE KEY `uq_no` (`no`)', $sql);
		$this->assertStringContains('FULLTEXT KEY `ft_com` (`com`)', $sql);
	}

	public function testForeignKeyResolvesTheReferencedTableByLogicalKey(): void {
		$blueprint = (new tableBlueprint())
			->column('boardUID', 'INT NOT NULL')
			->foreign('fk_board', 'boardUID', 'BOARD_TABLE', 'board_uid', 'CASCADE');

		$sql = $blueprint->toCreateSql('posts', $this->runner());

		$this->assertStringContains(
			'CONSTRAINT `fk_board` FOREIGN KEY (`boardUID`) REFERENCES `boards` (`board_uid`) ON DELETE CASCADE',
			$sql
		);
	}

	public function testRejectsInjectedIdentifiers(): void {
		$this->assertThrows(
			static fn () => (new tableBlueprint())->column('id`, DROP TABLE posts --', 'INT'),
			InvalidArgumentException::class
		);

		$this->assertThrows(
			static fn () => (new tableBlueprint())->index('idx`x', ['a']),
			InvalidArgumentException::class
		);

		$this->assertThrows(
			static fn () => (new tableBlueprint())->index('idx', ['a); DROP TABLE posts']),
			InvalidArgumentException::class
		);
	}

	public function testRejectsUnknownReferentialActions(): void {
		$this->assertThrows(
			static fn () => (new tableBlueprint())->foreign('fk', 'a', 'POST_TABLE', 'b', 'SET DEFAULT'),
			InvalidArgumentException::class
		);
	}

	public function testRefusesToEmitATableWithNoColumns(): void {
		$this->assertThrows(
			fn () => (new tableBlueprint())->toCreateSql('widgets', $this->runner()),
			InvalidArgumentException::class
		);
	}
}
