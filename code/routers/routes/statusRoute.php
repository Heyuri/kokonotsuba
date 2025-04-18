<?php

// status route - draws some info about the board

class statusRoute {
	private board $board;
	private array $config;
	private readonly globalHTML $globalHTML;
	private readonly staffAccountFromSession $staffSession;

	private templateEngine $templateEngine;
	private moduleEngine $moduleEngine;

	private readonly mixed $PIO;
	private readonly mixed $FileIO;

	public function __construct(board $board, 
		array $config,
		globalHTML $globalHTML,
		staffAccountFromSession $staffSession,
		templateEngine $templateEngine,
		moduleEngine $moduleEngine,
		mixed $PIO,
		mixed $FileIO) {
		$this->board = $board;
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->staffSession = $staffSession;

		$this->templateEngine = $templateEngine;
		$this->moduleEngine = $moduleEngine;

		$this->PIO = $PIO;
		$this->FileIO = $FileIO;
	}

	/* Show instance/board information */
	public function drawStatus() {
		$countline = $this->PIO->postCountFromBoard($this->board); // Calculate the current number of data entries in the submitted text log file
		$counttree = $this->PIO->threadCountFromBoard($this->board); // Calculate the current number of data entries in the tree structure log file
		$tmp_total_size = $this->FileIO->getCurrentStorageSize($this->board); // The total size of the attached image file usage
		$tmp_ts_ratio = $this->config['STORAGE_MAX'] > 0 ? $tmp_total_size / $this->config['STORAGE_MAX'] : 0; // Additional image file usage

		// Determines the color of the "Additional Image File Usage" prompt
		if ($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
		elseif ($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
		elseif ($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
		elseif ($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
		else $clrflag_sl = 'F2004A';

		// Generate preview image object information and whether the functions of the generated preview image are normal
		$func_thumbWork = '<span class="offline">'._T('info_nonfunctional').'</span>';
		$func_thumbInfo = '(No thumbnail)';
		if ($this->config['USE_THUMB'] !== 0) {
				$thumbType = $this->config['USE_THUMB']; if ($this->config['USE_THUMB'] == 1) { $thumbType = 'gd'; }
				require(getBackendCodeDir() . 'thumb/thumb.' . $thumbType . '.php');
				$thObj = new ThumbWrapper();
				if ($thObj->isWorking()) $func_thumbWork = '<span class="online">'._T('info_functional').'</span>';
				$func_thumbInfo = $thObj->getClass();
				unset($thObj);
		}

		// PIOSensor
		if (count($this->config['LIMIT_SENSOR']))
				$PIOsensorInfo = nl2br(PIOSensor::info($this->board, $this->config['LIMIT_SENSOR']));

		$dat = '';
		$this->globalHTML->head($dat);
		$links = '[<a href="' . $this->config['PHP_SELF2'] . '?' . time() . '">' . _T('return') . '</a>] [<a href="' . $this->config['PHP_SELF'] . '?mode=moduleloaded">' . _T('module_info_top') . '</a>]';
		$level = $this->staffSession->getRoleLevel();
		$this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$links, 'status', $level));
		$dat .= $links . '<h2 class="theading2">' . _T('info_top') . '</h2>
<table id="status" class="postlists">
	<thead>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_basic') . '</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td width="240">' . _T('info_basic_threadsperpage') . '</td>
			<td colspan="3"> ' . $this->config['PAGE_DEF'] . ' ' . _T('info_basic_threads') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_postsperpage') . '</td>
			<td colspan="3"> ' . $this->config['RE_DEF'] . ' ' . _T('info_basic_posts') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_postsinthread') . '</td>
			<td colspan="3"> ' . $this->config['RE_PAGE_DEF'] . ' ' . _T('info_basic_posts') . ' ' . _T('info_basic_posts_showall') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_bumpposts') . '</td>
			<td colspan="3"> ' . $this->config['MAX_RES'] . ' ' . _T('info_basic_posts') . ' ' . _T('info_basic_0disable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_bumphours') . '</td>
			<td colspan="3"> ' . $this->config['MAX_AGE_TIME'] . ' ' . _T('info_basic_hours') . ' ' . _T('info_basic_0disable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_urllinking') . '</td>
			<td colspan="3"> ' . $this->config['AUTO_LINK'] . ' ' . _T('info_0no1yes') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_com_limit') . '</td>
			<td colspan="3"> ' . $this->config['COMM_MAX'] . _T('info_basic_com_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_anonpost') . '</td>
			<td colspan="3"> ' . $this->config['ALLOW_NONAME'] . ' ' . _T('info_basic_anonpost_opt') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_del_incomplete') . '</td>
			<td colspan="3"> ' . $this->config['KILL_INCOMPLETE_UPLOAD'] . ' ' . _T('info_0no1yes') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_use_sample', $this->config['THUMB_SETTING']['Quality']) . '</td>
			<td colspan="3"> ' . $this->config['USE_THUMB'] . ' ' . _T('info_0notuse1use') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_useblock') . '</td>
			<td colspan="3"> ' . $this->config['BAN_CHECK'] . ' ' . _T('info_0disable1enable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_showid') . '</td>
			<td colspan="3"> ' . $this->config['DISP_ID'] . ' ' . _T('info_basic_showid_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_cr_limit') . '</td>
			<td colspan="3"> ' . $this->config['BR_CHECK'] . _T('info_basic_cr_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_timezone') . '</td>
			<td colspan="3"> GMT ' . $this->config['TIME_ZONE'] . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_theme') . '</td>
			<td colspan="3"> ' . $this->templateEngine->BlockValue('THEMENAME') . ' ' . $this->templateEngine->BlockValue('THEMEVER') . '<div>by ' . $this->templateEngine->BlockValue('THEMEAUTHOR') . '</div></td>
		</tr>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_dsusage_top') . '</th>
		</tr>
		<tr class="centerText">
			<td>' . _T('info_basic_threadcount') . '</td>
			<td colspan="' . (isset($this->PIOsensorInfo) ? '2' : '3') . '"> ' . $counttree . ' ' . _T('info_basic_threads') . '</td>' . (isset($this->PIOsensorInfo) ? '
			<td rowspan="2">' . $PIOsensorInfo . '</td>' : '') . '
		</tr>
		<tr class="centerText">
			<td>' . _T('info_dsusage_count') . '</td>
			<td colspan="' . (isset($this->PIOsensorInfo) ? '2' : '3') . '">' . $countline . '</td>
		</tr>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_fileusage_top') . $this->config['STORAGE_LIMIT'] . ' ' . _T('info_0disable1enable') . '</th>
		</tr>';

		if ($this->config['STORAGE_LIMIT']) {
				$dat .= '
		<tr class="centerText">
			<td>' . _T('info_fileusage_limit') . '</td>
			<td colspan="2">' . $this->config['STORAGE_MAX'] . ' KB</td>
			<td rowspan="2">' . _T('info_dsusage_usage') . '<div><span style="color:#' . $clrflag_sl . '">' . substr(($tmp_ts_ratio * 100), 0, 6) . '</span> %</div></td>
		</tr>
		<tr class="centerText">
			<td>' . _T('info_fileusage_count') . '</td>
			<td colspan="2"><span style="color:#' . $clrflag_sl . '">' . $tmp_total_size . ' KB</span></td>
		</tr>';
		} else {
				$dat .= '
		<tr class="centerText">
			<td>' . _T('info_fileusage_count') . '</td>
			<td>' . $tmp_total_size . ' KB</td>
			<td colspan="2">' . _T('info_dsusage_usage') . '<br><span class="green">' . _T('info_fileusage_unlimited') . '</span></td>
		</tr>';
		}

		$dat .= '
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_server_top') . '</th>
		</tr>
		<tr class="centerText">
			<td colspan="3">' . $func_thumbInfo . '</td>
			<td>' . $func_thumbWork . '</td>
		</tr>
	</tbody>
</table>
<hr>';

		$this->globalHTML->foot($dat);
		echo $dat;
	}

}