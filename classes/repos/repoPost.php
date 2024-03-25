<?php
require_once  './DBConnection.php';
require_once  './interfaces.php';
require_once '../postData.php';

class postRepoClass implements PostDataRepositoryInterface {
    private $db;
    private static $instance = null;

    private function __construct() {
        $this->db = DatabaseConnection::getInstance();
    }

    private function __clone() {}
    private function __wakeup() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new postRepoClass();
        }
        return self::$instance;
    }

    public function createPost($boardConf, $post) {
        // Start transaction
        $this->db->begin_transaction();
    
        try {
            // Step 1: Increment lastPostID for the board directly without a prepared statement
            $updateQuery = "UPDATE boardTable SET lastPostID = lastPostID + 1 WHERE boardID = " . intval($boardConf['boardID']);
            $this->db->query($updateQuery);
    
            // Retrieve the updated lastPostID
            $lastIdQuery = "SELECT lastPostID FROM boardTable WHERE boardID = " . intval($boardConf['boardID']);
            $result = $this->db->query($lastIdQuery);
            $lastPostID = null;
            if ($row = $result->fetch_assoc()) {
                $lastPostID = $row['lastPostID'];
            }
    
            if (is_null($lastPostID)) {
                throw new Exception("Failed to retrieve updated lastPostID.");
            }
    
            // Step 2: Insert the new post with the lastPostID
            // Assuming this part remains unchanged, using a prepared statement
            $post->setPostID($lastPostID);
            $insertQuery = "INSERT INTO posts (boardID, threadID, postID, name, email, subject, comment, password, postTime, IP, special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bind_param("iiisssssiss", $boardConf['boardID'], $post->getThreadID(), $post->getPostID, $post->getName(), $post->getEmail(), $post->getSubject(), $post->getComment(), $post->getPassword(), $post->getUnixTime(), $post->getIP(), $post->getSpecial());
            $insertSuccess = $insertStmt->execute();
            $insertStmt->close();
    
            if (!$insertSuccess) {
                throw new Exception("Failed to insert new post.");
            }
            
            // Commit the transaction
            $this->db->commit();
    
            return $lastPostID;
        } catch (Exception $e) {
            // Rollback the transaction on error
            $this->db->rollback();
            error_log($e->getMessage());
            return false;
        }
    }

    public function loadPostByID($boardConf, $postID) {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE postID = ? and boardID = ?");
        $stmt->bind_param("ii", $postID, $boardConf['boardID']);
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

    public function updatePost($boardConf, $post) {
        $query = "UPDATE posts SET boardID = ?, threadID = ?, name = ?, email = ?, subject = ?, comment = ?, password = ?, postTime = ?, IP = ?, special = ?,  postID = ? WHERE postID = ? and boardID = ?";
        $stmt = $this->db->prepare($query);
         $stmt->bind_param("iisssssissii",$boardConf['boardID'], $post->getThreadID(), $post->getName(), $post->getEmail(), $post->getSubject(), 
                                      $post->getComment(), $post->getPassword(), $post->getUnixTime(), $post->getIP(), 
                                      $post->getSpecial(), $post->getPostID(), $post->getPostID(), $boardConf['boardID']);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
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
