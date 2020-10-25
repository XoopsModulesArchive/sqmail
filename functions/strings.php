<?php

/**
 * strings.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This code provides various string manipulation functions that are
 * used by the rest of the Squirrelmail code.
 *
 * $Id: strings.php,v 1.183 2003/04/03 17:58:31 kink Exp $
 */
require_once SM_PATH . 'functions/global.php';

/**
 * SquirrelMail version number -- DO NOT CHANGE
 */
global $version;
$version = '1.4.0';

/**
 * SquirrelMail internal version number -- DO NOT CHANGE
 * $sm_internal_version = array (release, major, minor)
 */
global $SQM_INTERNAL_VERSION;
$SQM_INTERNAL_VERSION = [1, 4, 0];

/**
 * Wraps text at $wrap characters
 *
 * Has a problem with special HTML characters, so call this before
 * you do character translation.
 *
 * Specifically, &#039 comes up as 5 characters instead of 1.
 * This should not add newlines to the end of lines.
 * @param mixed $line
 * @param mixed $wrap
 */
function sqWordWrap(&$line, $wrap)
{
    global $languages, $squirrelmail_language;

    if (isset($languages[$squirrelmail_language]['XTRA_CODE'])
        && function_exists($languages[$squirrelmail_language]['XTRA_CODE'])) {
        if ('ASCII' != mb_detect_encoding($line, mb_detect_order(), true)) {
            $line = $languages[$squirrelmail_language]['XTRA_CODE']('wordwrap', $line, $wrap);

            return;
        }
    }

    preg_match("^([\t >]*)([^\t >].*)?$", $line, $regs);

    $beginning_spaces = $regs[1];

    if (isset($regs[2])) {
        $words = explode(' ', $regs[2]);
    } else {
        $words = '';
    }

    $i = 0;

    $line = $beginning_spaces;

    while ($i < count($words)) {
        /* Force one word to be on a line (minimum) */

        $line .= $words[$i];

        $line_len = mb_strlen($beginning_spaces) + mb_strlen($words[$i]) + 2;

        if (isset($words[$i + 1])) {
            $line_len += mb_strlen($words[$i + 1]);
        }

        $i++;

        /* Add more words (as long as they fit) */

        while ($line_len < $wrap && $i < count($words)) {
            $line .= ' ' . $words[$i];

            $i++;

            if (isset($words[$i])) {
                $line_len += mb_strlen($words[$i]) + 1;
            } else {
                $line_len += 1;
            }
        }

        /* Skip spaces if they are the first thing on a continued line */

        while (!isset($words[$i]) && $i < count($words)) {
            $i++;
        }

        /* Go to the next line if we have more to process */

        if ($i < count($words)) {
            $line .= "\n";
        }
    }
}

/**
 * Does the opposite of sqWordWrap()
 * @param mixed $body
 */
function sqUnWordWrap(&$body)
{
    global $squirrelmail_language;

    if ('ja_JP' == $squirrelmail_language) {
        return;
    }

    $lines = explode("\n", $body);

    $body = '';

    $PreviousSpaces = '';

    $cnt = count($lines);

    for ($i = 0; $i < $cnt; $i++) {
        preg_match("/^([\t >]*)([^\t >].*)?$/", $lines[$i], $regs);

        $CurrentSpaces = $regs[1];

        $CurrentRest = $regs[2] ?? '';

        if (0 == $i) {
            $PreviousSpaces = $CurrentSpaces;

            $body = $lines[$i];
        } elseif (($PreviousSpaces == $CurrentSpaces) /* Do the beginnings match */
                  && (mb_strlen($lines[$i - 1]) > 65)    /* Over 65 characters long */
                  && mb_strlen($CurrentRest)) {          /* and there's a line to continue with */
            $body .= ' ' . $CurrentRest;
        } else {
            $body .= "\n" . $lines[$i];

            $PreviousSpaces = $CurrentSpaces;
        }
    }

    $body .= "\n";
}

/**
 * If $haystack is a full mailbox name and $needle is the mailbox
 * separator character, returns the last part of the mailbox name.
 * @param mixed $haystack
 * @param mixed $needle
 * @return mixed|string
 * @return mixed|string
 */
function readShortMailboxName($haystack, $needle)
{
    if ('' == $needle) {
        $elem = $haystack;
    } else {
        $parts = explode($needle, $haystack);

        $elem = array_pop($parts);

        while ('' == $elem && count($parts)) {
            $elem = array_pop($parts);
        }
    }

    return ($elem);
}

/**
 * Returns an array of email addresses.
 * Be cautious of "user@host.com"
 * @param mixed $text
 * @return array|false|string[]
 * @return array|false|string[]
 */
function parseAddrs($text)
{
    if ('' == trim($text)) {
        return [];
    }

    $text = str_replace(' ', '', $text);

    $text = preg_replace('"[^"]*"', '', $text);

    $text = preg_replace('\\([^\\)]*\\)', '', $text);

    $text = str_replace(',', ';', $text);

    $array = explode(';', $text);

    for ($i = 0, $iMax = count($array); $i < $iMax; $i++) {
        $array[$i] = eregi_replace('^.*[<]', '', $array[$i]);

        $array[$i] = eregi_replace('[>].*$', '', $array[$i]);
    }

    return $array;
}

/**
 * Returns a line of comma separated email addresses from an array.
 * @param mixed $array
 * @return string
 * @return string
 */
function getLineOfAddrs($array)
{
    if (is_array($array)) {
        $to_line = implode(', ', $array);

        $to_line = preg_replace(', (, )+', ', ', $to_line);

        $to_line = trim(preg_replace('^, ', '', $to_line));

        if (',' == mb_substr($to_line, -1)) {
            $to_line = mb_substr($to_line, 0, -1);
        }
    } else {
        $to_line = '';
    }

    return ($to_line);
}

function php_self()
{
    if (sqgetGlobalVar('REQUEST_URI', $req_uri, SQ_SERVER) && !empty($req_uri)) {
        return $req_uri;
    }

    if (sqgetGlobalVar('PHP_SELF', $php_self, SQ_SERVER) && !empty($php_self)) {
        return $php_self;
    }

    return '';
}

/**
 * This determines the location to forward to relative to your server.
 * If this doesnt work correctly for you (although it should), you can
 * remove all this code except the last two lines, and change the header()
 * function to look something like this, customized to the location of
 * SquirrelMail on your server:
 *
 *   http://www.myhost.com/squirrelmail/src/login.php
 */
function get_location()
{
    global $imap_server_type;

    /* Get the path, handle virtual directories */

    $path = mb_substr(php_self(), 0, mb_strrpos(php_self(), '/'));

    if (sqgetGlobalVar('sq_base_url', $full_url, SQ_SESSION)) {
        return $full_url . $path;
    }

    /* Check if this is a HTTPS or regular HTTP request. */

    $proto = 'http://';

    /*
     * If you have 'SSLOptions +StdEnvVars' in your apache config
     *     OR if you have HTTPS=on in your HTTP_SERVER_VARS
     *     OR if you are on port 443
     */

    $getEnvVar = getenv('HTTPS');

    if ((isset($getEnvVar) && !strcasecmp($getEnvVar, 'on'))
        || (sqgetGlobalVar('HTTPS', $https_on, SQ_SERVER) && !strcasecmp($https_on, 'on'))
        || (sqgetGlobalVar('SERVER_PORT', $server_port, SQ_SERVER) && 443 == $server_port)) {
        $proto = 'https://';
    }

    /* Get the hostname from the Host header or server config. */

    if (!sqgetGlobalVar('HTTP_HOST', $host, SQ_SERVER) || empty($host)) {
        if (!sqgetGlobalVar('SERVER_NAME', $host, SQ_SERVER) || empty($host)) {
            $host = '';
        }
    }

    $port = '';

    if (!mb_strstr($host, ':')) {
        if (sqgetGlobalVar('SERVER_PORT', $server_port, SQ_SERVER)) {
            if ((80 != $server_port && 'http://' == $proto)
                || (443 != $server_port && 'https://' == $proto)) {
                $port = sprintf(':%d', $server_port);
            }
        }
    }

    /* this is a workaround for the weird macosx caching that
       causes Apache to return 16080 as the port number, which causes
       SM to bail */

    if ('macosx' == $imap_server_type && ':16080' == $port) {
        $port = '';
    }

    /* Fallback is to omit the server name and use a relative */

    /* URI, although this is not RFC 2616 compliant.          */

    $full_url = ($host ? $proto . $host . $port : '');

    sqsession_register($full_url, 'sq_base_url');

    return $full_url . $path;
}

/**
 * These functions are used to encrypt the passowrd before it is
 * stored in a cookie.
 * @param mixed $string
 * @param mixed $epad
 * @return string
 * @return string
 */
function OneTimePadEncrypt($string, $epad)
{
    $pad = base64_decode($epad, true);

    $encrypted = '';

    for ($i = 0, $iMax = mb_strlen($string); $i < $iMax; $i++) {
        $encrypted .= chr(ord($string[$i]) ^ ord($pad[$i]));
    }

    return base64_encode($encrypted);
}

function OneTimePadDecrypt($string, $epad)
{
    $pad = base64_decode($epad, true);

    $encrypted = base64_decode($string, true);

    $decrypted = '';

    for ($i = 0, $iMax = mb_strlen($encrypted); $i < $iMax; $i++) {
        $decrypted .= chr(ord($encrypted[$i]) ^ ord($pad[$i]));
    }

    return $decrypted;
}

/**
 * Randomize the mt_rand() function.  Toss this in strings or integers
 * and it will seed the generator appropriately. With strings, it is
 * better to get them long. Use md5() to lengthen smaller strings.
 * @param mixed $Val
 */
function sq_mt_seed($Val)
{
    /* if mt_getrandmax() does not return a 2^n - 1 number,
       this might not work well.  This uses $Max as a bitmask. */

    $Max = mt_getrandmax();

    if (!is_int($Val)) {
        $Val = crc32($Val);
    }

    if ($Val < 0) {
        $Val *= -1;
    }

    if ($Val = 0) {
        return;
    }

    // mt_srand(($Val ^ mt_rand(0, $Max)) & $Max);
}

/**
 * This function initializes the random number generator fairly well.
 * It also only initializes it once, so you don't accidentally get
 * the same 'random' numbers twice in one session.
 */
function sq_mt_randomize()
{
    static $randomized;

    if ($randomized) {
        return;
    }

    /* Global. */

    sqgetGlobalVar('REMOTE_PORT', $remote_port, SQ_SERVER);

    sqgetGlobalVar('REMOTE_ADDR', $remote_addr, SQ_SERVER);

    sq_mt_seed((int)((float)microtime() * 1000000));

    sq_mt_seed(md5($remote_port . $remote_addr . getmypid()));

    /* getrusage */

    if (function_exists('getrusage')) {
        /* Avoid warnings with Win32 */

        $dat = @getrusage();

        if (isset($dat) && is_array($dat)) {
            $Str = '';

            foreach ($dat as $k => $v) {
                $Str .= $k . $v;
            }

            sq_mt_seed(md5($Str));
        }
    }

    if (sqgetGlobalVar('UNIQUE_ID', $unique_id, SQ_SERVER)) {
        sq_mt_seed(md5($unique_id));
    }

    $randomized = 1;
}

function OneTimePadCreate($length = 100)
{
    sq_mt_randomize();

    $pad = '';

    for ($i = 0; $i < $length; $i++) {
        $pad .= chr(mt_rand(0, 255));
    }

    return base64_encode($pad);
}

/**
 *  Returns a string showing the size of the message/attachment.
 * @param mixed $bytes
 * @return string
 * @return string
 */
function show_readable_size($bytes)
{
    $bytes /= 1024;

    $type = 'k';

    if ($bytes / 1024 > 1) {
        $bytes /= 1024;

        $type = 'M';
    }

    if ($bytes < 10) {
        $bytes *= 10;

        $bytes = (int) $bytes;

        $bytes /= 10;
    } else {
        $bytes = (int) $bytes;
    }

    return $bytes . '<small>&nbsp;' . $type . '</small>';
}

/**
 * Generates a random string from the caracter set you pass in
 *
 * Flags:
 *   1 = add lowercase a-z to $chars
 *   2 = add uppercase A-Z to $chars
 *   4 = add numbers 0-9 to $chars
 * @param mixed $size
 * @param mixed $chars
 * @param mixed $flags
 * @return string
 * @return string
 */
function GenerateRandomString($size, $chars, $flags = 0)
{
    if ($flags & 0x1) {
        $chars .= 'abcdefghijklmnopqrstuvwxyz';
    }

    if ($flags & 0x2) {
        $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    if ($flags & 0x4) {
        $chars .= '0123456789';
    }

    if (($size < 1) || (mb_strlen($chars) < 1)) {
        return '';
    }

    sq_mt_randomize(); /* Initialize the random number generator */

    $String = '';

    $j = mb_strlen($chars) - 1;

    while (mb_strlen($String) < $size) {
        $String .= $chars[mt_rand(0, $j)];
    }

    return $String;
}

function quoteimap($str)
{
    return preg_replace('(["\\])', '\\\\1', $str);
}

/**
 * Trims every element in the array
 * @param mixed $array
 */
function TrimArray(&$array)
{
    foreach ($array as $k => $v) {
        global $$k;

        if (is_array($$k)) {
            foreach ($$k as $k2 => $v2) {
                $$k[$k2] = mb_substr($v2, 1);
            }
        } else {
            $$k = mb_substr($v, 1);
        }

        /* Re-assign back to array. */

        $array[$k] = $$k;
    }
}

/**
 * Removes slashes from every element in the array
 * @param mixed $array
 */
function RemoveSlashes(&$array)
{
    foreach ($array as $k => $v) {
        global $$k;

        if (is_array($$k)) {
            foreach ($$k as $k2 => $v2) {
                $newArray[stripslashes($k2)] = stripslashes($v2);
            }

            $$k = $newArray;
        } else {
            $$k = stripslashes($v);
        }

        /* Re-assign back to the array. */

        $array[$k] = $$k;
    }
}

$PHP_SELF = php_self();
