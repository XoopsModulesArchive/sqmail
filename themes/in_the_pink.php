<?php

/**
 * in_the_pink.php
 *    Name:    In the Pink
 *    Author:  Jorey Bump
 *    Date:    October 20, 2001
 *    Comment: This theme generates random colors, featuring a reddish
 *             background with dark text.
 *
 * Copyright (c) 2000-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: in_the_pink.php,v 1.5 2002/12/31 12:49:43 kink Exp $
 */

/* seed the random number generator */
sq_mt_randomize();

for ($i = 0; $i <= 15; $i++) {
    /* background/foreground toggle */

    if (0 == $i or 3 == $i or 4 == $i or 5 == $i or 9 == $i or 10 == $i or 12 == $i) {
        /* background */

        $r = mt_rand(248, 255);

        $b = mt_rand(140, 255);

        $g = mt_rand(128, $b);
    } else {
        /* text */

        $b = mt_rand(2, 128);

        $r = mt_rand(1, $b);

        $g = mt_rand(0, $r);
    }

    /* set array element as hex string with hashmark (for HTML output) */

    $color[$i] = sprintf('#%02X%02X%02X', $r, $g, $b);
}

/* Reference from  http://www.squirrelmail.org/wiki/CreatingThemes

$color[0]   = '#xxxxxx';  // Title bar at the top of the page header
$color[1]   = '#xxxxxx';  // Not currently used
$color[2]   = '#xxxxxx';  // Error messages (usually red)
$color[3]   = '#xxxxxx';  // Left folder list background color
$color[4]   = '#xxxxxx';  // Normal background color
$color[5]   = '#xxxxxx';  // Header of the message index // (From, Date,Subject)
$color[6]   = '#xxxxxx';  // Normal text on the left folder list
$color[7]   = '#xxxxxx';  // Links in the right frame
$color[8]   = '#xxxxxx';  // Normal text (usually black)
$color[9]   = '#xxxxxx';  // Darker version of #0
$color[10]  = '#xxxxxx';  // Darker version of #9
$color[11]  = '#xxxxxx';  // Special folders color (INBOX, Trash, Sent)
$color[12]  = '#xxxxxx';  // Alternate color for message list // Alters between #4 and this one
$color[13]  = '#xxxxxx';  // Color for quoted text -- > 1 quote
$color[14]  = '#xxxxxx';  // Color for quoted text -- >> 2 or more

*/
