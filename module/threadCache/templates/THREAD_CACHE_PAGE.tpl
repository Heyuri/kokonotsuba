	<link rel="stylesheet" href="{$CSS_URL}">
	<div class="threadCacheContainer">
		<h2>{$TITLE}</h2>
		<p>{$INTRO}</p>

		<!--&IF($SUCCESS_MESSAGE,'<p class="threadCacheSuccess">{$SUCCESS_MESSAGE}</p>','')-->

		<h3>{$ACTIONS_HEADING}</h3>
		<p>{$ACTIONS_DESC}</p>
		<form method="POST" action="{$MODULE_URL}" id="threadCacheForm" data-confirm-clear="{$CONFIRM_CLEAR}">
			{$CSRF_TOKEN}
			<table class="formtable">
				<tbody>
					<tr>
						<td class="postblock"><label for="threadCacheBoard">{$SCOPE_LABEL}</label></td>
						<td>
							<select name="boardUID" id="threadCacheBoard" class="inputtext">
								{$SCOPE_OPTIONS}
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="buttonSection">
				<button type="submit" name="threadCacheMode" value="clear">{$BTN_CLEAR}</button>
				<button type="submit" name="threadCacheMode" value="current">{$BTN_CURRENT}</button>
				<button type="submit" name="threadCacheMode" value="all">{$BTN_ALL}</button>
			</div>
			<p id="threadCacheStatus" class="threadCacheStatus" aria-live="polite"></p>
		</form>

		<h3>{$SUMMARY_HEADING}</h3>
		<div class="threadCacheScroll">
			{$BOARD_TABLE}
		</div>

		<h3>{$MOST_HIT_HEADING}</h3>
		<p>{$MOST_HIT_DESC}</p>
		<div class="threadCacheScroll">
			{$MOST_HIT_TABLE}
		</div>
	</div>
