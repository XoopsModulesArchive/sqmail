<?php

/**
 * Disposition.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to handle mime messages.
 *
 * $Id: Disposition.class.php,v 1.2 2002/12/31 12:49:30 kink Exp $
 */
class Disposition
{
    public function __construct($name)
    {
        $this->name = $name;

        $this->properties = [];
    }

    public function getProperty($par)
    {
        $value = mb_strtolower($par);

        return $this->properties[$par] ?? '';
    }
}
