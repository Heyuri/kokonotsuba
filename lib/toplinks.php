<?php

class TopLink {
	private $toplinkJSONfile, $jsonfile;
	
	public function __construct($jsonfile) {
		$this->jsonfile = $jsonfile;
		
		
	}

	private function getTopLinkAsArray() {
		if (!is_file($this->jsonfile)) {
			throw new Exception("Top link JSON ({$this->jsonfile}) isn't a valid file in " . __CLASS__ . ", " . __FILE__);
		}
		
		$fileContents = file_get_contents($this->jsonfile);
		if ($fileContents === false) {
			throw new Exception("Failed to read the file: {$this->jsonfile}");
		}

		$jsonArray = json_decode($fileContents, true);
		
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception("JSON decode error: " . json_last_error_msg() . " in file {$this->jsonfile}");
		}

		return $jsonArray;
	}
	
	public function getTopLinkNeoMenu() {
		$topLinkArray = $this->getTopLinkAsArray();
		if(!isset($topLinkArray['Neomenu'])) throw new Exception("Neomenu json not found.");
		else return $topLinkArray['Neomenu'];	
	}
	
	public function getTopLinkClassicMenu() {
		$topLinkArray = $this->getTopLinkAsArray();
		if(!isset($topLinkArray['Classicmenu'])) throw new Exception("Classicmenu json not found.");
		else return $topLinkArray['Classicmenu'];	
	}
	
}
