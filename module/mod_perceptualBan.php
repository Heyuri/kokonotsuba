<?php

require_once __DIR__ . "/../lib/lib_modHelper.php";

use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Jenssegers\ImageHash\Hash;


class mod_perceptualBan extends ModuleHelper{
    private $hammingTrigger = 2;
    private $hashes = [];
    private $dir;


	public function __construct($PMS) {
		parent::__construct($PMS);
        $this->dir = getModDirectory($this);
	}

	public function getModuleName(){
		return 'mod_perceptualBan : bans images perceptualy';
	}

	public function getModuleVersionInfo(){
		return '0.1';
	}

    public function ModulePage() {
        // give a way to configure hamming for admins and also disable it.
        // give a way for admin to select post and get prceptual hash.
    }

    public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $isReply, $imgWH, &$status) {
        $this->hashes = $this->getData("badHashes.txt");
        $hasher = new ImageHash(new DifferenceHash());
        $preseptualHash = $hasher->hash($dest);

        foreach($this->hashes as $badHash){
            $hashObject = Hash::fromBits($this->hashToBits($badHash));
            $hamming = $hasher->distance($hashObject, $preseptualHash);
            if ($hamming >= $this->hammingTrigger) { 
                unlink($dest);
                echo("bad file detected... fukkin saved!");
                die();
            }
        }
	}

    private function hashToBits($hash){
        $value = hex2bin($hash);

        $bits = '';
        $digits = unpack('J*', $value);
        foreach ($digits as $digit) {
            $bits .= sprintf('%064b', $digit);
        }

        return $bits;
    }

    private function getData($file) {
        // Build the full path to the file using the $dir property
        $filePath = $this->dir . DIRECTORY_SEPARATOR . $file;
    
        // Check if the file exists; if not, create it and return an empty array
        if (!file_exists($filePath)) {
            file_put_contents($filePath, ''); // Create an empty file if it doesn't exist
            return [];
        }
    
        // Read the file line by line and store each line as a separate array element
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
    
        return $lines; // Return the array with each line as a separate item
    }
}
