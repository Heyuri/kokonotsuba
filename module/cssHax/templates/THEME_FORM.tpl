	<h3>Theme for thread No.{$THREAD_NUMBER}</h3>
	<p>Edit the thread's style and elements - AKA css hax.</p>
	<div class="themeForm">
		<form method="POST" action="{$MODULE_URL}">
			<input name="action" value="<!--&IF($IS_EDIT,'edit','create')-->" type="hidden">
			<input name="thread_uid" value="<!--&IF($THREAD_UID,'{$THREAD_UID}','')-->" type="hidden">
			<table>
				<tbody>
					<tr>
						<td class="postblock"><label for="backgroundHexColor">Background color</label></td>
						<td>
							<div class="formItemDescription">The background color. It will be obscured by a background image if one is set.</div>
							<input id="backgroundHexColor" name="backgroundHexColor" <!--&IF($BACKGROUND_HEX_COLOR,'value="{$BACKGROUND_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="replyBackgroundHexColor">Reply background color</label></td>
						<td>
							<div class="formItemDescription">The background color of reply elements.</div>
							<input id="replyBackgroundHexColor" name="replyBackgroundHexColor" <!--&IF($REPLY_BACKGROUND_HEX_COLOR,'value="{$REPLY_BACKGROUND_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="backgroundImageUrl">Background image</label></td>
						<td>
							<div class="formItemDescription">URL of an image which will be displayed as a tiled background for the thread.</div>
							<input class="inputtext" id="backgroundImageUrl" name="backgroundImageUrl" <!--&IF($BACKGROUND_IMAGE_URL,'value="{$BACKGROUND_IMAGE_URL}"','')--> placeholder="https://up.heyuri.net/src/0001.jpg">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="textHexColor">Text color</label></td>
						<td>
							<div class="formItemDescription">The color of the text.</div>
							<input id="textHexColor" name="textHexColor" <!--&IF($TEXT_HEX_COLOR,'value="{$TEXT_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="audio">Audio</label></td>
						<td>
							<div class="formItemDescription">URL of audio that plays when opening the thread.</div>
							<input class="inputtext" id="audio" name="audio" <!--&IF($AUDIO,'value="{$AUDIO}"','')--> placeholder="https://up.heyuri.net/src/0002.mp3">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="rawStyling">Raw styling</label></td>
						<td>
							<div class="formItemDescription">Raw CSS for the thread - you will have to create blocks yourself.</div>
							<textarea class="inputtext" id="rawStyling" name="rawStyling"placeholder="#t1_123 { display: hidden; }"><!--&IF($RAW_STYLING,'{$RAW_STYLING}','')--></textarea>
						</td>
					</tr>
					<!--&IF($DATE_ADDED,'
						<tr>
							<td class="postblock">Date added</td>
							<td>
								<div class="dateThemeAdded">{$DATE_ADDED}</div>
							</td>
						</tr>
						','')-->
					<!--&IF($ADDED_BY,'
						<tr>
							<td class="postblock">Theme added by</td>
							<td>
								<div class="themeAddedBy">{$ADDED_BY}</div>
							</td>
						</tr>
						','')-->
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="Submit">
				<!--&IF($IS_EDIT,'<button name="action" value="delete">Delete theme</button>','')-->
			</div>
		</form>
	</div>