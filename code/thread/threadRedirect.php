<?php
class threadRedirect {
    public $original_board_uid, $new_board_uid, $thread_uid, $post_op_number;

    public function getOriginalBoardUID() { return $this->original_board_uid ?? ''; }
    public function getNewBoardUID() { return $this->new_board_uid ?? ''; }
    public function getThreadUID() { return $this->thread_uid ?? ''; }
    public function getPostOPNumber() { return $this->post_op_number ?? '';}
}