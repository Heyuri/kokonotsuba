<?php
//encapsulate action from db
class loggedActionEntry {
	public $time_added, $name, $role, $log_action, $id, $ip_address, $board_uid, $board_title;

	public function getTimeAdded() { return $this->time_added; }
	public function getName() { return $this->name; }
	public function getRole() { return $this->role; }
	public function getLogAction() { return $this->log_action; }
	public function getId() { return $this->id; }
	public function getIpAddress() { return $this->ip_address; }
	public function getBoardUID() { return $this->board_uid; }
	public function getBoardTitle() { return $this->board_title; }
}

