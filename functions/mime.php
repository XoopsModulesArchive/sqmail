<?php

/**
 * mime.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains the functions necessary to detect and decode MIME
 * messages.
 *
 * $Id: mime.php,v 1.265 2003/04/01 20:47:00 kink Exp $
 */
require_once SM_PATH . 'functions/imap.php';
require_once SM_PATH . 'functions/attachment_common.php';

/* --------------------------------------------------------------------------------- */
/* MIME DECODING                                                                     */
/* --------------------------------------------------------------------------------- */

/* This function gets the structure of a message and stores it in the "message" class.
 * It will return this object for use with all relevant header information and
 * fully parsed into the standard "message" object format.
 */

function mime_structure($bodystructure, $flags = [])
{
    /* Isolate the body structure and remove beginning and end parenthesis. */

    $read = trim(mb_substr($bodystructure, mb_strpos(mb_strtolower($bodystructure), 'bodystructure') + 13));

    $read = trim(mb_substr($read, 0, -1));

    $i = 0;

    $msg = Message::parseStructure($read, $i);

    if (!is_object($msg)) {
        require_once SM_PATH . 'functions/display_messages.php';

        global $color, $mailbox;

        /* removed urldecode because $_GET is auto urldecoded ??? */

        displayPageHeader($color, $mailbox);

        echo "<BODY TEXT=\"$color[8]\" BGCOLOR=\"$color[4]\" LINK=\"$color[7]\" VLINK=\"$color[7]\" ALINK=\"$color[7]\">\n\n" . '<CENTER>';

        $errormessage = _('SquirrelMail could not decode the bodystructure of the message');

        $errormessage .= '<BR>' . _('the provided bodystructure by your imap-server') . ':<BR><BR>';

        $errormessage .= '<table><tr><td>' . htmlspecialchars($read, ENT_QUOTES | ENT_HTML5) . '</td></tr></table>';

        plain_error_message($errormessage, $color);

        echo '</body></html>';

        exit;
    }

    if (count($flags)) {
        foreach ($flags as $flag) {
            $char = mb_strtoupper($flag[1]);

            switch ($char) {
                case 'S':
                    if ('\\seen' == mb_strtolower($flag)) {
                        $msg->is_seen = true;
                    }
                    break;
                case 'A':
                    if ('\\answered' == mb_strtolower($flag)) {
                        $msg->is_answered = true;
                    }
                    break;
                case 'D':
                    if ('\\deleted' == mb_strtolower($flag)) {
                        $msg->is_deleted = true;
                    }
                    break;
                case 'F':
                    if ('\\flagged' == mb_strtolower($flag)) {
                        $msg->is_flagged = true;
                    }
                    break;
                case 'M':
                    if ('$mdnsent' == mb_strtolower($flag)) {
                        $msg->is_mdnsent = true;
                    }
                    break;
                default:
                    break;
            }
        }
    }

    //    listEntities($msg);

    return $msg;
}

/* This starts the parsing of a particular structure.  It is called recursively,
 * so it can be passed different structures.  It returns an object of type
 * $message.
 * First, it checks to see if it is a multipart message.  If it is, then it
 * handles that as it sees is necessary.  If it is just a regular entity,
 * then it parses it and adds the necessary header information (by calling out
 * to mime_get_elements()
 */

function mime_fetch_body($imap_stream, $id, $ent_id = 1)
{
    global $uid_support;

    /* Do a bit of error correction.  If we couldn't find the entity id, just guess
     * that it is the first one.  That is usually the case anyway.
     */

    if (!$ent_id) {
        $cmd = "FETCH $id BODY[]";
    } else {
        $cmd = "FETCH $id BODY[$ent_id]";
    }

    $data = sqimap_run_command($imap_stream, $cmd, true, $response, $message, $uid_support);

    do {
        $topline = trim(array_shift($data));
    } while (false !== ($topline && ('*' == $topline[0]) && !preg_match('/\* [0-9]+ FETCH.*/i', $topline)));

    $wholemessage = implode('', $data);

    if (preg_match('\\{([^\\}]*)\\}', $topline, $regs)) {
        $ret = mb_substr($wholemessage, 0, $regs[1]);

        /* There is some information in the content info header that could be important
         * in order to parse html messages. Let's get them here.
         */

        if ('<' == $ret[0]) {
            $data = sqimap_run_command($imap_stream, "FETCH $id BODY[$ent_id.MIME]", true, $response, $message, $uid_support);
        }
    } elseif (preg_match('"([^"]*)"', $topline, $regs)) {
        $ret = $regs[1];
    } else {
        global $where, $what, $mailbox, $passed_id, $startMessage;

        $par = 'mailbox=' . urlencode($mailbox) . '&amp;passed_id=' . $passed_id;

        if (isset($where) && isset($what)) {
            $par .= '&amp;where=' . urlencode($where) . '&amp;what=' . urlencode($what);
        } else {
            $par .= '&amp;startMessage=' . $startMessage . '&amp;show_more=0';
        }

        $par .= '&amp;response=' . urlencode($response) . '&amp;message=' . urlencode($message) . '&amp;topline=' . urlencode($topline);

        echo '<tt><br>'
             . '<table width="80%"><tr>'
             . '<tr><td colspan=2>'
             . _('Body retrieval error. The reason for this is most probably that the message is malformed.')
             . '</td></tr>'
             . '<tr><td><b>'
             . _('Command:')
             . "</td><td>$cmd</td></tr>"
             . '<tr><td><b>'
             . _('Response:')
             . "</td><td>$response</td></tr>"
             . '<tr><td><b>'
             . _('Message:')
             . "</td><td>$message</td></tr>"
             . '<tr><td><b>'
             . _('FETCH line:')
             . "</td><td>$topline</td></tr>"
             . '</table><BR></tt></font><hr>';

        $data = sqimap_run_command($imap_stream, "FETCH $passed_id BODY[]", true, $response, $message, $uid_support);

        array_shift($data);

        $wholemessage = implode('', $data);

        $ret = $wholemessage;
    }

    return $ret;
}

function mime_print_body_lines($imap_stream, $id, $ent_id, $encoding)
{
    global $uid_support;

    $sid = sqimap_session_id($uid_support);

    /* Don't kill the connection if the browser is over a dialup
     * and it would take over 30 seconds to download it.
     * Don´t call set_time_limit in safe mode.
     */

    if (!ini_get('safe_mode')) {
        set_time_limit(0);
    }

    if ($uid_support) {
        $sid_s = mb_substr($sid, 0, mb_strpos($sid, ' '));
    } else {
        $sid_s = $sid;
    }

    $body = mime_fetch_body($imap_stream, $id, $ent_id);

    echo decodeBody($body, $encoding);

    /*
        fputs ($imap_stream, "$sid FETCH $id BODY[$ent_id]\r\n");
        $cnt = 0;
        $continue = true;
        $read = fgets ($imap_stream,8192);


        // This could be bad -- if the section has sqimap_session_id() . ' OK'
        // or similar, it will kill the download.
        while (!preg_match("^".$sid_s." (OK|BAD|NO)(.*)$", $read, $regs)) {
            if (trim($read) == ')==') {
                $read1 = $read;
                $read = fgets ($imap_stream,4096);
                if (preg_match("^".$sid." (OK|BAD|NO)(.*)$", $read, $regs)) {
                    return;
                } else {
                    echo decodeBody($read1, $encoding) .
                         decodeBody($read, $encoding);
                }
            } else if ($cnt) {
                echo decodeBody($read, $encoding);
            }
            $read = fgets ($imap_stream,4096);
            $cnt++;
    //      break;
        }
    */
}

/* -[ END MIME DECODING ]----------------------------------------------------------- */

/* This is here for debugging purposes.  It will print out a list
 * of all the entity IDs that are in the $message object.
 */
function listEntities($message)
{
    if ($message) {
        echo '<tt>' . $message->entity_id . ' : ' . $message->type0 . '/' . $message->type1 . ' parent = ' . $message->parent->entity_id . '<br>';

        for ($i = 0; isset($message->entities[$i]); $i++) {
            echo "$i : ";

            $msg = listEntities($message->entities[$i]);

            if ($msg) {
                echo 'return: ';

                return $msg;
            }
        }
    }
}

function getPriorityStr($priority)
{
    $priority_level = mb_substr($priority, 0, 1);

    switch ($priority_level) {
        /* Check for a higher then normal priority. */ case '1':
        case '2':
            $priority_string = _('High');
            break;
        /* Check for a lower then normal priority. */ case '4':
        case '5':
            $priority_string = _('Low');
            break;
        /* Check for a normal priority. */ case '3':
        default:
            $priority_level = '3';
            $priority_string = _('Normal');
            break;
    }

    return $priority_string;
}

/* returns a $message object for a particular entity id */
function getEntity($message, $ent_id)
{
    return $message->getEntity($ent_id);
}

/* translateText
 * Extracted from strings.php 23/03/2002
 */

function translateText(&$body, $wrap_at, $charset)
{
    global $where, $what;   /* from searching */ global $color;          /* color theme */

    require_once SM_PATH . 'functions/url_parser.php';

    $body_ary = explode("\n", $body);

    for ($i = 0, $iMax = count($body_ary); $i < $iMax; $i++) {
        $line = $body_ary[$i];

        if (mb_strlen($line) - 2 >= $wrap_at) {
            sqWordWrap($line, $wrap_at);
        }

        $line = charset_decode($charset, $line);

        $line = str_replace("\t", '        ', $line);

        parseUrl($line);

        $quotes = 0;

        $pos = 0;

        $j = mb_strlen($line);

        while ($pos < $j) {
            if (' ' == $line[$pos]) {
                $pos++;
            } elseif (mb_strpos($line, '&gt;', $pos) === $pos) {
                $pos += 4;

                $quotes++;
            } else {
                break;
            }
        }

        if ($quotes % 2) {
            if (!isset($color[13])) {
                $color[13] = '#800000';
            }

            $line = '<font color="' . $color[13] . '">' . $line . '</font>';
        } elseif ($quotes) {
            if (!isset($color[14])) {
                $color[14] = '#FF0000';
            }

            $line = '<font color="' . $color[14] . '">' . $line . '</font>';
        }

        $body_ary[$i] = $line;
    }

    $body = '<pre>' . implode("\n", $body_ary) . '</pre>';
}

/* This returns a parsed string called $body. That string can then
 * be displayed as the actual message in the HTML. It contains
 * everything needed, including HTML Tags, Attachments at the
 * bottom, etc.
 */
function formatBody($imap_stream, $message, $color, $wrap_at, $ent_num, $id, $mailbox = 'INBOX')
{
    /* This if statement checks for the entity to show as the
     * primary message. To add more of them, just put them in the
     * order that is their priority.
     */ global $startMessage, $username, $key, $imapServerAddress, $imapPort, $show_html_default, $sort, $has_unsafe_images, $passed_ent_id;

    global $languages, $squirrelmail_language;

    if (!sqgetGlobalVar('view_unsafe_images', $view_unsafe_images, SQ_GET)) {
        $view_unsafe_images = false;
    }

    $body = '';

    $urlmailbox = urlencode($mailbox);

    $body_message = getEntity($message, $ent_num);

    if (('text' == $body_message->header->type0)
        || ('rfc822' == $body_message->header->type0)) {
        $body = mime_fetch_body($imap_stream, $id, $ent_num);

        $body = decodeBody($body, $body_message->header->encoding);

        if (isset($languages[$squirrelmail_language]['XTRA_CODE'])
            && function_exists($languages[$squirrelmail_language]['XTRA_CODE'])) {
            if ('ASCII' != mb_detect_encoding($body, mb_detect_order(), true)) {
                $body = $languages[$squirrelmail_language]['XTRA_CODE']('decode', $body);
            }
        }

        $hookResults = do_hook('message_body', $body);

        $body = $hookResults[1];

        /* If there are other types that shouldn't be formatted, add
         * them here.
         */

        if ('html' == $body_message->header->type1) {
            if (1 != $show_html_default) {
                $entity_conv = [
                    '&nbsp;' => ' ',
                    '<p>' => "\n",
                    '<br>' => "\n",
                    '<P>' => "\n",
                    '<BR>' => "\n",
                    '&gt;' => '>',
                    '&lt;' => '<',
                ];

                $body = strtr($body, $entity_conv);

                $body = strip_tags($body);

                $body = trim($body);

                translateText(
                    $body,
                    $wrap_at,
                    $body_message->header->getParameter('charset')
                );
            } else {
                $body = magicHTML($body, $id, $message, $mailbox);
            }
        } else {
            translateText(
                $body,
                $wrap_at,
                $body_message->header->getParameter('charset')
            );
        }

        $link = 'read_body.php?passed_id=' . $id . '&amp;ent_id=' . $ent_num . '&amp;mailbox=' . $urlmailbox . '&amp;sort=' . $sort . '&amp;startMessage=' . $startMessage . '&amp;show_more=0';

        if (isset($passed_ent_id)) {
            $link .= '&amp;passed_ent_id=' . $passed_ent_id;
        }

        if ($view_unsafe_images) {
            $text = _('Hide Unsafe Images');
        } else {
            if (isset($has_unsafe_images) && $has_unsafe_images) {
                $link .= '&amp;view_unsafe_images=1';

                $text = _('View Unsafe Images');
            } else {
                $text = '';
            }
        }

        $body .= '<center><small><a href="' . $link . '">' . $text . '</a></small></center><br>' . "\n";
    }

    return $body;
}

function formatAttachments($message, $exclude_id, $mailbox, $id)
{
    global $where, $what, $startMessage, $color;

    static $ShownHTML = 0;

    $att_ar = $message->getAttachments($exclude_id);

    if (!count($att_ar)) {
        return '';
    }

    $attachments = '';

    $urlMailbox = urlencode($mailbox);

    foreach ($att_ar as $att) {
        $ent = $att->entity_id;

        $header = $att->header;

        $type0 = mb_strtolower($header->type0);

        $type1 = mb_strtolower($header->type1);

        $name = '';

        $links['download link']['text'] = _('download');

        $links['download link']['href'] = "../src/download.php?absolute_dl=true&amp;passed_id=$id&amp;mailbox=$urlMailbox&amp;ent_id=$ent";

        $ImageURL = '';

        if ('message' == $type0 && 'rfc822' == $type1) {
            $default_page = '../src/read_body.php';

            $rfc822_header = $att->rfc822_header;

            $filename = $rfc822_header->subject;

            if ('' == trim($filename)) {
                $filename = 'untitled-[' . $ent . ']';
            }

            $from_o = $rfc822_header->from;

            if (is_object($from_o)) {
                $from_name = $from_o->getAddress(false);
            } else {
                $from_name = _('Unknown sender');
            }

            $from_name = decodeHeader(($from_name));

            $description = $from_name;
        } else {
            $default_page = '../src/download.php';

            if (is_object($header->disposition)) {
                $filename = $header->disposition->getProperty('filename');

                if ('' == trim($filename)) {
                    $name = decodeHeader($header->disposition->getProperty('name'));

                    if ('' == trim($name)) {
                        $name = $header->getParameter('name');

                        if ('' == trim($name)) {
                            if ('' == trim($header->id)) {
                                $filename = 'untitled-[' . $ent . ']';
                            } else {
                                $filename = 'cid: ' . $header->id;
                            }
                        } else {
                            $filename = $name;
                        }
                    } else {
                        $filename = $name;
                    }
                }
            } else {
                $filename = $header->getParameter('name');

                if (!trim($filename)) {
                    if ('' == trim($header->id)) {
                        $filename = 'untitled-[' . $ent . ']';
                    } else {
                        $filename = 'cid: ' . $header->id;
                    }
                }
            }

            if ($header->description) {
                $description = decodeHeader($header->description);
            } else {
                $description = '';
            }
        }

        $display_filename = $filename;

        if (isset($passed_ent_id)) {
            $passed_ent_id_link = '&amp;passed_ent_id=' . $passed_ent_id;
        } else {
            $passed_ent_id_link = '';
        }

        $defaultlink = $default_page . "?startMessage=$startMessage" . "&amp;passed_id=$id&amp;mailbox=$urlMailbox" . '&amp;ent_id=' . $ent . $passed_ent_id_link . '&amp;absolute_dl=true';

        if ($where && $what) {
            $defaultlink .= '&amp;where=' . urlencode($where) . '&amp;what=' . urlencode($what);
        }

        /* This executes the attachment hook with a specific MIME-type.
         * If that doesn't have results, it tries if there's a rule
         * for a more generic type.
         */

        $hookresults = do_hook(
            "attachment $type0/$type1",
            $links,
            $startMessage,
            $id,
            $urlMailbox,
            $ent,
            $defaultlink,
            $display_filename,
            $where,
            $what
        );

        if (count($hookresults[1]) <= 1) {
            $hookresults = do_hook(
                "attachment $type0/*",
                $links,
                $startMessage,
                $id,
                $urlMailbox,
                $ent,
                $defaultlink,
                $display_filename,
                $where,
                $what
            );
        }

        $links = $hookresults[1];

        $defaultlink = $hookresults[6];

        $attachments .= '<TR><TD>' . '<A HREF="' . $defaultlink . '">' . decodeHeader($display_filename) . '</A>&nbsp;</TD>' . '<TD><SMALL><b>' . show_readable_size($header->size) . '</b>&nbsp;&nbsp;</small></TD>' . "<TD><SMALL>[ $type0/$type1 ]&nbsp;</SMALL></TD>" . '<TD><SMALL>';

        $attachments .= '<b>' . $description . '</b>';

        $attachments .= '</SMALL></TD><TD><SMALL>&nbsp;';

        $skipspaces = 1;

        foreach ($links as $val) {
            if ($skipspaces) {
                $skipspaces = 0;
            } else {
                $attachments .= '&nbsp;&nbsp;|&nbsp;&nbsp;';
            }

            $attachments .= '<a href="' . $val['href'] . '">' . $val['text'] . '</a>';
        }

        unset($links);

        $attachments .= "</TD></TR>\n";
    }

    return $attachments;
}

/* This function decodes the body depending on the encoding type. */
function decodeBody($body, $encoding)
{
    global $show_html_default;

    $body = str_replace("\r\n", "\n", $body);

    $encoding = mb_strtolower($encoding);

    if ('quoted-printable' == $encoding
        || 'quoted_printable' == $encoding) {
        $body = quoted_printable_decode($body);

        while (preg_match("=\n", $body)) {
            $body = preg_replace("=\n", '', $body);
        }
    } elseif ('base64' == $encoding) {
        $body = base64_decode($body, true);
    }

    // All other encodings are returned raw.

    return $body;
}

/*
 * This functions decode strings that is encoded according to
 * RFC1522 (MIME Part Two: Message Header Extensions for Non-ASCII Text).
 * Patched by Christian Schmidt <christian@ostenfeld.dk>  23/03/2002
 */
function decodeHeader($string, $utfencode = true, $htmlsave = true)
{
    global $languages, $squirrelmail_language;

    if (is_array($string)) {
        $string = implode("\n", $string);
    }

    if (isset($languages[$squirrelmail_language]['XTRA_CODE'])
        && function_exists($languages[$squirrelmail_language]['XTRA_CODE'])) {
        $string = $languages[$squirrelmail_language]['XTRA_CODE']('decodeheader', $string);

        // Do we need to return at this point?
        // return $string;
    }

    $i = 0;

    $aString = explode(' ', $string);

    foreach ($aString as $chunk) {
        $encoded = false;

        $aString[$i] = '';

        while (preg_match('/^(.*)=\?([^?]*)\?(Q|B)\?([^?]*)\?=(.*)$/Ui', $chunk, $res)) {
            $aString[$i] .= $res[1];

            //echo "$chunk match ". $res[5] . "<br>";

            $encoding = ucfirst($res[3]);

            switch ($encoding) {
                case 'B':
                    $replace = base64_decode($res[4], true);
                    $aString[$i] .= charset_decode($res[2], $replace);
                    break;
                case 'Q':
                    $replace = str_replace('_', ' ', $res[4]);
                    $replace = preg_replace(
                        '/=([0-9a-f]{2})/ie',
                        'chr(hexdec("\1"))',
                        $replace
                    );
                    /* Only encode into entities by default. Some places
                     * don't need the encoding, like the compose form.
                     */
                    if ($utfencode) {
                        $replace = charset_decode($res[2], $replace);
                    } else {
                        if ($htmlsave) {
                            $replace = htmlspecialchars($replace, ENT_QUOTES | ENT_HTML5);
                        }
                    }
                    $aString[$i] .= $replace;
                    break;
                default:
                    break;
            }

            $chunk = $res[5];

            $encoded = true;
        }

        if (!$encoded && $htmlsave) {
            $aString[$i] = htmlspecialchars($chunk, ENT_QUOTES | ENT_HTML5);
        } else {
            $aString[$i] .= $chunk;
        }

        ++$i;
    }

    return implode(' ', $aString);
}

/*
 * Encode a string according to RFC 1522 for use in headers if it
 * contains 8-bit characters or anything that looks like it should
 * be encoded.
 */
function encodeHeader($string)
{
    global $default_charset, $languages, $squirrelmail_language;

    if (isset($languages[$squirrelmail_language]['XTRA_CODE'])
        && function_exists($languages[$squirrelmail_language]['XTRA_CODE'])) {
        return $languages[$squirrelmail_language]['XTRA_CODE']('encodeheader', $string);
    }

    if ('iso-8859-1' == mb_strtolower($default_charset)) {
        $string = str_replace("\240", ' ', $string);
    }

    // Encode only if the string contains 8-bit characters or =?

    $j = mb_strlen($string);

    $max_l = 75 - mb_strlen($default_charset) - 7;

    $aRet = [];

    $ret = '';

    $iEncStart = $enc_init = false;

    $cur_l = $iOffset = 0;

    for ($i = 0; $i < $j; ++$i) {
        switch ($string[$i]) {
            case '=':
            case '<':
            case '>':
            case ',':
            case '?':
            case '_':
                if (false === $iEncStart) {
                    $iEncStart = $i;
                }
                $cur_l += 3;
                if ($cur_l > ($max_l - 2)) {
                    $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

                    $aRet[] = "=?$default_charset?Q?$ret?=";

                    $iOffset = $i;

                    $cur_l = 0;

                    $ret = '';

                    $iEncStart = false;
                } else {
                    $ret .= sprintf('=%02X', ord($string[$i]));
                }
                break;
            case '(':
            case ')':
                if (false !== $iEncStart) {
                    $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

                    $aRet[] = "=?$default_charset?Q?$ret?=";

                    $iOffset = $i;

                    $cur_l = 0;

                    $ret = '';

                    $iEncStart = false;
                }
                break;
            case ' ':
                if (false !== $iEncStart) {
                    $cur_l++;

                    if ($cur_l > $max_l) {
                        $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

                        $aRet[] = "=?$default_charset?Q?$ret?=";

                        $iOffset = $i;

                        $cur_l = 0;

                        $ret = '';

                        $iEncStart = false;
                    } else {
                        $ret .= '_';
                    }
                }
                break;
            default:
                $k = ord($string[$i]);
                if ($k > 126) {
                    if (false === $iEncStart) {
                        $iEncStart = $i;
                    }

                    $cur_l += 3;

                    if ($cur_l > ($max_l - 2)) {
                        if (false !== $iEncStart) {
                            $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

                            $aRet[] = "=?$default_charset?Q?$ret?=";
                        } else {
                            $aRet[] = mb_substr($string, $iOffset, $i - $iOffset);
                        }

                        $cur_l = 3;

                        $ret = '';

                        $iOffset = $i;
                    }

                    $enc_init = true;

                    $ret .= sprintf('=%02X', $k);
                } else {
                    if (false !== $iEncStart) {
                        $cur_l++;

                        if ($cur_l > $max_l) {
                            $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

                            $aRet[] = "=?$default_charset?Q?$ret?=";

                            $iEncStart = false;

                            $iOffset = $i;

                            $cur_l = 0;

                            $ret = '';
                        } else {
                            $ret .= $string[$i];
                        }
                    }
                }
                break;
        }
    }

    if ($enc_init) {
        if (false !== $iEncStart) {
            $aRet[] = mb_substr($string, $iOffset, $iEncStart - $iOffset);

            $aRet[] = "=?$default_charset?Q?$ret?=";
        } else {
            $aRet[] = mb_substr($string, $iOffset);
        }

        $string = implode('', $aRet);
    }

    return $string;
}

/* This function trys to locate the entity_id of a specific mime element */
function find_ent_id($id, $message)
{
    for ($i = 0, $ret = ''; '' == $ret && $i < count($message->entities); $i++) {
        if ('multipart' == $message->entities[$i]->header->type0) {
            $ret = find_ent_id($id, $message->entities[$i]);
        } else {
            if (0 == strcasecmp($message->entities[$i]->header->id, $id)) {
                //                if (sq_check_save_extension($message->entities[$i])) {

                return $message->entities[$i]->entity_id;
                //                }
            }
        }
    }

    return $ret;
}

function sq_check_save_extension($message)
{
    $filename = $message->getFilename();

    $ext = mb_substr($filename, mb_strrpos($filename, '.') + 1);

    $save_extensions = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];

    return in_array($ext, $save_extensions, true);
}

/**
 * * HTMLFILTER ROUTINES
 * @param mixed $tagname
 * @param mixed $attary
 * @param mixed $tagtype
 */

/**
 * This function returns the final tag out of the tag name, an array
 * of attributes, and the type of the tag. This function is called by
 * sq_sanitize internally.
 *
 * @param  the $tagname name of the tag.
 * @param  the $attary  array of attributes and their values
 * @param The  $tagtype type of the tag (see in comments).
 * @return           a string with the final tag representation.
 */
function sq_tagprint($tagname, $attary, $tagtype)
{
    $me = 'sq_tagprint';

    if (2 == $tagtype) {
        $fulltag = '</' . $tagname . '>';
    } else {
        $fulltag = '<' . $tagname;

        if (is_array($attary) && count($attary)) {
            $atts = [];

            while (list($attname, $attvalue) = each($attary)) {
                $atts[] = "$attname=$attvalue";
            }

            $fulltag .= ' ' . implode(' ', $atts);
        }

        if (3 == $tagtype) {
            $fulltag .= ' /';
        }

        $fulltag .= '>';
    }

    return $fulltag;
}

/**
 * A small helper function to use with array_walk. Modifies a by-ref
 * value and makes it lowercase.
 *
 * @param  a $val value passed by-ref.
 */
function sq_casenormalize(&$val)
{
    $val = mb_strtolower($val);
}

/**
 * This function skips any whitespace from the current position within
 * a string and to the next non-whitespace value.
 *
 * @param  the   $body string
 * @param  the $offset offset within the string where we should start
 *                 looking for the next non-whitespace character.
 * @return         the location within the $body where the next
 *                 non-whitespace char is located.
 */
function sq_skipspace($body, $offset)
{
    $me = 'sq_skipspace';

    preg_match('/^(\s*)/s', mb_substr($body, $offset), $matches);

    if (count($matches[1])) {
        $count = mb_strlen($matches[1]);

        $offset += $count;
    }

    return $offset;
}

/**
 * This function looks for the next character within a string.  It's
 * really just a glorified "strpos", except it catches if failures
 * nicely.
 *
 * @param The   $body   string to look for needle in.
 * @param Start $offset looking from this position.
 * @param The   $needle character/string to look for.
 * @return false|int of the next occurance of the needle, or
 *                      strlen($body) if needle wasn't found.
 */
function sq_findnxstr($body, $offset, $needle)
{
    $me = 'sq_findnxstr';

    $pos = mb_strpos($body, $needle, $offset);

    if (false === $pos) {
        $pos = mb_strlen($body);
    }

    return $pos;
}

/**
 * This function takes a PCRE-style regexp and tries to match it
 * within the string.
 *
 * @param The   $body   string to look for needle in.
 * @param Start $offset looking from here.
 * @param A     $reg    PCRE-style regex to match.
 * @return array|false a false if no matches found, or an array
 *                      with the following members:
 *                      - integer with the location of the match within $body
 *                      - string with whatever content between offset and the match
 *                      - string with whatever it is we matched
 */
function sq_findnxreg($body, $offset, $reg)
{
    $me = 'sq_findnxreg';

    $matches = [];

    $retarr = [];

    preg_match("%^(.*?)($reg)%s", mb_substr($body, $offset), $matches);

    if (!$matches[0]) {
        $retarr = false;
    } else {
        $retarr[0] = $offset + mb_strlen($matches[1]);

        $retarr[1] = $matches[1];

        $retarr[2] = $matches[2];
    }

    return $retarr;
}

/**
 * This function looks for the next tag.
 *
 * @param  String   $body where to look for the next tag.
 * @param  Start $offset looking from here.
 * @return         false if no more tags exist in the body, or
 *                 an array with the following members:
 *                 - string with the name of the tag
 *                 - array with attributes and their values
 *                 - integer with tag type (1, 2, or 3)
 *                 - integer where the tag starts (starting "<")
 *                 - integer where the tag ends (ending ">")
 *                 first three members will be false, if the tag is invalid.
 */
function sq_getnxtag($body, $offset)
{
    $me = 'sq_getnxtag';

    if ($offset > mb_strlen($body)) {
        return false;
    }

    $lt = sq_findnxstr($body, $offset, '<');

    if ($lt == mb_strlen($body)) {
        return false;
    }

    /**
     * We are here:
     * blah blah <tag attribute="value">
     * \---------^
     */

    $pos = sq_skipspace($body, $lt + 1);

    if ($pos >= mb_strlen($body)) {
        return [false, false, false, $lt, mb_strlen($body)];
    }

    /**
     * There are 3 kinds of tags:
     * 1. Opening tag, e.g.:
     *    <a href="blah">
     * 2. Closing tag, e.g.:
     *    </a>
     * 3. XHTML-style content-less tag, e.g.:
     *    <img src="blah">
     */

    $tagtype = false;

    switch (mb_substr($body, $pos, 1)) {
        case '/':
            $tagtype = 2;
            $pos++;
            break;
        case '!':
/**
 * A comment or an SGML declaration.
 */ if ('--' == mb_substr($body, $pos + 1, 2)) {
    $gt = mb_strpos($body, '-->', $pos);

    if (false === $gt) {
        $gt = mb_strlen($body);
    } else {
        $gt += 2;
    }

    return [false, false, false, $lt, $gt];
}  
                $gt = sq_findnxstr($body, $pos, '>');

                return [false, false, false, $lt, $gt];
            break;
        default:
/**
 * Assume tagtype 1 for now. If it's type 3, we'll switch values
 * later.
 */ $tagtype = 1;
            break;
    }

    $tag_start = $pos;

    $tagname = '';

    /**
     * Look for next [\W-_], which will indicate the end of the tag name.
     */

    $regary = sq_findnxreg($body, $pos, "[^\w\-_]");

    if (false === $regary) {
        return [false, false, false, $lt, mb_strlen($body)];
    }

    [$pos, $tagname, $match] = $regary;

    $tagname = mb_strtolower($tagname);

    /**
     * $match can be either of these:
     * '>'  indicating the end of the tag entirely.
     * '\s' indicating the end of the tag name.
     * '/'  indicating that this is type-3 xhtml tag.
     *
     * Whatever else we find there indicates an invalid tag.
     */

    switch ($match) {
        case '/':
/**
 * This is an xhtml-style tag with a closing / at the
 * end, like so: <img src="blah">. Check if it's followed
 * by the closing bracket. If not, then this tag is invalid
 */ if ('>' == mb_substr($body, $pos, 2)) {
    $pos++;

    $tagtype = 3;
} else {
    $gt = sq_findnxstr($body, $pos, '>');

    $retary = [false, false, false, $lt, $gt];

    return $retary;
}
        // no break
        case '>':
            return [$tagname, false, $tagtype, $lt, $pos];
            break;
        default:
/**
 * Check if it's whitespace
 */ if (!preg_match('/\s/', $match)) {
    /**
     * This is an invalid tag! Look for the next closing ">".
     */

    $gt = sq_findnxstr($body, $offset, '>');

    return [false, false, false, $lt, $gt];
}
            break;
    }

    /**
     * At this point we're here:
     * <tagname  attribute='blah'>
     * \-------^
     *
     * At this point we loop in order to find all attributes.
     */

    $attname = '';

    $atttype = false;

    $attary = [];

    while ($pos <= mb_strlen($body)) {
        $pos = sq_skipspace($body, $pos);

        if ($pos == mb_strlen($body)) {
            /**
             * Non-closed tag.
             */

            return [false, false, false, $lt, $pos];
        }

        /**
         * See if we arrived at a ">" or ">", which means that we reached
         * the end of the tag.
         */

        $matches = [];

        if (preg_match("%^(\s*)(>|>)%s", mb_substr($body, $pos), $matches)) {
            /**
             * Yep. So we did.
             */

            $pos += mb_strlen($matches[1]);

            if ('>' == $matches[2]) {
                $tagtype = 3;

                $pos++;
            }

            return [$tagname, $attary, $tagtype, $lt, $pos];
        }

        /**
         * There are several types of attributes, with optional
         * [:space:] between members.
         * Type 1:
         *   attrname[:space:]=[:space:]'CDATA'
         * Type 2:
         *   attrname[:space:]=[:space:]"CDATA"
         * Type 3:
         *   attr[:space:]=[:space:]CDATA
         * Type 4:
         *   attrname
         *
         * We leave types 1 and 2 the same, type 3 we check for
         * '"' and convert to "&quot" if needed, then wrap in
         * double quotes. Type 4 we convert into:
         * attrname="yes".
         */

        $regary = sq_findnxreg($body, $pos, "[^\w\-_]");

        if (false === $regary) {
            /**
             * Looks like body ended before the end of tag.
             */

            return [false, false, false, $lt, mb_strlen($body)];
        }

        [$pos, $attname, $match] = $regary;

        $attname = mb_strtolower($attname);

        /**
         * We arrived at the end of attribute name. Several things possible
         * here:
         * '>'  means the end of the tag and this is attribute type 4
         * '/'  if followed by '>' means the same thing as above
         * '\s' means a lot of things -- look what it's followed by.
         *      anything else means the attribute is invalid.
         */

        switch ($match) {
            case '/':
/**
 * This is an xhtml-style tag with a closing / at the
 * end, like so: <img src="blah">. Check if it's followed
 * by the closing bracket. If not, then this tag is invalid
 */ if ('>' == mb_substr($body, $pos, 2)) {
    $pos++;

    $tagtype = 3;
} else {
    $gt = sq_findnxstr($body, $pos, '>');

    $retary = [false, false, false, $lt, $gt];

    return $retary;
}
            // no break
            case '>':
                $attary[$attname] = '"yes"';

                return [$tagname, $attary, $tagtype, $lt, $pos];
                break;
            default:
/**
 * Skip whitespace and see what we arrive at.
 */ $pos = sq_skipspace($body, $pos);
                $char = mb_substr($body, $pos, 1);
                /**
                 * Two things are valid here:
                 * '=' means this is attribute type 1 2 or 3.
                 * \w means this was attribute type 4.
                 * anything else we ignore and re-loop. End of tag and
                 * invalid stuff will be caught by our checks at the beginning
                 * of the loop.
                 */
                if ('=' == $char) {
                    $pos++;

                    $pos = sq_skipspace($body, $pos);

                    /**
                     * Here are 3 possibilities:
                     * "'"  attribute type 1
                     * '"'  attribute type 2
                     * everything else is the content of tag type 3
                     */

                    $quot = mb_substr($body, $pos, 1);

                    if ("'" == $quot) {
                        $regary = sq_findnxreg($body, $pos + 1, "\'");

                        if (false === $regary) {
                            return [false, false, false, $lt, mb_strlen($body)];
                        }

                        [$pos, $attval, $match] = $regary;

                        $pos++;

                        $attary[$attname] = "'" . $attval . "'";
                    } elseif ('"' == $quot) {
                        $regary = sq_findnxreg($body, $pos + 1, '\"');

                        if (false === $regary) {
                            return [false, false, false, $lt, mb_strlen($body)];
                        }

                        [$pos, $attval, $match] = $regary;

                        $pos++;

                        $attary[$attname] = '"' . $attval . '"';
                    } else {
                        /**
                         * These are hateful. Look for \s, or >.
                         */

                        $regary = sq_findnxreg($body, $pos, "[\s>]");

                        if (false === $regary) {
                            return [false, false, false, $lt, mb_strlen($body)];
                        }

                        [$pos, $attval, $match] = $regary;

                        /**
                         * If it's ">" it will be caught at the top.
                         */

                        $attval = preg_replace('/"/s', '&quot;', $attval);

                        $attary[$attname] = '"' . $attval . '"';
                    }
                } elseif (preg_match("|[\w>]|", $char)) {
                    /**
                     * That was attribute type 4.
                     */

                    $attary[$attname] = '"yes"';
                } else {
                    /**
                     * An illegal character. Find next '>' and return.
                     */

                    $gt = sq_findnxstr($body, $pos, '>');

                    return [false, false, false, $lt, $gt];
                }
                break;
        }
    }

    /**
     * The fact that we got here indicates that the tag end was never
     * found. Return invalid tag indication so it gets stripped.
     */

    return [false, false, false, $lt, mb_strlen($body)];
}

/**
 * This function checks attribute values for entity-encoded values
 * and returns them translated into 8-bit strings so we can run
 * checks on them.
 *
 * @param  A $attvalue string to run entity check against.
 * @return           Translated value.
 */
function sq_deent($attvalue)
{
    $me = 'sq_deent';

    /**
     * See if we have to run the checks first. All entities must start
     * with "&".
     */

    if (false === mb_strpos($attvalue, '&')) {
        return $attvalue;
    }

    /**
     * Check named entities first.
     */

    $trans = get_html_translation_table(HTML_ENTITIES);

    /**
     * Leave &quot; in, as it can mess us up.
     */

    $trans = array_flip($trans);

    unset($trans['&quot;']);

    while (list($ent, $val) = each($trans)) {
        $attvalue = preg_replace("/$ent*(\W)/si", "$val\\1", $attvalue);
    }

    /**
     * Now translate numbered entities from 1 to 255 if needed.
     */

    if (false !== mb_strpos($attvalue, '#')) {
        $omit = [34, 39];

        for ($asc = 1; $asc < 256; $asc++) {
            if (!in_array($asc, $omit, true)) {
                $chr = chr($asc);

                $attvalue = preg_replace(
                    "/\&#0*$asc;*(\D)/si",
                    "$chr\\1",
                    $attvalue
                );

                $attvalue = preg_replace(
                    "/\&#x0*" . dechex($asc) . ";*(\W)/si",
                    "$chr\\1",
                    $attvalue
                );
            }
        }
    }

    return $attvalue;
}

/**
 * This function runs various checks against the attributes.
 *
 * @param  String         $tagname with the name of the tag.
 * @param  Array          $attary with all tag attributes.
 * @param  See     $rm_attnames description for sq_sanitize
 * @param  See     $bad_attvals description for sq_sanitize
 * @param  See $add_attr_to_tag description for sq_sanitize
 * @param  message         $message object
 * @param  message              $id id
 * @param mixed $mailbox
 * @return                  array with modified attributes.
 */
function sq_fixatts(
    $tagname,
    $attary,
    $rm_attnames,
    $bad_attvals,
    $add_attr_to_tag,
    $message,
    $id,
    $mailbox
) {
    $me = 'sq_fixatts';

    while (list($attname, $attvalue) = each($attary)) {
        /**
         * See if this attribute should be removed.
         */

        foreach ($rm_attnames as $matchtag => $matchattrs) {
            if (preg_match($matchtag, $tagname)) {
                foreach ($matchattrs as $matchattr) {
                    if (preg_match($matchattr, $attname)) {
                        unset($attary[$attname]);

                        continue;
                    }
                }
            }
        }

        /**
         * Remove any entities.
         */

        $attvalue = sq_deent($attvalue);

        /**
         * Now let's run checks on the attvalues.
         * I don't expect anyone to comprehend this. If you do,
         * get in touch with me so I can drive to where you live and
         * shake your hand personally. :)
         */

        foreach ($bad_attvals as $matchtag => $matchattrs) {
            if (preg_match($matchtag, $tagname)) {
                foreach ($matchattrs as $matchattr => $valary) {
                    if (preg_match($matchattr, $attname)) {
                        /**
                         * There are two arrays in valary.
                         * First is matches.
                         * Second one is replacements
                         */

                        [$valmatch, $valrepl] = $valary;

                        $newvalue = preg_replace($valmatch, $valrepl, $attvalue);

                        if ($newvalue != $attvalue) {
                            $attary[$attname] = $newvalue;
                        }
                    }
                }
            }
        }

        /**
         * Turn cid: urls into http-friendly ones.
         */

        if (preg_match("/^[\'\"]\s*cid:/si", $attvalue)) {
            $attary[$attname] = sq_cid2http($message, $id, $attvalue, $mailbox);
        }
    }

    /**
     * See if we need to append any attributes to this tag.
     */

    foreach ($add_attr_to_tag as $matchtag => $addattary) {
        if (preg_match($matchtag, $tagname)) {
            $attary = array_merge($attary, $addattary);
        }
    }

    return $attary;
}

/**
 * This function edits the style definition to make them friendly and
 * usable in squirrelmail.
 *
 * @param  the  $message message object
 * @param  the       $id message id
 * @param  a  $content string with whatever is between <style> and </style>
 * @return           a string with edited content.
 */
function sq_fixstyle($message, $id, $content)
{
    global $view_unsafe_images;

    $me = 'sq_fixstyle';

    /**
     * First look for general BODY style declaration, which would be
     * like so:
     * body {background: blah-blah}
     * and change it to .bodyclass so we can just assign it to a <div>
     */

    $content = preg_replace("|body(\s*\{.*?\})|si", '.bodyclass\\1', $content);

    $secremoveimg = '../images/' . _('sec_remove_eng.png');

    /**
     * Fix url('blah') declarations.
     */

    $content = preg_replace(
        "|url\(([\'\"])\s*\S+script\s*:.*?([\'\"])\)|si",
        "url(\\1$secremoveimg\\2)",
        $content
    );

    /**
     * Fix url('https*://.*) declarations but only if $view_unsafe_images
     * is false.
     */

    if (!$view_unsafe_images) {
        $content = preg_replace(
            "|url\(([\'\"])\s*https*:.*?([\'\"])\)|si",
            "url(\\1$secremoveimg\\2)",
            $content
        );
    }

    /**
     * Fix urls that refer to cid:
     */

    while (preg_match(
        "|url\(([\'\"]\s*cid:.*?[\'\"])\)|si",
        $content,
        $matches
    )) {
        $cidurl = $matches[1];

        $httpurl = sq_cid2http($message, $id, $cidurl);

        $content = preg_replace(
            "|url\($cidurl\)|si",
            "url($httpurl)",
            $content
        );
    }

    /**
     * Fix stupid css declarations which lead to vulnerabilities
     * in IE.
     */

    $match = [
        '/expression/si',
        '/behaviou*r/si',
        '/binding/si',
    ];

    $replace = ['idiocy', 'idiocy', 'idiocy'];

    $content = preg_replace($match, $replace, $content);

    return $content;
}

/**
 * This function converts cid: url's into the ones that can be viewed in
 * the browser.
 *
 * @param  the  $message message object
 * @param  the       $id message id
 * @param  the   $cidurl cid: url.
 * @param mixed $mailbox
 * @return           a string with a http-friendly url
 */
function sq_cid2http($message, $id, $cidurl, $mailbox)
{
    /**
     * Get rid of quotes.
     */

    $quotchar = mb_substr($cidurl, 0, 1);

    $cidurl = str_replace($quotchar, '', $cidurl);

    $cidurl = mb_substr(trim($cidurl), 4);

    $linkurl = find_ent_id($cidurl, $message);

    /* in case of non-save cid links $httpurl should be replaced by a sort of
       unsave link image */

    $httpurl = '';

    if ($linkurl) {
        $httpurl = $quotchar . '../src/download.php?absolute_dl=true&amp;' . "passed_id=$id&amp;mailbox=" . urlencode($mailbox) . '&amp;ent_id=' . $linkurl . $quotchar;
    }

    return $httpurl;
}

/**
 * This function changes the <body> tag into a <div> tag since we
 * can't really have a body-within-body.
 *
 * @param an $attary array of attributes and values of <body>
 * @return string[] modified array of attributes to be set for <div>
 */
function sq_body2div($attary)
{
    $me = 'sq_body2div';

    $divattary = ['class' => "'bodyclass'"];

    $bgcolor = '#ffffff';

    $text = '#000000';

    $styledef = '';

    if (is_array($attary) && count($attary) > 0) {
        foreach ($attary as $attname => $attvalue) {
            $quotchar = mb_substr($attvalue, 0, 1);

            $attvalue = str_replace($quotchar, '', $attvalue);

            switch ($attname) {
                case 'background':
                    $styledef .= "background-image: url('$attvalue'); ";
                    break;
                case 'bgcolor':
                    $styledef .= "background-color: $attvalue; ";
                    break;
                case 'text':
                    $styledef .= "color: $attvalue; ";
                    break;
            }
        }

        if (mb_strlen($styledef) > 0) {
            $divattary['style'] = "\"$styledef\"";
        }
    }

    return $divattary;
}

/**
 * This is the main function and the one you should actually be calling.
 * There are several variables you should be aware of an which need
 * special description.
 *
 * Since the description is quite lengthy, see it here:
 * http://www.mricon.com/html/phpfilter.html
 *
 * @param the                 $body string with HTML you wish to filter
 * @param see             $tag_list description above
 * @param see $rm_tags_with_content description above
 * @param see    $self_closing_tags description above
 * @param see    $force_tag_closing description above
 * @param see          $rm_attnames description above
 * @param see          $bad_attvals description above
 * @param see      $add_attr_to_tag description above
 * @param message              $message object
 * @param message                   $id id
 * @param mixed $mailbox
 * @return                      sanitized html safe to show on your pages.
 */
function sq_sanitize(
    $body,
    $tag_list,
    $rm_tags_with_content,
    $self_closing_tags,
    $force_tag_closing,
    $rm_attnames,
    $bad_attvals,
    $add_attr_to_tag,
    $message,
    $id,
    $mailbox
) {
    $me = 'sq_sanitize';

    /**
     * Normalize rm_tags and rm_tags_with_content.
     */

    @array_walk($rm_tags, 'sq_casenormalize');

    @array_walk($rm_tags_with_content, 'sq_casenormalize');

    @array_walk($self_closing_tags, 'sq_casenormalize');

    /**
     * See if tag_list is of tags to remove or tags to allow.
     * false  means remove these tags
     * true   means allow these tags
     */

    $rm_tags = array_shift($tag_list);

    $curpos = 0;

    $open_tags = [];

    $trusted = "<!-- begin sanitized html -->\n";

    $skip_content = false;

    /**
     * Take care of netscape's stupid javascript entities like
     * &{alert('boo')};
     */

    $body = preg_replace("/&(\{.*?\};)/si", '&amp;\\1', $body);

    while (false !== ($curtag = sq_getnxtag($body, $curpos))) {
        [$tagname, $attary, $tagtype, $lt, $gt] = $curtag;

        $free_content = mb_substr($body, $curpos, $lt - $curpos);

        /**
         * Take care of <style>
         */

        if ('style' == $tagname && 2 == $tagtype) {
            /**
             * This is a closing </style>. Edit the
             * content before we apply it.
             */

            $free_content = sq_fixstyle($message, $id, $free_content);
        }

        if (false === $skip_content) {
            $trusted .= $free_content;
        }

        if (false !== $tagname) {
            if (2 == $tagtype) {
                if ($skip_content == $tagname) {
                    /**
                     * Got to the end of tag we needed to remove.
                     */

                    $tagname = false;

                    $skip_content = false;
                } else {
                    if (false === $skip_content) {
                        if ('body' == $tagname) {
                            $tagname = 'div';
                        } else {
                            if (isset($open_tags[$tagname])
                                && $open_tags[$tagname] > 0) {
                                $open_tags[$tagname]--;
                            } else {
                                $tagname = false;
                            }
                        }
                    }
                }
            } else {
                /**
                 * $rm_tags_with_content
                 */

                if (false === $skip_content) {
                    /**
                     * See if this is a self-closing type and change
                     * tagtype appropriately.
                     */

                    if (1 == $tagtype
                        && in_array($tagname, $self_closing_tags, true)) {
                        $tagtype = 3;
                    }

                    /**
                     * See if we should skip this tag and any content
                     * inside it.
                     */

                    if (1 == $tagtype
                        && in_array($tagname, $rm_tags_with_content, true)) {
                        $skip_content = $tagname;
                    } else {
                        if ((false === $rm_tags
                             && in_array($tagname, $tag_list, true))
                            || (true === $rm_tags
                                && !in_array($tagname, $tag_list, true))) {
                            $tagname = false;
                        } else {
                            if (1 == $tagtype) {
                                if (isset($open_tags[$tagname])) {
                                    $open_tags[$tagname]++;
                                } else {
                                    $open_tags[$tagname] = 1;
                                }
                            }

                            /**
                             * This is where we run other checks.
                             */

                            if (is_array($attary) && count($attary) > 0) {
                                $attary = sq_fixatts(
                                    $tagname,
                                    $attary,
                                    $rm_attnames,
                                    $bad_attvals,
                                    $add_attr_to_tag,
                                    $message,
                                    $id,
                                    $mailbox
                                );
                            }

                            /**
                             * Convert body into div.
                             */

                            if ('body' == $tagname) {
                                $tagname = 'div';

                                $attary = sq_body2div($attary, $message, $id);
                            }
                        }
                    }
                }
            }

            if (false !== $tagname && false === $skip_content) {
                $trusted .= sq_tagprint($tagname, $attary, $tagtype);
            }
        }

        $curpos = $gt + 1;
    }

    $trusted .= mb_substr($body, $curpos, mb_strlen($body) - $curpos);

    if (true === $force_tag_closing) {
        foreach ($open_tags as $tagname => $opentimes) {
            while ($opentimes > 0) {
                $trusted .= '</' . $tagname . '>';

                $opentimes--;
            }
        }

        $trusted .= "\n";
    }

    $trusted .= "<!-- end sanitized html -->\n";

    return $trusted;
}

/**
 * This is a wrapper function to call html sanitizing routines.
 *
 * @param  the  $body body of the message
 * @param  the    $id id of the message
 * @param mixed $message
 * @param mixed $mailbox
 * @return        a string with html safe to display in the browser.
 */
function magicHTML($body, $id, $message, $mailbox = 'INBOX')
{
    global $attachment_common_show_images, $view_unsafe_images, $has_unsafe_images;

    /**
     * Don't display attached images in HTML mode.
     */

    $attachment_common_show_images = false;

    $tag_list = [
        false,
        'object',
        'meta',
        'html',
        'head',
        'base',
        'link',
        'frame',
        'iframe',
    ];

    $rm_tags_with_content = [
        'script',
        'applet',
        'embed',
        'title',
    ];

    $self_closing_tags = [
        'img',
        'br',
        'hr',
        'input',
    ];

    $force_tag_closing = false;

    $rm_attnames = [
        '/.*/' => [
            '/target/si',
            '/^on.*/si',
            '/^dynsrc/si',
            '/^data.*/si',
        ],
    ];

    $secremoveimg = '../images/' . _('sec_remove_eng.png');

    $bad_attvals = [
        '/.*/' => [
            '/^src|background/i' => [
                [
                    "|^([\'\"])\s*\.\./.*([\'\"])|si",
                    "/^([\'\"])\s*\S+script\s*:.*([\'\"])/si",
                    "/^([\'\"])\s*mocha\s*:*.*([\'\"])/si",
                    "/^([\'\"])\s*about\s*:.*([\'\"])/si",
                ],
                [
                    "\\1$secremoveimg\\2",
                    "\\1$secremoveimg\\2",
                    "\\1$secremoveimg\\2",
                    "\\1$secremoveimg\\2",
                ],
            ],
            '/^href|action/i' => [
                [
                    "|^([\'\"])\s*\.\./.*([\'\"])|si",
                    "/^([\'\"])\s*\S+script\s*:.*([\'\"])/si",
                    "/^([\'\"])\s*mocha\s*:*.*([\'\"])/si",
                    "/^([\'\"])\s*about\s*:.*([\'\"])/si",
                ],
                [
                    '\\1#\\2',
                    '\\1#\\2',
                    '\\1#\\2',
                    '\\1#\\2',
                ],
            ],
            '/^style/si' => [
                [
                    '/expression/si',
                    '/binding/si',
                    '/behaviou*r/si',
                    "|url\(([\'\"])\s*\.\./.*([\'\"])\)|si",
                    "/url\(([\'\"])\s*\S+script\s*:.*([\'\"])\)/si",
                    "/url\(([\'\"])\s*mocha\s*:.*([\'\"])\)/si",
                    "/url\(([\'\"])\s*about\s*:.*([\'\"])\)/si",
                ],
                [
                    'idiocy',
                    'idiocy',
                    'idiocy',
                    'url(\\1#\\2)',
                    'url(\\1#\\2)',
                    'url(\\1#\\2)',
                    'url(\\1#\\2)',
                ],
            ],
        ],
    ];

    if (!sqgetGlobalVar('view_unsafe_images', $view_unsafe_images, SQ_GET)) {
        $view_unsafe_images = false;
    }

    if (!$view_unsafe_images) {
        /**
         * Remove any references to http/https if view_unsafe_images set
         * to false.
         */

        $bad_attvals['/.*/']['/^src|background/i'][0][] = '/^([\'\"])\s*https*:.*([\'\"])/si';

        $bad_attvals['/.*/']['/^src|background/i'][1][] = "\\1$secremoveimg\\2";

        $bad_attvals['/.*/']['/^style/si'][0][] = '/url\(([\'\"])\s*https*:.*([\'\"])\)/si';

        $bad_attvals['/.*/']['/^style/si'][1][] = "url(\\1$secremoveimg\\2)";
    }

    $add_attr_to_tag = [
        '/^a$/si' => ['target' => '"_new"'],
    ];

    $trusted = sq_sanitize(
        $body,
        $tag_list,
        $rm_tags_with_content,
        $self_closing_tags,
        $force_tag_closing,
        $rm_attnames,
        $bad_attvals,
        $add_attr_to_tag,
        $message,
        $id,
        $mailbox
    );

    if (preg_match("|$secremoveimg|si", $trusted)) {
        $has_unsafe_images = true;
    }

    return $trusted;
}
