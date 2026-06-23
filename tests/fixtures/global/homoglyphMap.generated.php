<?php

/**
 * Homoglyph lookup-table fixture for the test suite.
 *
 * normalize\_buildHomoglyphMap() loads this file when it exists, instead of
 * fetching Unicode's confusables.txt over the network. Using the built-in
 * manual map keeps the unit tests and fuzzer hermetic and deterministic.
 */
return \Puchiko\normalize\_buildManualHomoglyphMap();
