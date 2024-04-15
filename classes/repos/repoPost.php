<?php
require_once  __DIR__ .'/DBConnection.php';
require_once  __DIR__ .'/interfaces.php';
require_once __DIR__ .'/../post.php';
require_once __DIR__ .'/../../common.php';

class PostRepoClass implements PostDataRepositoryInterface {
    private function __clone() {}
    public function __wakeup() { throw new Exception("Unserialization of AuthClass instances is not allowed.");}

    private $db;
    private static $instance = null;

    private function __construct() {
        $this->db = DatabaseConnection::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new postRepoClass();
        }
        return self::$instance;
    }
    /* this feels a bit hacky to get the newstPostid on the new post*/
    public function createPost($boardConf, $post) {
        // Start transaction
        $this->db->begin_transaction();
    
        try {
            // increment the lastPostID from the boad table.
            $updateQuery = "UPDATE boards SET lastPostID = lastPostID + 1 WHERE boardID = " . intval($boardConf['boardID']);
            $this->db->query($updateQuery);
    
            // get the lastPostID. this will be used for new post
            $lastIdQuery = "SELECT lastPostID FROM boards WHERE boardID = " . intval($boardConf['boardID']);
            $result = $this->db->query($lastIdQuery);
            $lastPostID = null;
            if ($row = $result->fetch_assoc()) {
                $lastPostID = $row['lastPostID'];
            }
    
            if (is_null($lastPostID)) {
                throw new Exception("Failed to retrieve new lastPostID from board table. where boardID = ".$boardConf['boardID']);
            }
            // why is sqli like this...
            $threadID = $post->getThreadID();
            $name = $post->getName();
            $email = $post->getEmail();
            $sub = $post->getSubject();
            $comment = $post->getComment();
            $pass = $post->getPassword();
            $time = $post->getUnixTime();
            $ip = $post->getIp();
            $special = $post->getSpecial();
    
            // create post in db
            $insertQuery = "INSERT INTO posts ( boardID, threadID, postID, name, 
                                                email, subject, comment, password, 
                                                postTime, IP, special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bind_param("iiisssssiss",  $boardConf['boardID'], $threadID, $lastPostID, $name, 
                                                    $email, $sub, $comment, $pass, 
                                                    $time, $ip, $special);
            $insertSuccess = $insertStmt->execute();
            $insertStmt->close();
    
            if (!$insertSuccess) {
                throw new Exception("Failed to insert new post in post table.");
            }
            
            // comit and update post object.
            $this->db->commit();
            $post->setPostID($lastPostID);
            
            return true;
        } catch (Exception $e) {
            // Rollback the transaction on error
            $this->db->rollback();
            error_log($e->getMessage());
            displayErrorPageAndDie($e->getMessage());
            return false;
        }
    }
    public function loadPostByID($boardConf, $postID) {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE boardID = ? and postID = ? ");
        $stmt->bind_param("ii", $boardConf['boardID'], $postID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return new PostDataClass($boardConf, $row['name'], $row['email'], $row['subject'], 
                                     $row['comment'], $row['password'], $row['unixTime'], $row['IP'], 
                                     $row['threadID'], $row['postID'], $row['special']);
        }
        $stmt->close();
        return null;
    }
    public function loadPostByThreadID($boardConf, $threadID, $postID) {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE boardID = ? and postID = ? and threadID = ? ");
        $stmt->bind_param("iii", $boardConf['boardID'], $postID, $threadID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return new PostDataClass($boardConf, $row['name'], $row['email'], $row['subject'], 
                                     $row['comment'], $row['password'], $row['unixTime'], $row['IP'], 
                                     $row['threadID'], $row['postID'], $row['special']);
        }
        $stmt->close();
        return null;
    }
    public function loadPosts($boardConf){
        $posts = [];
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE boardID = ?");
        $stmt->bind_param("i", $boardConf['boardID']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $posts[] =  new PostDataClass($boardConf, $row['name'], $row['email'], $row['subject'], 
                                     $row['comment'], $row['password'], $row['unixTime'], $row['IP'], 
                                     $row['threadID'], $row['postID'], $row['special']);
        }
        
        $stmt->close();
        return $posts;
    }
    public function loadPostsByThreadID($boardConf, $threadID){
        $posts = [];
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE boardID = ? and threadID = ? ");
        $stmt->bind_param("ii", $boardConf['boardID'], $threadID);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $posts[] =  new PostDataClass($boardConf, $row['name'], $row['email'], $row['subject'], 
                                     $row['comment'], $row['password'], $row['unixTime'], $row['IP'], 
                                     $row['threadID'], $row['postID'], $row['special']);
        }
        
        $stmt->close();
        return $posts;
    }
    public function loadLastPostByThreadID($boardConf, $threadID, $num=0){
        $posts = [];
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE boardID = ? and threadID = ? ORDER BY postTime DESC LIMIT ?");
        $stmt->bind_param("iii", $boardConf['boardID'], $threadID, $num);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $posts[] =  new PostDataClass($boardConf, $row['name'], $row['email'], $row['subject'], 
                                     $row['comment'], $row['password'], $row['unixTime'], $row['IP'], 
                                     $row['threadID'], $row['postID'], $row['special']);
        }
        
        $stmt->close();
        return $posts;
    }
    public function updatePost($boardConf, $post) {
        // why is sqli like this...
        $threadID = $post->getThreadID();
        $name = $post->getName();
        $email = $post->getEmail();
        $sub = $post->getSubject();
        $comment = $post->getComment();
        $pass = $post->getPassword();
        $time = $post->getUnixTime();
        $id = $post->getPostID();
        $ip = $post->getIp();
        $special = $post->getSpecial();
        $query = "UPDATE posts SET      boardID = ?, threadID = ?, name = ?, email = ?,
                                        subject = ?, comment = ?, password = ?, postTime = ?, 
                                        ip = ?, special = ?
                                    WHERE postID = ? and boardID = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iisssssissii",
                                        $boardConf['boardID'], $threadID, $name, $email, 
                                        $sub, $comment, $pass, $time, 
                                        $ip, $special,
                                    $id, $boardConf['boardID']);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    public function setPostID($boardConf, $post, $newPostID) {
        $this->db->begin_transaction();
        
        try {
            // check if newPostID is in use by another post on the board.
            $checkQuery = "SELECT COUNT(*) AS cnt FROM posts WHERE postID = ? AND boardID = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            if (!$checkStmt) {
                throw new Exception("Failed to prepare statement for checking postID.");
            }
            $checkStmt->bind_param("ii", $newPostID, $boardConf['boardID']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
    
            if ($row['cnt'] > 0) {
                // The newPostID is already in use
                throw new Exception("The new postID is already in use.");
            }
    
            // set the post with the new postID
            $updateQuery = "UPDATE posts SET postID = ? WHERE postID = ? AND boardID = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare statement for updating postID.");
            }
            $id = $post->postID();
            $updateStmt->bind_param("iii", $newPostID, $id, $boardConf['boardID']);
            $success = $updateStmt->execute();
            $updateStmt->close();
    
            if (!$success) {
                throw new Exception("Failed to update the post with the new postID.");
            }
    
            // Commit the transaction
            $this->db->commit();
            $post->setPostID($newPostID);
    
            return true;
        } catch (Exception $e) {
            // Rollback the transaction on error
            $this->db->rollback();
            error_log($e->getMessage());
            return false;
        }
    }
    public function deletePostByID($boardConf, $postID) {
        $query = "DELETE FROM posts WHERE boardID = ? and postID = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $boardConf['boardID'], $postID);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}
