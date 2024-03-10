<?php
// board will work as a virutal instance. thats why each call must have boardID it came from. 
// deleteing a board is like deleting the insance.
// overboard dose not care about virtual instance when getting threads.

interface repoInterface {
    public function getBoardByID($boardID); // get the board from its ID
    public function updateBoard($board); // set the board from its ID
    public function getBoards(); // get an array of all boards.
    public function addBoard($board); // add new board to board table
    public function removeBoard($boardID); // remove board (this would delete all the posts and files from the db)

    public function getThreadsFromBoard($boardID);
    public function getNThreadsFromBoard($n, $boardID); // most recently bumped first
    public function getThreads();
    public function getNThreads($n);
    public function getThreadByID($threadID, $boardID);
    public function addThreadToBoardByID($thread, $boardID); // make sure thread a OP post
    public function removeThreadFromBoardByID($threadID, $boardID); // cascades and delets all posts included
    public function updateThread($thread, $boardID);

    public function getPostbyID($postID, $boardID); 
    public function getPostsFromThread($threadID, $boardID);
    public function addPostToThread($post, $threadID, $boardID);
    public function updatePost($post, $boardID);

    public function addFile($file, $postID, $boardID);
    public function removeFile($fileNameOnDisk, $boardID);
    public function updateFile($file, $boardID);
}
