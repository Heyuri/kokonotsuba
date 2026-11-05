<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\routers\securityHeaders;

/** The staff CSP: configured origins land in the right directives, relative ones add nothing. */
class SecurityHeadersTest extends TestCase {

	public function testRelativeUrlsLeaveOnlySelf(): void {
		$policy = securityHeaders::staffPolicy('/static/', '/');

		$this->assertSame(
			"script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; object-src 'none'; base-uri 'self'; form-action 'self'",
			$policy
		);
	}

	public function testStaticAndCdnOriginsAreScriptSources(): void {
		$policy = securityHeaders::staffPolicy('https://Static.Example.net/static/', '/', 'https://cdn.example.net:8443/img/');

		$this->assertStringContains("script-src 'self' https://static.example.net https://cdn.example.net:8443 'unsafe-inline'", $policy);
		$this->assertStringContains("form-action 'self'", $policy);
	}

	public function testWebsiteOriginIsAFormTarget(): void {
		$policy = securityHeaders::staffPolicy('/static/', 'https://boards.example.net/');

		$this->assertStringContains("form-action 'self' https://boards.example.net", $policy);
		$this->assertStringNotContains('boards.example.net', explode(';', $policy)[0]);
	}

	public function testProtocolRelativeUrlKeepsThePageScheme(): void {
		$policy = securityHeaders::staffPolicy('//static.example.net/', '/');

		$this->assertStringContains("script-src 'self' static.example.net 'unsafe-inline'", $policy);
	}

	public function testDuplicateAndNonHttpOriginsAreDropped(): void {
		$policy = securityHeaders::staffPolicy('https://static.example.net/', '/', 'https://static.example.net/cdn/');
		$this->assertSame(1, substr_count($policy, 'https://static.example.net'));

		$policy = securityHeaders::staffPolicy('javascript:alert(1)', '/');
		$this->assertStringNotContains('javascript', $policy);
	}

	public function testPageDomainAndSubdomainsAreScriptSources(): void {
		$policy = securityHeaders::staffPolicy('/static/', '/', null, 'Boards.Example.net:8080');

		$this->assertStringContains("script-src 'self' example.net *.example.net 'unsafe-inline'", $policy);
		$this->assertStringNotContains('example.net', explode('script-src', $policy)[0] . explode(';', $policy, 2)[1]);
	}

	public function testIpAndSingleLabelHostsAddNoDomain(): void {
		$bare = "script-src 'self' 'unsafe-inline'";
		$this->assertStringContains($bare, securityHeaders::staffPolicy('/static/', '/', null, '127.0.0.1:8080'));
		$this->assertStringContains($bare, securityHeaders::staffPolicy('/static/', '/', null, 'localhost'));
		$this->assertStringContains($bare, securityHeaders::staffPolicy('/static/', '/', null, 'evil.net/ x'));
	}
}
