<?php

/**
 * Rfc822Header.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to handle mime messages.
 *
 * $Id: Rfc822Header.class.php,v 1.17 2003/03/24 14:29:06 kink Exp $
 */

/*
 * rdc822_header class
 * input: header_string or array
 */

class Rfc822Header
{
    public $date = '';

    public $subject = '';

    public $from = [];

    public $sender = '';

    public $reply_to = [];

    public $to = [];

    public $cc = [];

    public $bcc = [];

    public $in_reply_to = '';

    public $message_id = '';

    public $references = '';

    public $mime = false;

    public $content_type = '';

    public $disposition = '';

    public $xmailer = '';

    public $priority = 3;

    public $dnt = '';

    public $mlist = [];

    public $more_headers = []; /* only needed for constructing headers
                                    in smtp.php */

    public function parseHeader($hdr)
    {
        if (is_array($hdr)) {
            $hdr = implode('', $hdr);
        }

        /* First we unfold the header */

        $hdr = trim(str_replace(["\r\n\t", "\r\n "], ['', ''], $hdr));

        /* Now we can make a new header array with */

        /* each element representing a headerline  */

        $hdr = explode("\r\n", $hdr);

        foreach ($hdr as $line) {
            $pos = mb_strpos($line, ':');

            if ($pos > 0) {
                $field = mb_substr($line, 0, $pos);

                if (!mb_strstr($field, ' ')) { /* valid field */
                    $value = trim(mb_substr($line, $pos + 1));

                    $this->parseField($field, $value);
                }
            }
        }

        if ('' == $this->content_type) {
            $this->parseContentType('text/plain; charset=us-ascii');
        }
    }

    public function stripComments($value)
    {
        $result = '';

        $cnt = mb_strlen($value);

        for ($i = 0; $i < $cnt; ++$i) {
            switch ($value[$i]) {
                case '"':
                    $result .= '"';
                    while ((++$i < $cnt) && ('"' != $value[$i])) {
                        if ('\\' == $value[$i]) {
                            $result .= '\\';

                            ++$i;
                        }

                        $result .= $value[$i];
                    }
                    $result .= $value[$i];
                    break;
                case '(':
                    $depth = 1;
                    while (($depth > 0) && (++$i < $cnt)) {
                        switch ($value[$i]) {
                            case '\\':
                                ++$i;
                                break;
                            case '(':
                                ++$depth;
                                break;
                            case ')':
                                --$depth;
                                break;
                            default:
                                break;
                        }
                    }
                    break;
                default:
                    $result .= $value[$i];
                    break;
            }
        }

        return $result;
    }

    public function parseField($field, $value)
    {
        $field = mb_strtolower($field);

        switch ($field) {
            case 'date':
                $value = $this->stripComments($value);
                $d = strtr($value, ['  ' => ' ']);
                $d = explode(' ', $d);
                $this->date = getTimeStamp($d);
                break;
            case 'subject':
                $this->subject = $value;
                break;
            case 'from':
                $this->from = $this->parseAddress($value, true);
                break;
            case 'sender':
                $this->sender = $this->parseAddress($value);
                break;
            case 'reply-to':
                $this->reply_to = $this->parseAddress($value, true);
                break;
            case 'to':
                $this->to = $this->parseAddress($value, true);
                break;
            case 'cc':
                $this->cc = $this->parseAddress($value, true);
                break;
            case 'bcc':
                $this->bcc = $this->parseAddress($value, true);
                break;
            case 'in-reply-to':
                $this->in_reply_to = $value;
                break;
            case 'message-id':
                $value = $this->stripComments($value);
                $this->message_id = $value;
                break;
            case 'references':
                $value = $this->stripComments($value);
                $this->references = $value;
                break;
            case 'x-confirm-reading-to':
            case 'return-receipt-to':
            case 'disposition-notification-to':
                $value = $this->stripComments($value);
                $this->dnt = $this->parseAddress($value);
                break;
            case 'mime-version':
                $value = $this->stripComments($value);
                $value = str_replace(' ', '', $value);
                $this->mime = ('1.0' == $value ? true : $this->mime);
                break;
            case 'content-type':
                $value = $this->stripComments($value);
                $this->parseContentType($value);
                break;
            case 'content-disposition':
                $value = $this->stripComments($value);
                $this->parseDisposition($value);
                break;
            case 'user-agent':
            case 'x-mailer':
                $this->xmailer = $value;
                break;
            case 'x-priority':
                $this->priority = $value;
                break;
            case 'list-post':
                $value = $this->stripComments($value);
                $this->mlist('post', $value);
                break;
            case 'list-reply':
                $value = $this->stripComments($value);
                $this->mlist('reply', $value);
                break;
            case 'list-subscribe':
                $value = $this->stripComments($value);
                $this->mlist('subscribe', $value);
                break;
            case 'list-unsubscribe':
                $value = $this->stripComments($value);
                $this->mlist('unsubscribe', $value);
                break;
            case 'list-archive':
                $value = $this->stripComments($value);
                $this->mlist('archive', $value);
                break;
            case 'list-owner':
                $value = $this->stripComments($value);
                $this->mlist('owner', $value);
                break;
            case 'list-help':
                $value = $this->stripComments($value);
                $this->mlist('help', $value);
                break;
            case 'list-id':
                $value = $this->stripComments($value);
                $this->mlist('id', $value);
                break;
            default:
                break;
        }
    }

    /*
     * parseAddress: recursive function for parsing address strings and store
     *               them in an address stucture object.
     *               input: $address = string
     *                      $ar      = boolean (return array instead of only the
     *                                 first element)
     *                      $addr_ar = array with parsed addresses
     *                      $group   = string
     *                      $host    = string (default domainname in case of
     *                                 addresses without a domainname)
     *                      $lookup  = callback function (for lookup address
     *                                 strings which are probably nicks
     *                                 (without @ ) )
     *               output: array with addressstructure objects or only one
     *                       address_structure object.
     *  personal name: encoded: =?charset?Q|B?string?=
     *                 quoted:  "string"
     *                 normal:  string
     *  email        : <mailbox@host>
     *               : mailbox@host
     *  This function is also used for validating addresses returned from compose
     *  That's also the reason that the function became a little bit huge and horrible
     *  Todo: Find a way to clean up this mess a bit (Marc Groot Koerkamp)
     */

    public function parseAddress($address, $ar = false, $addr_ar = [], $group = '', $host = '', $lookup = false)
    {
        $pos = 0;

        $name = $addr = $comment = $is_encoded = '';

        /*
         * in case of 8 bit addresses some how <SPACE> is represented as
         * NON BRAKING SPACE
         * This only happens when we validate addresses from the compose form.
         *
         * Note: when other charsets have dificulties with characters
         * =,;:<>()"<SPACE>
         * then we should find out the value for those characters ans replace
         * them by proper ASCII values before we start parsing.
         *
         */

        $address = str_replace("\240", ' ', $address);

        $address = trim($address);

        $j = mb_strlen($address);

        while ($pos < $j) {
            $char = $address[$pos];

            switch ($char) {
                case '=':
                    /* get the encoded personal name */ if (preg_match('/^(=\?([^?]*)\?(Q|B)\?([^?]*)\?=)(.*)/Ui', mb_substr($address, $pos), $reg)) {
                        $name .= $reg[1];

                        $pos += mb_strlen($reg[1]);
                    } else {
                        ++$pos;
                    }
                    $addr_start = $pos;
                    $is_encoded = true;
                    break;
                case '"': /* get the personal name */ $start_encoded = $pos;
                    ++$pos;
                    if ('"' == $address[$pos]) {
                        ++$pos;
                    } else {
                        $personal_start = $personal_end = $pos;

                        while ($pos < $j) {
                            $personal_end = mb_strpos($address, '"', $pos);

                            if (($personal_end - 2) > 0
                                && ('\\"' === mb_substr($address, $personal_end - 2, 2)
                                    || '\\\\' === mb_substr($address, $personal_end - 2, 2))) {
                                $pos = $personal_end + 1;
                            } else {
                                $name .= mb_substr($address, $personal_start, $personal_end - $personal_start);

                                break;
                            }
                        }

                        if ($personal_end) {
                            $pos = $personal_end + 1;
                        } else {
                            $pos = $j;
                        }
                    }
                    $addr_start = $pos;
                    break;
                case '<':  /* get email address */ $addr_start = $pos;
                    $addr_end = mb_strpos($address, '>', $addr_start);
                    $addr = mb_substr($address, $addr_start + 1, $addr_end - $addr_start - 1);
                    if ($addr_end) {
                        $pos = $addr_end + 1;
                    } else {
                        $addr = mb_substr($address, $addr_start + 1);

                        $pos = $j;
                    }
                    break;
                case '(':  /* rip off comments */ $addr_start = $pos;
                    $pos = mb_strpos($address, ')');
                    if (false !== $pos) {
                        $comment = mb_substr($address, $addr_start + 1, ($pos - $addr_start - 1));

                        $address_start = mb_substr($address, 0, $addr_start);

                        $address_end = mb_substr($address, $pos + 1);

                        $address = $address_start . $address_end;
                    }
                    $j = mb_strlen($address);
                    $pos = $addr_start + 1;
                    break;
                case ',':  /* we reached a delimiter */ if (!$name && !$addr) {
                    $addr = mb_substr($address, 0, $pos);
                } elseif (!$addr) {
                    $addr = trim(mb_substr($address, $addr_start, $pos));
                } elseif ('' == $name) {
                    $name = trim(mb_substr($address, 0, $addr_start));
                }
                    $at = mb_strpos($addr, '@');
                    $addr_structure = new AddressStructure();
                    if (!$name && $comment) {
                        $name = $comment;
                    }
                    if (!$is_encoded) {
                        $addr_structure->personal = encodeHeader($name);
                    } else {
                        $addr_structure->personal = $name;
                    }
                    $is_encoded = false;
                    $addr_structure->group = $group;
                    if ($at) {
                        $addr_structure->mailbox = mb_substr($addr, 0, $at);

                        $addr_structure->host = mb_substr($addr, $at + 1);
                    } else {
                        /* if lookup function */

                        if ($lookup) {
                            $aAddr = call_user_func_array($lookup, [$addr]);

                            if (isset($aAddr['email'])) {
                                $at = mb_strpos($aAddr['email'], '@');

                                $addr_structure->mailbox = mb_substr($aAddr['email'], 0, $at);

                                $addr_structure->host = mb_substr($aAddr['email'], $at + 1);

                                if (isset($aAddr['name'])) {
                                    $addr_structure->personal = $aAddr['name'];
                                } else {
                                    $addr_structure->personal = encodeHeader($addr);
                                }
                            }
                        }

                        if (!$addr_structure->mailbox) {
                            $addr_structure->mailbox = trim($addr);

                            if ($host) {
                                $addr_structure->host = $host;
                            }
                        }
                    }
                    $address = trim(mb_substr($address, $pos + 1));
                    $j = mb_strlen($address);
                    $pos = 0;
                    $name = '';
                    $addr = '';
                    $addr_ar[] = $addr_structure;
                    break;
                case ':':  /* process the group addresses */ /* group marker */ $group = mb_substr($address, 0, $pos);
                    $address = mb_substr($address, $pos + 1);
                    $result = $this->parseAddress($address, $ar, $addr_ar, $group);
                    $addr_ar = $result[0];
                    $pos = $result[1];
                    $address = mb_substr($address, $pos++);
                    $j = mb_strlen($address);
                    $group = '';
                    break;
                case ';':
                    if ($group) {
                        $address = mb_substr($address, 0, $pos - 1);
                    }
                    ++$pos;
                    break;
                case ' ':
                    ++$pos;
                    break;
                default:
                    /*
                     * this happens in the folowing situations :
                     * 1: unquoted personal name
                     * 2: emailaddress without < and >
                     * 3: unquoted personal name from compose that should be encoded.
                     * if it's a personal name then an emailaddress should follow
                     * the personal name may not have ',' inside it
                     * If it's a emailaddress then the personal name is not set.
                     * we should look for the delimiter ',' or a SPACE
                     */ /* check for emailaddress */ $i_space = mb_strpos($address, ' ', $pos);
                    $i_del = mb_strpos($address, ',', $pos);
                    if ($i_space || $i_del) {
                        if ($i_del) {
                            $address_part = mb_substr($address, $pos, $i_del - $pos);
                        } else {
                            $address_part = mb_substr($address, $pos);
                        }

                        if ($i = mb_strpos($address_part, '@')) {
                            /* an email address is following */

                            if (($i + $pos) < $i_space) {
                                $addr_start = $pos;

                                if ($i_space < $i_del && $i_del) {
                                    if ($i_space) {
                                        $addr = mb_substr($address, $pos, $i_space - $pos);

                                        $pos = $i_space;
                                    } else {
                                        $addr = mb_substr($address, $pos);

                                        $pos = $j;
                                    }
                                } else {
                                    if ($i_del) {
                                        $addr = mb_substr($address, $pos, $i_del - $pos);

                                        $pos = $i_del;
                                    } else {
                                        $addr = mb_substr($address, $pos);

                                        $pos = $j;
                                    }
                                }
                            } else {
                                if ($i_space) {
                                    $name .= mb_substr($address, $pos, $i_space - $pos) . ' ';

                                    $addr_start = $i_space + 1;

                                    $pos = $i_space + 1;
                                } else {
                                    $addr = mb_substr($address, $pos, $i_del - $pos);

                                    $addr_start = $pos;

                                    if ($i_del) {
                                        $pos = $i_del;
                                    } else {
                                        $pos = $j;
                                    }
                                }
                            }
                        } else {
                            /* email address without domain name, could be an alias */

                            $addr_start = $pos;

                            $addr = $address_part;

                            $pos = mb_strlen($address_part) + $pos;
                        }
                    } else {
                        $addr = mb_substr($address, $pos);

                        $addr_start = $pos;

                        $pos = $j;
                    }
                    break;
            }
        }

        if (!$name && !$addr) {
            $addr = mb_substr($address, 0, $pos);
        } elseif (!$addr) {
            $addr = trim(mb_substr($address, $addr_start, $pos));
        } elseif ('' == $name) {
            $name = trim(mb_substr($address, 0, $addr_start));
        }

        if (!$name && $comment) {
            $name = $comment;
        } elseif ($name && $comment) {
            $name .= ' (' . $comment . ')';
        }

        $at = mb_strpos($addr, '@');

        $addr_structure = new AddressStructure();

        $addr_structure->group = $group;

        if ($at) {
            $addr_structure->mailbox = trim(mb_substr($addr, 0, $at));

            $addr_structure->host = trim(mb_substr($addr, $at + 1));
        } else {
            /* if lookup function */

            if ($lookup) {
                $aAddr = call_user_func_array($lookup, [$addr]);

                if (isset($aAddr['email'])) {
                    $at = mb_strpos($aAddr['email'], '@');

                    $addr_structure->mailbox = mb_substr($aAddr['email'], 0, $at);

                    $addr_structure->host = mb_substr($aAddr['email'], $at + 1);

                    if (isset($aAddr['name']) && $aAddr['name']) {
                        $name = $aAddr['name'];
                    } else {
                        $name = $addr;
                    }
                }
            }

            if (!$addr_structure->mailbox) {
                $addr_structure->mailbox = trim($addr);

                if ($host) {
                    $addr_structure->host = $host;
                }
            }
        }

        $name = trim($name);

        if (!$is_encoded && !$group) {
            $name = encodeHeader($name);
        }

        if ($group && '' == $addr) { /* no addresses found in group */
            $name = $group;

            $addr_structure->personal = $name;

            $addr_ar[] = $addr_structure;

            return ([$addr_ar, $pos + 1]);
        } elseif ($group) {
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

    public function parseContentType($value)
    {
        $pos = mb_strpos($value, ';');

        $props = '';

        if ($pos > 0) {
            $type = trim(mb_substr($value, 0, $pos));

            $props = trim(mb_substr($value, $pos + 1));
        } else {
            $type = $value;
        }

        $content_type = new ContentType($type);

        if ($props) {
            $properties = $this->parseProperties($props);

            if (!isset($properties['charset'])) {
                $properties['charset'] = 'us-ascii';
            }

            $content_type->properties = $this->parseProperties($props);
        }

        $this->content_type = $content_type;
    }

    public function parseProperties($value)
    {
        $propArray = explode(';', $value);

        $propResultArray = [];

        foreach ($propArray as $prop) {
            $prop = trim($prop);

            $pos = mb_strpos($prop, '=');

            if ($pos > 0) {
                $key = trim(mb_substr($prop, 0, $pos));

                $val = trim(mb_substr($prop, $pos + 1));

                if ('"' == $val[0]) {
                    $val = mb_substr($val, 1, -1);
                }

                $propResultArray[$key] = $val;
            }
        }

        return $propResultArray;
    }

    public function parseDisposition($value)
    {
        $pos = mb_strpos($value, ';');

        $props = '';

        if ($pos > 0) {
            $name = trim(mb_substr($value, 0, $pos));

            $props = trim(mb_substr($value, $pos + 1));
        } else {
            $name = $value;
        }

        $props_a = $this->parseProperties($props);

        $disp = new Disposition($name);

        $disp->properties = $props_a;

        $this->disposition = $disp;
    }

    public function mlist($field, $value)
    {
        $res_a = [];

        $value_a = explode(',', $value);

        foreach ($value_a as $val) {
            $val = trim($val);

            if ('<' == $val[0]) {
                $val = mb_substr($val, 1, -1);
            }

            if ('mailto:' == mb_substr($val, 0, 7)) {
                $res_a['mailto'] = mb_substr($val, 7);
            } else {
                $res_a['href'] = $val;
            }
        }

        $this->mlist[$field] = $res_a;
    }

    /*
     * function to get the addres strings out of the header.
     * Arguments: string or array of strings !
     * example1: header->getAddr_s('to').
     * example2: header->getAddr_s(array('to', 'cc', 'bcc'))
     */

    public function getAddr_s($arr, $separator = ',', $encoded = false)
    {
        $s = '';

        if (is_array($arr)) {
            foreach ($arr as $arg) {
                if ($this->getAddr_s($arg, $separator, $encoded)) {
                    $s .= $separator . $result;
                }
            }

            $s = ($s ? mb_substr($s, 2) : $s);
        } else {
            $addr = $this->{$arr};

            if (is_array($addr)) {
                foreach ($addr as $addr_o) {
                    if (is_object($addr_o)) {
                        if ($encoded) {
                            $s .= $addr_o->getEncodedAddress() . $separator;
                        } else {
                            $s .= $addr_o->getAddress() . $separator;
                        }
                    }
                }

                $s = mb_substr($s, 0, -mb_strlen($separator));
            } else {
                if (is_object($addr)) {
                    if ($encoded) {
                        $s .= $addr->getEncodedAddress();
                    } else {
                        $s .= $addr->getAddress();
                    }
                }
            }
        }

        return $s;
    }

    public function getAddr_a($arg, $excl_arr = [], $arr = [])
    {
        if (is_array($arg)) {
            foreach ($arg as $argument) {
                $arr = $this->getAddr_a($argument, $excl_arr, $arr);
            }
        } else {
            $addr = $this->{$arg};

            if (is_array($addr)) {
                foreach ($addr as $next_addr) {
                    if (is_object($next_addr)) {
                        if (isset($next_addr->host) && ('' != $next_addr->host)) {
                            $email = $next_addr->mailbox . '@' . $next_addr->host;
                        } else {
                            $email = $next_addr->mailbox;
                        }

                        $email = mb_strtolower($email);

                        if ($email && !isset($arr[$email]) && !isset($excl_arr[$email])) {
                            $arr[$email] = $next_addr->personal;
                        }
                    }
                }
            } else {
                if (is_object($addr)) {
                    $email = $addr->mailbox;

                    $email .= (isset($addr->host) ? '@' . $addr->host : '');

                    $email = mb_strtolower($email);

                    if ($email && !isset($arr[$email]) && !isset($excl_arr[$email])) {
                        $arr[$email] = $addr->personal;
                    }
                }
            }
        }

        return $arr;
    }

    public function findAddress($address, $recurs = false)
    {
        $result = false;

        if (is_array($address)) {
            $i = 0;

            foreach ($address as $argument) {
                $match = $this->findAddress($argument, true);

                $last = end($match);

                if ($match[1]) {
                    return $i;
                }  

                if (count($match[0]) && !$result) {
                    $result = $i;
                }

                ++$i;
            }
        } else {
            if (!is_array($this->cc)) {
                $this->cc = [];
            }

            $srch_addr = $this->parseAddress($address);

            $results = [];

            foreach ($this->to as $to) {
                if ($to->host == $srch_addr->host) {
                    if ($to->mailbox == $srch_addr->mailbox) {
                        $results[] = $srch_addr;

                        if ($to->personal == $srch_addr->personal) {
                            if ($recurs) {
                                return [$results, true];
                            }
  

                            return true;
                        }
                    }
                }
            }

            foreach ($this->cc as $cc) {
                if ($cc->host == $srch_addr->host) {
                    if ($cc->mailbox == $srch_addr->mailbox) {
                        $results[] = $srch_addr;

                        if ($cc->personal == $srch_addr->personal) {
                            if ($recurs) {
                                return [$results, true];
                            }
  

                            return true;
                        }
                    }
                }
            }

            if ($recurs) {
                return [$results, false];
            } elseif (count($result)) {
                return true;
            }
  

            return false;
        }

        //exit;

        return $result;
    }

    public function getContentType($type0, $type1)
    {
        $type0 = $this->content_type->type0;

        $type1 = $this->content_type->type1;

        return $this->content_type->properties;
    }
}
