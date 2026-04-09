[<a href="{$STATIC_INDEX_FILE}">Return</a>]
	<div class="postApiInfo">
		<h2>{$POST_API_TITLE}</h2>
		<p>{$POST_API_DESCRIPTION}</p>

		<h3>{$GET_SINGLE_POST}</h3>
		<pre><code>GET {$API_BASE_URL}&amp;post_uid={post_uid}</code></pre>
		<p>{$RETURNS_JSON_POST}</p>
		<h4>{$PARAMETERS}</h4>
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td><code>post_uid</code></td><td>integer</td><td>{$POST_UID_DESC}</td></tr>
		</table>

		<h4>{$RESPONSE_FIELDS}</h4>
		<table class="postlists">
			<tr><th>{$TH_FIELD}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td><code>post_uid</code></td><td>integer</td><td>{$FIELD_POST_UID}</td></tr>
			<tr><td><code>timestamp</code></td><td>string</td><td>{$FIELD_TIMESTAMP}</td></tr>
			<tr><td><code>name</code></td><td>string</td><td>{$FIELD_NAME}</td></tr>
			<tr><td><code>tripcode</code></td><td>string</td><td>{$FIELD_TRIPCODE}</td></tr>
			<tr><td><code>secure_tripcode</code></td><td>string</td><td>{$FIELD_SECURE_TRIPCODE}</td></tr>
			<tr><td><code>capcode</code></td><td>string</td><td>{$FIELD_CAPCODE}</td></tr>
			<tr><td><code>email</code></td><td>string</td><td>{$FIELD_EMAIL}</td></tr>
			<tr><td><code>subject</code></td><td>string</td><td>{$FIELD_SUBJECT}</td></tr>
			<tr><td><code>comment</code></td><td>string</td><td>{$FIELD_COMMENT}</td></tr>
			<tr><td><code>html</code></td><td>string</td><td>{$FIELD_HTML}</td></tr>
		</table>

		<h3>{$GET_THREAD_POSTS}</h3>
		<pre><code>GET {$API_BASE_URL}&amp;pageName=thread&amp;thread_uid={thread_uid}</code></pre>
		<p>{$RETURNS_JSON_THREAD}</p>
		<h4>{$PARAMETERS}</h4>
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td><code>thread_uid</code></td><td>string</td><td>{$THREAD_UID_DESC}</td></tr>
		</table>

		<h4>{$RESPONSE}</h4>
		<p>{$THREAD_RESPONSE_DESC}</p>
	</div>
<hr>