<?php

	// image source => URL
	$ads = array(
	'rules2.png' => 'https://www.heyuri.net/index.php?p=rules',
	'nominate1.png' => 'https://cgi.heyuri.net/nominate/',
	'sw2.png' => 'https://dis.heyuri.net/sw/',
	);
	
	$random = array_rand($ads);
	echo '<!DOCTYPE html>
	<html lang="en" style="overflow:hidden;">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Full banner</title>
	</head>
	<body style="margin: 0;">
	<a href="'.$ads[$random].'" target="_blank"><img style="max-width: 100%;" src="'.$random.'">
	</body>
	</html>';
	
?>