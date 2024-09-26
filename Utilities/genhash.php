<?php
    $hash = '';
    $str = filter_input(INPUT_POST, 'str');
    $salt = filter_input(INPUT_POST, 'salt');
    if (!empty($str) && !empty($salt)) {
        $hash = crypt($str, $salt);
        if (array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            exit($hash);
        }
    }
    ?><!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>Generate a password hash</title>
        </head>
        <body>
            <form method="post" id="generator">
                <div>
                    <input type="password" id="str" name="str" placeholder="Password"  required>
                </div>
                <div>
                    <select id="type">
                        <option>Select hash type</option>
                        <option>CRYPT_DES</option>
                        <option>CRYPT_MD5</option>
                        <option>CRYPT_SHA256</option>
                        <option>CRYPT_SHA512</option>
                        <option>CRYPT_BLOWFISH</option>
                    </select>
                    <input type="text" id="salt" name="salt" placeholder="Salt" required>
                </div>
                <div>
                    <!--<input type="submit" value="Generate" id="submit">-->
                    <button id="submit">Generate</button>
                    <button id="reset">Regenerate salt</button>
                </div>
                <div>
                    <p>Your ADMIN_HASH:</p>
                    <span id="myhash"><?php echo $hash; ?></span>
                </div>
            </form>

<hr><small><ol>
  <li>Select a random hash type from the dropdown menu</li>
  <li>If JavaScript is enabled, the salt field will be auto-filled based on your selected hash type. You don't need to touch it</li>
  <li>Enter your password and click "Generate" to create the hash. Provide the generated hash to the administrator privately</li>
</ol></small>
<noscript>
    <b>It seems you don't have javascript enabled.</b><br>
    Please manually enter a salt value according to the selected hash type:<br>
    <ul>
        <li><b>CRYPT_DES:</b> 2 characters long.</li>
        <li><b>CRYPT_MD5:</b> Should start with <code>$1$</code> followed by 12 random alphanumeric characters.</li>
        <li><b>CRYPT_SHA256:</b> Should start with <code>$5$</code> followed by 16 random alphanumeric characters.</li>
        <li><b>CRYPT_SHA512:</b> Should start with <code>$6$</code> followed by 16 random alphanumeric characters.</li>
        <li><b>CRYPT_BLOWFISH:</b> Should start with <code>$2y$10$</code> followed by 22 random alphanumeric characters.</li>
    </ul>
    Once you have entered the salt and your password, click "Generate" to create the hash.
</noscript>
            <script>
                function randomString(length) {
                    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                    let str = '';
                    for (let i = 0; i < length; i++) {
                        str += characters[Math.floor(Math.random() * characters.length)];
                    }
                    return str;
                }
                function genSalt(type) {
                    let salt = '';
                    switch (type) {
                        case "CRYPT_DES":
                            salt = randomString(2);
                            break;
                        case "CRYPT_MD5":
                            salt = '$1$' + randomString(12);
                            break;
                        case "CRYPT_SHA256":
                            salt = '$5$' + randomString(16);
                            break;
                        case "CRYPT_SHA512":
                            salt = '$6$' + randomString(16);
                            break;
                        case "CRYPT_BLOWFISH":
                            salt = '$2y$10$' + randomString(22);
                            break;
                    }
                    return salt;
                }
                function updateSalt() {
                    const salt = document.getElementById('salt');
                    const type = document.getElementById('type');
                    salt.value = genSalt(type.value);
                }
                function submit(event) {
                    const str = document.getElementById('str').value;
                    const salt = document.getElementById('salt').value;
                    if (!str || !salt) return;
     
                    event.preventDefault();
                    const form = document.getElementById('generator');
                    fetch(window.location.href, {
                        method: 'post',
                        headers: {"X-Requested-With": 'XMLHttpRequest'},
                        body: new FormData(form)
                    }).then(res => res.text()).then(text => {
                        const myhash = document.getElementById('myhash');
                        myhash.innerHTML = text;
                    });
                }
     
                document.getElementById('type').addEventListener('change', updateSalt);
                document.getElementById('submit').addEventListener('click', submit);
                document.getElementById('reset').addEventListener('click', event => {
                    event.preventDefault();
                    updateSalt();
                });
            </script>
        </body>
    </html>
