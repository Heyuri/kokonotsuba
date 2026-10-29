<?php

namespace Kokonotsuba\kernel\providers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\ip\IPValidator;
use Kokonotsuba\log_in\authenticationHandler;
use Kokonotsuba\log_in\loginAttemptPolicy;
use Kokonotsuba\log_in\loginAttemptService;
use Kokonotsuba\log_in\loginSessionHandler;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Site-scope services, built from the global config rather than a board's.
 * The staff session is shared by every board and the front page, so its settings are too.
 */
final class siteProvider {
	public static function register(appContainer $container): void {
		$request = $container->get('request');

		$siteConfig = $container->get('configService')->getEffectiveConfig(GLOBAL_BOARD_UID);

		$loginSessionHandler = new loginSessionHandler($request, $siteConfig['STAFF_LOGIN_TIMEOUT']);
		$authenticationHandler = new authenticationHandler();

		// Brute-force throttle: a tight per-account counter, plus a looser per-IP one so spraying
		// usernames does not sidestep it.
		$loginAttemptService = new loginAttemptService(
			$container->get('loginAttemptRepository'),
			new loginAttemptPolicy(
				$siteConfig['LOGIN_MAX_ATTEMPTS'],
				$siteConfig['LOGIN_ATTEMPT_WINDOW'],
				$siteConfig['LOGIN_LOCKOUT_SECONDS'],
				$siteConfig['LOGIN_LOCKOUT_MAX_SECONDS']
			),
			new loginAttemptPolicy(
				$siteConfig['LOGIN_MAX_ATTEMPTS_PER_IP'],
				$siteConfig['LOGIN_ATTEMPT_WINDOW'],
				$siteConfig['LOGIN_LOCKOUT_SECONDS'],
				$siteConfig['LOGIN_LOCKOUT_MAX_SECONDS']
			),
			$siteConfig['LOGIN_ATTEMPT_RETENTION_DAYS']
		);

		$IPValidator = new IPValidator($siteConfig, $request->userIp(), $request);

		$container->set('siteConfig', $siteConfig);
		$container->set('loginSessionHandler', $loginSessionHandler);
		$container->set('authenticationHandler', $authenticationHandler);
		$container->set('loginAttemptService', $loginAttemptService);
		$container->set('IPValidator', $IPValidator);
	}
}
