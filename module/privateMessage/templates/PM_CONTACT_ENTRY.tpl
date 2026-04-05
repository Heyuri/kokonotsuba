	<div class="contactEntryContainer">
		<div class="contactEntry" data-contact-tripcode="{$CONTACT_TRIPCODE}">
			<a class="contactEntryAnchor" href="{$CONTACT_THREAD_URL}">
				<div class="contactTrip">
					<span class="unreadPmIndicator warning">
						<!--&IF($CONTACT_IS_UNREAD,'!','')-->
					</span>
					<span class="contactTripcodeText">
						{$CONTACT_TRIPCODE}
					</span>
				</div>
				<div class="contactMessagePreview">
					<span class="contactPreviewText">{$CONTACT_PREVIEW}</span>
				</div>
			</a>
		</div>
	</div>