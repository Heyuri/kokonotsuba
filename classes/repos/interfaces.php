<?php

interface BoardRepositoryInterface {
    public function updateBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board, callable $callBackErr);
}

interface ThreadRepositoryInterface {
    public function createThread($boardConf, $thread, $post, callable $callBackErr);
    public function loadThreadByID($boardConf, $threadID);
    public function loadThreads($boardConf);
    public function updateThread($boardConf, $thread);
    public function deleteThreadByID($boardCon, $threadID);
}

interface PostDataRepositoryInterface {
    public function createPost($boardConf, $post, callable $callBackErr);
    public function loadPostByID($boardConf, $postID);
    public function loadPosts($boardConf);
    public function loadPostsFromThreadID($boardConf, $threadID);
    public function setPostID($boardConf, $post, $newPostID);
    public function updatePost($boardConf, $post);
    public function deletePostByID($boardConf, $postID);
}

interface FileRepositoryInterface {
    public function saveBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board, $callBackErr);
}