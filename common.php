<?php

function drawErrorPageAndDie($txt){
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Error recived</title>
	<style>
		body {
			background-color: #d0f0c0;
			font-family: Arial, sans-serif;
		}
		.postblock {
			padding: 20px;
			background-color: #ffcccc;
			border: 2px solid #ff0000;
			margin: 10px 0;
			text-align: center;
		}
	</style>
	</head>
	<body>

	<div class="postblock">
		<p><?php echo $txt; ?></p>
	</div>

	</body>
	</html>
	<?php
	die();
}