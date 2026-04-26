	<h2>Ad Manager</h2>

	<details class="detailsbox">
		<summary>Slot reference</summary>
		<table class="postlists">
			<thead>
				<tr><th>Slot</th><th>Where it appears</th><th>Notes</th></tr>
			</thead>
			<tbody>
				<tr><td><strong>top</strong></td><td>Above all threads on the index, desktop only</td><td>Leaderboard (728&times;90). Hidden on mobile.</td></tr>
				<tr><td><strong>mobile</strong></td><td>Above all threads on the index, mobile only</td><td>Mobile banner (320&times;100). Hidden on desktop.</td></tr>
				<tr><td><strong>sticky</strong></td><td>Fixed bar at the bottom of every page</td><td>Rotates if multiple ads are enabled. Dismissible for 1&nbsp;hour.</td></tr>
				<tr><td><strong>popunder</strong></td><td>Opens behind the current window on first click or keypress per tab</td><td>Redirects visitor to the ad&rsquo;s Click&nbsp;URL. One fire per tab; cooldown applies cross-tab.</td></tr>
				<tr><td><strong>above</strong></td><td>Between the page header and the first thread</td><td>Standard leaderboard (728&times;90).</td></tr>
				<tr><td><strong>below</strong></td><td>After the last thread, before the post form</td><td>Standard leaderboard (728&times;90).</td></tr>
				<tr><td><strong>inline</strong></td><td>Between threads, every N threads (configurable)</td><td>Three ads shown side-by-side in a row (728&times;90 each).</td></tr>
				<tr><td><strong>post_ad</strong></td><td>Between replies in a thread, every N replies (configurable)</td><td>Rendered as a fake reply post (300&times;250). Uses a random name from the name list.</td></tr>
			</tbody>
		</table>
	</details>

	<form action="{$MODULE_URL}" method="post">
		<input type="hidden" name="action" value="add">
		<h3>Add new ad</h3>
		<table class="formtable">
			<tbody>
				<tr>
					<td class="postblock"><label for="new_slot">Slot</label></td>
					<td>
						<select id="new_slot" name="ad_slot">
							{$SLOT_OPTIONS}
						</select>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new_type">Type</label></td>
					<td>
						<select id="new_type" name="ad_type">
							<option value="image">image</option>
							<option value="script">script / raw HTML</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new_src">Image URL <small>(image type)</small></label></td>
					<td><input type="text" id="new_src" name="ad_src" class="inputtext" size="60" placeholder="https://example.com/banner.png"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="new_href">Click URL <small>(image type)</small></label></td>
					<td><input type="text" id="new_href" name="ad_href" class="inputtext" size="60" placeholder="https://example.com/"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="new_alt">Alt text <small>(image type)</small></label></td>
					<td><input type="text" id="new_alt" name="ad_alt" class="inputtext" size="40"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="new_html">HTML / script <small>(script type)</small></label></td>
					<td><textarea id="new_html" name="ad_html" class="inputtext" cols="60" rows="5"></textarea></td>
				</tr>
			</tbody>
		</table>
		<div class="buttonSection">
			<input type="submit" value="Add ad">
		</div>
	</form>

	<h3>Ad inventory</h3>

	<form action="{$FILTER_URL}" method="get">
		<label for="slot_filter">Filter by slot:</label>
		<select id="slot_filter" name="slot">
			<option value="">— all —</option>
			{$SLOT_OPTIONS}
		</select>
		<input type="submit" value="Filter">
		<!--&IF($SLOT_FILTER,'<a href="{$FILTER_URL}">[clear]</a>','')-->
	</form>

	<form action="{$MODULE_URL}" method="post">
		<input type="hidden" name="action" value="bulk">
		<div class="tableViewportWrapper">
			<table class="postlists" id="inventoryTable">
				<thead>
					<tr>
						<th>Slot</th>
						<th>Type</th>
						<th>Src</th>
						<th>Click URL</th>
						<th>Alt</th>
						<th>HTML</th>
						<th>Enabled</th>
						<th>Added</th>
						<th>Del</th>
					</tr>
				</thead>
				<tbody>
					<!--&IF($EMPTY,'<tr><td colspan="9">No ads yet.</td></tr>','')-->
					<!--&FOREACH($ROWS,'ADS_ADMIN_ROW')-->
				</tbody>
			</table>
		</div>
		<div class="buttonSection">
			<input type="submit" name="bulk_save" value="Save changes">
			<input type="submit" name="bulk_delete" value="Delete selected">
		</div>
	</form>
