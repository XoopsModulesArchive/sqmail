<?php

/**
 * validate.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * $Id: validate.php,v 1.9 2003/03/03 04:44:39 ebullient Exp $
 */

/* include the mime class before the session start ! otherwise we can't store
 * messages with a session_register.
 *
 * From http://www.php.net/manual/en/language.oop.serialization.php:
 *   In case this isn't clear:
 *   In 4.2 and below:
 *      session.auto_start and session objects are mutually exclusive.
 *
 * We need to load the classes before the session is started,
 * except that the session could be started automatically
 * via session.auto_start. So, we'll close the session,
 * then load the classes, and reopen the session which should
 * make everything happy.
 *
 * ** Note this means that for the 1.3.2 release, we should probably
 * recommend that people set session.auto_start=0 to avoid this altogether.
 */

// session_write_close();

/**
 * Reset the $theme() array in case a value was passed via a cookie.
 * This is until theming is rewritten.
 */
global $theme;
//unset($theme);
//$theme=array();

/* SquirrelMail required files. */
require_once SM_PATH . 'class/mime.class.php';
require_once SM_PATH . 'functions/global.php';
require_once SM_PATH . 'functions/strings.php';
require_once SM_PATH . 'config/config.php';

/* set the name of the session cookie */
//if(isset($session_name) && $session_name) {
//    ini_set('session.name' , $session_name);
//} else {
//    ini_set('session.name' , 'SQMSESSID');
//}
// $session_name should set to Xoops session name
if ($xoopsConfig['use_mysession'] && '' != $xoopsConfig['session_name']) {
    if (isset($HTTP_COOKIE_VARS[$xoopsConfig['session_name']])) {
        $session_name = $HTTP_COOKIE_VARS[$xoopsConfig['session_name']];
    }
} else {
    unset($session_name);
}

session_start();

require_once SM_PATH . 'functions/i18n.php';
require_once SM_PATH . 'functions/auth.php';

is_logged_in();

/**
 * Auto-detection
 *
 * if $send (the form button's name) contains "\n" as the first char
 * and the script is compose.php, then trim everything. Otherwise, we
 * don't have to worry.
 *
 * This is for a RedHat package bug and a Konqueror (pre 2.1.1?) bug
 */
global $send, $PHP_SELF;
if (isset($send)
    && ("\n" == mb_substr($send, 0, 1))
    && ('/compose.php' == mb_substr($PHP_SELF, -12))) {
    if ('POST' == $REQUEST_METHOD) {
        global $_POST;

        TrimArray($_POST);
    } else {
        global $_GET;

        TrimArray($_GET);
    }
}

require_once SM_PATH . 'include/load_prefs.php';
require_once SM_PATH . 'functions/page_header.php';
require_once SM_PATH . 'functions/prefs.php';

/* Set up the language (i18n.php was included by auth.php). */
global $username, $data_dir;
set_up_language(getPref($data_dir, $username, 'language'));

$timeZone = getPref($data_dir, $username, 'timezone');

/* Check to see if we are allowed to set the TZ environment variable.
 * We are able to do this if ...
 *   safe_mode is disabled OR
 *   safe_mode_allowed_env_vars is empty (you are allowed to set any) OR
 *   safe_mode_allowed_env_vars contains TZ
 */
$tzChangeAllowed = (!ini_get('safe_mode'))
                   || !strcmp(ini_get('safe_mode_allowed_env_vars'), '')
                   || preg_match('/^([\w_]+,)*TZ/', ini_get('safe_mode_allowed_env_vars'));

if (SMPREF_NONE != $timeZone && ('' != $timeZone)
    && $tzChangeAllowed) {
    putenv('TZ=' . $timeZone);
}
