<?php

class fileDataClass {
    // move this into its own object.
    private $fileName; //file name
    private $fileNameOnDisk;//filename as stored on the system
    private $fileSize; //file size
    private $md5chksum; //file hash

    
    /*
     * why not get this on the fly when rebuilding a page?? 
     * 
        private $imgw; //image width
        private $imgh; //image hight

        private $tw; //thumbnail width
        private $th; //thumbnail hight

        $md5chksum = md5_file($dest)
    */
    public function __construct($fileName ='noName', $fileNameOnDisk, $fileSize=-1, $md5chksum='null') {
        $this->fileName = $fileName;
        $this->fileNameOnDisk = $fileNameOnDisk;
        $this->fileSize = $fileSize;
        $this->md5chksum = $md5chksum;

    }        
}
/*
		if (function_exists('exif_read_data') && function_exists('exif_imagetype')) {
			$imageType = exif_imagetype($dest);
			if ($imageType == IMAGETYPE_JPEG) {
				$exif = @exif_read_data($dest);
				if ($exif !== false) {
					// Remove Exif data
					$image = imagecreatefromjpeg($dest);
					imagejpeg($image, $dest, 100);
					imagedestroy($image);
				}
			}
		}
*/
/*
        // Now $validFiles contains information about all validly uploaded files
        // Process or save these files as needed
        foreach ($validFiles as $file) {
            // For example, moving the file to a permanent directory
            $uploadPath = 'uploads/' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                echo "The file " . htmlspecialchars($file['name']) . " has been uploaded.";
            } else {
                echo "Error: Failed to save the file " . htmlspecialchars($file['name']) . ".";
            }
        }
*/