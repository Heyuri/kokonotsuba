	<div>[<a href="{$RETURN_URL}">Return</a>]</div>

	<h2 id="banHeading" class="centerText warning">
		<!--&IF($IS_BANNED,'You have been {$BAN_TYPE}! ヽ(ー_ー )ノ','You are not banned! <span class="ascii">ヽ(´∇`)ノ</span>')-->
	</h2>

	<div id="banScreen">
		<div id="banScreenText">
			<p><!--&IF($REASON,'{$REASON}','')--></p>
			<!--&IF($IS_BANNED,'{$BAN_DETAIL}','')-->
		</div>

		<img id="banimg" src="{$BAN_IMAGE}" alt="<!--&IF($IS_BANNED,'BANNED!','NOT BANNED!')-->">
	</div>

	<hr id="hrBan">