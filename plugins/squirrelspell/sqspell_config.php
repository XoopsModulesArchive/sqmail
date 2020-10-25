<?php
/**
 * sqspell_config.php -- SquirrelSpell Configuration file.
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 *
 *
 * $Id: sqspell_config.php,v 1.14 2003/03/11 15:57:21 kink Exp $
 */
require_once SM_PATH . 'functions/prefs.php';

/* Just for poor wretched souls with E_ALL. :) */
global $data_dir;

sqgetGlobalVar('username', $username, SQ_SESSION);

/**
 * Example:
 *
 * $SQSPELL_APP = array( 'English' => 'ispell -a',
 *                     'Spanish' => 'ispell -d spanish -a' );
 */
$SQSPELL_APP = [
    'English' => 'ispell -a',
    'Spanish' => 'ispell -d spanish -a',
];
$SQSPELL_APP_DEFAULT = 'English';
$SQSPELL_WORDS_FILE = getHashedFile($username, $data_dir, "$username.words");

$SQSPELL_EREG = 'ereg';
