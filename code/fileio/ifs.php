<?php
/**
 * FileIO Index File System
 *
 * Cache and record the various attributes of the remote image file locally to facilitate program access
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */

class IndexFS{
	var $logfile, $backend, $index, $modified, $keylist;

	/* Constructor */
	function IndexFS($logfile){
		// Index log file location
		$this->logfile = $logfile;
	}

	/* initialization */
	function init(){
		switch($this->backend){
			case 'pdo_sqlite':
				$execText = 'CREATE TABLE IndexFS (
				"imgName" VARCHAR(20)  NOT NULL PRIMARY KEY,
				"imgSize" INTEGER  NOT NULL,
				"imgURL" VARCHAR(255)  NOT NULL
				); CREATE INDEX IDX_IndexFS_imgName ON IndexFS(imgName);';
				$this->index->exec($execText);
				break;
			case 'log':
				touch($this->logfile); chmod($this->logfile, 0666); // Create index file
				break;
			case 'sqlite2':
				$execText = 'CREATE TABLE IndexFS (
				"imgName" VARCHAR(20)  NOT NULL PRIMARY KEY,
				"imgSize" INTEGER  NOT NULL,
				"imgURL" VARCHAR(255)  NOT NULL
				); CREATE INDEX IDX_IndexFS_imgName ON IndexFS(imgName);';
				sqlite_exec($this->index, $execText);
				break;
		}
	}

	/* Open the index file and read in */
	function openIndex(){
		if(extension_loaded('pdo_sqlite')){
			$this->backend = 'pdo_sqlite';
			$this->index = new PDO('sqlite:'.$this->logfile);
			if($this->index->query("SELECT COUNT(name) FROM sqlite_master WHERE name LIKE 'IndexFS'")->fetchColumn() === '0') $this->init();
		}else if(extension_loaded('SQLite')){
			$this->backend = 'sqlite2';

			$this->index = sqlite_open($this->logfile, 0666);
			if(sqlite_num_rows(sqlite_query($this->index, "SELECT name FROM sqlite_master WHERE name LIKE 'IndexFS'"))===0) $this->init();
		}else{
			$this->backend = 'log';
			$this->modified = false;
			if(!file_exists($this->logfile)){ $this->init(); return; }
			if(filesize($this->logfile)==0) return;
			$indexlog = file($this->logfile); $indexlog_count = count($indexlog); // Read the index file and calculate the current number
			$this->index = array();
			for($i = 0; $i < $indexlog_count; $i++){
				if(!($trimline = rtrim($indexlog[$i]))) continue; // This line is meaningless
				$field = explode("\t\t", $trimline);
				$this->index[$field[0]] = array('imgSize' => $field[1], 'imgURL' => isset($field[2]) ? $field[2] : '');
				// Index format: file name, file size, corresponding path
			}
			$this->keylist = array_keys($this->index);
			unset($indexlog);
		}
		PMCLibrary::getLoggerInstance(__CLASS__)->
			info('Backend: %s, Path: %s', $this->backend, $this->logfile);
	}

	/* Does the index exist */
	function beRecord($id){
		switch($this->backend){
			case 'pdo_sqlite':
				$sth = $this->index->prepare('SELECT COUNT(imgName) FROM IndexFS WHERE imgName = ?');
				$sth->execute(array($id));
				return $sth->fetchColumn() != false;
			case 'log':
				return isset($this->index[$id]);
			case 'sqlite2':
				return (sqlite_fetch_array(sqlite_query($this->index, 'SELECT imgName FROM IndexFS WHERE imgName = "'.sqlite_escape_string($id).'"'), SQLITE_ASSOC) ? true : false);
		}
	}

	/* Search for preview image file name */
	function findThumbName($pattern){
		switch($this->backend){
			case 'pdo_sqlite':
				$sth = $this->index->prepare('SELECT imgName FROM IndexFS WHERE imgName >= ? AND imgName < ?');
				$sth->execute(array($pattern.'s', $pattern.'t'));
				return $sth->fetchColumn();
			case 'log':
				if(count($this->keylist) != count($this->index)){ // Index Sync
					$this->keylist = array_keys($this->index);
				}
				// O(n) not optimized
				foreach($this->keylist as $k){
					if(strpos($k, $pattern.'s.') !== false) return $k;
				}
				return false;
			case 'sqlite2':
				$ptrn = sqlite_escape_string($pattern);
				// LIKE Optimization by >= AND < using index
				// Original: LIKE "1234567890123s.%"
				$result = sqlite_fetch_array(sqlite_query($this->index, 'SELECT imgName FROM IndexFS WHERE imgName >= "'.$ptrn.'s" AND imgName < "'.$ptrn.'t"'), SQLITE_ASSOC);
				return (isset($result) ? $result['imgName'] : false);
		}
	}

	/* Get an index */
	function getRecord($id){
		switch($this->backend){
			case 'pdo_sqlite':
				$sth = $this->index->prepare('SELECT * FROM IndexFS WHERE imgName = ?');
				$sth->execute(array($id));
				return $sth->fetch();
			case 'log':
				return isset($this->index[$id]) ? $this->index[$id] : false;
			case 'sqlite2':
				return sqlite_fetch_array(sqlite_query($this->index, 'SELECT * FROM IndexFS WHERE imgName = "'.sqlite_escape_string($id).'"'), SQLITE_ASSOC);
		}
	}

	/* Add an index */
	function addRecord($id, $imgSize, $imgURL){
		switch($this->backend){
			case 'pdo_sqlite':
				$sth = $this->index->prepare('INSERT INTO IndexFS (imgName, imgSize, imgURL) VALUES (?, ?, ?)');
				$sth->execute(array($id, $imgSize, $imgURL));
				break;
			case 'log':
				$this->modified = true;
				$this->index[$id] = array('imgSize' => $imgSize, 'imgURL' => $imgURL); // Added to the index
				break;
			case 'sqlite2':
				sqlite_exec($this->index, 'INSERT INTO IndexFS (imgName, imgSize, imgURL) VALUES ("'.sqlite_escape_string($id).'", '.sqlite_escape_string($imgSize).', "'.sqlite_escape_string($imgURL).'");');
				break;
		}
	}

	/* Delete an index */
	function delRecord($id){
		switch($this->backend){
			case 'pdo_sqlite':
				$sth = $this->index->prepare('DELETE FROM IndexFS WHERE imgName = ?');
				return $sth->execute(array($id));
			case 'log':
				if(isset($this->index[$id])){ unset($this->index[$id]); $this->modified = true; return true; }
				return false;
			case 'sqlite2':
				return sqlite_exec($this->index, 'DELETE FROM IndexFS WHERE imgName = "'.sqlite_escape_string($id).'";');
		}
	}

	/* Save index changes */
	function saveIndex(){
		if($this->backend=='log' && $this->modified){ // If the index is modified, it will be saved back
			$indexlog = '';
			if(count($this->index)) foreach($this->index as $ikey => $ival){ $indexlog .= $ikey."\t\t".$ival['imgSize']."\t\t".$ival['imgURL']."\n"; } // Run the loop only if you have information
			$fp = fopen($this->logfile, 'w');
			fwrite($fp, $indexlog);
			fclose($fp);
		}elseif($this->backend=='sqlite2'){
			sqlite_close($this->index);
		}
	}

	/* Get the size of all files in the current index */
	function getCurrentStorageSize(){
		switch($this->backend){
			case 'pdo_sqlite':
				$size = $this->index->query('SELECT SUM(imgSize) FROM IndexFS');
				return intval($size->fetchColumn());
			case 'log':
				$size = 0;
				if(count($this->index)){
					foreach($this->index as $ival){
						$size += $ival['imgSize'];
					}
				}
				return intval($size);
			case 'sqlite2':
				$size = sqlite_fetch_array(sqlite_query($this->index, 'SELECT SUM(imgSize) FROM IndexFS'));
				return intval($size[0]);
		}
	}
}
?>