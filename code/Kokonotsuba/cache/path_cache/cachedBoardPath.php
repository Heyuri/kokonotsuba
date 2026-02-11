<?php

namespace Kokonotsuba\cache\path_cache;

class cachedBoardPath {
    public $boardUID, $id, $board_path;

    public function getRowBoardUID() {
        return $this->boardUID ?? '';        
    }

    public function getRowID() {
        return $this->id ?? '';
    }

    public function getBoardPath() {
        return $this->board_path ?? '';
    }
}
