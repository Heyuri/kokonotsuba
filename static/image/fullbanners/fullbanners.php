<?php

	// image source => URL
	$ads = array(
	'rules2.png' => 'https://www.heyuri.net/index.php?p=rules',
	'nominate1.png' => 'https://cgi.heyuri.net/nominate/',
	'sw2.png' => 'https://dis.heyuri.net/sw/',
	);
	
	$random = array_rand($ads);
	echo '<body style="margin: 0;">
	<a href="'.$ads[$random].'" target="_blank"><img style="max-width: 100%;" src="'.$random.'">
	</body>';
	
?>