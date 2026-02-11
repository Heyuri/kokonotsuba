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


/**
 * Function to require all PHP files in a directory
 * 
 * This is essentially a function autoloader that saves you having to `require` each 
 * individual file in a namespaces that isn't classes. 
 * 
 * This can potentially be insecure if you don't know the contents of the directory.
 * So it's crucial that permissions are read-only for /code/.
 * 
 * @param string $path - the file path of the directory to autoload from
 * @return void
 */
function autoloadDirectory(string $baseDir): void {

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() === 'php') {
			require_once $file->getRealPath();
		}
	}
}