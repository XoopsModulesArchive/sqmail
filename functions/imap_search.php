<?php

/**
 * imap_search.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * IMAP search routines
 *
 * $Id: imap_search.php,v 1.63 2002/12/31 12:49:31 kink Exp $
 */
require_once SM_PATH . 'functions/imap.php';
require_once SM_PATH . 'functions/date.php';
require_once SM_PATH . 'functions/mailbox_display.php';
require_once SM_PATH . 'functions/mime.php';

function sqimap_search(
    $imapConnection,
    $search_where,
    $search_what,
    $mailbox,
    $color,
    $search_position,
    $search_all,
    $count_all
) {
    global $message_highlight_list, $squirrelmail_language, $languages, $index_order, $pos, $allow_charset_search, $uid_support, $imap_server_type;

    $pos = $search_position;

    $urlMailbox = urlencode($mailbox);

    /* construct the search query, taking multiple search terms into account */

    $multi_search = [];

    $search_what = trim($search_what);

    $search_what = preg_replace('[ ]{2,}', ' ', $search_what);

    $multi_search = explode(' ', $search_what);

    $search_string = '';

    /* it seems macosx does not support the prefered search
       syntax so we fall back to the older style. This IMAP
       server has a problem with multiple search terms. Instead
       of returning the messages that match all the terms it
       returns the messages that match each term. Could be fixed
       on the client side, but should be fixed on the server
       as per the RFC */

    if ('macosx' == $imap_server_type) {
        foreach ($multi_search as $multi_search_part) {
            if ('ISO-2022-JP' == mb_strtoupper($languages[$squirrelmail_language]['CHARSET'])) {
                $multi_search_part = mb_convert_encoding($multi_search_part, 'JIS', 'auto');
            }

            $search_string .= $search_where . ' ' . $multi_search_part . ' ';
        }
    } else {
        foreach ($multi_search as $multi_search_part) {
            if ('ISO-2022-JP' == mb_strtoupper($languages[$squirrelmail_language]['CHARSET'])) {
                $multi_search_part = mb_convert_encoding($multi_search_part, 'JIS', 'auto');
            }

            $search_string .= $search_where . ' {' . mb_strlen($multi_search_part) . "}\r\n" . $multi_search_part . ' ';
        }
    }

    $search_string = trim($search_string);

    /* now use $search_string in the imap search */

    if ($allow_charset_search && isset($languages[$squirrelmail_language]['CHARSET'])
        && $languages[$squirrelmail_language]['CHARSET']) {
        $ss = 'SEARCH CHARSET ' . mb_strtoupper($languages[$squirrelmail_language]['CHARSET']) . " ALL $search_string";
    } else {
        $ss = "SEARCH ALL $search_string";
    }

    /* read data back from IMAP */

    $readin = sqimap_run_command($imapConnection, $ss, false, $result, $message, $uid_support);

    /* try US-ASCII charset if search fails */

    if (isset($languages[$squirrelmail_language]['CHARSET'])
        && 'no' == mb_strtolower($result)) {
        $ss = "SEARCH CHARSET \"US-ASCII\" ALL $search_string";

        $readin = sqimap_run_command(
            $imapConnection,
            $ss,
            true,
            $result,
            $message
        );
    }

    unset($messagelist);

    /* Keep going till we find the SEARCH response */

    foreach ($readin as $readin_part) {
        /* Check to see if a SEARCH response was received */

        if ('* SEARCH ' == mb_substr($readin_part, 0, 9)) {
            $messagelist = preg_preg_split('/ /', mb_substr($readin_part, 9));
        } elseif (isset($errors)) {
            $errors .= $readin_part;
        } else {
            $errors = $readin_part;
        }
    }

    /* If nothing is found * SEARCH should be the first error else echo errors */

    if (isset($errors)) {
        if (mb_strstr($errors, '* SEARCH')) {
            return [];
        }

        echo '<!-- ' . htmlspecialchars($errors, ENT_QUOTES | ENT_HTML5) . ' -->';
    }

    global $sent_folder;

    $cnt = count($messagelist);

    for ($q = 0; $q < $cnt; $q++) {
        $id[$q] = trim($messagelist[$q]);
    }

    $issent = ($mailbox == $sent_folder);

    $msgs = fillMessageArray($imapConnection, $id, $cnt);

    return $msgs;
}
