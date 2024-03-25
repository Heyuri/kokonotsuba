<?php

interface BoardRepositoryInterface {
    public function updateBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board);
}

interface ThreadRepositoryInterface {
    public function createThread($boardConf, $thread);
    public function loadThreadByID($boardConf, $threadID);
    public function loadThreads($boardConf);
    public function updateThread($boardConf, $thread);
    public function deleteThreadByID($boardCon, $threadID);
}

interface PostDataRepositoryInterface {
    public function createPost($boardConf, $post);
    public function loadPostByID($boardConf, $postID);
    public function updatePost($boardConf, $post);
    public function deletePostByID($boardConf, $postID);
}

interface FileRepositoryInterface {
    public function saveBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board);
}