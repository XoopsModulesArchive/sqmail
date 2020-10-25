<?php

/**
 * folders_subscribe.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Subscribe and unsubcribe from folders.
 * Called from folders.php
 *
 * $Id: folders_subscribe.php,v 1.28 2003/03/09 18:54:34 ebullient Exp $
 */
if (!isset($xoopsIntro)) {
    require dirname(__DIR__, 3) . '/mainfile.php';

    $GLOBALS['xoopsOption']['template_main'] = 'sqmail_index.html';

    require XOOPS_ROOT_PATH . '/header.php';

    $xoopsIntro = true;
}
ob_start();

/* Path for SquirrelMail required files. */
define('SM_PATH', '../');

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/global.php';
require_once SM_PATH . 'functions/imap.php';
require_once SM_PATH . 'functions/display_messages.php';

/* globals */
sqgetGlobalVar('key', $key, SQ_COOKIE);
sqgetGlobalVar('username', $username, SQ_SESSION);
sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
sqgetGlobalVar('method', $method, SQ_GET);
sqgetGlobalVar('mailbox', $mailbox, SQ_POST);
/* end globals */

$location = get_location();

if (!isset($mailbox) || !isset($mailbox[0]) || '' == $mailbox[0]) {
    header("Location: $location/folders.php");

    exit(0);
}

$imapConnection = sqimap_login($username, $key, $imapServerAddress, $imapPort, 0);

if ('sub' == $method) {
    if ($no_list_for_subscribe && 'cyrus' == $imap_server_type) {
        /* Cyrus, atleast, does not typically allow subscription to
     * nonexistent folders (this is an optional part of IMAP),
         * lets catch it here and report back cleanly. */

        if (!sqimap_mailbox_exists($imapConnection, $mailbox[0])) {
            header("Location: $location/folders.php?success=subscribe-doesnotexist");

            sqimap_logout($imapConnection);

            exit(0);
        }
    }

    for ($i = 0, $iMax = count($mailbox); $i < $iMax; $i++) {
        $mailbox[$i] = trim($mailbox[$i]);

        sqimap_subscribe($imapConnection, $mailbox[$i]);
    }

    $success = 'subscribe';
} else {
    for ($i = 0, $iMax = count($mailbox); $i < $iMax; $i++) {
        $mailbox[$i] = trim($mailbox[$i]);

        sqimap_unsubscribe($imapConnection, $mailbox[$i]);
    }

    $success = 'unsubscribe';
}

sqimap_logout($imapConnection);
header("Location: $location/folders.php?success=$success");
