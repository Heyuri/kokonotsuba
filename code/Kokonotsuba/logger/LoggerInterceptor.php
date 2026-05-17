<?php
/**
 * AOP Logger
 *
 * @package PMCLibrary
 * @version $Id$
 * @since 7th.Release
 */

namespace Kokonotsuba\logger;

use Exception;
use InvalidArgumentException;
use Kokonotsuba\interfaces\ILogger;
use Kokonotsuba\interfaces\MethodInterceptor;

/**
 * Logger Around Advice Interceptor
 */
class LoggerInterceptor implements MethodInterceptor {
	private $LOG;

	public function __construct(ILogger $logger) {
		$this->setLogger($logger);
	}

	private function setLogger(ILogger $logger) {
		$this->LOG = $logger;
	}

	public function invoke(array $callable, array $args) {
		$result = null;
		$methodName = $callable[1];

		try {
			$result = call_user_func_array($callable, $args);
		} catch (Exception $e) {
			$this->LOG->error('[%s] %s', $methodName, $e);
		}

		return $result;
	}
}

/**
 * Logger injector.
 * Uses MethodInterceptor to proxy-wrap an object's methods so that a Logger can be injected.
 */
class LoggerInjector {
	private $principalClass;
	private $mi;

	public function __construct($principalClass, MethodInterceptor $mi) {
		$this->setPrincipalClass($principalClass);
		$this->setMethodInterceptor($mi);
	}

	private function setPrincipalClass($principalClass) {
		if (!is_object($principalClass)) {
			throw new InvalidArgumentException('PrincipalClass is not a valid object.');
		}
		$this->principalClass = $principalClass;
	}

	private function setMethodInterceptor(MethodInterceptor $mi) {
		$this->mi = $mi;
	}

	/**
	 * Inject the logger via the MethodInterceptor.
	 *
	 * @param  string $name Name of the method being called
	 * @param  array $args Arguments passed to the method
	 * @return mixed       Return value of the called method
	 */
	public function __call($name, $args) {
		if (!method_exists($this->principalClass, $name)) {
			return;
		}
		return $this->mi->invoke(array($this->principalClass, $name), $args);
	}
}