<?php

/*
 *  This function tell other modules what users have access
 *  to the plugin.
 *
 *  Philippe Mingo
 *
 *  $Id: auth.php,v 1.10 2003/01/04 01:12:14 ebullient Exp $
 */

function adm_check_user()
{
    global $PHP_SELF;

    require_once SM_PATH . 'functions/global.php';

    if (!sqgetGlobalVar('username', $username, SQ_SESSION)) {
        $username = '';
    }

    /* This needs to be first, for all non_options pages */

    if (mb_strpos('options.php', $PHP_SELF)) {
        $auth = false;
    } elseif (file_exists(SM_PATH . 'plugins/administrator/admins')) {
        $auths = file(SM_PATH . 'plugins/administrator/admins');

        $auth = in_array("$username\n", $auths, true);
    } elseif (file_exists(SM_PATH . 'config/admins')) {
        $auths = file(SM_PATH . 'config/admins');

        $auth = in_array("$username\n", $auths, true);
    } elseif ($adm_id = fileowner(SM_PATH . 'config/config.php')) {
        $adm = posix_getpwuid($adm_id);

        $auth = ($username == $adm['name']);
    } else {
        $auth = false;
    }

    return ($auth);
}
