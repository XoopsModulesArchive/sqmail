<?php
/**
 * mailout.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: mailout.php,v 1.13 2003/03/11 15:57:18 kink Exp $
 */
define('SM_PATH', '../../');

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/page_header.php';
require_once SM_PATH . 'include/load_prefs.php';
require_once SM_PATH . 'functions/html.php';

displayPageHeader($color, $mailbox);

/* get globals */
sqgetGlobalVar('mailbox', $mailbox, SQ_GET);
sqgetGlobalVar('send_to', $send_to, SQ_GET);
sqgetGlobalVar('subject', $subject, SQ_GET);
sqgetGlobalVar('body', $body, SQ_GET);
sqgetGlobalVar('action', $action, SQ_GET);

echo html_tag('p', '', 'left') . html_tag('table', '', 'center', $color[0], 'border="0" width="75%"') . "\n" . html_tag(
    'tr',
    html_tag('th', _('Mailinglist') . ' ' . _($action), '', $color[9])
) . html_tag('tr') . html_tag('td', '', 'left');

switch ($action) {
    case 'help':
        $out_string = _('This will send a message to %s requesting help for this list. You will receive an emailed response at the address below.');
        break;
    case 'subscribe':
        $out_string = _('This will send a message to %s requesting that you will be subscribed to this list. You will be subscribed with the address below.');
        break;
    case 'unsubscribe':
        $out_string = _('This will send a message to %s requesting that you will be unsubscribed from this list. It will try to unsubscribe the adress below.');
}

printf($out_string, htmlspecialchars($send_to, ENT_QUOTES | ENT_HTML5));

echo '<form method="post" action="../../src/compose.php">';

/*
 * Identity support (RFC 2369 sect. B.1.)
 *
 * I had to copy this from compose.php because there doesn't
 * seem to exist a function to get the identities.
 */

$defaultmail = htmlspecialchars(getPref($data_dir, $username, 'full_name'), ENT_QUOTES | ENT_HTML5);
$em = getPref($data_dir, $username, 'email_address');
if ('' != $em) {
    $defaultmail .= htmlspecialchars(' <' . $em . '>', ENT_QUOTES | ENT_HTML5) . "\n";
}
echo html_tag('p', '', 'center') . _('From:') . ' ';

$idents = getPref($data_dir, $username, 'identities');
if ('' != $idents && $idents > 1) {
    echo ' <select name="identity">' . "\n" . '<option value="default">' . $defaultmail;

    for ($i = 1; $i < $idents; $i++) {
        echo '<option value="' . $i . '"';

        if (isset($identity) && $identity == $i) {
            echo ' selected';
        }

        echo '>' . htmlspecialchars(
                getPref(
                    $data_dir,
                    $username,
                    'full_name' . $i
                ),
                ENT_QUOTES | ENT_HTML5
            );

        $em = getPref($data_dir, $username, 'email_address' . $i);

        if ('' != $em) {
            echo htmlspecialchars(' <' . $em . '>', ENT_QUOTES | ENT_HTML5) . "\n";
        }
    }

    echo '</select>' . "\n";
} else {
    echo $defaultmail;
}

echo '<br>'
     . '<input type="hidden" name="send_to" value="'
     . htmlspecialchars($send_to, ENT_QUOTES | ENT_HTML5)
     . '">'
     . '<input type="hidden" name="subject" value="'
     . htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5)
     . '">'
     . '<input type="hidden" name="body" value="'
     . htmlspecialchars($body, ENT_QUOTES | ENT_HTML5)
     . '">'
     . '<input type="hidden" name="mailbox" value="'
     . htmlspecialchars($mailbox, ENT_QUOTES | ENT_HTML5)
     . '">'
     . '<input type="submit" name="send" value="'
     . _('Send Mail')
     . '"><br><br></center>'
     . '</form></td></tr></table></p></body></html>';
