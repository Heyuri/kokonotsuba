				<div class="banEntry" id="ban{$BAN_ID}">
					<h3 class="banEntryHeading">{$SCOPE_LABEL}<!--&IF($BOARD_TITLE,' <span class="banEntryBoard">{$BOARD_TITLE}</span>','')--></h3>

					<div class="banEntryReason">
						<span class="banEntryReasonLabel">{$REASON_LABEL}</span> {$REASON}
					</div>

					<p class="banEntryDetail">
						{$FILED_TEXT}
						<!--&IF($IS_PERMANENT,'{$PERMANENT_TEXT}','')-->
						<!--&IF($HAS_EXPIRY,'<br>{$EXPIRES_TEXT}','')-->
					</p>
				</div>
