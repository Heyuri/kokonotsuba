<?php
	/*
	Online counter for kokonotsuba
	from http://php.loglog.jp/
	*/
	define(USR_LST, STORAGE_PATH."users.dat"); 
	define(TIMEOUT, 300); // Update every 5 minutes

	$usr_arr = file(USR_LST);
	touch(USR_LST);

	$fp = fopen(USR_LST, "w");
	$now = time();
	$addr = $_SERVER['REMOTE_ADDR'];

	for ($i = 0; $i < sizeof($usr_arr); $i++) { 
	  list($ip_addr,$stamp) = explode("|", $usr_arr[$i]);
	  if (($now - $stamp) < TIMEOUT && $ip_addr != $addr) { 
		  fputs($fp, $ip_addr.'|'.$stamp);
	  } 
	} 
	fputs($fp, $addr.'|'.$now."\n");
	fclose($fp);

	$count = count($usr_arr);
	$data = '<div style="background-color: #000000; color: #00FF00;">Currently <b>'.$count.'</b> unique user'.($count > 1 ? 's' : '').' online</div>';
	echo $data;
?>