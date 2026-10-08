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
				<defs>
					<pattern id="{$CHART_ID}-tier1" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect class="postStatsHatchStroke" width="3" height="6"/></pattern>
					<pattern id="{$CHART_ID}-tier2" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(135)"><rect class="postStatsHatchStroke" width="3" height="6"/></pattern>
				</defs>
				<!--&FOREACH($BANDS,'POSTSTATS_BAND')-->
				<!--&IF($PARTIAL,'<rect class="postStatsPartialShade" x="{$PARTIAL_X}" y="0" width="{$PARTIAL_WIDTH}" height="{$HEIGHT}"/>','')-->
			</svg>
			<!--&FOREACH($POINTS,'POSTSTATS_POINT')-->
		</div>
	</div>
	<div class="postStatsXAxis"><!--&FOREACH($AXIS,'POSTSTATS_AXIS_LABEL')--></div>
	{$LEGEND}
</figure>
