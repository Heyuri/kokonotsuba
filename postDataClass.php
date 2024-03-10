<?php
require_once 'fileDataClass.php';
require_once 'libCommon.php';
class postDataClass {
    private $threadID;//threadID, resto
	private $category;//thread catagory
    private $lastBumpTime;//last time thread has been bumped. root in db

    private $postID;//postID 

    private $utime;//time in unix seconds
	private $time;// time but normal.

    private $files = [];

    private $password;//post password
	private $passwordCookie;//password from a cookie
    private $name;//name
    private $email;//email
    private $sub;//subject
    private $com;//comment
    private $host;//poster's ip
    private $status;//special things like. auto sage, locked, animated gif, etc. split by a _thing_

    // moduels should have fun messing around withthings like this. 
    public $maxFileSize = null;
    public $forceAllowFileType = false;
    public $CleanseComment = true;

	public function __construct($data = []) {
        $this->postID = $data['no'] ?? null;
        $this->threadID = $data['resto'] ?? null;
        $this->category = $data['category'] ?? null;

        $this->lastBumpTime = $data['root'] ?? null;
        $this->utime = $data['time'] ?? null; //unix time stamp
        $this->time = $data['now'] ?? null; //time with proper time zone as string.

        $this->md5chksum = $data['md5chksum'] ?? null; //file md5 hash
        $this->fileName = $data['fname'] ?? null;
        $this->fileExtension = $data['ext'] ?? null;
        $this->imgw = $data['imgw'] ?? null;// width
        $this->imgh = $data['imgh'] ?? null;// hight
        $this->imgsize = $data['imgsize'] ?? null;
        $this->tw = $data['tw'] ?? null;// thumbnail witdh
        $this->th = $data['th'] ?? null;// thumbnail hight
        $this->fileNameOnDisk = $data['tim'] ?? null;// its time plus miliseconds at the end i only found to use with filenames

        $this->password = $data['pwd'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->subject = $data['sub'] ?? null;
        $this->comment = $data['com'] ?? null;
        $this->host = $data['host'] ?? null;
        $this->status = $data['status'] ?? null;
    }

	public function LoadFromDBfromPostID($postID){
        // this should be done inside a repository pattern no int this object.
        // the repository pattern should use the regual constructor.
	}

	//this should only ever be called when user post a post
	public function loadDataFromPostRequest() {
		$this->name = $_POST['name'] ?? '';
		$this->email = $_POST['email'] ?? '';
		$this->subject = $_POST['sub'] ?? '';
		$this->comment = $_POST['com'] ?? '';
		$this->password = $_POST['pwd'] ?? '';
		$this->category = $_POST['category'] ?? '';
		$this->threadID = intval($_POST['resto'] ?? 0);
		$this->host = getREMOTE_ADDR(); 
		$this->time = (new DateTime())->format('Y/m/d (D) H:i:s'); //time with proper time zone as string.
        $this->utime = time(); //unix time stamp
        
        $this->loadFilesFromPostRequest();
    }

    // files uploaded will be clensed
    private function loadFilesFromPostRequest(){
        $config = require('config.php')['fileConf'];

        // Check if the file input exists and a file is uploaded
        if (!isset($_FILES['upfile'])){
            echo "Error: No file uploaded.";
            return;
        }

        // Loop through each file (single or multiple)
        foreach ($_FILES['upfile']['error'] as $key => $error) {
            // Check for upload errors
            if ($error != UPLOAD_ERR_OK) {
                echo "Error: There was an error uploading file {$fileName}.";
                continue;
            }
            
            $tmpName = $_FILES['upfile']['tmp_name'][$key];
            $fileName = $_FILES['upfile']['name'][$key];
            $fileSize = $_FILES['upfile']['size'][$key];
            $fileType = $_FILES['upfile']['type'][$key];

            // Validate file size.
            if ($fileSize > $this->maxFileSize ?? $config['maxFileSize']) {
                echo "Error: File {$fileName} is too large. Maximum file size is {$config['maxFileSize']}.";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMimeType = $finfo->file($tmpName);

            // Validate file type
            if (!in_array($realMimeType, $config['allowedMimeTypes']) || !$this->forceAllowFileType) {
                echo "Error: File {$fileName} type is not allowed.";
                continue; 
            }

            // File passed validation checks.
            // make a valid name for the new file
            // make the object and attach it to the posts list.
            $fileExtention = getExtensionByMimeType($realMimeType);
            $fileNameOnDisk =  uniqid() . $fileExtention;
            move_uploaded_file($tmpName, $config['fileStoreLocation'] . "/" . $fileNameOnDisk);

            $this->files[] = new fileDataClass($fileName, $fileNameOnDisk, $fileSize);
        }
    }

    public function cleansePost(){
        //this should not cleans mysql. that should be done in the repository pattern.
        
        /*
        if(strlenUnicode($name) > INPUT_MAX) error(_T('regist_nametoolong'), $dest);
        if(strlenUnicode($email) > INPUT_MAX) error(_T('regist_emailtoolong'), $dest);
        if(strlenUnicode($sub) > INPUT_MAX) error(_T('regist_topictoolong'), $dest);
        if(strlenUnicode($resto) > INPUT_MAX) error(_T('regist_longthreadnum'), $dest);
        */
    }
    public function procssesFiles(){

    }
    public function addFile($file) {
        $this->files[] = $file;
    }
    public function getFiles() {
        return $this->files;
    }
	public function getAttributes(){
        //do this in a way to split up status string. 
		return $this->status;
	}
	public function getParentThread(){
		//connstruct thread from resto ID
	}
}