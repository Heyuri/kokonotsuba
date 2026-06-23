<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\Modules\soudane\soudaneService;
use Kokonotsuba\Modules\soudane\soudaneRepository;

/**
 * Unit tests for the soudane (yeah/nope voting) service.
 *
 * The repository is replaced by a stub returning canned rows, so the service's
 * own normalisation and validation logic is tested without a database.
 */
final class SoudaneServiceTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('soudane/soudaneRepository.php');
		requireModuleFile('soudane/soudaneService.php');
	}

	/** Stub repository returning fixed count rows. */
	private function serviceReturning(array $rows): soudaneService {
		$repo = new class($rows) extends soudaneRepository {
			public function __construct(private array $rows) {}
			public function getVoteCountsByPostUids(array $postUids, bool $isYeah): array {
				return $this->rows;
			}
		};
		return new soudaneService($repo);
	}

	public function testVoteCountsAreNormalisedToIntMap(): void {
		// Repository rows arrive as strings (as from PDO); service must cast.
		$service = $this->serviceReturning([
			['post_uid' => '10', 'vote_count' => '3'],
			['post_uid' => '11', 'vote_count' => '0'],
		]);

		$counts = $service->getYeahCounts([10, 11]);

		$this->assertSame([10 => 3, 11 => 0], $counts);
	}

	public function testEmptyResultYieldsEmptyMap(): void {
		$this->assertSame([], $this->serviceReturning([])->getNopeCounts([1, 2, 3]));
	}

	public function testValidateTypeAcceptsKnownTypes(): void {
		$service = $this->serviceReturning([]);
		$service->validateType('yeah');
		$service->validateType('nope');
		$this->pass();
	}

	public function testValidateTypeRejectsUnknownType(): void {
		$service = $this->serviceReturning([]);
		$this->assertThrows(fn() => $service->validateType('maybe'), BoardException::class);
	}
}
