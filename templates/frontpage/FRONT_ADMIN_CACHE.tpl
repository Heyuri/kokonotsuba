			<h3 id="frontAdminCache">{$HEADING}</h3>
			<form class="frontCacheForm" method="POST" action="{$ACTION_URL}">
				{$CSRF_TOKEN}
				<small>{$HINT}</small>
				<button type="submit">{$REBUILD_LABEL}</button>
			</form>
