<figure class="postStatsFigure">
	<figcaption class="postStatsCaption">{$CAPTION}</figcaption>
	<div class="postStatsPlot">
		<div class="postStatsYAxis">
			<span>{$PEAK}</span>
			<span>{$MIDPOINT}</span>
			<span>0</span>
		</div>
		<svg class="postStatsSvg" viewBox="0 0 {$WIDTH} {$HEIGHT}" preserveAspectRatio="none" role="img" aria-label="{$CAPTION}">
			<path class="postStatsArea" d="{$AREA}"/>
			<path class="postStatsLine" d="{$LINE}"/>
			<!--&IF($PARTIAL_LINE,'<path class="postStatsLine postStatsLinePartial" d="{$PARTIAL_LINE}"/>','')-->
			<g class="postStatsHits"><!--&FOREACH($HITS,'POSTSTATS_HIT')--></g>
		</svg>
	</div>
	<div class="postStatsXAxis"><!--&FOREACH($AXIS,'POSTSTATS_AXIS_LABEL')--></div>
</figure>
