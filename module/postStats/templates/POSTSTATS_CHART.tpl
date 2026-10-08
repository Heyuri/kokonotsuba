<figure class="postStatsFigure">
	<figcaption class="postStatsCaption">{$CAPTION}</figcaption>
	<div class="postStatsPlot">
		<div class="postStatsYAxis">
			<span>{$PEAK}</span>
			<span>{$MIDPOINT}</span>
			<span>0</span>
		</div>
		<div class="postStatsCanvas">
			<svg class="postStatsSvg" viewBox="0 0 {$WIDTH} {$HEIGHT}" preserveAspectRatio="none" role="img" aria-label="{$CAPTION}">
				<path class="postStatsArea" d="{$AREA}" fill-opacity="0.15"/>
				<path class="postStatsLine" d="{$LINE}" fill="none"/>
				<!--&IF($PARTIAL_LINE,'<path class="postStatsLine postStatsLinePartial" d="{$PARTIAL_LINE}" fill="none"/>','')-->
			</svg>
			<!--&FOREACH($POINTS,'POSTSTATS_POINT')-->
		</div>
	</div>
	<div class="postStatsXAxis"><!--&FOREACH($AXIS,'POSTSTATS_AXIS_LABEL')--></div>
</figure>
