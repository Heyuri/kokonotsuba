<?php

/**
 * PSR-4–style autoloader that maps fully qualified class names
 * directly to files under the /code directory.
 *
 * All namespaces are resolved relative to the /code directory.
 * The autoloader will silently ignore classes whose files do not exist.
 *
 * @param string $class Fully qualified class name
 * @return void
 */
spl_autoload_register(function (string $class): void {
	$baseDir = __DIR__ . '/code/';

	$file = $baseDir . str_replace('\\', '/', $class) . '.php';

	if (is_file($file)) {
		require $file;
	}
});