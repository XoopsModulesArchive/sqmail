<?php

/**
 * testsound.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: testsound.php,v 1.12 2003/03/31 04:18:21 ebullient Exp $
 */
define('SM_PATH', '../../');

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/global.php';
require_once SM_PATH . 'functions/html.php';

displayHtmlHeader(_('Test Sound'), '', false);

echo '<body bgcolor="' . $color[4] . '" topmargin=0 leftmargin=0 rightmargin=0 marginwidth=0 marginheight=0>' . "\n";

if (!sqgetGlobalVar('sound', $sound, SQ_GET)) {
    $sound = 'Click.wav';
} elseif ('(none)' == $sound) {
    echo '<center><form><br><br>' . '<b>' . _('No sound specified') . '</b><br><br>' . '<input type="button" name="close" value="' . _('Close') . '" onClick="window.close()">' . '</form></center>' . '</body></html>';

    return;
}

echo html_tag(
    'table',
    html_tag(
             'tr',
             html_tag(
                 'td',
                 "<embed src=\"$sound\" hidden=true autostart=true>" . '<br>' . '<b>' . _('Loading the sound...') . '</b><br>' . '<form>' . '<input type="button" name="close" value="  ' . _('Close') . '  " onClick="window.close()">' . '</form>',
                 'center'
             )
         ),
    'center'
) . '</body></html>';
