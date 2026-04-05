<div class="warnFormContainer">
	<form action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->" method="POST">
		<h3 class="centerText">Warn user</h3>

		<label> Post No. <span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span> </label><br>
		<input type="hidden" name="postUid"  value="<!--&IF($POST_UID,'{$POST_UID}','')-->"><br>
		<label>Reason:<br>
			<textarea name="msg" cols="80" rows="6"><!--&IF($REASON_DEFAULT,'{$REASON_DEFAULT}','')--></textarea>
		</label><br>
		<label>Public? <input type="checkbox" name="public"></label>

		<div class="buttonSection centerText">
			<input type="submit" value="Warn">
		</div>
	</form>
</div>