<?php

/**
 * *  mail_fetch/functions.php
 * *
 * *  Copyright (c) 1999-2003 The SquirrelMail Project Team
 * *  Licensed under the GNU GPL. For full terms see the file COPYING.
 * *
 * *  Functions for the mailfetch plugin.
 * *
 * *  Original code from LexZEUS <lexzeus@mifinca.com>
 * *  and josh@superfork.com (extracted from php manual)
 * *  Adapted for MailFetch by Philippe Mingo <mingo@rotedic.com>
 * *
 * *  $Id: functions.php,v 1.5 2002/12/31 12:49:37 kink Exp $
 * @param mixed $data
 *
 * @return string
 * @return string
 */
function hex2bin($data)
{
    /* Original code by josh@superfork.com */

    $len = mb_strlen($data);

    $newdata = '';

    for ($i = 0; $i < $len; $i += 2) {
        $newdata .= pack('C', hexdec(mb_substr($data, $i, 2)));
    }

    return $newdata;
}

function mf_keyED($txt)
{
    global $MF_TIT;

    if (!isset($MF_TIT)) {
        $MF_TIT = 'MailFetch Secure for SquirrelMail 1.x';
    }

    $encrypt_key = md5($MF_TIT);

    $ctr = 0;

    $tmp = '';

    for ($i = 0, $iMax = mb_strlen($txt); $i < $iMax; $i++) {
        if ($ctr == mb_strlen($encrypt_key)) {
            $ctr = 0;
        }

        $tmp .= mb_substr($txt, $i, 1) ^ mb_substr($encrypt_key, $ctr, 1);

        $ctr++;
    }

    return $tmp;
}

function encrypt($txt)
{
    mt_srand((float)microtime() * 1000000);

    $encrypt_key = md5(mt_rand(0, 32000));

    $ctr = 0;

    $tmp = '';

    for ($i = 0, $iMax = mb_strlen($txt); $i < $iMax; $i++) {
        if ($ctr == mb_strlen($encrypt_key)) {
            $ctr = 0;
        }

        $tmp .= mb_substr($encrypt_key, $ctr, 1) . (mb_substr($txt, $i, 1) ^ mb_substr($encrypt_key, $ctr, 1));

        $ctr++;
    }

    return bin2hex(mf_keyED($tmp));
}

function decrypt($txt)
{
    $txt = mf_keyED(hex2bin($txt));

    $tmp = '';

    for ($i = 0, $iMax = mb_strlen($txt); $i < $iMax; $i++) {
        $md5 = mb_substr($txt, $i, 1);

        $i++;

        $tmp .= (mb_substr($txt, $i, 1) ^ $md5);
    }

    return $tmp;
}
