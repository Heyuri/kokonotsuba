<?php

function generateHeadHtml(array $config, templateEngine $templateEngine, moduleEngine $moduleEngine, string $pageTitle = '', int $resno = 0, bool $isStaff = false) {
	$html = '';

	$pte_vals = prepareBaseTemplateValues($resno, $isStaff);

	$moduleEngine->dispatch('ModuleHeader', array(&$pte_vals['{$MODULE_HEADER_HTML}']));

	$pte_vals['{$PAGE_TITLE}'] = $pageTitle;

	$html .= $templateEngine->ParseBlock('HEADER', $pte_vals);
	$moduleEngine->dispatch('Head', array(&$html, $resno)); // Hook: Head
	$html .= '</head>';

	$pte_vals += array(
		'{$HOME}' => '[<a href="' . $config['HOME'] . '" target="_top">' . _T('head_home') . '</a>]',
		'{$STATUS}' => '[<a href="' . $config['LIVE_INDEX_FILE'] . '?mode=status">' . _T('head_info') . '</a>]',
		'{$ADMIN}' => '[<a href="' . $config['LIVE_INDEX_FILE'] . '?mode=admin">' . _T('head_admin') . '</a>]',
		'{$REFRESH}' => '[<a href="' . $config['STATIC_INDEX_FILE'] . '?">' . _T('head_refresh') . '</a>]',
		'{$HOOKLINKS}' => '', '{$BANNER}' => ''
	);

	$moduleEngine->dispatch('TopLinks', array(&$pte_vals['{$HOOKLINKS}'], !empty($resto)));
	$moduleEngine->dispatch('PageTop', array(&$pte_vals['{$BANNER}'])); // Hook: AboveTitle

	$html .= $templateEngine->ParseBlock('BODYHEAD', $pte_vals);

	return $html;
}

function prepareBaseTemplateValues(int $resno, bool $isStaff) {
	return array(
		'{$RESTO}' => $resno ? $resno : '',
		'{$IS_THREAD}' => boolval($resno),
		'{$IS_STAFF}' => $isStaff,
		'{$MODULE_HEADER_HTML}' => ''
	);
}

function generateFooterHtml(templateEngine $templateEngine, moduleEngine $moduleEngine, bool $isThread = false) {
	$html = '';

	$pte_vals = array(
		'{$FOOTER}' => '',
		'{$IS_THREAD}' => $isThread
	);

	$moduleEngine->dispatch('Foot', array(&$pte_vals['{$FOOTER}'])); // Hook: Foot

	$pte_vals['{$FOOTER}'] .= getDefaultFooterLinks();

	$html .= $templateEngine->ParseBlock('FOOTER', $pte_vals);

	return $html;
}

function getDefaultFooterLinks() {
	return '- <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + ' .
		   '<a rel="nofollow noreferrer license" href="http://www.2chan.net/" target="_blank">futaba</a> + ' .
		   '<a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + ' .
		   '<a rel="nofollow noreferrer license" href="https://kokonotsuba.github.io/" target="_blank">Kokonotsuba</a> -';
}

function generatePostFormHTML(int $resno,
	board $board,
	array $config,
	templateEngine $templateEngine,
	moduleEngine $moduleEngine,
	string $moduleInfoHook = '',
	string $name = '',
	string $email = '',
	string $subject = '',
	string $comment = '',
	string $category = '',
	bool $isStaff = false
) {
	$FileIO = PMCLibrary::getFileIOInstance();
	$isThread = $resno != 0;

	$pte_vals = preparePostFormTemplateValues($resno, $config['LIVE_INDEX_FILE'], $name, $email, $subject, $comment, $config, $isThread, $moduleInfoHook, $isStaff);

	if (!$config['TEXTBOARD_ONLY'] && ($config['RESIMG'] || !$resno)) {
		$pte_vals['{$FORM_ATTECHMENT_FIELD}'] = '<input type="file" name="upfile" id="upfile">';

		if (!$resno) {
			$pte_vals['{$FORM_NOATTECHMENT_FIELD}'] = '<input type="checkbox" name="noimg" id="noimg" value="on">';
		}

		$moduleEngine->dispatch('PostFormFile', array(&$pte_vals['{$FORM_FILE_EXTRA_FIELD}']));
	}

	$moduleEngine->dispatch('PostForm', array(&$pte_vals['{$FORM_EXTRA_COLUMN}'])); // Hook: PostForm

	$moduleEngine->dispatch('PostFormAdmin', array(&$pte_vals['{$FORM_STAFF_CHECKBOXES}'])); // Hook: PostFormAdmin

	if ($config['USE_CATEGORY']) {
		$pte_vals['{$FORM_CATEGORY_FIELD}'] = '<input type="text" name="category" id="category" value="' . $category . '" class="inputtext">';
	}

	if ($config['STORAGE_LIMIT']) {
		$pte_vals['{$FORM_NOTICE_STORAGE_LIMIT}'] = _T(
			'form_notice_storage_limit',
			$FileIO->getCurrentStorageSize($board),
			$config['STORAGE_MAX']
		);
	}

	$moduleEngine->dispatch('PostInfo', array(&$pte_vals['{$HOOKPOSTINFO}'])); // Hook: PostInfo

	$pte_vals['{$POST_FORM}'] .= $templateEngine->ParseBlock('POSTFORM',$pte_vals);

	return $templateEngine->ParseBlock('POST_AREA', $pte_vals);
}

function preparePostFormTemplateValues(int $resno, ?string $liveIndexFile, ?string $name, ?string $email, ?string $subject, ?string $comment, array $config, bool $isThread, ?string $moduleInfoHook, bool $isStaff = false) {
	$hidinput = $resno ? '<input type="hidden" name="resto" value="' . $resno . '">' : '';

	return array(
		'{$RESTO}' => strval($resno),
		'{$IS_STAFF}' => $isStaff,
		'{$FORM_STAFF_CHECKBOXES}' => '',
		'{$GLOBAL_MESSAGE}' => '',
		'{$PHP_SELF}' => $liveIndexFile,
		'{$BLOTTER}' => '',
		'{$IS_THREAD}' => $isThread,
		'{$FORM_HIDDEN}' => $hidinput,
		'{$MAX_FILE_SIZE}' => strval($config['TEXTBOARD_ONLY'] ? 0 : $config['MAX_KB'] * 1024),
		'{$FORM_NAME_FIELD}' => '<input maxlength="' . $config['INPUT_MAX'] . '" type="text" name="name" id="name" value="' . $name . '" class="inputtext">',
		'{$FORM_EMAIL_FIELD}' => '<input maxlength="' . $config['INPUT_MAX'] . '" type="text" name="email" id="email" value="' . $email . '" class="inputtext">',
		'{$FORM_TOPIC_FIELD}' => '<input maxlength="' . $config['INPUT_MAX'] . '"  type="text" name="sub" id="sub" value="' . $subject . '" class="inputtext">',
		'{$FORM_SUBMIT}' => '<button id="buttonPostFormSubmit" type="submit" name="mode" value="regist">' . ($resno ? 'Post' : 'New thread') . '</button>',
		'{$FORM_COMMENT_FIELD}' => '<textarea maxlength="' . $config['COMM_MAX'] . '" name="com" id="com" class="inputtext">' . $comment . '</textarea>',
		'{$FORM_DELETE_PASSWORD_FIELD}' => '<input type="password" name="pwd" id="pwd" maxlength="8" value="" class="inputtext">',
		'{$FORM_EXTRA_COLUMN}' => '',
		'{$FORM_FILE_EXTRA_FIELD}' => '',
		'{$FORM_NOTICE}' => (
			$config['TEXTBOARD_ONLY']
				? ''
				: _T(
					'form_notice',
					implode(', ', array_keys($config['ALLOW_UPLOAD_EXT'])),
					$config['MAX_KB'],
					$resno ? $config['MAX_RW'] : $config['MAX_W'],
					$resno ? $config['MAX_RH'] : $config['MAX_H']
				)
		),
		'{$HOOKPOSTINFO}' => '',
		'{$MODULE_INFO_HOOK}' => $moduleInfoHook,
		'{$POST_FORM}' => '');
}
