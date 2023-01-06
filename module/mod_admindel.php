<?php
// admin extra module made for kokonotsuba by deadking
class mod_admindel extends ModuleHelper {
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
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
