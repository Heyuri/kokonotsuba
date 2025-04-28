<?php
/*
* Vichan board importing object for Kokonotsuba!
* This currently has no implementation nor working code, but is planned
*/

class vichanBoardImporter extends abstractBoardImporter {

    public function importThreadsToBoard(): array {
        // import threads from vichan to kokonotsuba
        return []; // return nothing, WIP
    }

    public function importPostsToThreads(array $mappedRestoToThreadUids): void {
        // import posts from vichan to kokonotsuba
	}
}