	<nav class="sideNav" aria-label="{$ARIA_LABEL}">
		<div class="sideNavPanel">
			{$GROUPS}
			<hr>
			<div class="sideNavStyle js-only"><label>{$STYLE_LABEL} <select>{$STYLE_OPTIONS}</select></label><script>(function(){var v=null;try{v=localStorage.getItem("stylestyle")}catch(e){}if(v)document.currentScript.previousElementSibling.lastElementChild.value=v})();</script></div>
		</div>
	</nav>
