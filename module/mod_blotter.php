<?php
class mod_blotter extends ModuleHelper {
	private $mypage;
	private $BLOTTER_PATH = -1; // Path to blotter file

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->BLOTTER_PATH = $this->config['ModuleSettings']['BLOTTER_FILE'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Blotter';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}
	
	private function getBlotterFileData() {
		$data = [];
		if (file_exists($this->BLOTTER_PATH)) {
			$lines = file($this->BLOTTER_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				// Assuming each line in the file is formatted as COMMENT<>DATE
				list($comment, $date, $uid) = explode('<>', $line);
				$data[] = [
					'comment' => $comment,
					'date' => $date,
					'uid' => $uid ?? 0,
					];
			}
		}
		return $data;
	}
	
	private function drawBlotterTable(&$html) {
		$blotterData = $this->getBlotterFileData();
		
		usort($blotterData, function($a, $b) {
			return strtotime($b['date']) - strtotime($a['date']);
		});
		
		$html .= "<table class=\"postlists\" id=\"blotterlist\">";
		$html .= '<th>Date</th>
						<th>Entry</th>';
		foreach ($blotterData as $entry) {
			$html .= "<tr><td>{$entry['date']}</td> <td>{$entry['comment']}</td></tr>";
		}
		$html .= "</table>";
	}

	private function drawBlotterPage(&$html) {
		$html .= "<h2 class=\"theading2\">Blotter</h2>";
		$this->drawBlotterTable($html);
	}
	
	private function drawBlotterAdminPage(&$html) {
		$html .= "
			<h2 class=\"theading3\">Manage Blotter</h2>
			<form action=".$this->mypage." method='post'>
				<table cellpadding='1' cellspacing='1' class=\"formtable\">
					<tbody>
						<tr>
							<td class='postblock'><b>Blotter Comment</b></td>
							<td><textarea cols='50' rows='10' name='new_blot_txt'></textarea></td>
						</tr>
						<tr>
							<td colspan='2' align='right'><input type='submit' name='submit' value='Submit'></td>
						</tr>
					</tbody>
				</table>
			</form></fieldset>";
	}

	private function writeToBlotterFile($comment, $date, $uid) {
		$line = "{$comment}<>{$date}<>{$uid}\n";
		file_put_contents($this->BLOTTER_PATH, $line, FILE_APPEND);
	}
	
	private function handleBlotterAddition() {
		$newText = strval($_POST['new_blot_txt']) ?? '';
		$newDate = date($this->config['ModuleSettings']['BLOTTER_DATE_FORMAT']) ?? '';
		$newUID = substr(bin2hex(random_bytes(10)), 0, 10);
		
		$this->writeToBlotterFile($newText, $newDate, $newUID);
	}
	
	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		//If a regular user, draw blotter page
		if ($AccountIO->valid() < $this->config['roles']['LEV_ADMIN']) return;
		
		$link.= '[<a href="'.$this->mypage.'">Manage Blotter</a>] ';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$PMS = PMCLibrary::getPMSInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		$returnButton = '[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]';
		
		//If a regular user, draw blotter page
		if ($AccountIO->valid() < $this->config['roles']['LEV_ADMIN']) {
			$pageHTML = '';
			
			head($pageHTML);
			$pageHTML .= $returnButton;
			$this->drawBlotterPage($pageHTML);
			foot($pageHTML);
			
			echo $pageHTML;
			return;
		}
		
		// Admin panel to manage blotter
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['new_blot_txt'])) {
			$this->handleBlotterAddition();
		}
		
		$pageHTML = '';
		
		head($pageHTML);
		$pageHTML .= $returnButton;
		$this->drawBlotterAdminPage($pageHTML);
		$this->drawBlotterTable($pageHTML);
		foot($pageHTML);
		echo $pageHTML;
	}
}
?>

