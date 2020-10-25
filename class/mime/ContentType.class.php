<?php

/**
 * ContentType.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to handle mime messages.
 *
 * $Id: ContentType.class.php,v 1.2 2002/12/31 12:49:30 kink Exp $
 */
class ContentType
{
    public $type0 = 'text';

    public $type1 = 'plain';

    public $properties = '';

    public function __construct($type)
    {
        $pos = mb_strpos($type, '/');

        if ($pos > 0) {
            $this->type0 = mb_substr($type, 0, $pos);

            $this->type1 = mb_substr($type, $pos + 1);
        } else {
            $this->type0 = $type;
        }

        $this->properties = [];
    }
}
