<div class="mergeThreadContainer">
	<form id="thread-merge-form" method="POST" action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->">
		{$CSRF_TOKEN}
		<h3 class="centerText">Merge threads</h3>

		<input type="hidden" name="merge-thread-action" value="merge">
		<input type="hidden" name="merge-thread-uid" value="<!--&IF($THREAD_UID,'{$THREAD_UID}','')-->">

		<table>
			<tbody>
				<tr>
					<td class="postblock"><label for="merge-thread-destination">Merge into</label></td>
					<td><span id="merge-thread-destination">No.<span id="merge-thread-num">{$THREAD_NUMBER}</span> - <span id="merge-thread-subject">{$THREAD_SUBJECT}</span></span></td>
				</tr>
				<tr>
					<td class="postblock"><label>Threads</label></td>
					<td>
						<p class="mergeThreadHelp">Every post in the threads ticked below is moved into the thread above, which keeps its own post number and subject.</p>
						<ul class="littlelist mergeThreadList">{$THREAD_LIST_HTML}</ul>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="merge-source-numbers">Other threads</label></td>
					<td>
						<input type="text" id="merge-source-numbers" name="merge-source-numbers" size="28" placeholder="12345, 12346">
						<p class="mergeThreadHelp">Post numbers of threads not shown in the list, separated by commas.</p>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label>Options</label></td>
					<td>
						<label id="merge-thread-leave-shadow-thread" title="Copy the posts instead, leaving the original threads up and locked">
							<input type="checkbox" name="leave-shadow-thread" value="1">Leave shadow threads
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection centerText">
			<button type="submit" name="merge-thread-submit" value="merge it!">Merge threads</button>
		</div>
	</form>
</div>
