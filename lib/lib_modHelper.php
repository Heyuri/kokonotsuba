<?php
/*
 *  for heyuri from nashikouen.
 */

/**
 *  gets the storage folder for given moduel object.
 *
 *  @param  ModuleHelper    the curent moduel you are using. typicly $this
 *  @return string          path to moduel directory you have control over
 */
 function getModDirectory($modObj){
    $moduleName = get_class($modObj);
    $modDir = STORAGE_PATH . $moduleName . '/';

    if (!file_exists($modDir)) {
        mkdir($modDir, 0770, true);
    }

    return $modDir;
 }

 /*
/**
 * Gets the associative array from the file, or creates the file if it doesn't exist.
 *
 * @param string $filePath   Path to the file.
 * @return array             The associative array.
 *
function getData($filePath) {
    // If the file does not exist, create it and return an empty array
    if (!file_exists($filePath)) {
        // Attempt to create the file with empty content
        file_put_contents($filePath, '');
        return [];
    }

    $content = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($content === false) {
        return [];
    }

    $associativeTable = [];
    
    foreach ($content as $line) {
        list($key, $value) = explode(':', $line, 2);
        $key = str_replace(['\\:', '\\|'], [':', '|'], $key);
        $value = str_replace(['\\:', '\\|'], [':', '|'], $value);
        $associativeTable[$key] = $value;
    }

    return $associativeTable;
}

/**
 * Saves an associative array into the file with each key-value pair on a new line.
 *
 * @param string $filePath         Path to the file.
 * @param array  $associativeTable  The associative array to save.
 *
function setData($filePath, $associativeTable) {
    // Ensure it's an associative array
    if (!is_array($associativeTable)) {
        throw new InvalidArgumentException('Expected an associative array.');
    }

    $lines = [];
    
    foreach ($associativeTable as $key => $value) {
        $key = str_replace([':', '|'], ['\\:', '\\|'], $key);
        $value = str_replace([':', '|'], ['\\:', '\\|'], $value);
        $lines[] = $key . ':' . $value;
    }
    
    // Write each key-value pair on a new line in the file
    file_put_contents($filePath, implode(PHP_EOL, $lines));
}
*/