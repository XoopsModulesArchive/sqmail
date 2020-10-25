<?php

/**
 * MessageHeader.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to handle mime messages.
 *
 * $Id: MessageHeader.class.php,v 1.2 2002/12/31 12:49:30 kink Exp $
 */
class MessageHeader
{
    /** msg_header contains all variables available in a bodystructure **/

    /** entity like described in rfc2060                               **/

    public $type0 = '';

    public $type1 = '';

    public $parameters = [];

    public $id = 0;

    public $description = '';

    public $encoding = '';

    public $size = 0;

    public $md5 = '';

    public $disposition = '';

    public $language = '';

    /*
     * returns addres_list of supplied argument
     * arguments: array('to', 'from', ...) or just a string like 'to'.
     * result: string: address1, addres2, ....
     */

    public function setVar($var, $value)
    {
        $this->{$var} = $value;
    }

    public function getParameter($p)
    {
        $value = mb_strtolower($p);

        return ($this->parameters[$p] ?? '');
    }

    public function setParameter($parameter, $value)
    {
        $this->parameters[mb_strtolower($parameter)] = $value;
    }
}
