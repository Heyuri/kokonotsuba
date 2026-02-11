<?php

namespace Puchiko\misc;

function executeFunction(callable $func, ...$params) {
	return $func(...$params);
}