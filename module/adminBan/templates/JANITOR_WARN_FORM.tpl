	<div class="banFormContainer">
		<form class="warnForm" method="POST" action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->">
			<h3 class="centerText">{$FORM_HEADING}</h3>

			<input type="hidden" name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->">

			<table id="warnFormTable" class="formtable">
				<tbody>
					<tr>
						<td class="postblock"><label for="post_number">{$LABEL_POST}</label></td>
						<td><span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span></td>
					</tr>
					<tr>
						<td class="postblock"><label for="warnmsg">{$LABEL_REASON}</label></td>
						<td><textarea class="inputtext" id="warnmsg" name="msg" rows="4" cols="50"><!--&IF($REASON_DEFAULT,'{$REASON_DEFAULT}','')--></textarea></td>
					</tr>
					<tr>
						<td class="postblock"><label for="warnPublic">{$LABEL_PUBLIC}</label></td>
						<td>
							<input type="checkbox" id="warnPublic" name="public">
							<div class="formItemDescription">{$DESC_PUBLIC}</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection centerText">
				<input id="bigredbutton" type="submit" value="{$SUBMIT_TEXT}">
			</div>
		</form>
	</div>
