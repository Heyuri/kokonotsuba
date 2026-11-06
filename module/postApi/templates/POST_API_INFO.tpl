[<a href="{$STATIC_INDEX_FILE}">Return</a>]
	<div class="documentationPage postApiInfo">
		<h2>{$POST_API_TITLE}</h2>
		<p>{$POST_API_DESCRIPTION}</p>

		<ul class="postApiNav">
			<li><a href="#apiSinglePost">{$GET_SINGLE_POST}</a></li>
			<li><a href="#apiThreadPosts">{$GET_THREAD_POSTS}</a></li>
			<li><a href="#apiThreadList">{$GET_THREAD_LIST}</a></li>
			<li><a href="#apiQuoteSource">{$GET_QUOTE_SOURCE}</a></li>
		</ul>

		<h3 id="apiSinglePost">{$GET_SINGLE_POST}</h3>
		<pre class="code">GET {$API_BASE_URL}&amp;post_uid={post_uid}</pre>
		<p>{$RETURNS_JSON_POST}</p>
		<h4>{$PARAMETERS}</h4>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>post_uid</td><td>integer</td><td>{$POST_UID_DESC}</td></tr>
		</table>
		</div>

		<h4>{$RESPONSE_FIELDS}</h4>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_FIELD}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>post_uid</td><td>integer</td><td>{$FIELD_POST_UID}</td></tr>
			<tr><td>timestamp</td><td>string</td><td>{$FIELD_TIMESTAMP}</td></tr>
			<tr><td>name</td><td>string</td><td>{$FIELD_NAME}</td></tr>
			<tr><td>tripcode</td><td>string</td><td>{$FIELD_TRIPCODE}</td></tr>
			<tr><td>secure_tripcode</td><td>string</td><td>{$FIELD_SECURE_TRIPCODE}</td></tr>
			<tr><td>capcode</td><td>string</td><td>{$FIELD_CAPCODE}</td></tr>
			<tr><td>email</td><td>string</td><td>{$FIELD_EMAIL}</td></tr>
			<tr><td>subject</td><td>string</td><td>{$FIELD_SUBJECT}</td></tr>
			<tr><td>comment</td><td>string</td><td>{$FIELD_COMMENT}</td></tr>
			<tr><td>parent_thread_uid</td><td>integer</td><td>{$FIELD_PARENT_THREAD_UID}</td></tr>
			<tr><td>parent_post_number</td><td>integer</td><td>{$FIELD_PARENT_POST_NUMBER}</td></tr>
			<tr><td>html</td><td>string</td><td>{$FIELD_HTML}</td></tr>
		</table>
		</div>

		<h4>{$EXAMPLE_RESPONSE}</h4>
		<pre class="code">{
  "timestamp": "2026-11-03 06:58:30",
  "post_uid": 286886,
  "name": "Anonymous",
  "tripcode": "",
  "secure_tripcode": "",
  "capcode": "",
  "email": "",
  "subject": "",
  "comment": "&gt;&gt;286880\nI agree",
  "text_format": 1,
  "parent_thread_uid": "b817a716e3",
  "parent_post_number": 128450,
  "attachments": [
    {
      "file_id": 0,
      "file_name": "cat",
      "file_extension": ".png",
      "file_size": 48213,
      "file_width": 800,
      "file_height": 600,
      "thumb_width": 250,
      "thumb_height": 187,
      "mime_type": "image/png",
      "md5": "9e107d9d372bb6826bd81d3542a419d6"
    }
  ],
  "html": "&lt;div id=\"pc1_128456\" class=\"reply-container\"&gt;...&lt;/div&gt;"
}</pre>
		<p>{$EXAMPLE_ERROR}</p>
		<pre class="code">"Post not found!"</pre>

		<h3 id="apiThreadPosts">{$GET_THREAD_POSTS}</h3>
		<pre class="code">GET {$API_BASE_URL}&amp;pageName=thread&amp;thread_uid={thread_uid}&amp;page={page}</pre>
		<p>{$RETURNS_JSON_THREAD}</p>
		<h4>{$PARAMETERS}</h4>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>thread_uid</td><td>string</td><td>{$THREAD_UID_DESC}</td></tr>
			<tr><td>page</td><td>integer</td><td>{$PAGE_PARAM_DESC}</td></tr>
		</table>
		</div>

		<h4>{$RESPONSE}</h4>
		<p>{$THREAD_RESPONSE_DESC}</p>

		<h4>{$EXAMPLE_RESPONSE}</h4>
		<pre class="code">{
  "thread_uid": "b817a716e3",
  "page": 0,
  "post_count": 2,
  "posts": [
    { "post_uid": 286850, "parent_post_number": 128450, ... },
    { "post_uid": 286886, "parent_post_number": 128450, ... }
  ]
}</pre>

		<h3 id="apiThreadList">{$GET_THREAD_LIST}</h3>
		<pre class="code">GET {$API_BASE_URL}&amp;pageName=threads&amp;board_uid={board_uid}&amp;page={page}</pre>
		<p>{$RETURNS_JSON_THREAD_LIST}</p>
		<h4>{$PARAMETERS}</h4>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>board_uid</td><td>integer</td><td>{$BOARD_UID_DESC}</td></tr>
			<tr><td>page</td><td>integer</td><td>{$PAGE_PARAM_DESC}</td></tr>
		</table>
		</div>

		<h4>{$RESPONSE}</h4>
		<p>{$THREAD_LIST_RESPONSE_DESC}</p>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_FIELD}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>thread_uid</td><td>string</td><td>{$THREAD_LIST_FIELD_THREAD_UID}</td></tr>
			<tr><td>subject</td><td>string</td><td>{$THREAD_LIST_FIELD_SUBJECT}</td></tr>
			<tr><td>last_bump_time</td><td>string</td><td>{$THREAD_LIST_FIELD_LAST_BUMP_TIME}</td></tr>
			<tr><td>thread_created_time</td><td>string</td><td>{$THREAD_LIST_FIELD_CREATED_TIME}</td></tr>
			<tr><td>post_count</td><td>integer</td><td>{$THREAD_LIST_FIELD_POST_COUNT}</td></tr>
		</table>
		</div>

		<h4>{$EXAMPLE_RESPONSE}</h4>
		<pre class="code">{
  "page": 0,
  "threads_per_page": 15,
  "thread_count": 1,
  "threads": [
    {
      "thread_uid": "b817a716e3",
      "subject": "Cat thread",
      "last_bump_time": "2026-11-03 06:58:30",
      "thread_created_time": "2026-10-28 14:02:11",
      "post_count": 42
    }
  ]
}</pre>

		<h3 id="apiQuoteSource">{$GET_QUOTE_SOURCE}</h3>
		<pre class="code">GET {$API_BASE_URL}&amp;pageName=quote&amp;thread_uid={thread_uid}&amp;before_uid={post_uid}&amp;text={text}</pre>
		<p>{$RETURNS_JSON_QUOTE_SOURCE}</p>
		<h4>{$PARAMETERS}</h4>
		<div class="tableViewportWrapper">
		<table class="postlists">
			<tr><th>{$TH_PARAMETER}</th><th>{$TH_TYPE}</th><th>{$TH_DESCRIPTION}</th></tr>
			<tr><td>thread_uid</td><td>string</td><td>{$THREAD_UID_DESC}</td></tr>
			<tr><td>before_uid</td><td>integer</td><td>{$BEFORE_UID_DESC}</td></tr>
			<tr><td>text</td><td>string</td><td>{$QUOTE_TEXT_DESC}</td></tr>
			<tr><td>quoted</td><td>0 | 1</td><td>{$QUOTE_QUOTED_DESC}</td></tr>
		</table>
		</div>

		<h4>{$EXAMPLE_RESPONSE}</h4>
		<pre class="code">{ "post_uid": 286850, "parent_post_number": 128450, ... }</pre>
		<p>{$EXAMPLE_QUOTE_MISS}</p>
	</div>
<hr>