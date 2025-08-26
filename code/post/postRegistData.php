<?php

class postRegistData {
	private int $no;
	private string $poster_hash;
	private string $threadUIDFromUrl;
	private bool $is_op;
	private string $md5chksum, $category, $tim, $fname, $ext, $imgsize;
	private int $imgw, $imgh, $tw, $th;
	private string $pwd, $now, $name, $tripcode, $secure_tripcode, $capcode, $email, $sub, $com, $host;
	private bool $age;
	private string $status;
	private int $post_position;

	public function __construct(
		int $no, string $poster_hash, string $threadUIDFromUrl, bool $is_op, string $md5chksum, string $category,
		string $tim, string $fname, string $ext, int $imgw, int $imgh, string $imgsize,
		int $tw, int $th, string $pwd, string $now, string $name, string $tripcode,
		string $secure_tripcode, string $capcode, string $email, string $sub, string $com,
		string $host, bool $age, string $status
	) {
		$this->no = $no;
		$this->poster_hash = $poster_hash;
		$this->threadUIDFromUrl = $threadUIDFromUrl;
		$this->is_op = $is_op;
		$this->md5chksum = $md5chksum;
		$this->category = $category;
		$this->tim = $tim;
		$this->fname = $fname;
		$this->ext = $ext;
		$this->imgw = $imgw;
		$this->imgh = $imgh;
		$this->imgsize = $imgsize;
		$this->tw = $tw;
		$this->th = $th;
		$this->pwd = $pwd;
		$this->now = $now;
		$this->name = $name;
		$this->tripcode = $tripcode;
		$this->secure_tripcode = $secure_tripcode;
		$this->capcode = $capcode;
		$this->email = $email;
		$this->sub = $sub;
		$this->com = $com;
		$this->host = $host;
		$this->age = $age;
		$this->status = $status;
		$this->post_position = 0;
	}

	// Getters
	public function getNo(): int { return $this->no; }
	public function getThreadUIDFromUrl(): string { return $this->threadUIDFromUrl; }
	public function getIsOp(): bool { return $this->is_op; }
	public function getMd5chksum(): string { return $this->md5chksum; }
	public function getCategory(): string { return $this->category; }
	public function getTim(): string { return $this->tim; }
	public function getFname(): string { return $this->fname; }
	public function getExt(): string { return $this->ext; }
	public function getImgw(): int { return $this->imgw; }
	public function getImgh(): int { return $this->imgh; }
	public function getImgsize(): string { return $this->imgsize; }
	public function getTw(): int { return $this->tw; }
	public function getTh(): int { return $this->th; }
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

	// Setter
	public function setPostPosition(int $post_position): void {
		$this->post_position = $post_position;
	}

	public function setThreadUIDFromUrl(string $threadUIDFromUrl): void {
		$this->threadUIDFromUrl = $threadUIDFromUrl;
	}

	// Convert DTO to SQL parameter array
	public function toParams(int $boardUID, string $root, int $time, bool $isThread): array {
		return [
			':no' => $this->no,
			'poster_hash' => $this->poster_hash,
			':boardUID' => $boardUID,
			':thread_uid' => $this->threadUIDFromUrl,
			':post_position' => $this->post_position,
			':is_op' => (int)$isThread,
			':root' => $root,
			':time' => $time,
			':md5chksum' => $this->md5chksum,
			':category' => $this->category,
			':tim' => $this->tim,
			':fname' => $this->fname,
			':ext' => $this->ext,
			':imgw' => $this->imgw,
			':imgh' => $this->imgh,
			':imgsize' => $this->imgsize,
			':tw' => $this->tw,
			':th' => $this->th,
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
	}
}
