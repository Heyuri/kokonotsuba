	<div class="centerText">
		<h2 class=" error">{$MESG}</h2>
		<!--&IF($SHOW_ERROR_IMAGE,'<p><img class="errorImage" src="{$STATIC_URL}image/oopsie.jpg" width="640" height="480" alt=""></p>','')-->

		<p>
			[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
			[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
		</p>
		<hr>
	</div>