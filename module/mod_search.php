<?php
class mod_search extends ModuleHelper {
	private $mypage;
	private $THUMB_EXT = THUMB_SETTING['Format'];

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return __CLASS__.' : K! Search';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookToplink(&$linkbar, $isreply){
		$linkbar .= ' [<a href="'.$this->mypage.'">Search</a>] ';
	}

	public function ModulePage(){
		$PTE = PMCLibrary::getPTEInstance();
		$PMS = PMCLibrary::getPMSInstance();
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // The text you want to search
		$dat = '';
		head($dat);
		$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
		$level = $AccountIO->valid();
		$PMS->useModuleMethods('LinksAboveBar', array(&$links,'search',$level));
		$dat .= $links.'<center class="theading2"><b>'._T('search_top').'</b></center>
		</div>
		';
		
		echo $dat;
		if($searchKeyword==''){
		echo '<form action="'.PHP_SELF.'?mode=module&load=mod_search" method="post">
		<div id="search">
		<input type="hidden" name="mode" value="search" />
		';
		echo '<ul>'._T('search_notice').'<input type="text" name="keyword" size="30" />
	'._T('search_target').'<select name="field"><option value="com" selected="selected">'._T('search_target_comment').'</option><option value="name">'._T('search_target_name').'</option><option value="sub">'._T('search_target_topic').'</option><option value="no">'._T('search_target_number').'</option></select>
	'._T('search_method').'<select name="method"><option value="AND" selected="selected">'._T('search_method_and').'</option><option value="OR">'._T('search_method_or').'</option></select>
	<input type="submit" value="'._T('search_submit_btn').'" />
	</li>
	</ul>
	</div>
	</form>';
		}else{
			$searchField = $_POST['field']; // Search target (no:number, name:name, sub:title, com:text)
			$searchMethod = $_POST['method']; // Search method
			$searchKeyword = preg_split('/(　| )+/', strtolower(trim($searchKeyword))); // Search text is cut with spaces
			if ($searchMethod=='REG') $searchMethod = 'AND';
			$hitPosts = $PIO->searchPost($searchKeyword, $searchField, $searchMethod); // Directly return the matching article content array

			echo '<div id="searchresult">';
			$resultlist = '';
			foreach($hitPosts as $post){
				extract($post);
				if(USE_CATEGORY){
					$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
					$ary_category_count = count($ary_category);
					$ary_category2 = array();
					for($p = 0; $p < $ary_category_count; $p++){
						if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&c='.urlencode($c).'">'.$c.'</a>';
					}
					$category = implode(', ', $ary_category2);
				}else $category = '';
				$arrLabels = array('{$NO}'=>'<a href="'.PHP_SELF.'?res='.($resto?$resto.'#p'.$no:$no).'">'.$no.'</a>', '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'));
				$resultlist .= $PTE->ParseBlock('SEARCHRESULT',$arrLabels);
			}
			echo $resultlist ? $resultlist : '<center>'._T('search_notfound').'<br/>[<a href="?mode=search">'._T('search_back').'</a>]</center>';
			echo "</div>";
		}
	}
}
