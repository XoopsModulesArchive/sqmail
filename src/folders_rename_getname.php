<?php

/**
 * folders_rename_getname.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Gets folder names and enables renaming
 * Called from folders.php
 *
 * $Id: folders_rename_getname.php,v 1.47 2003/03/09 18:54:34 ebullient Exp $
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
require_once SM_PATH . 'functions/imap_mailbox.php';
require_once SM_PATH . 'functions/html.php';
require_once SM_PATH . 'functions/display_messages.php';

/* get globals we may need */
sqgetGlobalVar('key', $key, SQ_COOKIE);
sqgetGlobalVar('username', $username, SQ_SESSION);
sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
sqgetGlobalVar('delimiter', $delimiter, SQ_SESSION);
sqgetGlobalVar('old', $old, SQ_POST);
/* end of get globals */

if ('' == $old) {
    displayPageHeader($color, 'None');

    plain_error_message(
        _('You have not selected a folder to rename. Please do so.') . '<BR><A HREF="../src/folders.php">' . _('Click here to go back') . '</A>.',
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

if (mb_substr($old, mb_strlen($old) - mb_strlen($delimiter)) == $delimiter) {
    $isfolder = true;

    $old = mb_substr($old, 0, -1);
} else {
    $isfolder = false;
}

$old = imap_utf7_decode_local($old);

if (mb_strpos($old, $delimiter)) {
    $old_name = mb_substr($old, mb_strrpos($old, $delimiter) + 1, mb_strlen($old));

    $old_parent = mb_substr($old, 0, mb_strrpos($old, $delimiter));
} else {
    $old_name = $old;

    $old_parent = '';
}

displayPageHeader($color, 'None');
echo '<br>' . html_tag('table', '', 'center', '', 'width="95%" border="0"') . html_tag(
    'tr',
    html_tag('td', '<b>' . _('Rename a folder') . '</b>', 'center', $color[0])
) . html_tag('tr') . html_tag('td', '', 'center', $color[4]) . '<FORM ACTION="folders_rename_do.php" METHOD="POST">' . _('New name:') . "<br><B>$old_parent $delimiter </B><INPUT TYPE=TEXT SIZE=25 NAME=new_name VALUE=\"$old_name\"><BR>\n";
if ($isfolder) {
    echo '<INPUT TYPE=HIDDEN NAME="isfolder" VALUE="true">';
}
printf("<INPUT TYPE=HIDDEN NAME=\"orig\" VALUE=\"%s\">\n", $old);
printf("<INPUT TYPE=HIDDEN NAME=\"old_name\" VALUE=\"%s\">\n", $old_name);
echo '<INPUT TYPE=SUBMIT VALUE="' . _('Submit') . "\">\n" . '</FORM><BR></td></tr></table>';

//for xoops
$sqmaiContent = ob_get_contents();
ob_end_clean();
$xoopsTpl->assign('sqmailContent', $sqmaiContent);

// Xoops footer
require XOOPS_ROOT_PATH . '/footer.php';
