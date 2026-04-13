	<div id="styleswitch" class="js-only"><label>Style: <select id="styleSwitcherSelect"></select></label><script>(function(){var s=document.getElementById("styleSwitcherSelect"),l=document.getElementsByClassName("linkstyle"),o=document.createElement("option");o.value=o.textContent="Random";s.appendChild(o);for(var i=0;i<l.length;i++){o=document.createElement("option");o.value=o.textContent=l[i].title;s.appendChild(o)}var a=localStorage.getItem("stylestyle");if(a)s.value=a;else{for(var i=0;i<l.length;i++){if(!l[i].disabled){s.value=l[i].title;break}}}})();</script></div>
	<div id="footer">
		{$FOOTER}
		<div id="footerText">{$FOOTTEXT}</div>
	</div>
	<div id="bottom"></div>
</body>
</html>