<?php
/**
 * Wraps an object's method calls so failures get logged without the object itself
 * knowing about a logger.
 */

namespace Kokonotsuba\logger;

use Exception;
use InvalidArgumentException;
use Kokonotsuba\interfaces\ILogger;
use Kokonotsuba\interfaces\MethodInterceptor;

/**
 * Runs a call and logs anything it throws, returning null in place of the result.
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
 * Proxy that routes every call on the wrapped object through a MethodInterceptor.
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
	 * @param  string $name Method being called on the wrapped object
	 * @param  array  $args Arguments it was called with
	 * @return mixed        Whatever the interceptor returns
	 */
	public function __call($name, $args) {
		if (!method_exists($this->principalClass, $name)) {
			return;
		}
		return $this->mi->invoke(array($this->principalClass, $name), $args);
	}
}