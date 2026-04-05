	<div class="postApiInfo">
		<h2>Post API</h2>
		<p>Kokonotsuba provides a read-only API for fetching post data. You can fetch data from individual posts or whole threads.</p>

		<h3>Get a single post</h3>
		<pre><code>GET {$API_BASE_URL}&amp;post_uid={post_uid}</code></pre>
		<p>Returns JSON with the post data and rendered HTML.</p>
		<h4>Parameters</h4>
		<table class="postlists">
			<tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
			<tr><td><code>post_uid</code></td><td>integer</td><td>The unique ID of the post</td></tr>
		</table>

		<h4>Response fields</h4>
		<table class="postlists">
			<tr><th>Field</th><th>Type</th><th>Description</th></tr>
			<tr><td><code>post_uid</code></td><td>integer</td><td>Post unique ID</td></tr>
			<tr><td><code>timestamp</code></td><td>string</td><td>Post timestamp</td></tr>
			<tr><td><code>name</code></td><td>string</td><td>Poster name</td></tr>
			<tr><td><code>tripcode</code></td><td>string</td><td>Tripcode (formatted)</td></tr>
			<tr><td><code>secure_tripcode</code></td><td>string</td><td>Secure tripcode</td></tr>
			<tr><td><code>capcode</code></td><td>string</td><td>Staff capcode</td></tr>
			<tr><td><code>email</code></td><td>string</td><td>Email field</td></tr>
			<tr><td><code>subject</code></td><td>string</td><td>Post subject</td></tr>
			<tr><td><code>comment</code></td><td>string</td><td>Raw comment text</td></tr>
			<tr><td><code>html</code></td><td>string</td><td>Fully rendered post HTML</td></tr>
		</table>

		<h3>Get all posts from a thread</h3>
		<pre><code>GET {$API_BASE_URL}&amp;pageName=thread&amp;thread_uid={thread_uid}</code></pre>
		<p>Returns JSON with all posts in the specified thread.</p>
		<h4>Parameters</h4>
		<table class="postlists">
			<tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
			<tr><td><code>thread_uid</code></td><td>string</td><td>The unique ID of the thread</td></tr>
		</table>

		<h4>Response</h4>
		<p>Returns an object with <code>thread_uid</code>, <code>post_count</code>, and <code>posts</code> (array of post objects with the same fields as the single post endpoint).</p>
	</div>
<hr>