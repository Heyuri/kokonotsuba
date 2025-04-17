<?php

// admin route - shows log in page and a router using POST

class adminRoute {
    private readonly board $board;
    private readonly array $config;
    private readonly globalHTML $globalHTML;
    private readonly staffAccountFromSession $staffSession;
    private readonly AccountIO $AccountIO;

    private moduleEngine $moduleEngine;


    public function __construct(board $board, 
        array $config,
        globalHTML $globalHTML, 
        staffAccountFromSession $staffSession,
        moduleEngine $moduleEngine,
        AccountIO $AccountIO) {
        $this->board = $board;
        $this->config = $config;
        $this->globalHTML = $globalHTML;
        $this->staffSession = $staffSession;
        $this->AccountIO = $AccountIO;

        $this->moduleEngine = $moduleEngine;
    }

    public function drawAdminPage() {
		if(isset($_POST['username']) && isset($_POST['password'])) {
			adminLogin($this->AccountIO, $this->globalHTML);
		}
		$recentStaffAccountFromSession = new staffAccountFromSession;

		$currentRoleLevel = $recentStaffAccountFromSession->getRoleLevel(); // get the newly set role level if login was successful
		$adminPageHandler = new adminPageHandler($this->board, $this->moduleEngine); // router for some admin pages, mostly legacy
		$admin = $_REQUEST['admin']??'';
		$dat = '';
		$this->globalHTML->head($dat);
		$links = $this->globalHTML->generateAdminLinkButtons();
		
		$dat .= $links; //hook above bar links
		
		$this->globalHTML->drawAdminTheading($dat, $this->staffSession);
		
		$dat.= '<div id="adminOptionContainer" class="centerText"><form action="'.$this->config['PHP_SELF'].'" method="POST" name="adminform">';
		$admins = array(
			array('name'=>'del', 'level'=>$this->config['roles']['LEV_JANITOR'], 'label'=>'Manage posts', 'func'=>'admindel'),
			array('name'=>'action', 'level'=>$this->config['roles']['LEV_ADMIN'], 'label'=>'Action log', 'func'=>'actionlog'),
			array('name'=>'logout', 'level'=>$this->config['roles']['LEV_USER'], 'label'=>'Logout', 'func'=>'adminLogout'),
		);

		foreach ($admins as $adminmode) {
			if ($currentRoleLevel==$this->config['roles']['LEV_NONE'] && $adminmode['name']=='logout') continue;
			$checked = ($admin==$adminmode['name']) ? ' checked="checked"' : '';
			$dat.= '<label><input type="radio" name="admin" value="'.$adminmode['name'].'"'.$checked.'>'.$adminmode['label'].'</label> ';
		}
		if ($currentRoleLevel==$this->config['roles']['LEV_NONE']) {
			$dat.= $this->globalHTML->drawAdminLoginForm()."</form>";
		} else {
			$dat.= '<button type="submit" name="mode" value="admin">Submit</button></form>';
		}
		$find = false;
		
		$dat.= '</div><hr>';

		foreach ($admins as $adminmode) {
			if ($admin!=$adminmode['name']) continue;
			$find = true;
			if ($adminmode['level']>$currentRoleLevel) {
				$dat.= '<div class="centerText"><span class="error">ERROR: Access denied.</span></div><hr>';
				break;
			}
			if ($adminmode['func']) {
				$adminPageHandler->handleAdminPageSelection($adminmode['func'], $dat);
			}
		}

		$this->globalHTML->foot($dat);
		die($dat.'</body></html>');
	}
}