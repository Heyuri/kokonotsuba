					<tr class="pmRow {$PM_ROW_CLASS}">
						<td class="pmStatusCell"><!--&IF($PM_IS_UNREAD,'<span class="pmUnreadIndicator" title="Unread">!</span>','')--></td>
						<td class="pmFromCell"><span class="{$PM_DIRECTION_CLASS}">{$PM_DIRECTION}</span> {$PM_OTHER_TRIP}</td>
						<td class="pmSubjectCell">{$PM_SUBJECT}</td>
						<td class="pmPreviewCell">{$PM_PREVIEW}</td>
						<td class="pmDateCell">{$PM_DATE}</td>
						<td class="pmViewCell">[<a href="{$PM_VIEW_URL}">{$PM_VIEW_LABEL}</a>]</td>
					</tr>