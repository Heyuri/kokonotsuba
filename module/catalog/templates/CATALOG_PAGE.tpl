[<a href="{$STATIC_INDEX_FILE}?{$CACHE_BUST}">Return</a>]

<h2 class="theading2">Catalog</h2>

<div id="catalogControls">
	<form id="catalogSortForm" action="{$MODULE_PAGE_URL}" method="post">
		<span>Sort by:</span>
		<select name="sort_by" id="catalogSortSelect">
			<!--&IF($SORT_BUMP_SELECTED,'<option value="bump" selected>Bump order</option>','<option value="bump">Bump order</option>')-->
			<!--&IF($SORT_TIME_SELECTED,'<option value="time" selected>Creation date</option>','<option value="time">Creation date</option>')-->
		</select>
		<noscript><input type="submit" value="Apply"></noscript>
	</form>

	<form id="catalogSettings" action="{$MODULE_PAGE_URL}" method="post">
    	[<label><input type="checkbox" name="cat_fw" value="1" id="sett_fw" <!--&IF($FW_CHECKED,'checked','')-->>Full width</label>]
		<label title="0 for auto">Columns:<input type="number" name="cat_cols" id="sett_cols" class="inputtext" value="{$CAT_COLS_VALUE}" min="0" max="20"></label>
        <div class="js-only">
            [<label><input type="checkbox" id="sett_sscase">Case insensitive</label>]
            <input type="search" id="sett_ss" class="inputtext" placeholder="Search" value="">
        </div>
		<noscript><input type="submit" value="Apply"></noscript>
    </form>
</div>

<table id="catalogTable" class="{$TABLE_CLASSES}" style="--cat-cols: {$CAT_COLS}">
	<tbody>
		<tr>
			<!--&FOREACH($THREADS,'CATALOG_THREAD')-->
		</tr>
	</tbody>
</table>
