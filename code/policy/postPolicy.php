<?php

use Kokonotsuba\Root\Constants\userRole;

class postPolicy {
    public function __construct(
        private array $authLevels,
        private userRole $roleLevel
    ) {}


    public function authenticatePostDeletion(string $passwordHash, string $suppliedPassword): bool {
        // if the user's role is authorized to delete a post (regardless if they have the correct password)
        // then the user is authenticated for deleting the post - return true
        if($this->modCanDeletePost()) {
            return true;
        }

        // verify if the password supplied by the user is the correct password (provided by the hash)
        // then return true, as the user is authorized to delete the post
        else if(password_verify($suppliedPassword, $passwordHash)) {
            return true;
        }

        // no authentication methods were tripped - failed, return false
        else {
            return false;
        }
    }

    private function modCanDeletePost(): bool {
        // minimum role required for deleting posts
        $canDeletePost = $this->authLevels['CAN_DELETE_POST'];

        // get if the user's role is at least the required role
        $isAuthorized = $this->roleLevel->isAtLeast($canDeletePost);

        // return result
        return $isAuthorized;
    }

}