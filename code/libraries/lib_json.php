<?php
/**
 * Sends a JSON response with the given data and HTTP status code.
 *
 * @param mixed $data         The data to encode as JSON (array, object, etc.).
 * @param int   $statusCode   The HTTP status code to send (default 200).
 *
 * @return void
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sends a JSON response with caching headers.
 *
 * @param mixed $data          The data to encode as JSON (array, object, etc.).
 * @param int   $cacheSeconds  How long the response should be cached (in seconds).
 * @param int   $statusCode    The HTTP status code to send (default 200).
 *
 * @return void
 */
function sendCachedJsonResponse($data, $cacheSeconds = 3600, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    // Cache headers
    header('Cache-Control: public, max-age=' . (int)$cacheSeconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Renders a JSON error response with message and code.
 *
 * @param string $message     The error message to display.
 * @param int    $statusCode  The HTTP status code (default 400).
 *
 * @return void
 */
function renderJsonErrorPage($message, $statusCode = 400) {
    $errorData = [
        'error'   => true,
        'code'    => $statusCode,
        'message' => $message
    ];

    sendJsonResponse($errorData, $statusCode);
}

/**
 * Renders a normal JSON response (for example, a thread payload).
 *
 * @param mixed $data         The JSON-ready data to send.
 * @param int   $statusCode   The HTTP status code (default 200).
 *
 * @return void
 */
function renderJsonPage($data, $statusCode = 200) {
    sendJsonResponse($data, $statusCode);
}

/**
 * Renders a JSON response with HTTP cache headers.
 *
 * @param mixed $data          The JSON-ready data to send.
 * @param int   $cacheSeconds  How long to allow clients to cache (in seconds).
 * @param int   $statusCode    The HTTP status code (default 200).
 *
 * @return void
 */
function renderCachedJsonPage($data, $cacheSeconds = 3600, $statusCode = 200) {
    sendCachedJsonResponse($data, $cacheSeconds, $statusCode);
}
