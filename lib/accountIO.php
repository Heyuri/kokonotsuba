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
	private $flatfileName = ACCOUNT_FLATFILE; // Local Constant
	private $flatfileData = ''; //flatfile data will be loaded into memory with this variable
	private $readOnlyFFData = ''; //READ ONLY
	private $level, $ACCusername, $id; // account details

	public function __construct(){
		$this->level = 0;
		$this->id = 0;
		$this->ACCusername = '';
		
		if(!file_exists($this->flatfileName))
			if(!touch($this->flatfileName)) error("Could not create accounts.txt flatfile.");
	}

	public function flatfileConnect(){
		$this->readOnlyFFData = fopen($this->flatfileName, 'r');
	}
	
	public function getAllAccounts() {
		$this->flatfileConnect();
		$accounts = array();
		while (!feof($this->readOnlyFFData)) {
			$line = explode("<>", fgets($this->readOnlyFFData));
			$accounts[] = $line;
		}
		fclose($this->readOnlyFFData);
		return $accounts;
	}
	
	private function getLastID() {
		$this->flatfileConnect();
		$lastIDlist = [];
		while (!feof($this->readOnlyFFData)) {
			$line = fgets($this->readOnlyFFData);
			$data = explode("<>", $line);
			
			$lastIDlist[] = $data[0];
		}
		fclose($this->readOnlyFFData);
		
		//get largest ID from id array
		$lastID = max($lastIDlist);
		return $lastID;
	}
	
	public function addNewAccount($newUsername, $newPassword, $newRole) {
		$newRole = intval($newRole);
		$newUsername = substr($newUsername, 0, 30);
		$newPassword = substr($newPassword, 0, 200);
		$id = $this->getLastID();
		
		$this->flatfileConnect();
		$this->flatfileData = fopen($this->flatfileName, 'c+');
		if (!flock($this->flatfileData, LOCK_EX)) {
			echo "Could not lock log file.";
			fclose($fileHandle);
			return false;
		}

		//flatfile insert string
		$dataString = implode("<>", [$id + 1, $newUsername, $newPassword, $newRole]);
		$existingStringData = stream_get_contents($this->readOnlyFFData);

		if (fwrite($this->flatfileData, $existingStringData . PHP_EOL . $dataString) === false) {
			echo "Failed to write new account.";
			return false;
		}
		flock($this->flatfileData, LOCK_UN);
		fclose($this->flatfileData);
		fclose($this->readOnlyFFData);
		return true;
	}
	
	public function deleteAccount($id) {
		$accountfound = false;
		$newAccountFlatfile = [];
		$foundAccountRow = null;
		
		$this->flatfileConnect();
	
		while (!feof($this->readOnlyFFData)) {
			$line = fgets($this->readOnlyFFData);
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
		$openLogFile = fopen($this->flatfileName, 'w');
		flock($openLogFile, LOCK_EX);
 	   foreach ($newAccountFlatfile as $line) {
    	    fwrite($openLogFile, $line);
    	}
    	
    	flock($openLogFile, LOCK_UN);
		fclose($openLogFile);
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
			if(sizeof($account) != 4) continue;
			$account = array_combine(['id', 'username', 'password', 'role'], $account);
			if (crypt($pass, $account['password']) !== $account['password']) {
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
		fclose($this->readOnlyFFData);	
		return $this->level;
	}
}
