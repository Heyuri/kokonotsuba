<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\checkResult;
use Kokonotsuba\install\exposureProbe;
use Kokonotsuba\install\webServerRules;

/** Reading an HTTP response as "this file is public" or "the web server denies it". */
class ExposureProbeTest extends TestCase {

	/** @param array{status: int, body: string}|null $response */
	private function probe(?array $response, array &$requested = []): exposureProbe {
		return new exposureProbe(
			'https://example.net/kokonotsuba/',
			static function (string $url) use ($response, &$requested): ?array {
				$requested[] = $url;

				return $response;
			}
		);
	}

	public function testDeniedIsAPass(): void {
		foreach ([401, 403, 404] as $status) {
			$result = $this->probe(['status' => $status, 'body' => 'Forbidden'])
				->checkOne('databaseSettings.php', 'DATABASE_USERNAME');

			$this->assertSame(checkResult::OK, $result->status);
		}
	}

	public function testSourceServedAsTextIsAFailure(): void {
		$result = $this->probe(['status' => 200, 'body' => "<?php\nreturn ['DATABASE_USERNAME' => 'koko'];"])
			->checkOne('databaseSettings.php', 'DATABASE_USERNAME');

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains('plain text', $result->detail);
	}

	public function testRawPhpWithoutTheMarkerIsStillAFailure(): void {
		$result = $this->probe(['status' => 200, 'body' => "<?php echo 'hi';"])
			->checkOne('code/Kokonotsuba/constants.php', 'KOKO_VERSION');

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains('instead of executed', $result->detail);
	}

	public function testAReadableLogFileIsAFailure(): void {
		$result = $this->probe(['status' => 200, 'body' => 'this is the global message'])
			->checkOne('global/globalmsg.txt', '');

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains('Reachable', $result->detail);
	}

	public function testAnExecutedPhpFileIsOnlyAWarning(): void {
		$result = $this->probe(['status' => 200, 'body' => ''])
			->checkOne('databaseSettings.php', 'DATABASE_USERNAME');

		$this->assertSame(checkResult::WARN, $result->status);
		$this->assertStringContains('Deny it anyway', $result->detail);
	}

	public function testARedirectIsAWarningRatherThanAPass(): void {
		$result = $this->probe(['status' => 302, 'body' => ''])->checkOne('tables.php', 'SCHEMA_MIGRATION_TABLE');

		$this->assertSame(checkResult::WARN, $result->status);
	}

	public function testAFailedRequestIsReportedAsUnverified(): void {
		$result = $this->probe(null)->checkOne('tables.php', 'SCHEMA_MIGRATION_TABLE');

		$this->assertSame(checkResult::WARN, $result->status);
		$this->assertStringContains('unverified', $result->detail);
	}

	public function testTheUrlIsBuiltFromTheBaseWithoutDoubleSlashes(): void {
		$requested = [];
		$this->probe(['status' => 403, 'body' => ''], $requested)->checkOne('/tables.php', '');

		$this->assertSame(['https://example.net/kokonotsuba/tables.php'], $requested);
	}

	public function testEveryDeclaredTargetIsProbed(): void {
		$requested = [];
		$results = $this->probe(['status' => 403, 'body' => ''], $requested)->check();

		$this->assertCount(count(webServerRules::probeTargets()), $results);
		$this->assertCount(count(webServerRules::probeTargets()), $requested);
	}
}
