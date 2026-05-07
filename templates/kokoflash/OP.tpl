	<td class="tag"><!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')--></td>
	<td class="name">{$NAME}</td>
	<td class="filecol">[<a href="{$FILE_LINK}" download="{$FILE_NAME}">{$FILE_NAME}</a>]</td>
		<td>[<a class="flashboardEmbedText" onclick="openFlashEmbedWindow('{$FILE_LINK}', '{$ESCAPED_FILE_NAME}', '{$EXTENSION}', {$FILE_WIDTH}, {$FILE_HEIGHT})">Embed</a>]</td>
		<td class="title">{$SUB}</td>
		<td>{$FILE_SIZE}</td>
		<td class="time"> {$FORMATTED_THREAD_CREATED_TIME} </td>
		<td>{$REPLYNUM}</td>
		<td>{$REPLYBTN}</td>