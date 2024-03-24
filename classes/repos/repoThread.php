<?php
require_once  './DBConnection.php';
require_once  './interfaces.php';
require_once '../thread.php';

class ThreadRepoClass implements ThreadRepositoryInterface {
    private $db;
    private static $instance = null;

    private function __construct() {
        $this->db = DatabaseConnection::getInstance();
    }

    private function __clone() {}
    private function __wakeup() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ThreadRepoClass();
        }
        return self::$instance;
    }

    public function createThread($boardConf, $thread) {
        $query = "INSERT INTO threads (boardID, lastTimePosted, opPostID) VALUES ('{$boardConf->boardID}','{$thread->getLastBumpTime()}','{$thread->getOPPostID()}')";
        if ($this->db->query($query) === TRUE) {
            $thread->threadID = $this->db->insert_id;
            return true;
        } else {
            return false;
        }
    }
    public function loadThreadByID($boardConf, $threadID) {
        $query = "SELECT * FROM threads WHERE boardID = '{$boardConf->boardID}' AND threadID = '{$threadID}'";
        $result = $this->db->query($query);
        if ($row = $result->fetch_assoc()) {
            return new threadClass($boardConf, $row['threadID'], $row['lastTimePosted'], $row['opPostID']);
        } else {
            return null;
        }
    }
    /* the side effects are masive with this one */
    public function loadThreadsByBoardID($boardConf) {
        $threads = [];
        $query = "SELECT * FROM threads WHERE boardID = $boardConf->boardID";
        $result = $this->db->query($query);
        while ($row = $result->fetch_assoc()) {
            $threads[] = new threadClass($boardConf, $row['threadID'], $row['lastTimePosted'], $row['opPostID']);
        }
        return $threads;
    }
    public function updateThread($boardConf, $thread) {
        $query = "UPDATE threads SET lastTimePosted = '{$thread->getLastBumpTime()}', opPostID = {$thread->getOPPostID()} WHERE boardID = '{$boardConf->boardID}' AND threadID = '{$thread->getThreadID()}'";
        return $this->db->query($query);
    }
    public function deleteThreadByID($boardConf, $threadID) {
        $query = "DELETE FROM threads WHERE boardID = '{$boardConf->boardID}' AND threadID = '{$threadID}'";
        return $this->db->query($query);
    }
}