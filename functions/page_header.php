<?php

/**
 * page_header.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Prints the page header (duh)
 *
 * $Id: page_header.php,v 1.148 2003/04/03 11:40:47 pdontthink Exp $
 */
require_once SM_PATH . 'functions/strings.php';
require_once SM_PATH . 'functions/html.php';
require_once SM_PATH . 'functions/imap_mailbox.php';
require_once SM_PATH . 'functions/global.php';

/* Always set up the language before calling these functions */
function displayHtmlHeader($title = 'SquirrelMail', $xtra = '', $do_hook = true)
{
    ob_start();

    global $squirrelmail_language;

    if (!sqgetGlobalVar('base_uri', $base_uri, SQ_SESSION)) {
        global $base_uri;
    }

    global $theme_css, $custom_css;

    //    echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">' .

    //         "\n\n" . html_tag( 'html' ,'' , '', '', '' ) . "\n<head>\n";

    if (!isset($custom_css) || 'none' == $custom_css) {
        if ('' != $theme_css) {
            echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$theme_css\">";
        }
    } else {
        echo '<link rel="stylesheet" type="text/css" href="' . $base_uri . 'themes/css/' . $custom_css . '">';
    }

    if ('ja_JP' == $squirrelmail_language) {
        echo "<!-- \xfd\xfe -->\n";

        echo '<meta http-equuiv="Content-type" content="text/html; charset=euc-jp">' . "\n";
    }

    if ($do_hook) {
        do_hook('generic_header');
    }

    echo "$xtra\n";

    $headerContent = ob_get_contents();

    ob_end_clean();

    return $headerContent;
}

function displayInternalLink($path, $text, $target = '')
{
    sqgetGlobalVar('base_uri', $base_uri, SQ_SESSION);

    // no frame for xoops, so ignore $target

    //    if ($target != '') {

    //        $target = " target=\"$target\"";

    //    }

    //    echo '<a href="'.$base_uri.$path.'"'.$target.'>'.$text.'</a>';

    echo '<a href="' . $base_uri . $path . '">' . $text . '</a>';
}

function displayPageHeader($color, $mailbox, $xtra = '', $session = false)
{
    global $hide_sm_attributions, $PHP_SELF, $frame_top, $compose_new_win, $compose_width, $compose_height, $attachemessages, $provider_name, $provider_uri;

    sqgetGlobalVar('base_uri', $base_uri, SQ_SESSION);

    sqgetGlobalVar('delimiter', $delimiter, SQ_SESSION);

    $module = mb_substr($PHP_SELF, (mb_strlen($PHP_SELF) - mb_strlen($base_uri)) * -1);

    if ($qmark = mb_strpos($module, '?')) {
        $module = mb_substr($module, 0, $qmark);
    }

    if (!isset($frame_top)) {
        $frame_top = '_top';
    }

    if ($session) {
        $compose_uri = $base_uri . 'src/compose.php?mailbox=' . urlencode($mailbox) . '&amp;attachedmessages=true&amp;session=' . (string)$session;
    } else {
        $compose_uri = $base_uri . 'src/compose.php?newmessage=1';

        $session = 0;
    }

    switch ($module) {
        case 'src/read_body.php':
            $js = '';
            if ('1' == $compose_new_win) {
                if (!preg_match('/^[0-9]{3,4}$/', $compose_width)) {
                    $compose_width = '640';
                }

                if (!preg_match('/^[0-9]{3,4}$/', $compose_height)) {
                    $compose_height = '550';
                }

                $js .= "\n" . '<script language="JavaScript" type="text/javascript">' . "\n<!--\n";

                $js .= "function comp_in_new(comp_uri) {\n"
                       . "       if (!comp_uri) {\n"
                       . '           comp_uri = "'
                       . $compose_uri
                       . "\";\n"
                       . '       }'
                       . "\n"
                       . '    var newwin = window.open(comp_uri'
                       . ', "_blank",'
                       . '"width='
                       . $compose_width
                       . ',height='
                       . $compose_height
                       . ',scrollbars=yes,resizable=yes");'
                       . "\n"
                       . "}\n\n";

                $js .= 'function sendMDN() {' . "\n" . "mdnuri=window.location+'&sendreceipt=1';" . "var newwin = window.open(mdnuri,'right');" . "\n}\n\n";

                $js .= "// -->\n" . "</script>\n";
            }
            $xoops_module_header = displayHtmlHeader('SquirrelMail', $js);
            $onload = $xtra;
            break;
        case 'src/compose.php':
            $js = '<script language="JavaScript" type="text/javascript">'
                  . "\n<!--\n"
                  . "function checkForm() {\n"
                  . "var f = document.forms.length;\n"
                  . "var i = 0;\n"
                  . "var pos = -1;\n"
                  . "while( pos == -1 && i < f ) {\n"
                  . "var e = document.forms[i].elements.length;\n"
                  . "var j = 0;\n"
                  . "while( pos == -1 && j < e ) {\n"
                  . "if ( document.forms[i].elements[j].type == 'text' ) {\n"
                  . "pos = j;\n"
                  . "}\n"
                  . "j++;\n"
                  . "}\n"
                  . "i++;\n"
                  . "}\n"
                  . "if( pos >= 0 ) {\n"
                  . "document.forms[i-1].elements[pos].focus();\n"
                  . "}\n"
                  . "}\n";

            $js .= "// -->\n" . "</script>\n";
            $onload = 'onload="checkForm();"';
            $xoops_module_header = displayHtmlHeader('SquirrelMail', $js);
            break;
        default:
            $js = '<script language="JavaScript" type="text/javascript">'
                  . "\n<!--\n"
                  . "function checkForm() {\n"
                  . "var f = document.forms.length;\n"
                  . "var i = 0;\n"
                  . "var pos = -1;\n"
                  . "while( pos == -1 && i < f ) {\n"
                  . "var e = document.forms[i].elements.length;\n"
                  . "var j = 0;\n"
                  . "while( pos == -1 && j < e ) {\n"
                  . "if ( document.forms[i].elements[j].type == 'text' "
                  . "|| document.forms[i].elements[j].type == 'password' ) {\n"
                  . "pos = j;\n"
                  . "}\n"
                  . "j++;\n"
                  . "}\n"
                  . "i++;\n"
                  . "}\n"
                  . "if( pos >= 0 ) {\n"
                  . "document.forms[i-1].elements[pos].focus();\n"
                  . "}\n"
                  . "$xtra\n"
                  . "}\n";

            if ('1' == $compose_new_win) {
                if (!preg_match('/^[0-9]{3,4}$/', $compose_width)) {
                    $compose_width = '640';
                }

                if (!preg_match('/^[0-9]{3,4}$/', $compose_height)) {
                    $compose_height = '550';
                }

                $js .= "function comp_in_new(comp_uri) {\n"
                       . "       if (!comp_uri) {\n"
                       . '           comp_uri = "'
                       . $compose_uri
                       . "\";\n"
                       . '       }'
                       . "\n"
                       . '    var newwin = window.open(comp_uri'
                       . ', "_blank",'
                       . '"width='
                       . $compose_width
                       . ',height='
                       . $compose_height
                       . ',scrollbars=yes,resizable=yes");'
                       . "\n"
                       . "}\n\n";
            }
            $js .= "// -->\n" . "</script>\n";

            $onload = 'onload="checkForm();"';
            $xoops_module_header = displayHtmlHeader('SquirrelMail', $js);

            break;
    }

    //xoops should be moved to xoops theme file

    //    echo "<body text=\"$color[8]\" bgcolor=\"$color[4]\" link=\"$color[7]\" vlink=\"$color[7]\" alink=\"$color[7]\" $onload>\n\n";

    global $xoopsTpl;

    $xoopsTpl->assign('xoops_modules_header', $xoops_module_header);

    $xoopsTpl->assign('xoops_modules_onload', $onload);

    /** Here is the header and wrapping table **/

    $shortBoxName = imap_utf7_decode_local(
        readShortMailboxName($mailbox, $delimiter)
    );

    if ('INBOX' == $shortBoxName) {
        $shortBoxName = _('INBOX');
    }

    echo "<a name=\"pagetop\"></a>\n" . html_tag('table', '', '', $color[4], 'border="0" width="100%" cellspacing="0" cellpadding="2"') . "\n" . html_tag('tr', '', '', $color[9]) . "\n" . html_tag('td', '', 'left') . "\n";

    if ('' != $shortBoxName && 'none' != mb_strtolower($shortBoxName)) {
        echo '         ' . _('Current Folder') . ": <b>$shortBoxName&nbsp;</b>\n";
    } else {
        echo '&nbsp;';
    }

    echo "      </td>\n" . html_tag('td', '', 'right') . "<b>\n";

    displayInternalLink('src/webmail.php', _('Message List'), $frame_top);

    //    echo  "&nbsp;&nbsp;|&nbsp;&nbsp;";

    //    displayInternalLink ('src/signout.php', _("Sign Out"), $frame_top);

    echo "</b></td>\n" . "   </tr>\n" . html_tag('tr', '', '', $color[4]) . "\n" . html_tag('td', '', 'left') . "\n";

    $urlMailbox = urlencode($mailbox);

    if ('1' == $compose_new_win) {
        echo '<a href="javascript:void(0)" onclick="comp_in_new()">' . _('Compose') . '</a>';
    } else {
        displayInternalLink("src/compose.php?mailbox=$urlMailbox", _('Compose'), 'right');
    }

    echo "&nbsp;&nbsp;\n";

    displayInternalLink('src/addressbook.php', _('Addresses'), 'right');

    echo "&nbsp;&nbsp;\n";

    displayInternalLink('src/folders.php', _('Folders'), 'right');

    echo "&nbsp;&nbsp;\n";

    displayInternalLink('src/options.php', _('Options'), 'right');

    echo "&nbsp;&nbsp;\n";

    displayInternalLink("src/search.php?mailbox=$urlMailbox", _('Search'), 'right');

    echo "&nbsp;&nbsp;\n";

    displayInternalLink('src/help.php', _('Help'), 'right');

    echo "&nbsp;&nbsp;\n";

    do_hook('menuline');

    echo "      </td>\n" . html_tag('td', '', 'right') . "\n";

    if (!isset($provider_uri)) {
        $provider_uri = 'http://www.squirrelmail.org/';
    }

    if (!isset($provider_name)) {
        $provider_name = 'SquirrelMail';
    }

    echo($hide_sm_attributions ? '&nbsp;' : '<small><a href="' . $provider_uri . '" target="_blank">' . $provider_name . '</a> integrated by <a href="http://www.guanxicrm.com" target="_blank">wjue</a></small>');

    echo "</td>\n" . "   </tr>\n" . "</table><br>\n\n";
}

/* blatently copied/truncated/modified from the above function */
function compose_Header($color, $mailbox)
{
    global $delimiter, $hide_sm_attributions, $base_uri, $PHP_SELF, $data_dir, $username, $frame_top, $compose_new_win;

    $module = mb_substr($PHP_SELF, (mb_strlen($PHP_SELF) - mb_strlen($base_uri)) * -1);

    if (!isset($frame_top)) {
        $frame_top = '_top';
    }

    /*
        Locate the first displayable form element
    */

    switch ($module) {
        case 'src/search.php':
            $pos = getPref($data_dir, $username, 'search_pos', 0) - 1;
            $onload = "onload=\"document.forms[$pos].elements[2].focus();\"";
            $xoops_module_header = displayHtmlHeader(_('Compose'));
            break;
        default:
            $js = '<script language="JavaScript" type="text/javascript">'
                                   . "\n<!--\n"
                                   . "function checkForm() {\n"
                                   . "var f = document.forms.length;\n"
                                   . "var i = 0;\n"
                                   . "var pos = -1;\n"
                                   . "while( pos == -1 && i < f ) {\n"
                                   . "var e = document.forms[i].elements.length;\n"
                                   . "var j = 0;\n"
                                   . "while( pos == -1 && j < e ) {\n"
                                   . "if ( document.forms[i].elements[j].type == 'text' ) {\n"
                                   . "pos = j;\n"
                                   . "}\n"
                                   . "j++;\n"
                                   . "}\n"
                                   . "i++;\n"
                                   . "}\n"
                                   . "if( pos >= 0 ) {\n"
                                   . "document.forms[i-1].elements[pos].focus();\n"
                                   . "}\n"
                                   . "}\n";
            $js .= "// -->\n" . "</script>\n";
            $onload = 'onload="checkForm();"';
            $xoops_module_header = displayHtmlHeader(_('Compose'), $js);
            break;
    }

    //    echo "<body text=\"$color[8]\" bgcolor=\"$color[4]\" link=\"$color[7]\" vlink=\"$color[7]\" alink=\"$color[7]\" $onload>\n\n";

    global $xoopsTpl;

    $xoopsTpl->assign('xoops_modules_header', $xoops_module_header);

    $xoopsTpl->assign('xoops_modules_onload', $onload);
}
