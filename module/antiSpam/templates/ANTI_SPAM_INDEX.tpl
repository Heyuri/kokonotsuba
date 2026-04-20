	<h3>Anti-spam management</h3>
	<h4>Add new rule</h4>
	<!--&NEW_ENTRY_FORM/--> 
	<div class="spamRulesContainer">
		<h4>Spam ruleset</h4>
		<p>Anti-spam rules that every new post submission is checked against for active entries.</p>
		<form action="{$MODULE_URL}" method="POST">
			<input type="hidden" name="action" value="delete">
			<div class="tableViewportWrapper">
			<table class="spamList postlists">
				<thead>
					<tr> <th>Delete</th> <th>Pattern</th> <th>Active?</th> <th>Description</th> <th>Action</th> <th>Match type</th> <th>Fields</th> <th></th> </tr>
				</thead>
				<tbody>
					<!--&FOREACH($ROWS,'SPAM_ROW')-->
				</tbody>
			</table>
			</div>
			<input type="submit" value="Submit">
		</form>
	</div>