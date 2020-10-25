<?php

/**
 * AddressStructure.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to handle mime messages.
 *
 * $Id: AddressStructure.class.php,v 1.6 2003/03/07 22:12:51 stekkel Exp $
 */
class AddressStructure
{
    public $personal = '';

    public $adl = '';

    public $mailbox = '';

    public $host = '';

    public $group = '';

    public function getAddress($full = true, $encoded = false)
    {
        $result = '';

        if (is_object($this)) {
            $email = ($this->host ? $this->mailbox . '@' . $this->host : $this->mailbox);

            $personal = trim($this->personal);

            $is_encoded = false;

            if (preg_match('/(=\?([^?]*)\?(Q|B)\?([^?]*)\?=)(.*)/Ui', $personal, $reg)) {
                $is_encoded = true;
            }

            if ($personal) {
                if ($encoded && !$is_encoded) {
                    $personal_encoded = encodeHeader($personal);

                    if ($personal !== $personal_encoded) {
                        $personal = $personal_encoded;
                    } else {
                        $personal = '"' . $this->personal . '"';
                    }
                } else {
                    if (!$is_encoded) {
                        $personal = '"' . $this->personal . '"';
                    }
                }

                $addr = ($email ? $personal . ' <' . $email . '>' : $this->personal);

                $best_dpl = $this->personal;
            } else {
                $addr = $email;

                $best_dpl = $email;
            }

            $result = ($full ? $addr : $best_dpl);
        }

        return $result;
    }

    public function getEncodedAddress()
    {
        return $this->getAddress(true, true);
    }
}