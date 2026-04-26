<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: transparent; overflow: hidden; }
		body { display: flex; align-items: center; justify-content: center; }
		.pmViewport img, .pmViewport iframe { max-width: {$FRAME_WIDTH}px; max-height: {$FRAME_HEIGHT}px; width: auto; height: auto; display: block; }
	</style>
</head>
<body>
	<div class="pmViewport">{$AD_HTML}</div>
</body>
</html>