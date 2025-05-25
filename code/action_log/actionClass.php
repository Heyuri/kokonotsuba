<?php
//encapsulate action from db
class loggedActionEntry {
	public readonly string $time_added;
	public readonly string $name;
	public readonly string $role;
	public readonly string $log_action;
	public readonly int $id;
	public readonly string $ip_address;
	public readonly int $board_uid;
	public readonly string $board_title;

	public function getTimeAdded(): string { return $this->time_added; }
	public function getName(): string { return $this->name; }
	public function getRole(): string { return $this->role; }
	public function getLogAction(): string { return $this->log_action; }
	public function getId(): int { return $this->id; }
	public function getIpAddress(): string { return $this->ip_address; }
	public function getBoardUID(): int { return $this->board_uid; }
	public function getBoardTitle(): string { return $this->board_title; }
}