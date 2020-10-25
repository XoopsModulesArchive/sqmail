<?php

/*
 * db_prefs.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions for manipulating user preferences
 * stored in a database, accessed though the Pear DB layer.
 *
 * Database:
 * ---------
 *
 * The preferences table should have three columns:
 *    user       char  \  primary
 *    prefkey    char  /  key
 *    prefval    blob
 *
 *   CREATE TABLE userprefs (user CHAR(128) NOT NULL DEFAULT '',
 *                           prefkey CHAR(64) NOT NULL DEFAULT '',
 *                           prefval BLOB NOT NULL DEFAULT '',
 *                           primary key (user,prefkey));
 *
 * Configuration of databasename, username and password is done
 * by using conf.pl or the administrator plugin
 *
 * $Id: db_prefs.php,v 1.31 2003/03/28 05:22:51 ebullient Exp $
 */

define('SMDB_UNKNOWN', 0);
define('SMDB_MYSQL', 1);
define('SMDB_PGSQL', 2);

require_once __DIR__ . '/DB.php';
require_once SM_PATH . 'config/config.php';

global $prefs_are_cached, $prefs_cache;

function cachePrefValues($username)
{
    global $prefs_are_cached, $prefs_cache;

    if ($prefs_are_cached) {
        return;
    }

    sqsession_unregister('prefs_cache');

    sqsession_unregister('prefs_are_cached');

    $db = new dbPrefs();

    if (isset($db->error)) {
        printf(
            _('Preference database error (%s). Exiting abnormally'),
            $db->error
        );

        exit;
    }

    $db->fillPrefsCache($username);

    if (isset($db->error)) {
        printf(
            _('Preference database error (%s). Exiting abnormally'),
            $db->error
        );

        exit;
    }

    $prefs_are_cached = true;

    sqsession_register($prefs_cache, 'prefs_cache');

    sqsession_register($prefs_are_cached, 'prefs_are_cached');
}

class dbPrefs
{
    public $table = 'userprefs';

    public $user_field = 'user';

    public $key_field = 'prefkey';

    public $val_field = 'prefval';

    public $dbh = null;

    public $error = null;

    public $db_type = SMDB_UNKNOWN;

    public $default = [
        'theme_default' => 0,
        'show_html_default' => '0',
    ];

    public function open()
    {
        global $prefs_dsn, $prefs_table;

        global $prefs_user_field, $prefs_key_field, $prefs_val_field;

        if (isset($this->dbh)) {
            return true;
        }

        if (0 === strpos($prefs_dsn, "mysql")) {
            $this->db_type = SMDB_MYSQL;
        } elseif (0 === strpos($prefs_dsn, "pgsql")) {
            $this->db_type = SMDB_PGSQL;
        }

        if (!empty($prefs_table)) {
            $this->table = $prefs_table;
        }

        if (!empty($prefs_user_field)) {
            $this->user_field = $prefs_user_field;
        }

        if (!empty($prefs_key_field)) {
            $this->key_field = $prefs_key_field;
        }

        if (!empty($prefs_val_field)) {
            $this->val_field = $prefs_val_field;
        }

        $dbh = DB::connect($prefs_dsn, true);

        if (DB::isError($dbh)) {
            $this->error = DB::errorMessage($dbh);

            return false;
        }

        $this->dbh = $dbh;

        return true;
    }

    public function failQuery($res = null)
    {
        if (null === $res) {
            printf(
                _('Preference database error (%s). Exiting abnormally'),
                $this->error
            );
        } else {
            printf(
                _('Preference database error (%s). Exiting abnormally'),
                DB::errorMessage($res)
            );
        }

        exit;
    }

    public function getKey($user, $key, $default = '')
    {
        global $prefs_cache;

        cachePrefValues($user);

        return $prefs_cache[$key] ?? $this->default[$key] ?? $default;
    }

    public function deleteKey($user, $key)
    {
        global $prefs_cache;

        if (!$this->open()) {
            return false;
        }

        $query = sprintf(
            "DELETE FROM %s WHERE %s='%s' AND %s='%s'",
            $this->table,
            $this->user_field,
            $this->dbh->quoteString($user),
            $this->key_field,
            $this->dbh->quoteString($key)
        );

        $res = $this->dbh->simpleQuery($query);

        if (DB::isError($res)) {
            $this->failQuery($res);
        }

        unset($prefs_cache[$key]);

        return true;
    }

    public function setKey($user, $key, $value)
    {
        if (!$this->open()) {
            return false;
        }

        if (SMDB_MYSQL == $this->db_type) {
            $query = sprintf(
                'REPLACE INTO %s (%s, %s, %s) ' . "VALUES('%s','%s','%s')",
                $this->table,
                $this->user_field,
                $this->key_field,
                $this->val_field,
                $this->dbh->quoteString($user),
                $this->dbh->quoteString($key),
                $this->dbh->quoteString($value)
            );

            $res = $this->dbh->simpleQuery($query);

            if (DB::isError($res)) {
                $this->failQuery($res);
            }
        } elseif (SMDB_PGSQL == $this->db_type) {
            $this->dbh->simpleQuery('BEGIN TRANSACTION');

            $query = sprintf(
                "DELETE FROM %s WHERE %s='%s' AND %s='%s'",
                $this->table,
                $this->user_field,
                $this->dbh->quoteString($user),
                $this->key_field,
                $this->dbh->quoteString($key)
            );

            $res = $this->dbh->simpleQuery($query);

            if (DB::isError($res)) {
                $this->dbh->simpleQuery('ROLLBACK TRANSACTION');

                $this->failQuery($res);
            }

            $query = sprintf(
                "INSERT INTO %s (%s, %s, %s) VALUES ('%s', '%s', '%s')",
                $this->table,
                $this->user_field,
                $this->key_field,
                $this->val_field,
                $this->dbh->quoteString($user),
                $this->dbh->quoteString($key),
                $this->dbh->quoteString($value)
            );

            $res = $this->dbh->simpleQuery($query);

            if (DB::isError($res)) {
                $this->dbh->simpleQuery('ROLLBACK TRANSACTION');

                $this->failQuery($res);
            }

            $this->dbh->simpleQuery('COMMIT TRANSACTION');
        } else {
            $query = sprintf(
                "DELETE FROM %s WHERE %s='%s' AND %s='%s'",
                $this->table,
                $this->user_field,
                $this->dbh->quoteString($user),
                $this->key_field,
                $this->dbh->quoteString($key)
            );

            $res = $this->dbh->simpleQuery($query);

            if (DB::isError($res)) {
                $this->failQuery($res);
            }

            $query = sprintf(
                "INSERT INTO %s (%s, %s, %s) VALUES ('%s', '%s', '%s')",
                $this->table,
                $this->user_field,
                $this->key_field,
                $this->val_field,
                $this->dbh->quoteString($user),
                $this->dbh->quoteString($key),
                $this->dbh->quoteString($value)
            );

            $res = $this->dbh->simpleQuery($query);

            if (DB::isError($res)) {
                $this->failQuery($res);
            }
        }

        return true;
    }

    public function fillPrefsCache($user)
    {
        global $prefs_cache;

        if (!$this->open()) {
            return;
        }

        $prefs_cache = [];

        $query = sprintf(
            'SELECT %s AS prefkey, %s AS prefval FROM %s ' . "WHERE %s = '%s'",
            $this->key_field,
            $this->val_field,
            $this->table,
            $this->user_field,
            $this->dbh->quoteString($user)
        );

        $res = $this->dbh->query($query);

        if (DB::isError($res)) {
            $this->failQuery($res);
        }

        while (false !== ($row = $res->fetchRow(DB_FETCHMODE_ASSOC))) {
            $prefs_cache[$row['prefkey']] = $row['prefval'];
        }
    }
} /* end class dbPrefs */

/* returns the value for the pref $string */
function getPref($data_dir, $username, $string, $default = '')
{
    $db = new dbPrefs();

    if (isset($db->error)) {
        printf(
            _('Preference database error (%s). Exiting abnormally'),
            $db->error
        );

        exit;
    }

    return $db->getKey($username, $string, $default);
}

/* Remove the pref $string */
function removePref($data_dir, $username, $string)
{
    global $prefs_cache;

    $db = new dbPrefs();

    if (isset($db->error)) {
        $db->failQuery();
    }

    $db->deleteKey($username, $string);

    if (isset($prefs_cache[$string])) {
        unset($prefs_cache[$string]);
    }

    sqsession_register($prefs_cache, 'prefs_cache');
}

/* sets the pref, $string, to $set_to */
function setPref($data_dir, $username, $string, $set_to)
{
    global $prefs_cache;

    if (isset($prefs_cache[$string]) && ($prefs_cache[$string] == $set_to)) {
        return;
    }

    if ('' === $set_to) {
        removePref($data_dir, $username, $string);

        return;
    }

    $db = new dbPrefs();

    if (isset($db->error)) {
        $db->failQuery();
    }

    $db->setKey($username, $string, $set_to);

    $prefs_cache[$string] = $set_to;

    assert_options(ASSERT_ACTIVE, 1);

    assert_options(ASSERT_BAIL, 1);

    assert('$set_to == $prefs_cache[$string]');

    sqsession_register($prefs_cache, 'prefs_cache');
}

/* This checks if the prefs are available */
function checkForPrefs($data_dir, $username)
{
    $db = new dbPrefs();

    if (isset($db->error)) {
        $db->failQuery();
    }
}

/* Writes the Signature */
function setSig($data_dir, $username, $number, $string)
{
    if ('g' == $number) {
        $key = '___signature___';
    } else {
        $key = sprintf('___sig%s___', $number);
    }

    setPref($data_dir, $username, $key, $string);
}

/* Gets the signature */
function getSig($data_dir, $username, $number)
{
    if ('g' == $number) {
        $key = '___signature___';
    } else {
        $key = sprintf('___sig%d___', $number);
    }

    return getPref($data_dir, $username, $key);
}
