<?php

interface BoardRepositoryInterface {
    public function saveBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board);
}

interface ThreadRepositoryInterface {
    public function createThread($boardConf, $thread);
    public function loadThreadByID($boardConf, $threadID);
    public function loadThreadsByBoardID($boardConf);
    public function updateThread($boardConf, $thread);
    public function deleteThreadByID($boardCon, $threadID);
}


interface PostDataRepositoryInterface {
    public function saveBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board);
}

interface FileRepositoryInterface {
    public function saveBoard($board);
    public function loadBoards();
    public function loadBoardByID($boardID);
    public function deleteBoardByID($boardID);
    public function createBoard($board);
}