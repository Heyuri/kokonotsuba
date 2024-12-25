<?php
class boardStoredFile
{
	private $filename, $unixFileName, $extention;
	private $board;

	public function __construct($unixFileName, $extention, $board) {
		$this->unixFileName = $unixFileName;
        $this->extention = $extention;

        $this->filename = $unixFileName.$extention;
		$this->board = $board;
	}

	public function getFilename() {
		return $this->filename ?? '';
	}

    public function getUnixFileName() {
        return $this->unixFileName ?? '';
    }

    public function getExtention() {
        return $this->extention ?? '';
    }

	public function getBoard() {
		return $this->board ?? 0;
	}

}