<?php

namespace Kokonotsuba\module_classes\traits;

trait FormattingDetailsTrait {
	protected function renderFormattingDetails(string $id, string $label, string $content): string {
		return $this->moduleContext->templateEngine->ParseBlock('FORMATTING_DETAILS', [
			'{$CONTAINER_ID}' => $id,
			'{$CONTAINER_LABEL}' => $label,
			'{$CONTAINER_CONTENT}' => $content,
		]);
	}
}
