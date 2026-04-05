	<div class="thread<!--&IF($MODULE_THREAD_CSS_CLASSES,'{$MODULE_THREAD_CSS_CLASSES}','')-->" id="t{$BOARD_UID}_{$THREAD_NO}" data-thread-uid="{$THREAD_UID}">
		<div class="innerbox">
			{$BOARD_THREAD_NAME}
			<div class="tnav">{$THREADNAV}</div>
			{$THREAD_OP}
			<div class="repliesOmitted"></div>
			<div class="latestReplies">
				{$REPLIES}
			</div>
		</div>
	</div>