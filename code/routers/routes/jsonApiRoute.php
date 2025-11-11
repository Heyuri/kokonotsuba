<?php

class jsonApiRoute {
	public function __construct(
		private array $allowedClasses
	) {}

	public function routeApiRequests(): void {
		// get the API switch, used to determine which api to run
		$apiEndpoint = $_REQUEST['apiEndpoint'] ?? null;

		// throw exception if null, not a string, or empty
		// it should ONLY be a label for a api route like 'board' for the board API, or 'thread' for the thread api
		// any further requests can be handled by the associated API classes themselves.
		// Those classes mainly handle the filesystem side of API management - but can also handle some aspects of the request
		// this class/method is just meant to sent off requests to the correct api handler
		$this->validateEndpoint($apiEndpoint, "Invalid API endpoint route!");

		// get the endpoint page
		// endpointPage is essentially a value to represent a sub-page
		// so apiEndpoint represetnts which endpoint to use. But endpointPage what to do in that api
		// e.g:
		// apiEndpoint = 'board'
		// endpointPage = 'boardList'
		// which will get the board list from the board API
		$endpointPage = $_REQUEST['endpointPage'] ?? null;

		// validate specific endpoint page as well
		$this->validateEndpoint($endpointPage, "Invalid endpoint page!");

		// run the switch to route the request
		$this->invokeApi($apiEndpoint, $endpointPage);
	}

	private function validateEndpoint(mixed $apiEndpoint, string $errorMessage): void {
		// validate for null values, empty strings and integers
		if(is_null($apiEndpoint) || empty($apiEndpoint) || is_int($apiEndpoint)) {
			throw new BoardException($errorMessage);
		}
	}

	private function invokeApi(string $apiEndpoint, string $endpointPage): void {
		$fullClass = $this->resolveClassName($apiEndpoint);
		$this->validateClassExists($fullClass);

		$apiClass = $this->getAllowedClassInstance($fullClass);
		$this->validateApiClass($apiClass, $fullClass);

		$this->invokeApiMethod($apiClass, $endpointPage);
	}

	/**
	 * Convert the API endpoint string into a full class name.
	 */
	private function resolveClassName(string $apiEndpoint): string
	{
		if (empty($apiEndpoint)) {
			throw new BoardException("No API endpoint specified!");
		}

		// If endpoints are already full class names, just return them.
		// Otherwise, you could add normalization logic here:
		// e.g. ucfirst($apiEndpoint) or adding namespaces.
		return $apiEndpoint;
	}

	/**
	 * Ensure the class exists and is loadable.
	 */
	private function validateClassExists(string $fullClass): void {
		if (!class_exists($fullClass)) {
   			 throw new BoardException("Unknown API class '$fullClass'.");
		}
	}

	/**
	 * Retrieve a cached or registered instance of the allowed class.
	 */
	private function getAllowedClassInstance(string $fullClass): object {
		if (
			!isset($this->allowedClasses[$fullClass]) ||
			!is_object($this->allowedClasses[$fullClass])
		) {
			throw new BoardException("API class '$fullClass' is not registered or invalid in allowedClasses.");
		}

		return $this->allowedClasses[$fullClass];
	}

	/**
	 * Validate that the given object matches expectations.
	 */
	private function validateApiClass(object $apiClass, string $fullClass): void {
		if (!($apiClass instanceof $fullClass)) {
			throw new BoardException("Registered API class for '$fullClass' is not an instance of $fullClass.");
		}
	
		if (!method_exists($apiClass, 'invoke')) {
			throw new BoardException("API class '$fullClass' does not have an invoke() method.");
		}
	}

	/**
	 * Safely invoke the API class's invoke() method.
	 */
	private function invokeApiMethod(object $apiClass, string $endpointPage): void
	{
		try {
			$apiClass->invoke($endpointPage);
		} catch (Throwable $e) {
			throw new BoardException("Error while invoking API: " . $e->getMessage(), 0, $e);
		}
	}

}