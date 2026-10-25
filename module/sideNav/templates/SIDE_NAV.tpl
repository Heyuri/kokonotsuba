	<nav class="sideNav" aria-label="{$ARIA_LABEL}">
		<div class="sideNavPanel">
			<details class="sideNavGroup" data-group="main" open>
				<summary class="sideNavGroupTitle postblock">{$MAIN_LABEL}</summary>
				<ul class="sideNavList">
					<li class="sideNavItem"><a href="{$HOME_URL}" target="_top">{$HOME_LABEL}</a></li>
					<li class="sideNavItem sideNavHide js-only"><a href="javascript:void(0);">{$HIDE_LABEL}</a></li>
					<li class="sideNavItem"><a href="{$OVERBOARD_URL}">{$OVERBOARD_LABEL}</a></li>
				</ul>
			</details>
			{$GROUPS}
			<hr>
			<div class="sideNavStyle js-only"><label>{$STYLE_LABEL} <select></select></label></div>
		</div>
	</nav>
