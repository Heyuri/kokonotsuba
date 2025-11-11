<?php

class postWidget {
    public function __construct(
        private moduleEngine $moduleEngine
    ) {}

    public function addThreadReplyWidget(string &$widgetDataHtml, array &$post): void {
		// generate widget menu for reply
		$replyWidgets = $this->generateReplyWidgets($post);

		// append to widget data htnk
		$widgetDataHtml .= $replyWidgets;
	}

	public function addOpeningPostWidget(string &$widgetDataHtml, array $post, array $threadPosts): void {
		// generate widget menu for thread
		$threadWidgets = $this->generateThreadWidgets($post, $threadPosts);

		// append to widget data htnk
		$widgetDataHtml .= $threadWidgets;
	}

    public function addPostWidget(string &$widgetDataHtml, array &$post): void {
		// generate widget menu for post
		$postWidgets = $this->generatePostWidgets($post);

		// append to widget data htnk
		$widgetDataHtml .= $postWidgets;
	}

    public function addReplyModerateWidget(string &$modWidgetHtml, array &$post): void {
		// generate widget menu for reply
		$replyWidgets = $this->generateReplyWidgets($post, true);

		// append to mod widget html
		$modWidgetHtml .= $replyWidgets;
	}

	public function addThreadModerateWidget(string &$modWidgetHtml, array &$post, array &$threadPosts): void {
		// generate moderate widget menu for thread
		$threadWidgets = $this->generateThreadWidgets($post, $threadPosts, true);

		// append to mod widget html
		$modWidgetHtml .= $threadWidgets;
	}
    
    public function addPostModerateWidget(string &$modWidgetHtml, array &$post): void {
		// generate widget menu for post
		$postWidgets = $this->generatePostWidgets($post, true);

		// append to mod widget html
		$modWidgetHtml .= $postWidgets;
	}

	private function generateThreadWidgets(array $openingPost, array $threadPosts, bool $isModerate = false): string {
		// whether to use the moderate or user-end hook point
        // moderate hook points dont get called for statis html generation
        $threadWidgetHookPoint = $isModerate ? 'ModerateThreadWidget' : 'ThreadWidget';
        
        // build the thread widget array via hookpoint
		$threadWidgets = $this->buildWidgetArray($threadWidgetHookPoint, [$openingPost, $threadPosts]);

		// generate thread widget data div
		$widgetDataDiv = $this->buildWidgetMenuHtml($threadWidgets);

		// return widget data div
		return $widgetDataDiv;
	}

	private function generateReplyWidgets(array $replyPost, bool $isModerate = false): string {
        // whether to use the moderate or user-end hook point
        // moderate hook points dont get called for statis html generation
        $replyWidgetHookPoint = $isModerate ? 'ModerateReplyWidget' : 'ReplyWidget';

		// build the reply widget array via hookpoint
		$replyWidgets = $this->buildWidgetArray($replyWidgetHookPoint, [$replyPost]);

		// generate reply widget data div
		$widgetDataDiv = $this->buildWidgetMenuHtml($replyWidgets);

		// return widget data
		return $widgetDataDiv;
	}

    private function generatePostWidgets(array $post, bool $isModerate = false): string {
        // whether to use the moderate or user-end hook point
        // moderate hook points dont get called for statis html generation
        $postWidgetHookPoint = $isModerate ? 'ModeratePostWidget' : 'PostWidget';

		// build the post widget array via hookpoint
		$postWidgets = $this->buildWidgetArray($postWidgetHookPoint, [$post]);

		// generate post widget data div
		$widgetDataDiv = $this->buildWidgetMenuHtml($postWidgets);

		// return widget data
		return $widgetDataDiv;
    }

	private function buildWidgetMenuHtml(array $widgets): string {
        // init html
        $html = '';

        // loop through and build html
		foreach ($widgets as $w) {
			$href   = $w['href'] ?? '';
			$action = $w['action'] ?? '';
			$label  = $w['label'] ?? '';
            $subMenu = $w['subMenu'] ?? '';

			$html .= '<a href="' . htmlspecialchars($href) . '" data-action="' . htmlspecialchars($action) . '" data-label="' . htmlspecialchars($label) . '" data-subMenu="' . htmlspecialchars($subMenu) . '"></a>';
		}

		return $html;
	}

	private function buildWidgetArray(string $hook, array $params): array {
		// init widget array
		$widgets = [];

		// dispatch widget
		// pass the widget array as reference so we can return it
		$args = [&$widgets];
		foreach ($params as &$param) {
			$args[] = &$param;
		}
		$this->moduleEngine->dispatch($hook, $args);

		// return
		return $widgets; 
	}

    public function generatePostMenuHtml(string $widgetDataHtml): string {
		// start wrapper
		$html = '<div class="postMenu">';

		// arrow trigger (no href)
		$html .= '<a class="menuToggle" role="button" aria-label="Post menu">▶</a>';

		// hidden widget refs
		$html .= '<div class="widgetRefs" hidden>';

        // add widget refs
        $html .= $widgetDataHtml;

        // close containers
		$html .= '</div></div>';

        // return the html
        return $html;
    }
}