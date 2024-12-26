# Kokonotsuba

## Required stack
Kokonotsuba is designed and tested on the following stack, and isn't guaranteed to work on any other stack.<br>
OS: debian 10\~12<br>
Web server: nginx<br>
DB: mariadb<br>
PHP: PHP7.2\~PHP8.3

## Dependencies
- mbstring
- pdo
- gd
- bcmath

## Installation
- Clone the repository into your web directory
- Ensure that Kokonotsuba-related files have the necessary ownsership and permissions
- Open globalconfig.php and globalBoardConfig.php in a text editor and configure it to your needs
- Go to install.php and fill out the required details
- In the url bar, enter the `board identifier` of the newly created board at the end of it. E.g if you set it as `b`, https://example.net/b/koko.php
- If there were no errors, you should have a fully working Kokonotsuba instance!
