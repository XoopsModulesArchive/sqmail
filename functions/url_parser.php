<?php

/**
 * url_parser.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This code provides various string manipulation functions that are
 * used by the rest of the Squirrelmail code.
 *
 * $Id: url_parser.php,v 1.44 2002/12/31 12:49:32 kink Exp $
 * @param mixed $in
 * @param mixed $replace
 * @param mixed $start
 * @param mixed $end
 */
function replaceBlock(&$in, $replace, $start, $end)
{
    $begin = mb_substr($in, 0, $start);

    $end = mb_substr($in, $end, mb_strlen($in) - $end);

    $in = $begin . $replace . $end;
}

/* Having this defined in just one spot could help when changes need
 * to be made to the pattern
 * Make sure that the expression is evaluated case insensitively
 *
 * Here's pretty sophisticated IP matching:
 * $IPMatch = '(2[0-5][0-9]|1?[0-9]{1,2})';
 * $IPMatch = '\[?' . $IPMatch . '(\.' . $IPMatch . '){3}\]?';
 */
/* Here's enough: */
global $IP_RegExp_Match, $Host_RegExp_Match, $Email_RegExp_Match;
$IP_RegExp_Match = '\\[?[0-9]{1,3}(\\.[0-9]{1,3}){3}\\]?';
$Host_RegExp_Match = '(' . $IP_RegExp_Match . '|[0-9a-z]([-.]?[0-9a-z])*\\.[a-z][a-z]+)';
$Email_RegExp_Match = '[0-9a-z]([-_.+]?[0-9a-z])*(%' . $Host_RegExp_Match . ')?@' . $Host_RegExp_Match;

function parseEmail(&$body)
{
    global $color, $Email_RegExp_Match, $compose_new_win;

    $sbody = $body;

    $addresses = [];

    /* Find all the email addresses in the body */

    while (eregi($Email_RegExp_Match, $sbody, $regs)) {
        $addresses[$regs[0]] = $regs[0];

        $start = mb_strpos($sbody, $regs[0]) + mb_strlen($regs[0]);

        $sbody = mb_substr($sbody, $start);
    }

    /* Replace each email address with a compose URL */

    foreach ($addresses as $email) {
        $comp_uri = '../src/compose.php?send_to=' . urlencode($email);

        if ('1' == $compose_new_win) {
            $comp_uri = 'javascript:void(0)" onClick="comp_in_new(' . "'$comp_uri'" . ')';
        }

        $comp_uri = '<a href="' . $comp_uri . '">' . $email . '</a>';

        $body = str_replace($email, $comp_uri, $body);
    }

    /* Return number of unique addresses found */

    return count($addresses);
}

/* We don't want to re-initialize this stuff for every line.  Save work
 * and just do it once here.
 */
global $url_parser_url_tokens;
$url_parser_url_tokens = [
    'http://',
    'https://',
    'ftp://',
    'telnet:',  // Special case -- doesn't need the slashes
    'gopher://',
    'news://',
];

global $url_parser_poss_ends;
$url_parser_poss_ends = [
    ' ',
    "\n",
    "\r",
    '<',
    '>',
    ".\r",
    ".\n",
    '.&nbsp;',
    '&nbsp;',
    ')',
    '(',
    '&quot;',
    '&lt;',
    '&gt;',
    '.<',
    ']',
    '[',
    '{',
    '}',
    "\240",
    ', ',
    '. ',
    ",\n",
    ",\r",
];

function parseUrl(&$body)
{
    global $url_parser_poss_ends, $url_parser_url_tokens;

    $start = 0;

    $blength = mb_strlen($body);

    while ($start != $blength) {
        $target_token = '';

        $target_pos = $blength;

        /* Find the first token to replace */

        foreach ($url_parser_url_tokens as $the_token) {
            $pos = mb_strpos(mb_strtolower($body), $the_token, $start);

            if (is_int($pos) && $pos < $blength) {
                $target_pos = $pos;

                $target_token = $the_token;
            }
        }

        /* Look for email addresses between $start and $target_pos */

        $check_str = mb_substr($body, $start, $target_pos - $start);

        if (parseEmail($check_str)) {
            replaceBlock($body, $check_str, $start, $target_pos);

            $blength = mb_strlen($body);

            $target_pos = mb_strlen($check_str) + $start;
        }

        /* If there was a token to replace, replace it */

        if ('' != $target_token) {
            /* Find the end of the URL */

            $end = $blength;

            foreach ($url_parser_poss_ends as $val) {
                $enda = mb_strpos($body, $val, $target_pos);

                if (is_int($enda) && $enda < $end) {
                    $end = $enda;
                }
            }

            /* Extract URL */

            $url = mb_substr($body, $target_pos, $end - $target_pos);

            /* Needed since lines are not passed with \n or \r */

            while (preg_match("[,\.]$", $url)) {
                $url = mb_substr($url, 0, -1);

                $end--;
            }

            /* Replace URL with HyperLinked Url, requires 1 char in link */

            if ('' != $url && $url != $target_token) {
                $url_str = "<a href=\"$url\" target=\"_blank\">$url</a>";

                replaceBlock($body, $url_str, $target_pos, $end);

                $target_pos += mb_strlen($url_str);
            } else {
                // Not quite a valid link, skip ahead to next chance

                $target_pos += mb_strlen($target_token);
            }
        }

        /* Move forward */

        $start = $target_pos;

        $blength = mb_strlen($body);
    }
}