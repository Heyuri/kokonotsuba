<?php
require_once './fileDataClass.php';
require_once './auth.php';
require_once '../testLib.php';
class PostDataClass {
    private int $postID;//postID 
    private int $threadID;
    private array $files = [];//file objects
    private string $password;//post password
    private string $name;//name
    private string $email;//email
    private string $subject;//subject
    private string $comment;//comment
    private int $unixTime;//time posted
    private string $IP;//poster's ip
    private string $special;//special things like. auto sage, locked, animated gif, etc. split by a _thing_
    private $config;

	public function __construct(array $config, string $name, string $email, string $subject, 
                                string $comment, string $password, int $unixTime, string $IP, 
                                int $threadID, int $postID=-1, string $special='') {

        $this->config = $config;
		$this->name = $name ?? $config['defualtName'];
		$this->email = $email ?? $config['defaultEmail'];
		$this->subject = $subject ?? $config['defaultSubject'];
		$this->comment = $comment ?? $config['defaultComment'];
        $this->password = $password;

        $this->unixTime = $unixTime;
        $this->IP = $IP;
        $this->postID = $postID;
        $this->threadID = $threadID;
        $this->special = $special;
    }
    private function isValid():bool {
        if (mb_strlen($this->name, 'UTF-8') > INPUT_MAX) 
            return false;
        if (mb_strlen($this->email, 'UTF-8') > INPUT_MAX) 
            return false;
        if (mb_strlen($this->subject, 'UTF-8') > INPUT_MAX) 
            return false;
        if(strlenUnicode($this->comment) > COMM_MAX) 
		    return false;
        return true;
    }
    public function stripHtml(){
        $this->name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
		$this->email = htmlspecialchars($this->email, ENT_QUOTES, 'UTF-8');
		$this->subject = htmlspecialchars($this->subject, ENT_QUOTES, 'UTF-8');
		$this->comment = htmlspecialchars($this->comment, ENT_QUOTES, 'UTF-8');
    }
    public function embedLinks(){
        $regexUrl  = '/(https?:\/\/[^\s]+)/';
        $this->comment = preg_replace($regexUrl , '<a href="$1" target="_blank">$1</a>', $this->comment);
    }
    public function applyTripcode(){
        $this->name = convertTextToTripcodedText($this->name);
    }
    public function quoteLinks(){
        // just make this use a get request to other board
    }
    public function procssesFiles(){
        foreach ($this->files as $file) {
            $file->procssesFile();
        }
    }
    public function addFile(FileDataClass $file) {
        $this->files[] = $file;
    }

    public function getFiles() {
        return $this->files;
    }
	public function getParentThread(){
		//connstruct thread from resto ID
	}

    public function getPostID(){
        return $this->postID;
    }
    public function getThreadID(){
        return $this->threadID;
    }
    public function getName(){
        return $this->name;
    }
    public function getEmail(){
        return $this->email;
    }
    public function getSubject(){
        return $this->subject;
    }
    public function getComment(){
        return $this->comment;
    }
    public function getPassword(){
        return $this->password;
    }
    public function getUnixTime(){
        return $this->unixTime;
    }
    public function getIP(){
        return $this->IP;
    }
    public function getSpecial(){
        return $this->special;
    }

    public function setPostID($id){
        $this->postID = $id;
    }
    public function setThreadID($id){
        $this->threadID = $id;
    }
    public function setName($name){
        $this->name = $name;
    }
    public function setEmail($email){
        $this->email = $email;
    }
    public function setSubject($subject){
        $this->subject = $subject;
    }
    public function setComment($comment){
        $this->comment = $comment;
    }
    public function setPassword($password){
        $this->password = $password;
    }
    public function setUnixTime($unixTime){
        $this->unixTime = $unixTime;
    }
    public function setIP($IP){
        $this->IP = $IP;
    }
    public function setSpecial($special){
        $this->special = $special;
    }
}