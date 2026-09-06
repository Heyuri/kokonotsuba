					<tr class="pmRow {$PM_ROW_CLASS}">
						<td class="pmStatusCell"><!--&IF($PM_IS_UNREAD,'<span class="pmUnreadIndicator" title="Unread">!</span>','')--></td>
						<td class="pmSelectCell"><input type="checkbox" name="selected[]" value="{$PM_ID}"></td>
						<td class="pmFromCell"><span class="{$PM_DIRECTION_CLASS}">{$PM_DIRECTION}</span> <a href="{$PM_OTHER_TRIP_URL}" class="pmTripLink" title="{$PM_COMPOSE_TITLE}">{$PM_OTHER_TRIP}</a></td>
						<td class="pmSubjectCell">{$PM_SUBJECT}</td>
						<td class="pmPreviewCell">{$PM_PREVIEW}</td>
						<td class="pmDateCell">{$PM_DATE}</td>
						<td class="pmViewCell">[<a href="{$PM_VIEW_URL}">{$PM_VIEW_LABEL}</a>]</td>
					</tr>