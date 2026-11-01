			<h3 id="frontAdminLogo">{$HEADING}</h3>
			<!--&IF($LOGO_URL,'<p><img class="frontLogo" src="{$LOGO_URL}" alt=""></p>','<p>{$NO_LOGO}</p>')-->
			<form class="frontLogoForm" method="POST" action="{$ACTION_URL}" enctype="multipart/form-data">
				{$CSRF_TOKEN}
				<input type="file" name="logo" accept="image/png,image/gif,image/jpeg,image/webp" required>
				<button type="submit">{$UPLOAD_LABEL}</button>
				<small>{$HINT}</small>
			</form>
			<!--&IF($LOGO_URL,'<form class="frontLogoForm" method="POST" action="{$ACTION_URL}">{$CSRF_TOKEN}<input type="hidden" name="action" value="remove"><button type="submit" data-confirm="{$REMOVE_CONFIRM}">{$REMOVE_LABEL}</button></form>','')-->
