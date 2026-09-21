<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\debug\requestProfiler;
use Kokonotsuba\request\request;

/** The profile link, the profile document, and that the sampler stays out of the way where Excimer is not loaded. */
final class RequestProfilerTest extends TestCase {

	// ─── profileUrlFor ───────────────────────────────────────────

	public function testTheParameterIsAppendedToABareUrl(): void {
		$this->assertSame('/b/koko.php?kokoProfile=1', requestProfiler::profileUrlFor('/b/koko.php'));
	}

	public function testExistingQueryIsKept(): void {
		$url = requestProfiler::profileUrlFor('/b/koko.php?res=12&page=2');

		$this->assertSame('/b/koko.php?res=12&page=2&kokoProfile=1', $url);
	}

	public function testAnExistingParameterIsNotDoubled(): void {
		$url = requestProfiler::profileUrlFor('/b/koko.php?kokoProfile=1&res=3');

		$this->assertSame(1, substr_count($url, 'kokoProfile='));
		$this->assertStringContains('res=3', $url);
	}

	public function testAnEmptyQueryAndABareQuestionMark(): void {
		$this->assertSame('/b/koko.php?kokoProfile=1', requestProfiler::profileUrlFor('/b/koko.php?'));
		$this->assertSame('?kokoProfile=1', requestProfiler::profileUrlFor(''));
	}

	public function testArrayParametersSurvive(): void {
		$url = requestProfiler::profileUrlFor('/b/koko.php?boards%5B%5D=1&boards%5B%5D=2');

		$this->assertStringContains('boards%5B0%5D=1', $url);
		$this->assertStringContains('boards%5B1%5D=2', $url);
		$this->assertStringContains('kokoProfile=1', $url);
	}

	public function testHostileQueryIsReEncoded(): void {
		$url = requestProfiler::profileUrlFor('/b/koko.php?res=<script>&x="y"&' . rawurlencode("日本語") . '=' . rawurlencode('é&=?'));

		$this->assertStringNotContains('<', $url);
		$this->assertStringNotContains('"', $url);
		$this->assertStringContains('res=%3Cscript%3E', $url);
		$this->assertStringContains('%E6%97%A5%E6%9C%AC%E8%AA%9E=%C3%A9%26%3D%3F', $url);
	}

	public function testFragmentIsTreatedAsQueryTextNotDropped(): void {
		// A fragment never reaches the server; if one does appear it is just part of the query.
		$url = requestProfiler::profileUrlFor('/b/koko.php?res=1#top');

		$this->assertStringContains('kokoProfile=1', $url);
		$this->assertStringContains('res=1%23top', $url);
	}

	// ─── buildDocument ───────────────────────────────────────────

	private function speedscope(): array {
		return ['$schema' => 'https://www.speedscope.app/file-format-schema.json', 'shared' => ['frames' => [['name' => 'main']]], 'profiles' => [['type' => 'sampled']]];
	}

	private function snapshot(): array {
		return ['wallSeconds' => 0.2, 'cpuSeconds' => 0.05, 'cpuUserSeconds' => 0.04, 'cpuSystemSeconds' => 0.01, 'queryCount' => 2, 'querySeconds' => 0.0345, 'peakMemoryBytes' => 4194304];
	}

	public function testTheProfileKeepsWhatSpeedscopeNeedsAndAddsTheCounters(): void {
		$document = requestProfiler::buildDocument($this->speedscope(), $this->snapshot(), [
			['sql' => 'SELECT 1', 'seconds' => 0.0012],
			['sql' => 'UPDATE t SET a = ?', 'seconds' => 0.0333],
		], '/b/koko.php?res=1');

		$this->assertSame($this->speedscope()['shared'], $document['shared']);
		$this->assertSame($this->speedscope()['profiles'], $document['profiles']);
		$this->assertSame('/b/koko.php?res=1', $document['name']);
		$this->assertSame('/b/koko.php?res=1', $document['koko']['requestUri']);
		$this->assertSame(200.0, $document['koko']['wallMs']);
		$this->assertSame(50.0, $document['koko']['cpuMs']);
		$this->assertSame(4194304, $document['koko']['peakMemoryBytes']);
		$this->assertSame(2, $document['koko']['queries']['count']);
		$this->assertSame(34.5, $document['koko']['queries']['ms']);
		$this->assertSame([
			['sql' => 'SELECT 1', 'ms' => 1.2],
			['sql' => 'UPDATE t SET a = ?', 'ms' => 33.3],
		], $document['koko']['queries']['statements']);
	}

	public function testTheQueryCountIsTheCounterNotTheListLength(): void {
		// Statements are capped; the count is not, so it must come from the counter.
		$document = requestProfiler::buildDocument([], ['queryCount' => 7000, 'querySeconds' => 1.5] + $this->snapshot(), [], '/b/');

		$this->assertSame(7000, $document['koko']['queries']['count']);
		$this->assertSame([], $document['koko']['queries']['statements']);
	}

	public function testAnEmptyUriStillNamesTheProfile(): void {
		$document = requestProfiler::buildDocument([], $this->snapshot(), [], '');

		$this->assertSame('kokonotsuba', $document['name']);
		$this->assertMatchesRegex('/^\d{4}-\d{2}-\d{2}T/', $document['koko']['generatedAt']);
	}

	public function testTheDocumentIsJsonEncodable(): void {
		$document = requestProfiler::buildDocument($this->speedscope(), $this->snapshot(), [['sql' => "SELECT '日本語'", 'seconds' => 0.001]], '/b/koko.php');

		$json = json_encode($document);

		$this->assertNotSame(false, $json);
		$this->assertStringContains('"count":2', $json);
	}

	// ─── startIfRequested ────────────────────────────────────────

	public function testNothingStartsWithoutTheExtension(): void {
		if (requestProfiler::isAvailable()) {
			$this->pass();
			return;
		}

		$request = new request([requestProfiler::PARAMETER => '1'], [], ['REQUEST_TIME_FLOAT' => microtime(true)]);
		$level = ob_get_level();

		requestProfiler::startIfRequested($request);

		$this->assertFalse(requestProfiler::isRunning());
		$this->assertSame($level, ob_get_level());
	}
}
