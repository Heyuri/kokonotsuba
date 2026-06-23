<?php

namespace Koko\Tests\Framework;

/**
 * Thrown by TestCase assertions when an expectation is not met.
 *
 * A test that throws this is reported as a FAILURE (an expected condition was
 * false). Any other Throwable bubbling out of a test is reported as an ERROR
 * (the code under test, or the test itself, blew up unexpectedly).
 */
class AssertionFailedException extends \Exception {}
