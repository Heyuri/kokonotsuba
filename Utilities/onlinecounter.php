<?php
    /*
    Online counter for kokonotsuba
    Modified from http://php.loglog.jp/
    */
    define("USR_LST", "../dat/users.dat");
    define("TIMEOUT", 600); // Update every 10 minutes. Modify $data below accordingly if you change this

    $usr_arr = file(USR_LST);
    touch(USR_LST);

    $fp = fopen(USR_LST, "w");
    $now = time();
    $addr = $_SERVER['REMOTE_ADDR'];

    foreach ($usr_arr as $line) {
        $line = trim($line);
        if (!empty($line)) {
            list($ip_addr, $stamp) = explode("|", $line);
            // Ensure $stamp is a valid numeric value
            if (is_numeric($stamp) && ($now - $stamp) < TIMEOUT && $ip_addr != $addr) {
                fputs($fp, $ip_addr . '|' . $stamp . "\n");
            }
        }
    }
    fputs($fp, $addr . '|' . $now . "\n");
    fclose($fp);

    $count = count($usr_arr);
    $data = '<div style="background-color: #000000; color: #00FF00;"><b>' . $count . '</b> unique user' . ($count > 1 ? 's' : '') . ' in the last 10 minutes (including lurkers)</div>';
    echo $data;
?>
