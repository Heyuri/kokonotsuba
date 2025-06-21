# Kokonotsuba

## About Kokonotsuba
* https://kokonotsuba.github.io/

## Detailed installation instructions
* https://kokonotsuba.github.io/setup.html

## Required stack
Kokonotsuba is designed and tested on the following stack, and isn't guaranteed to work on any other stack.
- OS: Debian 10\~12
- Web server: nginx (but seems to work fine on Apache)
- DB: MariaDB
- PHP: PHP8\~PHP8.3

If you are going to suggest pull requests, please make sure the change would work on the above stack first.

## Dependencies
- mbstring
- pdo
- gd
- bcmath
- ffmpeg

## Basic installation instructions
- Clone the repository into your web directory
- Ensure that Kokonotsuba-related files have the necessary ownsership and permissions
- Open globalconfig.php and globalBoardConfig.php in a text editor and configure it to your needs
- Go to install.php and fill out the required details
- In the url bar, enter the `board identifier` of the newly created board at the end of it. E.g if you set it as `b`, https://example.net/b/koko.php
- If there were no errors, you should have a fully working Kokonotsuba instance!
