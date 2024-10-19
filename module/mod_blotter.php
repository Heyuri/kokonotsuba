<?php
class mod_blotter extends ModuleHelper {
	private $mypage;
	private $BLOTTER_PATH, $previewLimit = -1; // Path to blotter file

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->BLOTTER_PATH = $this->config['ModuleSettings']['BLOTTER_FILE'];
		if(!file_exists($this->BLOTTER_PATH)) touch($this->BLOTTER_PATH);
		
		$this->previewLimit = $this->config['ModuleSettings']['BLOTTER_PREVIEW_AMOUNT'];
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
				list($date, $comment, $uid) = explode('<>', $line);
				$data[] = [
					'date' => $date,
					'comment' => $comment,
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
		
		$html .= '<table class="postlists" id="blotterlist">';
		$html .= '<th>Date</th>
						<th>Entry</th>';
		foreach ($blotterData as $entry) {
			$html .= "<tr><td>{$entry['date']}</td> <td>{$entry['comment']}</td></tr>";
		}
		$html .= '</table>';
	}

	private function drawBlotterPage(&$html) {
		$html .= "<h2 class=\"theading2\">Blotter</h2>";
		$this->drawBlotterTable($html);
	}
	
	private function deleteBlotterEntries($uidsToDelete) {
	    $blotterData = $this->getBlotterFileData();
	    if(!is_array($uidsToDelete)) $uidsToDelete = [$uidsToDelete];
    
	    $updatedData = array_filter($blotterData, function($entry) use ($uidsToDelete) {
	        return !in_array($entry['uid'], $uidsToDelete);
	    });
	    $blotterContent = '';
	    foreach ($updatedData as $entry) {
	        $blotterContent .= "{$entry['date']}<>{$entry['comment']}<>{$entry['uid']}\n"; // Ensure UID is stored as well
	    }
	    
	    file_put_contents($this->BLOTTER_PATH, $blotterContent);
	    updatelog();
	}



	private function drawAdminBlotterTable(&$html) {
    		$blotterData = $this->getBlotterFileData();
     
    		usort($blotterData, function($a, $b) {
    			return strtotime($b['date']) - strtotime($a['date']);
    		});
     
    		$html .= '
    			<form id="blotterdeletionform" action="'.$this->mypage.'" method="POST">
    				<table class="postlists" id="blotterlist">
    					<thead>
    						<tr>
    							<th>Date</th>
    							<th>Entry</th>
    							<th>UID</th>
    							<th>Del</th>
    						</tr>
    					</thead>
    					<tbody>';
    		foreach ($blotterData as $entry) {
    			$html .= "
    						<tr>
    							<td>{$entry['date']}</td>
    							<td>{$entry['comment']}</td>
    							<td>{$entry['uid']}</td>
    							<td><input type=\"checkbox\" id=\"blotterdeletecheckbox\" name=\"entrydelete[]\" value=\"{$entry['uid']}\"></td>
    						</tr>";
    		}
    		$html .= '
    					</tbody>
    				</table>
    				<div class="centerText">
    					<input type="submit" name="delete_submit" value="Delete Selected">
    				</div>
    			</form>';
    }

	private function drawBlotterAdminForm(&$html) {
		$html .= "
			<h2 class=\"theading3\">Manage Blotter</h2>
			<fieldset class=\"adminfieldset\"> <legend>Add blotter entry</legend>
			<form action=".$this->mypage." method='post'>
				<table class=\"formtable\">
					<tbody>
						<tr>
							<td class='postblock'><b>Blotter Comment</b></td>
							<td><textarea cols='50' rows='10' name='new_blot_txt'></textarea></td>
						</tr>
						<tr>
							<td  colspan='4' align='right'><input type='submit' name='submit' value='Submit'></td>
						</tr>
					</tbody>
				</table>
			</form></fieldset>";
	}
	
	private function drawBlotterAdminPage(&$html) {
		$this->drawBlotterAdminForm($html);
		$this->drawAdminBlotterTable($html);
	}
	
	private function writeToBlotterFile($comment, $date, $uid) {
		$escapedComment = preg_replace('/<>/', '&lt;&gt;', $comment);
		$line = "{$date}<>{$comment}<>{$uid}\n";
		
		file_put_contents($this->BLOTTER_PATH, $line, FILE_APPEND);
	}
	
	private function handleBlotterAddition() {
		$newText = strval($_POST['new_blot_txt']) ?? '';
		$newDate = date($this->config['ModuleSettings']['BLOTTER_DATE_FORMAT']) ?? '';
		$newUID = substr(bin2hex(random_bytes(10)), 0, 10);
		
		$this->writeToBlotterFile($newText, $newDate, $newUID);
		updatelog();//rebuild all pages so it takes effect immedietly
	}
	
	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		if ($AccountIO->valid() < $this->config['roles']['LEV_ADMIN']) return;
		
		$link.= '[<a href="'.$this->mypage.'">Manage Blotter</a>] ';
	}
	
	public function autoHookBlotterPreview(&$html) {
		$html .= "<ul id=\"blotter\">";
		
		$blotterData = $this->getBlotterFileData();
		if(empty($blotterData)) $html .= '<li>- No blotter entries -</li>';
		foreach($blotterData as $key=>$entry) {
			if($key > $this->previewLimit - 1) break;
			$html .= '<li class="blotterListItem"><span class="blotterDate">' . $entry['date'] . '</span> - <span class="blotterMessage">' . $entry['comment'] . '</span></li>';
		}
		$html .= '<li class="blotterListShowAll">[<a href="'.$this->mypage.'">Show All</a>]</li>';

		$html .= '</ul> <hr size="1">'; //close tags 
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
		
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && $AccountIO->valid() >= $this->config['roles']['LEV_ADMIN']) {
			if (!empty($_POST['new_blot_txt'])) {
				$this->handleBlotterAddition();
			}
			
			if (!empty($_POST['delete_submit']) && !empty($_POST['entrydelete'])) {
				$this->deleteBlotterEntries($_POST['entrydelete']);
			}
		}
		
		$pageHTML = '';
		
		head($pageHTML);
		$pageHTML .= $returnButton;
		$this->drawBlotterAdminPage($pageHTML);
		foot($pageHTML);
		echo $pageHTML;
	}
}
?>

