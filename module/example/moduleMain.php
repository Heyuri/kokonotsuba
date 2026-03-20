<?php

/* to use this module - add it to ModuleList in globalBoardConfig.php like so:
 * 'example' => true,
*/
// the section after 'Modules' (in this case 'example') must be the same as the directory name.
namespace Kokonotsuba\Modules\example;

// make sure to `use` relevant objects from their respective namespaces
use Kokonotsuba\module_classes\abstractModuleMain;

class moduleMain extends abstractModuleMain {
    // you can include some optional properties here.
    // typically you set their values in initialize()
    private string $property = 'heyuri!';
    private string $thisValueCanBeSetInInitialize;
	
	// Names
	public function getName(): string {
		return 'Kokonotsuba Example Module!';
	}

	public function getVersion(): string {
		return '1931';
	}

	public function initialize(): void {
        $this->thisValueCanBeSetInInitialize = 'This value is set in initialize() and can be used in other methods!';
	}

    private function exampleMethod(): void {
        // you can also call values from the moduleContext - which is dependencies that the module can use
        // you can find the list of them in moduleContext.php

        // for example - lets call the post repository to get a post by its id
        $postUid = 123; // example post id
        $postData = $this->moduleContext->postRepository->getPostByUid($postUid);

        // you can fetch the current board from the module context
        $currentBoard = $this->moduleContext->board;

        // you can also fetch all board objects from the global constant
        $allBoards = GLOBAL_BOARD_ARRAY;
    }

    /*
    *
    * This is the dedicated page for the module - it only runs if you access the module through its page URL
    * You can use this for displaying pages for the module or handling form submissions, requests, etc. that are specific to the module
    *
    */
	public function ModulePage(): void {
        echo '<h1>Welcome to the example module page!</h1>';
        echo '<p>Kokonotsuba is a futaba-styled bulletin board system in vanilla PHP.</p>';
        echo '<p>This was a value set in initialize(): ' . $this->thisValueCanBeSetInInitialize . '</p>';
	}
}