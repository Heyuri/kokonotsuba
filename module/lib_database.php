<?php

function addSlashesToArray(&$arrayOfValuesForQuery) {
	foreach ($arrayOfValuesForQuery as &$item) {
		$item = "'" . addslashes($item) . "'";
	}
}



