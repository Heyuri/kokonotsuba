<?php
/*────────────────────────────────────────────────────────────
	The main judgment of the functions of the program			
────────────────────────────────────────────────────────────*/

//Check if this is the backend
if(file_exists('.backend')) die("You are trying to access the instance's backend");

// Global configuration file
$globalConfig = getGlobalConfig();
