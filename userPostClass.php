<?php

class Post {
    public $no;//postID 
    public $resto;//threadID
	public $category;//thread catagory


    public $root;//last time thread has been bumped
    public $time;//time?
	public $now;//time posted

    public $md5chksum;//file hash
    public $fname;//file name
    public $ext;//file extention
    public $imgw;//image width
    public $imgh;//image hight
    public $imgsize;//file size
    public $tw;//thumbnail width
    public $th;//thumbnail hight
	public $tim;//filename as stored on the system

    public $pwd;//post password
    public $name;//name
    public $email;//email
    public $sub;//subject
    public $com;//comment
    public $host;//poster's ip
    public $status;//special things like. auto sage, locked, animated gif, etc. split by a _thing_

    // Add constructor if needed, or any other methods to manipulate the post object
}