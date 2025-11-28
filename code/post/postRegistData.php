<?php

class postRegistData {
    public function __construct(
        private int $no,
        private string $poster_hash,
        private string $threadUIDFromUrl,
        private int $is_op,
        private string $category,
        private string $pwd,
        private string $now,
        private string $name,
        private string $tripcode,
        private string $secure_tripcode,
        private string $capcode,
        private string $email,
        private string $sub,
        private string $com,
        private string $host,
        private bool $age,
        private string $status,
        private int $post_position = 0
    ) {}

    // Getters
    public function getNo(): int { return $this->no; }
    public function getPosterHash(): string { return $this->poster_hash; }
    public function getThreadUIDFromUrl(): string { return $this->threadUIDFromUrl; }
    public function getIsOp(): int { return $this->is_op; }
    public function getCategory(): string { return $this->category; }
    public function getPwd(): string { return $this->pwd; }
    public function getNow(): string { return $this->now; }
    public function getName(): string { return $this->name; }
    public function getTripcode(): string { return $this->tripcode; }
    public function getSecureTripcode(): string { return $this->secure_tripcode; }
    public function getCapcode(): string { return $this->capcode; }
    public function getEmail(): string { return $this->email; }
    public function getSub(): string { return $this->sub; }
    public function getCom(): string { return $this->com; }
    public function getHost(): string { return $this->host; }
    public function getAgeru(): bool { return $this->age; }
    public function getStatus(): string { return $this->status; }
    public function getPostPosition(): int { return $this->post_position; }

    // Setters
    public function setPostPosition(int $post_position): void {
        $this->post_position = $post_position;
    }

    public function setThreadUIDFromUrl(string $threadUIDFromUrl): void {
        $this->threadUIDFromUrl = $threadUIDFromUrl;
    }

    // Convert DTO to SQL parameter array
    public function toParams(int $boardUID, string $root, ?int $placeholderIndex = null): array {
        // Initialize an empty array to hold the parameters
        $params = [
            ':no' => $this->no,
            ':poster_hash' => $this->poster_hash,
            ':boardUID' => $boardUID,
            ':thread_uid' => $this->threadUIDFromUrl,
            ':post_position' => $this->post_position,
            ':is_op' => (int)$this->is_op,
            ':root' => $root,
            ':category' => $this->category,
            ':pwd' => $this->pwd,
            ':now' => $this->now,
            ':name' => $this->name,
            ':tripcode' => $this->tripcode,
            ':secure_tripcode' => $this->secure_tripcode,
            ':capcode' => $this->capcode,
            ':email' => $this->email,
            ':sub' => $this->sub,
            ':com' => $this->com,
            ':host' => $this->host,
            ':status' => $this->status
        ];

        // If $placeholderIndex is not null, append it to each key
        if ($placeholderIndex !== null) {
            $paramsWithIndex = [];
            foreach ($params as $key => $value) {
                $paramsWithIndex["{$key}_{$placeholderIndex}"] = $value;
            }
            return $paramsWithIndex;  // Return the updated parameters with indexed placeholders
        }

        return $params;  // Return the original params if $placeholderIndex is null
    }

}
