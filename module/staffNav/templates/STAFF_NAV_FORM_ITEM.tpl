	<li class="staffNavItem">
		<form class="staffNavForm" method="POST" action="{$URL}">
			{$CSRF_TOKEN}
			<button type="submit" class="staffNavLink buttonLink" title="{$LINK_TITLE}">
				<span class="staffNavLabel">{$LABEL}</span>
				<span class="indicator indicator-staffNav staffNavCount {$COUNT_CLASS}">{$COUNT}</span>
			</button>
		</form>
	</li>
