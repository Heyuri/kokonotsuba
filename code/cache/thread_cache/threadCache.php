<?php
/*
* Thread cache object for Kokonotsuba!
* This object is initialized by the singleton with PDO::FETCH_CLASS
*/

class threadCache {
    public readonly string $cache_id;
    public readonly string $thread_uid;
    public readonly int $board_uid;
    public readonly string $thread_html;
    public readonly string $thread_index_html;

    // Getter for $cache_id
    public function getCacheId(): string {
        return $this->cache_id;
    }

    // Getter for $thread_uid
    public function getThreadUid(): string {
        return $this->thread_uid;
    }

    // Getter for $board_uid
    public function getBoardUid(): int {
        return $this->board_uid;
    }

    // Getter for $thread_html
    public function getThreadHtml(): string {
        return $this->thread_html;
    }

    // Getter for $thread_index_html
    public function getThreadIndexHtml(): string {
        return $this->thread_index_html;
    }
}
