<figure class="postStatsFigure">
	<figcaption class="postStatsCaption">{$CAPTION}</figcaption>
	<div class="postStatsPlot">
		<div class="postStatsYAxis">
			<span>{$PEAK}</span>
			<span>{$MIDPOINT}</span>
			<span>0</span>
		</div>
		<div class="postStatsBars" style="--poststats-gap:{$GAP}"><!--&FOREACH($COLUMNS,'POSTSTATS_STACK_COLUMN')--></div>
	</div>
	<div class="postStatsXAxis">
		<span>{$FIRST_LABEL}</span>
		<span>{$MIDDLE_LABEL}</span>
		<span>{$LAST_LABEL}</span>
	</div>
	{$LEGEND}
</figure>
