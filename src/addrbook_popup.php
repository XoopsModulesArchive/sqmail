<?php

/**
 * addrbook_popup.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Frameset for the JavaScript version of the address book.
 *
 * $Id: addrbook_popup.php,v 1.22 2002/12/31 12:49:41 kink Exp $
 */
if (!isset($xoopsIntro)) {
    require dirname(__DIR__, 3) . '/mainfile.php';

    $GLOBALS['xoopsOption']['template_main'] = 'sqmail_index.html';

    require XOOPS_ROOT_PATH . '/header.php';

    $xoopsIntro = true;
}

/* Path for SquirrelMail required files. */
define('SM_PATH', '../');

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/addressbook.php';

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN">

<HTML>
<HEAD>
    <TITLE><?php echo "$org_title: " . _('Address Book'); ?></TITLE>
</HEAD>
<FRAMESET ROWS="60,*" BORDER=0>
    <FRAME NAME="abookmain"
           MARGINWIDTH="0"
           SCROLLING="NO"
           BORDER="0"
           SRC="addrbook_search.php?show=form">
    <FRAME NAME="abookres"
           MARGINWIDTH="0"
           BORDER="0"
           SRC="addrbook_search.php?show=blank">
</FRAMESET>
</HTML>
