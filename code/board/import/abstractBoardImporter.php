<?php
/*
* Board importing abstract class for Kokonotsuba!
* Can be used for importing different databases
*/

abstract class abstractBoardImporter {
    protected DatabaseConnection $databaseConnection;
    protected board $board;

	public function __construct(DatabaseConnection $databaseConnection, board $board) {
        $this->databaseConnection = $databaseConnection;
        $this->board = $board;
    }

    abstract public function importThreadsToBoard(): array;
    abstract public function importRepliesToThreads(array $mappedRestoToThreadUids): void;
}