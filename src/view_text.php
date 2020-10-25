<?php

/**
 * view_text.php -- Displays the main frameset
 *
 * Copyright (c) 1999-2003 The SquirrelMail development team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Who knows what this file does. However PUT IT HERE DID NOT PUT
 * A SINGLE FREAKING COMMENT IN! Whoever is responsible for this,
 * be very ashamed.
 *
 * $Id: view_text.php,v 1.17 2003/03/27 16:47:48 philippe_mingo Exp $
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
require_once SM_PATH . 'functions/mime.php';
require_once SM_PATH . 'functions/html.php';

sqgetGlobalVar('key', $key, SQ_COOKIE);
sqgetGlobalVar('username', $username, SQ_SESSION);
sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
sqgetGlobalVar('delimiter', $delimiter, SQ_SESSION);
sqgetGlobalVar('QUERY_STRING', $QUERY_STRING, SQ_SERVER);
sqgetGlobalVar('messages', $messages);
sqgetGlobalVar('passed_id', $passed_id, SQ_GET);

if (sqgetGlobalVar('mailbox', $temp, SQ_GET)) {
    $mailbox = $temp;
}
if (!sqgetGlobalVar('ent_id', $ent_id, SQ_GET)) {
    $ent_id = '';
}
if (!sqgetGlobalVar('passed_ent_id', $passed_ent_id, SQ_GET)) {
    $passed_ent_id = '';
}

$imapConnection = sqimap_login($username, $key, $imapServerAddress, $imapPort, 0);
$mbx_response = sqimap_mailbox_select($imapConnection, $mailbox);

$message = &$messages[$mbx_response['UIDVALIDITY']][(string)$passed_id];
$message_ent = &$message->getEntity($ent_id);
if ($passed_ent_id) {
    $message = &$message->getEntity($passed_ent_id);
}

$header = $message_ent->header;
$charset = $header->getParameter('charset');
$type0 = $header->type0;
$type1 = $header->type1;
$encoding = mb_strtolower($header->encoding);

$msg_url = 'read_body.php?' . $QUERY_STRING;
$msg_url = set_url_var($msg_url, 'ent_id', 0);

$body = mime_fetch_body($imapConnection, $passed_id, $ent_id);
$body = decodeBody($body, $encoding);

displayPageHeader($color, 'None');

echo "<BR><TABLE WIDTH=\"100%\" BORDER=0 CELLSPACING=0 CELLPADDING=2 ALIGN=CENTER><TR><TD BGCOLOR=\"$color[0]\">" . '<B><CENTER>' . _('Viewing a text attachment') . ' - ';
echo '<a href="' . $msg_url . '">' . _('View message') . '</a>';

$dwnld_url = '../src/download.php?' . $QUERY_STRING . '&amp;absolute_dl=true';
echo '</b></td><tr><tr><td><CENTER><A HREF="'
     . $dwnld_url
     . '">'
     . _('Download this as a file')
     . '</A></CENTER><BR>'
     . '</CENTER></B>'
     . '</TD></TR></TABLE>'
     . "<TABLE WIDTH=\"98%\" BORDER=0 CELLSPACING=0 CELLPADDING=2 ALIGN=CENTER><TR><TD BGCOLOR=\"$color[0]\">"
     . "<TR><TD BGCOLOR=\"$color[4]\"><TT>";

if (isset($languages[$squirrelmail_language]['XTRA_CODE'])
    && function_exists($languages[$squirrelmail_language]['XTRA_CODE'])) {
    if ('ASCII' != mb_detect_encoding($body, mb_detect_order(), true)) {
        $body = $languages[$squirrelmail_language]['XTRA_CODE']('decode', $body);
    }
}

if ('html' == $type1 || (isset($override_type1) && 'html' == $override_type1)) {
    $body = magicHTML($body, $passed_id, $message, $mailbox);
} else {
    translateText($body, $wrap_at, $charset);
}
echo $body . '</TT></TD></TR></TABLE>';

//for xoops
$sqmaiContent = ob_get_contents();
ob_end_clean();
$xoopsTpl->assign('sqmailContent', $sqmaiContent);

// Xoops footer
require XOOPS_ROOT_PATH . '/footer.php';
