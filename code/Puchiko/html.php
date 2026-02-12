//<?php

namespace Puchiko\html;

use DOMDocument;
use DOMNode;
use DOMXPath;

function drawAlert($message) {
	$escapedMessage = addslashes($message);
	$escapedMessage = str_replace(array("\r", "\n"), '', $escapedMessage);	
	echo "	<script type='text/javascript'> 
			alert('" . $escapedMessage . "');
		</script>";
}

/**
 * Create a DOMDocument from an HTML fragment.
 *
 * A temporary wrapper element is added around the fragment to prevent
 * DOMDocument from discarding or rearranging the user's original root
 * elements when normalizing the HTML. This wrapper is later removed
 * before returning the final truncated output.
 *
 * @param string $html The source HTML
 * @return DOMDocument The loaded DOMDocument instance
 */
function createDomFromHtml(string $html): DOMDocument {
	$dom = new DOMDocument();

	// Suppress libxml warnings during HTML parsing
	$prev = libxml_use_internal_errors(true);

	$dom->loadHTML(
		'<?xml encoding="utf-8" ?><div id="__root_wrapper__">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);

	// Clear any collected errors and restore previous state
	libxml_clear_errors();
	libxml_use_internal_errors($prev);

	return $dom;
}

/**
 * Get the actual root node that contains the HTML fragment.
 *
 * In this implementation, the traversal root remains <body> because the
 * recursive truncation logic already functions correctly starting from
 * the body element. The wrapper exists only to keep the fragment stable
 * during parsing and is stripped after traversal.
 *
 * @param DOMDocument $dom
 * @return DOMNode
 */
function getDomRoot(DOMDocument $dom): DOMNode {
	$body = $dom->getElementsByTagName('body')->item(0);

	if ($body !== null) {
		return $body;
	}

	return $dom->documentElement;
}

/**
 * Truncate an HTML string to a fixed number of text characters while
 * keeping the resulting markup valid. Tags remain balanced, entities
 * remain intact, and the DOM is walked recursively from the traversal
 * root returned by getDomRoot(). The wrapper introduced in
 * createDomFromHtml() is removed before returning the final result.
 *
 * @param string $html  The source HTML to truncate
 * @param int    $limit Maximum number of text characters allowed
 * @return string       The truncated, valid HTML
 */
function truncateHtml(string $html, int $limit): string {
	$dom = createDomFromHtml($html);
	$root = getDomRoot($dom);

	$count = 0;
	$end   = false;

	$walker = function(DOMNode $node) use (&$walker, &$count, $limit, &$end) {
		if ($end) {
			return;
		}

		// Handle plain text nodes
		if ($node->nodeType === XML_TEXT_NODE) {
			$len = mb_strlen($node->nodeValue);

			// If the limit would be exceeded, truncate this text node
			if ($count + $len > $limit) {
				$keep = max(0, $limit - $count);
				$node->nodeValue = mb_substr($node->nodeValue, 0, $keep);
				$end = true;
			}

			$count += $len;
			return;
		}

		// Recursively walk element child nodes
		for ($i = 0; $i < $node->childNodes->length; $i++) {
			$child = $node->childNodes->item($i);
			$walker($child);

			// After truncation, remove any remaining siblings
			if ($end) {
                while ($node->childNodes->length > $i + 1) {
					$node->removeChild($node->lastChild);
				}
				break;
			}
		}
	};

	$walker($root);

	// Remove the wrapper and return only its child content
	$wrapper = $dom->getElementById('__root_wrapper__');

	if ($wrapper !== null) {
		// If wrapper has a single child, return only that child's HTML
		if ($wrapper->childNodes->length === 1) {
			return $dom->saveHTML($wrapper->firstChild);
		}

		// Otherwise concatenate all children
		$out = '';
		for ($i = 0; $i < $wrapper->childNodes->length; $i++) {
			$out .= $dom->saveHTML($wrapper->childNodes->item($i));
		}
		return $out;
	}

	// Fallback: return the traversal root if wrapper is missing
	return $dom->saveHTML($root);
}

/**
 * Truncate an HTML fragment by limiting the number of <br> line breaks.
 * Everything after the allowed number of breaks is removed.
 *
 * @param string $html        The source HTML fragment
 * @param int    $maxLines    Maximum number of <br>-separated lines to keep
 * @return string             The truncated HTML fragment
 */
function truncateHtmlByLineBreak(string $html, int $maxLines): string {
	// Load HTML fragment into DOMDocument
	$dom = createDomFromHtml($html);

	// Select the actual fragment root
	$root = getDomRoot($dom);

	// Find all <br> elements under the root, in document order
	$xpath = new DOMXPath($dom);
	$brNodes = $xpath->query('.//br', $root);

	// If there are not more than $maxLines <br> tags, no truncation needed
	if ($brNodes === false || $brNodes->length <= $maxLines) {
		$wrapper = $dom->getElementById('__root_wrapper__');

		if ($wrapper !== null) {
			if ($wrapper->childNodes->length === 1) {
				return $dom->saveHTML($wrapper->firstChild);
			}

			$out = '';
			for ($i = 0; $i < $wrapper->childNodes->length; $i++) {
				$out .= $dom->saveHTML($wrapper->childNodes->item($i));
			}
			return $out;
		}

		return $dom->saveHTML($root);
	}

	// This is the first <br> that should be removed (the (maxLines + 1)-th one)
	$limitNode = $brNodes->item($maxLines);

	// Recursive pruner: walks the tree and, once it finds $limitNode,
	// removes it and everything that comes after it in document order.
	$prune = function(DOMNode $node) use (&$prune, $limitNode): bool {
		for ($i = 0; $i < $node->childNodes->length; $i++) {
			$child = $node->childNodes->item($i);

			// If we've reached the limit <br>, remove it and all following siblings
			if ($child === $limitNode) {
				while ($node->childNodes->length > $i) {
					$node->removeChild($node->lastChild);
				}
				return true;
			}

			// Recurse into children
			if ($child->hasChildNodes()) {
				if ($prune($child)) {
					// Truncation happened inside this child; remove all siblings after it
					while ($node->childNodes->length > $i + 1) {
						$node->removeChild($node->lastChild);
					}
					return true;
				}
			}
		}

		// No truncation triggered in this subtree
		return false;
	};

	// Perform the pruning starting from the fragment root
	$prune($root);

	// Strip the wrapper and return only the fragment content (same pattern as truncateHtml)
	$wrapper = $dom->getElementById('__root_wrapper__');

	if ($wrapper !== null) {
		if ($wrapper->childNodes->length === 1) {
			return $dom->saveHTML($wrapper->firstChild);
		}

		$out = '';
		for ($i = 0; $i < $wrapper->childNodes->length; $i++) {
			$out .= $dom->saveHTML($wrapper->childNodes->item($i));
		}
		return $out;
	}

	return $dom->saveHTML($root);
}

/**
 * Count the number of <br> elements in an HTML fragment.
 *
 * @param string $html The source HTML
 * @return int         The number of <br> tags found
 */
function countHtmlLineBreaks(string $html): int {
	$dom = createDomFromHtml($html);
	$root = getDomRoot($dom);

	$xpath = new DOMXPath($dom);
	$brNodes = $xpath->query('.//br', $root);

	if ($brNodes === false) {
		return 0;
	}

	return $brNodes->length;
}