<?php

/**
 * folders_create.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Creates folders on the IMAP server.
 * Called from folders.php
 *
 * $Id: folders_create.php,v 1.64 2003/03/09 18:54:33 ebullient Exp $
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

/* get globals we may need */
sqgetGlobalVar('key', $key, SQ_COOKIE);
sqgetGlobalVar('username', $username, SQ_SESSION);
sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
sqgetGlobalVar('delimiter', $delimiter, SQ_SESSION);
sqgetGlobalVar('folder_name', $folder_name, SQ_POST);
sqgetGlobalVar('subfolder', $subfolder, SQ_POST);
sqgetGlobalVar('contain_subs', $contain_subs, SQ_POST);
/* end of get globals */

$folder_name = trim($folder_name);

if (mb_substr_count($folder_name, '"') || mb_substr_count($folder_name, '\\')
    || mb_substr_count($folder_name, $delimiter)
    || ('' == $folder_name)) {
    displayPageHeader($color, 'None');

    plain_error_message(
        _('Illegal folder name.  Please select a different name.') . '<BR><A HREF="../src/folders.php">' . _('Click here to go back') . '</A>.',
        $color
    );

    //for xoops

    $sqmaiContent = ob_get_contents();

    ob_end_clean();

    $xoopsTpl->assign('sqmailContent', $sqmaiContent);

    // Xoops footer

    require XOOPS_ROOT_PATH . '/footer.php';

    exit;
}

$folder_name = imap_utf7_encode_local($folder_name);

if (isset($contain_subs) && $contain_subs) {
    $folder_name .= $delimiter;
}

if ($folder_prefix && (mb_substr($folder_prefix, -1) != $delimiter)) {
    $folder_prefix .= $delimiter;
}
if ($folder_prefix && (mb_substr($subfolder, 0, mb_strlen($folder_prefix)) != $folder_prefix)) {
    $subfolder_orig = $subfolder;

    $subfolder = $folder_prefix . $subfolder;
} else {
    $subfolder_orig = $subfolder;
}

$imapConnection = sqimap_login($username, $key, $imapServerAddress, $imapPort, 0);

if ('' == trim($subfolder_orig)) {
    sqimap_mailbox_create($imapConnection, $folder_prefix . $folder_name, '');
} else {
    sqimap_mailbox_create($imapConnection, $subfolder . $delimiter . $folder_name, '');
}

sqimap_logout($imapConnection);

$location = get_location();
header("Location: $location/folders.php?success=create");
