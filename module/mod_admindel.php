<?php
// admin extra module made for kokonotsuba by deadking
class mod_admindel extends ModuleHelper {
	private $BANFILE = STORAGE_PATH.'bans.log.txt';
	private $JANIMUTE_LENGTH = 30; // Janitor mute duration (in minutes)
    private $JANIMUTE_REASON = 'You have been muted temporarily!'; // Janitor mute reason
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
		touch($this->BANFILE);
	}

	public function getModuleName() {
		return __CLASS__.' : K! Admin Deletion';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$FileIO = PMCLibrary::getFileIOInstance();
		if (valid() < LEV_JANITOR) return;
		$modfunc.= '[<a href="'.$this->mypage.'&action=del&no='.$post['no'].'" title="Delete">D</a>]';
		if ($post['ext'] && $FileIO->imageExists($post['tim'].$post['ext'])) $modfunc.= '[<a href="'.$this->mypage.'&action=imgdel&no='.$post['no'].'" title="Delete File">Df</a>]';
		$modfunc.= '[<a href="'.$this->mypage.'&action=delmute&no='.$post['no'].'" title="Delete and Mute">DM</a>]';
//		if (THREAD_PAGINATION) $modfunc.= '[<a href="'.$this->mypage.'&action=cachedel&no='.$post['no'].'" title="Delete Cache">Dc</a>]';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$PMS = PMCLibrary::getPMSInstance();

		if (valid() < LEV_JANITOR) {
			error('403 Access denied');
		}

		$post = $PIO->fetchPosts(intval($_GET['no']??''))[0];
		if (!$post) error('ERROR: That post does not exist.');
		$files = false;
		switch ($_GET['action']??'') {
			case 'del':
				$PMS->useModuleMethods('PostOnDeletion', array($post['no'], 'backend'));
				$files = $PIO->removePosts(array($post['no']));
				deleteCache(array($post['no']));
				logtime('Deleted post No.'.$post['no'], valid());
				break;
			case 'delmute':
				$PMS->useModuleMethods('PostOnDeletion', array($post['no'], 'backend'));
				$files = $PIO->removePosts(array($post['no']));
				deleteCache(array($post['no']));
				$ip = $post['host'];
				$starttime = $_SERVER['REQUEST_TIME'];
				$expires = $starttime+intval($this->JANIMUTE_LENGTH)*60;
				$f = fopen($this->BANFILE, 'w');
				if ($ip) {
					$reason = $this->JANIMUTE_REASON;
					fwrite($f, "$ip,$starttime,$expires,$reason\r\n");
				}
				fclose($f);
				logtime('Muted '.$ip.' and deleted post No.'.$post['no'], valid());
				break;
			case 'imgdel':
				$files = $PIO->removeAttachments(array($post['no']));
				logtime('Deleted file for post No.'.$post['no'], valid());
				break;
			case 'cachedel':
				deleteCache(array($post['no']));
				logtime('Deleted cache for post No.'.$post['no'], valid());
				break;
			default:
				error('ERROR: Invalid action.');
				break;
		}
		if ($files) {
			$FileIO->updateStorageSize(-$FileIO->deleteImage($files));
		}
		$PIO->dbCommit();

		updatelog();
		redirect('back', 0);
	}
}
