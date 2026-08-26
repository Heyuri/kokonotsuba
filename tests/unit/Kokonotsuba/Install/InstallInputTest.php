<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\installInput;

/** Validation of the install form, and the config arrays it turns into. */
class InstallInputTest extends TestCase {

	private string $staticDir = '';

	protected function setUp(): void {
		$this->staticDir = sys_get_temp_dir().'/koko-install-input-'.bin2hex(random_bytes(4));
		mkdir($this->staticDir.'/static', 0777, true);
	}

	protected function tearDown(): void {
		@rmdir($this->staticDir.'/static');
		@rmdir($this->staticDir);
	}

	/** @param array<string, string> $changes */
	private function post(array $changes = []): array {
		return array_merge([
			'db_host' => '127.0.0.1',
			'db_port' => '3306',
			'db_name' => 'kokonotsuba',
			'db_user' => 'koko_user',
			'db_password' => 'hunter2',
			'admin_username' => 'admin',
			'admin_password' => 'correct horse',
			'admin_password_confirm' => 'correct horse',
			'board_identifier' => 'b',
			'board_title' => 'board@example.net',
			'board_sub_title' => 'an example board',
			'website_url' => 'https://example.net/kokonotsuba/boards/',
			'home_url' => 'https://example.net/',
			'static_url' => 'https://example.net/kokonotsuba/static/',
			'static_path' => $this->staticDir.'/static/',
		], $changes);
	}

	public function testAcceptsACompleteForm(): void {
		$input = installInput::fromArray($this->post());

		$this->assertTrue($input->isValid(), 'errors: '.json_encode($input->errors()));
	}

	public function testRejectsAnIdentifierWithASlash(): void {
		$errors = installInput::fromArray($this->post(['board_identifier' => 'b/../etc']))->errors();

		$this->assertTrue(isset($errors['board_identifier']));
	}

	public function testRejectsAShortAdminPassword(): void {
		$errors = installInput::fromArray($this->post(['admin_password' => 'short', 'admin_password_confirm' => 'short']))->errors();

		$this->assertStringContains('8 characters', $errors['admin_password'] ?? '');
	}

	public function testRejectsMismatchedPasswords(): void {
		$errors = installInput::fromArray($this->post(['admin_password_confirm' => 'something else']))->errors();

		$this->assertTrue(isset($errors['admin_password_confirm']));
	}

	public function testRejectsAUrlWithoutATrailingSlash(): void {
		$errors = installInput::fromArray($this->post(['website_url' => 'https://example.net/boards']))->errors();

		$this->assertStringContains('slash', $errors['website_url'] ?? '');
	}

	public function testAcceptsARootRelativeUrl(): void {
		$input = installInput::fromArray($this->post(['website_url' => '/boards/']));

		$this->assertTrue($input->isValid(), 'errors: '.json_encode($input->errors()));
	}

	public function testAcceptsAnyReasonableHomeLink(): void {
		foreach (['https://example.net/', '/', 'index.html', '../'] as $home) {
			$input = installInput::fromArray($this->post(['home_url' => $home]));

			$this->assertTrue($input->isValid(), $home.' rejected: '.json_encode($input->errors()));
			$this->assertSame($home, $input->siteSettings()['HOME']);
		}
	}

	public function testRejectsAnEmptyOrMalformedHomeLink(): void {
		$this->assertStringContains('Required', installInput::fromArray($this->post(['home_url' => '']))->errors()['home_url'] ?? '');
		$this->assertStringContains(
			'spaces',
			installInput::fromArray($this->post(['home_url' => 'https://example.net/a b']))->errors()['home_url'] ?? ''
		);
	}

	public function testRejectsAStaticPathThatIsNotThere(): void {
		$errors = installInput::fromArray($this->post(['static_path' => $this->staticDir.'/nope/']))->errors();

		$this->assertStringContains('No such directory', $errors['static_path'] ?? '');
	}

	public function testRejectsAnOutOfRangePort(): void {
		$errors = installInput::fromArray($this->post(['db_port' => '70000']))->errors();

		$this->assertTrue(isset($errors['db_port']));
	}

	public function testReportsOneErrorPerField(): void {
		$errors = installInput::fromArray($this->post(['db_name' => '', 'db_user' => '']))->errors();

		$this->assertCount(2, $errors);
	}

	public function testKeepsPasswordsExactlyAsTyped(): void {
		$input = installInput::fromArray($this->post(['db_password' => '  spaced  ']));

		$this->assertSame('  spaced  ', $input->value('db_password'));
	}

	public function testDoesNotSendPasswordsBackToTheForm(): void {
		$redraw = installInput::fromArray($this->post())->redrawValues();

		$this->assertFalse(isset($redraw['admin_password']));
		$this->assertFalse(isset($redraw['db_password']));
		$this->assertSame('b', $redraw['board_identifier']);
	}

	public function testDsnOmitsThePortForLocalhost(): void {
		$this->assertStringNotContains('port=', installInput::fromArray($this->post(['db_host' => 'localhost']))->databaseDsn());
		$this->assertStringContains('port=3306', installInput::fromArray($this->post())->databaseDsn());
	}

	public function testGeneratesSecretsWhenThereAreNone(): void {
		$settings = installInput::fromArray($this->post())->siteSettings();

		$this->assertMatchesRegex('/^[0-9a-f]{64}$/', $settings['TRIPSALT']);
		$this->assertNotSame($settings['TRIPSALT'], $settings['IDSEED']);
	}

	public function testKeepsSecretsThatAlreadyExist(): void {
		$existing = ['TRIPSALT' => 'keep-me', 'IDSEED' => 'keep-me-too'];
		$settings = installInput::fromArray($this->post())->siteSettings($existing);

		$this->assertSame('keep-me', $settings['TRIPSALT']);
		$this->assertSame('keep-me-too', $settings['IDSEED']);
	}

	public function testDatabaseSettingsKeepAnExistingAnonSalt(): void {
		$settings = installInput::fromArray($this->post())->databaseSettings('existing-salt');

		$this->assertSame('existing-salt', $settings['ANON_IP_SALT']);
		$this->assertSame(3306, $settings['DATABASE_PORT']);
	}

	public function testStaticPathAlwaysEndsInASlash(): void {
		$settings = installInput::fromArray($this->post(['static_path' => $this->staticDir.'/static']))->siteSettings();

		$this->assertSame($this->staticDir.'/static/', $settings['STATIC_PATH']);
	}
}
