			
		<div class="thread<!--&IF($MODULE_THREAD_CSS_CLASSES,'{$MODULE_THREAD_CSS_CLASSES}','')-->" id="t{$BOARD_UID}_{$THREAD_NO}" data-thread-uid="{$THREAD_UID}">
			<!--&IF($BOARD_THREAD_NAME,'{$BOARD_THREAD_NAME}','')-->
			<div class="tnav">{$THREADNAV}</div>
			<div class="threadHeader">
				<!--&IF($MODULE_THREAD_HEADER,'{$MODULE_THREAD_HEADER}','')-->
			</div>
			{$THREAD_OP}
			{$REPLIES}
		</div>