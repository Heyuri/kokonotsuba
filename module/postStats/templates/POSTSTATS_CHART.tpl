<figure class="postStatsFigure">
	<figcaption class="postStatsCaption">{$CAPTION}</figcaption>
	<div class="postStatsPlot">
		<div class="postStatsYAxis">
			<span>{$PEAK}</span>
			<span>{$MIDPOINT}</span>
			<span>0</span>
		</div>
		<div class="postStatsBars"><!--&FOREACH($BARS,'POSTSTATS_BAR')--></div>
	</div>
	<div class="postStatsXAxis">
		<span>{$FIRST_LABEL}</span>
		<span>{$MIDDLE_LABEL}</span>
		<span>{$LAST_LABEL}</span>
	</div>
</figure>
