	<h3>Capcodes</h3> 
	<!--&CAPCODE_CREATE_FORM/-->
	<div class="capcodeListContainer">
		<h4>Capcode list</h4>
		<p>User capcodes that can be used as long as the user knows the trip password.</p>
		<table class="capcodeList postlists">
			<thead>
				<tr> <th>Tripcode</th> <th>Color hexadecimal</th> <th>Capcode text</th> <th>Preview</th> <th></th> </tr>
			</thead>
			<tbody>
				<!--&FOREACH($CAPCODES,'CAPCODE_ROW')-->
				<!--&IF($ARE_NO_CAPCODES,'
					<tr>
						<td colspan="5" class="centerText">No capcodes found.</td>
					</tr>','')-->
			</tbody>
		</table>
	</div>
	
	<div class="staffCapcodeListContainer">
		<h4>Staff capcode list</h4>
		<p>Only usable by staff. Can be edited in <code>globalconfig.php</code></p>

		<table class="staffCapcodeList postlists">
			<thead>
				<tr> <th>Capcode</th> <th>Preview</th> <th>Required role to use</th> </tr>
			</thead>
			<tbody>
			<!--&FOREACH($STAFF_CAPCODES,'STAFF_CAPCODE_ROW')-->
			</tbody>
		</table>
	</div>