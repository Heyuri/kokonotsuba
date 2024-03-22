<?php

class fileHandlerClass {
    private $config;
    private $maxFileSize = null;
    private $forceAllowAllfileTypes = false;
    public function __construct($config) {
        $this->config = $config;
    }

    public function getFilesFromPostRequest(int $maxFiles = null): array {
        $filesGotten = [];
        $processedFilesCount = 0;
        $fileLimit = $maxFiles ?? $this->config['maxFiles'];

        if (!isset($_FILES['upfile'])) {
            return $filesGotten;
        }

        // Loop through each file
        foreach ($_FILES['upfile']['error'] as $key => $error) {
            if ($error != UPLOAD_ERR_OK) {
                echo "Error: There was an error uploading file {$fileName}.";
                continue;
            }
            // Stop processing if the maximum number of files has been reached
            if ($processedFilesCount >= $fileLimit) {
                break;
            }

            $tmpName = $_FILES['upfile']['tmp_name'][$key];
            $fileName = $_FILES['upfile']['name'][$key];
            $fileSize = $_FILES['upfile']['size'][$key];

            // Validate file size.
            if ($fileSize > $this->maxFileSize ?? $this->config['maxFileSize']) {
                echo "Error: File {$fileName} is too large. Maximum file size is ".$this->config['maxFileSize'];
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMimeType = $finfo->file($tmpName);

            // Validate file type
            if (!in_array($realMimeType, $this->config['allowedMimeTypes']) || !$this->forceAllowAllfileTypes) {
                echo "Error: File {$fileName} type is not allowed.";
                continue; 
            }

            // File passed validation checks.
            // make a valid name for the new file
            // make the object and attach it to the posts list.
            $fileExtention = getExtensionByMimeType($realMimeType);
            $fileNameOnDisk =  uniqid() . $fileExtention;
            move_uploaded_file($tmpName, $this->config['fileStoreLocation'] . "/" . $fileNameOnDisk);

            $filesGotten [] = new fileDataClass($fileName, $fileNameOnDisk, $fileSize);
            $processedFilesCount++;
        }
        return $filesGotten;
    }
    public function SetMaxFileSize(int $maxFileSize){
        $this->maxFileSize = $maxFileSize;
    }
    public function setAllowAllFileTypes(bool $allowAllFileTypes){
        $this->allowAllFileTypes = $allowAllFileTypes;
    }
}