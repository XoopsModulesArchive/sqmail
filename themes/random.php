<?php

/**
 * random.php
 *    Name:    Random Theme Every Login
 *    Author:  Tyler Akins
 *    Date:    December 24, 2001
 *    Comment: Guess what this does!
 *
 * Copyright (c) 2000-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: random.php,v 1.6 2003/03/07 15:26:45 kink Exp $
 */

sq_mt_randomize();

require_once SM_PATH . 'functions/global.php';

global $theme, $random_theme_good_themes;

if (!sqisset(\ < ? php

/**
 * random.php
 *    Name:    Random Theme Every Login
 *    Author:  Tyler Akins
 *    Date:    December 24, 2001
 *    Comment: Guess what this does!
 *
 * Copyright (c) 2000-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: random.php,v 1.6 2003/03/07 15:26:45 kink Exp $
 */

sq_mt_randomize();

require_once SM_PATH . 'functions/global.php';

global $theme, $random_theme_good_themes;

if (!sqsession_is_registered('random_theme_good_theme')) {
    $good_themes = [];
    foreach ($theme as $data) {
        if (substr($data['PATH'], -18) != '/themes/random.php') {
            $good_themes[] = $data['PATH'];
        }
    }
    if (count($good_themes) == 0) {
        $good_themes[] = '../themes/default.php';
    }
    $which                   = mt_rand(0, count($good_themes));
    $random_theme_good_theme = $good_themes[$which];
    sqsession_register($random_theme_good_theme, 'random_theme_good_theme');
}

@require_once $random_theme_good_theme;

?>
SESSION['random_theme_good_theme'])) {
    $good_themes = [];
    foreach ($theme as $data) {
        if (substr($data['PATH'], -18) != '/themes/random.php') {
            $good_themes[] = $data['PATH'];
        }
    }
    if (count($good_themes) == 0) {
        $good_themes[] = '../themes/default.php';
    }
    $which                   = mt_rand(0, count($good_themes));
    $random_theme_good_theme = $good_themes[$which];
    sqsession_register($random_theme_good_theme, 'random_theme_good_theme');
}

@require_once $random_theme_good_theme;

?>
