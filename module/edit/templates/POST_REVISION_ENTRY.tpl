				<div class="postRevision" data-revision-id="{$REVISION_ID}">
					<div class="postRevisionHeader">
						<span class="postRevisionWhen">{$REVISION_HEADING}</span>
						<i class="postRevisionWho">{$REVISION_BY}</i>
						<!--&IF($CAN_RESTORE,'<span class="adminFunctions postRevisionRestore">[<button type="submit" class="buttonLink" name="revisionId" value="{$REVISION_ID}" title="{$RESTORE_TITLE}">{$RESTORE_LABEL}</button>]</span>','')-->
					</div>
					<table class="postRevisionFields">
						<tbody>
							{$REVISION_FIELDS}
						</tbody>
					</table>
				</div>
