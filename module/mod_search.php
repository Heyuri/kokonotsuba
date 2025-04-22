<?php
class mod_search extends moduleHelper {
	private $mypage;
	private $THUMB_EXT = -1;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->THUMB_EXT = $this->config['THUMB_SETTING']['Format'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return __CLASS__.' : K! Search';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookToplink(&$linkbar, $isreply){
		$linkbar .= ' [<a href="'.$this->mypage.'">'._T('head_search').'</a>] ';
	}

	public function ModulePage(){
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getinstance();

		$staffSession = new staffAccountFromSession;
		$globalHTML = new globalHTML($this->board);

		$roleLevel = $staffSession->getRoleLevel();
		
		$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // The text you want to search
		$dat = '';
		$globalHTML->head($dat);
		$links = '[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]';
		$dat .= $links;
		echo $dat;
		
		echo '
			<h2 class="theading2">'._T('search_top').'</h2>
				<div class="modulePageContent">
			';

		if($searchKeyword==''){
			echo '
				<form action="'.$this->mypage.'" method="post">
					<div id="search">
						<ul>'._T('search_notice').'</ul>
						<input type="hidden" name="mode" value="search">
						
						<table>
							<tbody>
								<tr>
									<td class="postblock"><label for="searchKeyword">'._T('search_keyword').'</label></td>
									<td><input type="text" class="inputtext" id="searchKeyword" name="keyword"></td>
								</tr>
								<tr>
									<td class="postblock"><label for="searchTarget">'._T('search_target').'</label></td>
									<td>
										<select id="searchTarget" name="field">
											<option value="com" selected="selected">'._T('search_target_comment').'</option>
											<option value="name">'._T('search_target_name').'</option>
											<option value="sub">'._T('search_target_topic').'</option>
											<option value="no">'._T('search_target_number').'</option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="postblock"><label for="searchMethod">'._T('search_method').'</label></td>
									<td>
										<select id="searchMethod" name="method">
											<option value="AND" selected="selected">'._T('search_method_and').'</option>
											<option value="OR">'._T('search_method_or').'</option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>

						<div class="buttonSection">
							<input type="submit" value="'._T('search_submit_btn').'">
						</div>
					</div>
				</form>
				<hr>
				';
		}else{
			$searchField = $_POST['field']; // Search target (no:number, name:name, sub:title, com:text)
			$searchMethod = $_POST['method']; // Search method
			$searchKeyword = preg_split('/(　| )+/', strtolower(trim($searchKeyword))); // Search text is cut with spaces
			if ($searchMethod=='REG') $searchMethod = 'AND';
			$hitPosts = $PIO->searchPost($this->board, $searchKeyword, $searchField, $searchMethod) ?? []; // Directly return the matching article content array

			echo '<div id="searchresult">';
			$resultlist = '';
			foreach($hitPosts as $post){
				extract($post);
				$resno = $threadSingleton->resolveThreadNumberFromUID($thread_uid);
				if($this->config['USE_CATEGORY']){
					$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
					$ary_category_count = count($ary_category);
					$ary_category2 = array();
					for($p = 0; $p < $ary_category_count; $p++){
						if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.$this->config['PHP_SELF'].'??mode=module&load=mod_searchcategory&&c='.urlencode($c).'">'.$c.'</a>';
					}
					$category = implode(', ', $ary_category2);
				}else $category = '';
					$com = $globalHTML->quote_link($this->board, $PIO, $com);
					$com = $globalHTML->quote_unkfunc($com);
				
					$arrLabels = array('{$NO}'=>'<a href="'.$this->config['PHP_SELF'].'?res='.($resno?$resno.'#p'.$no:$no).'">'.$no.'</a>', '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'));
				$resultlist .= $this->templateEngine->ParseBlock('SEARCHRESULT',$arrLabels);
			}
			echo $resultlist ? $resultlist : '
				<div class="centerText">
					<p>'._T('search_notfound').'</p>
					<p>[<a href="'.$this->mypage.'">'._T('search_back').'</a>]</p>
				</div>
				<hr>';
			echo "</div>";
		}

		echo '</div>';
	}
}
