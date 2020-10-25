<?php

function sqimap_parse_RFC822Header($read, $hdr)
{
    $i = 0;

    /* Set up some defaults */

    $hdr->type0 = 'text';

    $hdr->type1 = 'plain';

    $hdr->charset = 'us-ascii';

    $count = count($read);

    while ($i < $count) {
        /* unfold multi-line headers */

        while (($i + 1 < $count) && (strspn($read[$i + 1], "\t ") > 0)) {
            $read[$i + 1] = mb_substr($read[$i], 0, -2) . ' ' . ltrim($read[$i + 1]);

            array_splice($read, $i, 1);

            $count--;
        }

        $line = $read[$i];

        $c = mb_strtolower($line[0]);

        switch ($c) {
            case 'm':
                $c2 = mb_strtolower($line[1]);
                switch ($c2) {
                    case 'i':
                        if ('MIME-Version: 1.0' == mb_substr($line, 0, 17)) {
                            $hdr->mime = true;
                        }
                        $i++;
                        break;
                    case 'e':
                        /* MESSAGE ID */ if ('message-id:' == mb_strtolower(mb_substr($line, 0, 11))) {
                            $hdr->message_id = trim(mb_substr($line, 11));
                        }
                        $i++;
                        break;
                    default:
                        $i++;
                        break;
                }
                break;
            case 'c':
                $c2 = mb_strtolower($line[1]);
                switch ($c2) {
                    case 'o':
                        /* Content-Transfer-Encoding */ if ('content-transfer-encoding:' == mb_substr(mb_strtolower($line), 0, 26)) {
                            $hdr->encoding = mb_strtolower(trim(mb_substr($line, 26)));

                            $i++;
                        } /* Content-Type */ elseif ('content-type:' == mb_strtolower(mb_substr($line, 0, 13))) {
                            $cont = mb_strtolower(trim(mb_substr($line, 13)));

                            if (mb_strpos($cont, ';')) {
                                $cont = mb_substr($cont, 0, mb_strpos($cont, ';'));
                            }

                            if (mb_strpos($cont, '/')) {
                                $hdr->type0 = mb_substr($cont, 0, mb_strpos($cont, '/'));

                                $hdr->type1 = mb_substr($cont, mb_strpos($cont, '/') + 1);
                            } else {
                                $hdr->type0 = $cont;
                            }

                            $line = $read[$i];

                            $i++;

                            while ((':' != mb_substr(mb_substr($read[$i], 0, mb_strpos($read[$i], ' ')), -1)) && ('' != trim($read[$i])) && (')' != trim($read[$i]))) {
                                str_replace("\n", '', $line);

                                str_replace("\n", '', $read[$i]);

                                $line = "$line $read[$i]";

                                $i++;
                            }

                            /* Detect the boundary of a multipart message */

                            if (eregi('boundary="([^"]+)"', $line, $regs)) {
                                $hdr->boundary = $regs[1];
                            }

                            /* Detect the charset */

                            if (mb_strpos(mb_strtolower(trim($line)), 'charset=')) {
                                $pos = mb_strpos($line, 'charset=') + 8;

                                $charset = trim($line);

                                if (mb_strpos($line, ';', $pos) > 0) {
                                    $charset = mb_substr($charset, $pos, mb_strpos($line, ';', $pos) - $pos);
                                } else {
                                    $charset = mb_substr($charset, $pos);
                                }

                                $charset = str_replace('"', '', $charset);

                                $hdr->charset = $charset;
                            } else {
                                $hdr->charset = 'us-ascii';
                            }

                            /* Detect type in case of multipart/related */

                            if (mb_strpos(mb_strtolower(trim($line)), 'type=')) {
                                $pos = mb_strpos($line, 'type=') + 6;

                                $type = trim($line);

                                if (mb_strpos($line, ';', $pos) > 0) {
                                    $type = mb_substr($type, $pos, mb_strpos($line, ';', $pos) - $pos);
                                } else {
                                    $type = mb_substr($type, $pos);
                                }

                                $hdr->type = $type;
                            }
                        } elseif ('content-disposition:' == mb_strtolower(mb_substr($line, 0, 20))) {
                            /* Add better content-disposition support */

                            $i++;

                            while ((':' != mb_substr(mb_substr($read[$i], 0, mb_strpos($read[$i], ' ')), -1)) && ('' != trim($read[$i])) && (')' != trim($read[$i]))) {
                                str_replace("\n", '', $line);

                                str_replace("\n", '', $read[$i]);

                                $line = "$line $read[$i]";

                                $i++;
                            }

                            /* Detects filename if any */

                            if (mb_strpos(mb_strtolower(trim($line)), 'filename=')) {
                                $pos = mb_strpos($line, 'filename=') + 9;

                                $name = trim($line);

                                if (mb_strpos($line, ' ', $pos) > 0) {
                                    $name = mb_substr($name, $pos, mb_strpos($line, ' ', $pos));
                                } else {
                                    $name = mb_substr($name, $pos);
                                }

                                $name = str_replace('"', '', $name);

                                $hdr->filename = $name;
                            }
                        } else {
                            $i++;
                        }
                        break;
                    case 'c': /* Cc */ if ('cc:' == mb_strtolower(mb_substr($line, 0, 3))) {
                        $hdr->cc = sqimap_parse_address(trim(mb_substr($line, 3, -1)), true);
                    }
                        $i++;
                        break;
                    default:
                        $i++;
                        break;
                }
                break;
            case 'r': /* Reply-To */ if ('reply-to:' == mb_strtolower(mb_substr($line, 0, 9))) {
                $hdr->replyto = sqimap_parse_address(trim(mb_substr($line, 9, -1)), false);
            }
                $i++;
                break;
            case 'f': /* From */ if ('from:' == mb_strtolower(mb_substr($line, 0, 5))) {
                $hdr->from = sqimap_parse_address(trim(mb_substr($line, 5, -1)), false);

                if (!isset($hdr->replyto) || '' == $hdr->replyto) {
                    $hdr->replyto = $hdr->from;
                }
            }
                $i++;
                break;
            case 'd':
                $c2 = mb_strtolower($line[1]);
                switch ($c2) {
                    case 'a': /* Date */ if ('date:' == mb_strtolower(mb_substr($line, 0, 5))) {
                        $d = mb_substr($read[$i], 5);

                        $d = trim($d);

                        $d = strtr($d, ['  ' => ' ']);

                        $d = explode(' ', $d);

                        $hdr->date = getTimeStamp($d);
                    }
                        $i++;
                        break;
                    case 'i': /* Disposition-Notification-To */ if ('disposition-notification-to:' == mb_strtolower(mb_substr($line, 0, 28))) {
                        $dnt = trim(mb_substr($read[$i], 28));

                        $hdr->dnt = sqimap_parse_address($dnt, false);
                    }
                        $i++;
                        break;
                    default:
                        $i++;
                        break;
                }
                break;
            case 's':
                /* SUBJECT */ if ('subject:' == mb_strtolower(mb_substr($line, 0, 8))) {
                    $hdr->subject = trim(mb_substr($line, 8, -1));

                    if (0 == mb_strlen(rtrim($hdr->subject))) {
                        $hdr->subject = _('(no subject)');
                    }
                }
                $i++;
                break;
            case 'b':
                /* BCC */ if ('bcc:' == mb_strtolower(mb_substr($line, 0, 4))) {
                    $hdr->bcc = sqimap_parse_address(trim(mb_substr($line, 4, -1)), true);
                }
                $i++;
                break;
            case 't':
                /* TO */ if ('to:' == mb_strtolower(mb_substr($line, 0, 3))) {
                    $hdr->to = sqimap_parse_address(trim(mb_substr($line, 3, -1)), true);
                }
                $i++;
                break;
            case ')':
                /* ERROR CORRECTION */ if (0 == mb_strlen(trim($hdr->subject))) {
                    $hdr->subject = _('(no subject)');
                }
                if (!is_object($hdr->from) && 0 == mb_strlen(trim($hdr->from))) {
                    $hdr->from = _('(unknown sender)');
                }
                if (0 == mb_strlen(trim($hdr->date))) {
                    $hdr->date = time();
                }
                $i++;
                break;
            case 'x':
                /* X-PRIORITY */ if ('x-priority:' == mb_strtolower(mb_substr($line, 0, 11))) {
                    $hdr->priority = trim(mb_substr($line, 11));
                } elseif ('x-mailer:' == mb_strtolower(mb_substr($line, 0, 9))) {
                    $hdr->xmailer = trim(mb_substr($line, 9));
                }
                $i++;
                break;
            case 'u':
                /* User-Agent */ if ('user-agent' == mb_strtolower(mb_substr($line, 0, 10))) {
                    $hdr->xmailer = trim(mb_substr($line, 10));
                }
                $i++;
                break;
            default:
                $i++;
                break;
        }
    }

    return $hdr;
}

/**
 * function to process addresses.
 * @param mixed $address
 * @param mixed $ar
 * @param mixed $addr_ar
 * @param mixed $group
 * @return \AddressStructure|array|mixed
 * @return \AddressStructure|array|mixed
 */
function sqimap_parse_address($address, $ar, $addr_ar = [], $group = '')
{
    $pos = 0;

    $j = mb_strlen($address);

    $name = '';

    $addr = '';

    while ($pos < $j) {
        if ('"' == $address[$pos]) { /* get the personal name */
            $pos++;

            while ('"' != $address[$pos]
                   && $pos < $j) {
                if ('\\"' == mb_substr($address, $pos, 2)) {
                    $name .= $address[$pos];

                    $pos++;
                } elseif ('\\\\' == mb_substr($address, $pos, 2)) {
                    $name .= $address[$pos];

                    $pos++;
                }

                $name .= $address[$pos];

                $pos++;
            }
        } elseif ('<' == $address[$pos]) { /* get email address */
            $addr_start = $pos;

            $pos++;

            while ('>' != $address[$pos]
                   && $pos < $j) {
                $addr .= $address[$pos];

                $pos++;
            }
        } elseif ('(' == $address[$pos]) { /* rip off comments */
            $addr_start = $pos;

            $pos++;

            while (')' != $address[$pos]
                   && $pos < $j) {
                $addr .= $address[$pos];

                $pos++;
            }

            $address_start = mb_substr($address, 0, $addr_start);

            $address_end = mb_substr($address, $pos + 1);

            $address = $address_start . $address_end;

            $j = mb_strlen($address);

            $pos = $addr_start;
        } elseif (',' == $address[$pos]) { /* we reached a delimiter */
            if ('' == $addr) {
                $addr = mb_substr($address, 0, $pos);
            } elseif ('' == $name) {
                $name = mb_substr($address, 0, $addr_start);
            }

            $at = mb_strpos($addr, '@');

            $addr_structure = new AddressStructure();

            $addr_structure->personal = $name;

            $addr_structure->group = $group;

            if ($at) {
                $addr_structure->mailbox = mb_substr($addr, 0, $at);

                $addr_structure->host = mb_substr($addr, $at + 1);
            } else {
                $addr_structure->mailbox = $addr;
            }

            $address = mb_substr($address, $pos + 1);

            $j = mb_strlen($address);

            $pos = 0;

            $name = '';

            $addr = '';

            $addr_ar[] = $addr_structure;
        } elseif (':' == $address[$pos]) { /* process the group addresses */
            /* group marker */

            $group = mb_substr($address, 0, $pos);

            $address = mb_substr($address, $pos + 1);

            $result = sqimap_parse_address($address, $ar, $addr_ar, $group);

            $addr_ar = $result[0];

            $pos = $result[1];

            $address = mb_substr($address, $pos);

            $j = mb_strlen($address);

            $group = '';
        } elseif (';' == $address[$pos] && $group) {
            $address = mb_substr($address, 0, $pos - 1);

            break;
        }

        $pos++;
    }

    if ('' == $addr) {
        $addr = mb_substr($address, 0, $pos);
    } elseif ('' == $name) {
        $name = mb_substr($address, 0, $addr_start);
    }

    $at = mb_strpos($addr, '@');

    $addr_structure = new AddressStructure();

    $addr_structure->group = $group;

    if ($at) {
        $addr_structure->mailbox = trim(mb_substr($addr, 0, $at));

        $addr_structure->host = trim(mb_substr($addr, $at + 1));
    } else {
        $addr_structure->mailbox = trim($addr);
    }

    if ($group && '' == $addr) { /* no addresses found in group */
        $name = "$group: Undisclosed recipients;";

        $addr_structure->personal = $name;

        $addr_ar[] = $addr_structure;

        return ([$addr_ar, $pos + 1]);
    }  

    $addr_structure->personal = $name;

    if ($name || $addr) {
        $addr_ar[] = $addr_structure;
    }

    if ($ar) {
        return ($addr_ar);
    }
  

    return ($addr_ar[0]);
}
