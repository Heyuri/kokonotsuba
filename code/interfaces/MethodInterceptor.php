<?php

/**
 * MethodInterceptor (AOP Around Advice)
 */
interface MethodInterceptor {
	/**
	 * Proxy method invocation.
	 *
	 * @param  array $callable The method to be invoked
	 * @param  array $args     Arguments passed to the method
	 * @return mixed           Result of the method execution
	 */
	public function invoke(array $callable, array $args);
}
