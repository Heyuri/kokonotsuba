<?php
/**
 * Account Flatfile API
 *
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */

class AccountIO {
	private $flatfileName = STORAGE_PATH.'accounts.txt'; // Local Constant
	private $flatfileData = ''; //flatfile data will be loaded into memory with this variable
	private $readOnlyFFData = ''; //READ ONLY
	private $level, $ACCusername, $id; // account details

	public function __construct(){
		$this->level = 0;
		$this->ACCusername = '';
		$this->id = 0;
		
//	$this->flatfileConnect();
	}

	/* Process connection string/connection */
	public function flatfileConnect(){
		$this->flatfileData = fopen($this->flatfileName, 'r+'); //open for reading and writing
		// Acquire an exclusive lock
		if (!flock($this->flatfileData, LOCK_EX)) {
			echo "Could not lock log file.";
			fclose($this->flatfileData);
			return false;
		}
		$this->readOnlyFFData = fopen($this->flatfileName, 'r');
	}
	
	public function getAllAccounts() {
		$this->flatfileConnect();
		$accounts = array();
		while (!feof($this->readOnlyFFData)) {
			$line = explode("<>", fgets($this->readOnlyFFData));
			$accounts[] = $line;
		}
		flock($this->flatfileData, LOCK_UN);
		fclose($this->flatfileData);
		fclose($this->readOnlyFFData);
		return $accounts;
	}
	
	private function getLastID() {
	
	}
	
	public function addNewAccount($newUsername, $newPassword, $newRole) {
		$newRole = intval($newRole);
		$newUsername = substr($newUsername, 0, 30);
		$newPassword = substr($newPassword, 0, 50);
		$id = $this->getLastID();
		
		$this->flatfileConnect();
		
		//flatfile insert string
		$dataString = implode("<>", [$id + 1, $newUsername, $newPassword, $newRole]);
		
    	rewind($this->flatfileData);

		if (fwrite($this->flatfileData, $dataString . $this->readOnlyFFData) === false) {
			echo "Failed to write new account.";
			return false;
		}
		flock($this->flatfileData, LOCK_UN);
		fclose($this->flatfileData);
		fclose($this->readOnlyFFData);
		return true;
	}
	
	public function deleteAccount($id) {;
		$accountfound = false;
		$newAccountFlatfile = [];
		$foundAccountRow = null;
		
		$this->flatfileConnect();
	
		while (!feof($this->flatfileData)) {
			$line = fgets($this->flatfileData);
			$data = explode("<>", $line);
			if ($data[0] == $id) {
				$accountfound = true;
				$foundAccountRow = $data;
			} else {
				$newAccountFlatfile[] = $line;
			}
		}


		// data was not found.
		if ($accountfound == false) {
    	    return false;
		}



 	   foreach ($newAccountFlatfile as $line) {
    	    fwrite($this->flatfileData, $line);
    	}
		flock($this->flatfileData, LOCK_UN);
		fclose($this->flatfileData);
		fclose($this->readOnlyFFData);
	    return true;
	}
	
	public function getRoleLevel() {
		return num2Role($this->level);
	}

	public function getUsername() {
		if(empty($this->ACCusername)) return "Anonymous";
		return $this->ACCusername;	
	}
	
	public function valid($pass='') {
		if (!$pass) $pass = $_SESSION['kokologin']??'';
		$this->level = LEV_NONE;
		
		//get all acounts
		$totalStoredAccounts = $this->getAllAccounts();
		
		$this->flatfileConnect();
		foreach($totalStoredAccounts as $account) {
			if(sizeof($account) < 4) break;
			$account = array_combine(['id', 'username', 'password', 'role'], $account);
			if (crypt($pass, TRIPSALT) !== $account['password']) {
				$this->level = LEV_NONE; //pass wrong
				continue;
			}
			switch($account['role']) {
				case 0:
					$this->level = LEV_NONE;
					$this->ACCusername = $account['username'];
				break 2;
				case 1:
					$this->level = LEV_USER;
					$this->ACCusername = $account['username'];		
				break 2;
				case 2:
					$this->level = LEV_JANITOR;
					$this->ACCusername = $account['username'];
				break 2;
				case 3:
					$this->level = LEV_MODERATOR;
					$this->ACCusername = $account['username'];
				break;
				case 4:
					$this->level = LEV_ADMIN;
					$this->ACCusername = $account['username'];
				break 2;
			}
		}
		flock($this->flatfileData, LOCK_UN);
		fclose($this->flatfileData);
		fclose($this->readOnlyFFData);	
		return $this->level;
	}
}
