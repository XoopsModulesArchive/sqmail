<?php

/**
 * vcard.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This file shows an attched vcard
 *
 * $Id: vcard.php,v 1.23 2003/03/11 17:39:25 kink Exp $
 */

//for xoops
$sqmaiContent = ob_get_contents();
ob_end_clean();
$xoopsTpl->assign('sqmailContent', $sqmaiContent);

// Xoops footer
require XOOPS_ROOT_PATH . '/footer.php';

/* Path for SquirrelMail required files. */
define('SM_PATH', '../');

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/date.php';
require_once SM_PATH . 'functions/page_header.php';
require_once SM_PATH . 'functions/mime.php';
require_once SM_PATH . 'include/load_prefs.php';

/* globals */
sqgetGlobalVar('username', $username, SQ_SESSION);
sqgetGlobalVar('key', $key, SQ_COOKIE);
sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);

sqgetGlobalVar('passed_id', $passed_id, SQ_GET);
sqgetGlobalVar('mailbox', $mailbox, SQ_GET);
sqgetGlobalVar('ent_id', $ent_id, SQ_GET);
sqgetGlobalVar('startMessage', $startMessage, SQ_GET);
/* end globals */

$imapConnection = sqimap_login($username, $key, $imapServerAddress, $imapPort, 0);
sqimap_mailbox_select($imapConnection, $mailbox);

displayPageHeader($color, 'None');

echo '<br><table width="100%" border="0" cellspacing="0" cellpadding="2" ' . 'align="center">' . "\n" . '<tr><td bgcolor="' . $color[0] . '">' . '<b><center>' . _('Viewing a Business Card') . ' - ';
$msg_url = 'read_body.php?mailbox=' . urlencode($mailbox) . '&amp;startMessage=' . $startMessage . '&amp;passed_id=' . $passed_id;

$msg_url = set_url_var($msg_url, 'ent_id', 0);

echo '<a href="' . $msg_url . '">' . _('View message') . '</a>';

echo '</center></b></td></tr>';

$message = sqimap_get_message($imapConnection, $passed_id, $mailbox);

$entity_vcard = getEntity($message, $ent_id);

$vcard = mime_fetch_body($imapConnection, $passed_id, $ent_id);
$vcard = decodeBody($vcard, $entity_vcard->header->encoding);
$vcard = explode("\n", $vcard);
foreach ($vcard as $l) {
    $k = mb_substr($l, 0, mb_strpos($l, ':'));

    $v = mb_substr($l, mb_strpos($l, ':') + 1);

    $attributes = explode(';', $k);

    $k = mb_strtolower(array_shift($attributes));

    foreach ($attributes as $attr) {
        if ('quoted-printable' == $attr) {
            $v = quoted_printable_decode($v);
        } else {
            $k .= ';' . mb_strtolower($attr);
        }
    }

    $v = str_replace(';', "\n", $v);

    $vcard_nice[$k] = $v;
}

if ('2.1' == $vcard_nice['version']) {
    // get firstname and lastname for sm addressbook

    $vcard_nice['firstname'] = mb_substr(
        $vcard_nice['n'],
        mb_strpos($vcard_nice['n'], "\n") + 1,
        mb_strlen($vcard_nice['n'])
    );

    $vcard_nice['lastname'] = mb_substr(
        $vcard_nice['n'],
        0,
        mb_strpos($vcard_nice['n'], "\n")
    );

    // workaround for Outlook, should be fixed in a better way,

    // maybe in new 'vCard' class.

    if (isset($vcard_nice['email;pref;internet'])) {
        $vcard_nice['email;internet'] = $vcard_nice['email;pref;internet'];
    }
} else {
    echo '<tr><td align=center>vCard Version ' . $vcard_nice['version'] . ' is not supported.  Some information might not be converted ' . "correctly.</td></tr>\n";
}

foreach ($vcard_nice as $k => $v) {
    $v = htmlspecialchars($v, ENT_QUOTES | ENT_HTML5);

    $v = trim($v);

    $vcard_safe[$k] = trim(nl2br($v));
}

$ShowValues = [
    'fn' => _('Name'),
    'title' => _('Title'),
    'email;internet' => _('Email'),
    'url' => _('Web Page'),
    'org' => _('Organization / Department'),
    'adr' => _('Address'),
    'tel;work' => _('Work Phone'),
    'tel;home' => _('Home Phone'),
    'tel;cell' => _('Cellular Phone'),
    'tel;fax' => _('Fax'),
    'note' => _('Note'),
];

echo '<tr><td><br>' . '<TABLE border=0 cellpadding=2 cellspacing=0 align=center>' . "\n";

if (isset($vcard_safe['email;internet'])) {
    $vcard_safe['email;internet'] = '<A HREF="../src/compose.php?send_to=' . $vcard_safe['email;internet'] . '">' . $vcard_safe['email;internet'] . '</A>';
}
if (isset($vcard_safe['url'])) {
    $vcard_safe['url'] = '<A HREF="' . $vcard_safe['url'] . '">' . $vcard_safe['url'] . '</A>';
}

foreach ($ShowValues as $k => $v) {
    if (isset($vcard_safe[$k]) && $vcard_safe[$k]) {
        echo "<tr><td align=right><b>$v:</b></td><td>" . $vcard_safe[$k] . "</td><tr>\n";
    }
}

echo '</table>'
     . '<br>'
     . '</td></tr></table>'
     . '<table width="100%" border="0" cellspacing="0" cellpadding="2" '
     . 'align="center">'
     . '<tr>'
     . '<td bgcolor="'
     . $color[0]
     . '">'
     . '<b><center>'
     . _('Add to Addressbook')
     . '</td></tr>'
     . '<tr><td align=center>'
     . '<FORM ACTION="../src/addressbook.php" METHOD="POST" NAME=f_add>'
     . '<table border=0 cellpadding=2 cellspacing=0 align=center>'
     . '<tr><td align=right><b>Nickname:</b></td>'
     . '<td><input type=text name="addaddr[nickname]" size=20 value="'
     . $vcard_safe['firstname']
     . '-'
     . $vcard_safe['lastname']
     . '"></td></tr>'
     . '<tr><td align=right><b>Note Field Contains:</b></td><td>'
     . '<select name="addaddr[label]">';

if (isset($vcard_nice['url'])) {
    echo '<option value="' . htmlspecialchars($vcard_nice['url'], ENT_QUOTES | ENT_HTML5) . '">' . _('Web Page') . "</option>\n";
}
if (isset($vcard_nice['adr'])) {
    echo '<option value="' . $vcard_nice['adr'] . '">' . _('Address') . "</option>\n";
}
if (isset($vcard_nice['title'])) {
    echo '<option value="' . $vcard_nice['title'] . '">' . _('Title') . "</option>\n";
}
if (isset($vcard_nice['org'])) {
    echo '<option value="' . $vcard_nice['org'] . '">' . _('Organization / Department') . "</option>\n";
}
if (isset($vcard_nice['title'])) {
    echo '<option value="' . $vcard_nice['title'] . '; ' . $vcard_nice['org'] . '">' . _('Title & Org. / Dept.') . "</option>\n";
}
if (isset($vcard_nice['tel;work'])) {
    echo '<option value="' . $vcard_nice['tel;work'] . '">' . _('Work Phone') . "</option>\n";
}
if (isset($vcard_nice['tel;home'])) {
    echo '<option value="' . $vcard_nice['tel;home'] . '">' . _('Home Phone') . "</option>\n";
}
if (isset($vcard_nice['tel;cell'])) {
    echo '<option value="' . $vcard_nice['tel;cell'] . '">' . _('Cellular Phone') . "</option>\n";
}
if (isset($vcard_nice['tel;fax'])) {
    echo '<option value="' . $vcard_nice['tel;fax'] . '">' . _('Fax') . "</option>\n";
}
if (isset($vcard_nice['note'])) {
    echo '<option value="' . $vcard_nice['note'] . '">' . _('Note') . "</option>\n";
}
echo '</select>'
     . '</td></tr>'
     . '<tr><td colspan=2 align=center>'
     . '<INPUT NAME="addaddr[email]" type=hidden value="'
     . htmlspecialchars($vcard_nice['email;internet'], ENT_QUOTES | ENT_HTML5)
     . '">'
     . '<INPUT NAME="addaddr[firstname]" type=hidden value="'
     . $vcard_safe['firstname']
     . '">'
     . '<INPUT NAME="addaddr[lastname]" type=hidden value="'
     . $vcard_safe['lastname']
     . '">'
     . '<INPUT TYPE=submit NAME="addaddr[SUBMIT]" '
     . 'VALUE="Add to Address Book">'
     . '</td></tr>'
     . '</table>'
     . '</FORM>'
     . '</td></tr>'
     . '<tr><td align=center>'
     . '<a href="../src/download.php?absolute_dl=true&amp;passed_id='
     . $passed_id
     . '&amp;mailbox='
     . urlencode($mailbox)
     . '&amp;ent_id='
     . urlencode($ent_id)
     . '">'
     . _('Download this as a file')
     . '</A>'
     . '</TD></TR></TABLE>'
     .

     '<TABLE BORDER=0 CELLSPACING=0 CELLPADDING=2 ALIGN=CENTER>'
     . '<TR><TD BGCOLOR="'
     . $color[4]
     . '">'
     . '</TD></TR></TABLE>';

//for xoops
$sqmaiContent = ob_get_contents();
ob_end_clean();
$xoopsTpl->assign('sqmailContent', $sqmaiContent);

// Xoops footer
require XOOPS_ROOT_PATH . '/footer.php';
