<?php

/**
 * addressbook.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Functions and classes for the addressbook system.
 *
 * $Id: addressbook.php,v 1.47 2002/12/31 12:49:31 kink Exp $
 */

/*
   This is the path to the global site-wide addressbook.
   It looks and feels just like a user's .abook file
   If this is in the data directory, use "$data_dir/global.abook"
   If not, specify the path as though it was accessed from the
   src/ directory ("../global.abook" -> in main directory)

   If you don't want a global site-wide addressbook, comment these
   two lines out.  (They are disabled by default.)

   The global addressbook is unmodifiable by anyone.  You must actually
   use a shell script or whatnot to modify the contents.

  global $data_dir;
  $address_book_global_filename = "$data_dir/global.abook";

  Include backends here.
*/

require_once SM_PATH . 'functions/abook_local_file.php';
require_once SM_PATH . 'functions/abook_ldap_server.php';

global $addrbook_dsn;

/* Use this if you wanna have a global address book */
if (isset($address_book_global_filename)) {
    require_once SM_PATH . 'functions/abook_global_file.php';
}

/* Only load database backend if database is configured */
if (isset($addrbook_dsn) && !empty($addrbook_dsn)) {
    require_once SM_PATH . 'functions/abook_database.php';
}

/*
   Create and initialize an addressbook object.
   Returns the created object
*/
function addressbook_init($showerr = true, $onlylocal = false)
{
    global $data_dir, $username, $ldap_server, $address_book_global_filename;

    global $addrbook_dsn, $addrbook_table;

    /* Create a new addressbook object */

    $abook = new AddressBook();

    /*
        Always add a local backend. We use *either* file-based *or* a
        database addressbook. If $addrbook_dsn is set, the database
        backend is used. If not, addressbooks are stores in files.
    */

    if (isset($addrbook_dsn) && !empty($addrbook_dsn)) {
        /* Database */

        if (!isset($addrbook_table) || empty($addrbook_table)) {
            $addrbook_table = 'address';
        }

        $r = $abook->add_backend(
            'database',
            [
                'dsn' => $addrbook_dsn,
                'owner' => $username,
                'table' => $addrbook_table,
            ]
        );

        if (!$r && $showerr) {
            echo _('Error initializing addressbook database.');

            exit;
        }
    } else {
        /* File */

        $filename = getHashedFile($username, $data_dir, "$username.abook");

        $r = $abook->add_backend(
            'local_file',
            [
                'filename' => $filename,
                'create' => true,
            ]
        );

        if (!$r && $showerr) {
            printf(_('Error opening file %s'), $filename);

            exit;
        }
    }

    /* This would be for the global addressbook */

    if (isset($address_book_global_filename)) {
        $r = $abook->add_backend('global_file');

        if (!$r && $showerr) {
            echo _('Error initializing global addressbook.');

            exit;
        }
    }

    if ($onlylocal) {
        return $abook;
    }

    /* Load configured LDAP servers (if PHP has LDAP support) */

    if (isset($ldap_server) && is_array($ldap_server) && function_exists('ldap_connect')) {
        reset($ldap_server);

        while (list($undef, $param) = each($ldap_server)) {
            if (is_array($param)) {
                $r = $abook->add_backend('ldap_server', $param);

                if (!$r && $showerr) {
                    printf(
                        '&nbsp;' . _('Error initializing LDAP server %s:') . "<BR>\n",
                        $param['host']
                    );

                    echo '&nbsp;' . $abook->error;

                    exit;
                }
            }
        }
    }

    /* Return the initialized object */

    return $abook;
}

/*
 *   Had to move this function outside of the Addressbook Class
 *   PHP 4.0.4 Seemed to be having problems with inline functions.
 */
function addressbook_cmp($a, $b)
{
    if ($a['backend'] > $b['backend']) {
        return 1;
    } elseif ($a['backend'] < $b['backend']) {
        return -1;
    }

    return (mb_strtolower($a['name']) > mb_strtolower($b['name'])) ? 1 : -1;
}

/*
 * This is the main address book class that connect all the
 * backends and provide services to the functions above.
 *
 */

class AddressBook
{
    public $backends = [];

    public $numbackends = 0;

    public $error = '';

    public $localbackend = 0;

    public $localbackendname = '';

    // Constructor function.

    public function __construct()
    {
        $localbackendname = _('Personal address book');
    }

    /*
     * Return an array of backends of a given type,
     * or all backends if no type is specified.
     */

    public function get_backend_list($type = '')
    {
        $ret = [];

        for ($i = 1; $i <= $this->numbackends; $i++) {
            if (empty($type) || $type == $this->backends[$i]->btype) {
                $ret[] = &$this->backends[$i];
            }
        }

        return $ret;
    }

    /*
       ========================== Public ========================

        Add a new backend. $backend is the name of a backend
        (without the abook_ prefix), and $param is an optional
        mixed variable that is passed to the backend constructor.
        See each of the backend classes for valid parameters.
     */

    public function add_backend($backend, $param = '')
    {
        $backend_name = 'abook_' . $backend;

        eval('$newback = new()() ' . $backend_name . '($param);');

        if (!empty($newback->error)) {
            $this->error = $newback->error;

            return false;
        }

        $this->numbackends++;

        $newback->bnum = $this->numbackends;

        $this->backends[$this->numbackends] = $newback;

        /* Store ID of first local backend added */

        if (0 == $this->localbackend && 'local' == $newback->btype) {
            $this->localbackend = $this->numbackends;

            $this->localbackendname = $newback->sname;
        }

        return $this->numbackends;
    }

    /*
     * This function takes a $row array as returned by the addressbook
     * search and returns an e-mail address with the full name or
     * nickname optionally prepended.
     */

    public function full_address($row)
    {
        global $addrsrch_fullname, $data_dir, $username;

        if (($prefix = getPref($data_dir, $username, 'addrsrch_fullname') or isset($addrsrch_fullname) and $prefix = $addrsrch_fullname) and 'noprefix' !== $prefix) {
            $name = ('nickname' === $prefix) ? $row['nickname'] : $row['name'];

            return $name . ' <' . trim($row['email']) . '>';
        }
  

        return trim($row['email']);
    }

    /*
        Return a list of addresses matching expression in
        all backends of a given type.
    */

    public function search($expression, $bnum = -1)
    {
        $ret = [];

        $this->error = '';

        /* Search all backends */

        if (-1 == $bnum) {
            $sel = $this->get_backend_list('');

            $failed = 0;

            for ($i = 0, $iMax = count($sel); $i < $iMax; $i++) {
                $backend = &$sel[$i];

                $backend->error = '';

                $res = $backend->search($expression);

                if (is_array($res)) {
                    $ret = array_merge($ret, $res);
                } else {
                    $this->error .= "<br>\n" . $backend->error;

                    $failed++;
                }
            }

            /* Only fail if all backends failed */

            if ($failed >= count($sel)) {
                $ret = false;
            }
        } else {
            /* Search only one backend */

            $ret = $this->backends[$bnum]->search($expression);

            if (!is_array($ret)) {
                $this->error .= "<br>\n" . $this->backends[$bnum]->error;

                $ret = false;
            }
        }

        return ($ret);
    }

    /* Return a sorted search */

    public function s_search($expression, $bnum = -1)
    {
        $ret = $this->search($expression, $bnum);

        if (is_array($ret)) {
            usort($ret, 'addressbook_cmp');
        }

        return $ret;
    }

    /*
     *  Lookup an address by alias. Only possible in
     *  local backends.
     */

    public function lookup($alias, $bnum = -1)
    {
        $ret = [];

        if ($bnum > -1) {
            $res = $this->backends[$bnum]->lookup($alias);

            if (is_array($res)) {
                return $res;
            }  

            $this->error = $backend->error;

            return false;
        }

        $sel = $this->get_backend_list('local');

        for ($i = 0, $iMax = count($sel); $i < $iMax; $i++) {
            $backend = &$sel[$i];

            $backend->error = '';

            $res = $backend->lookup($alias);

            if (is_array($res)) {
                if (!empty($res)) {
                    return $res;
                }
            } else {
                $this->error = $backend->error;

                return false;
            }
        }

        return $ret;
    }

    /* Return all addresses */

    public function list_addr($bnum = -1)
    {
        $ret = [];

        if (-1 == $bnum) {
            $sel = $this->get_backend_list('local');
        } else {
            $sel = [0 => &$this->backends[$bnum]];
        }

        for ($i = 0, $iMax = count($sel); $i < $iMax; $i++) {
            $backend = &$sel[$i];

            $backend->error = '';

            $res = $backend->list_addr();

            if (is_array($res)) {
                $ret = array_merge($ret, $res);
            } else {
                $this->error = $backend->error;

                return false;
            }
        }

        return $ret;
    }

    /*
     * Create a new address from $userdata, in backend $bnum.
     * Return the backend number that the/ address was added
     * to, or false if it failed.
     */

    public function add($userdata, $bnum)
    {
        /* Validate data */

        if (!is_array($userdata)) {
            $this->error = _('Invalid input data');

            return false;
        }

        if (empty($userdata['firstname']) && empty($userdata['lastname'])) {
            $this->error = _('Name is missing');

            return false;
        }

        if (empty($userdata['email'])) {
            $this->error = _('E-mail address is missing');

            return false;
        }

        if (empty($userdata['nickname'])) {
            $userdata['nickname'] = $userdata['email'];
        }

        if (eregi('[ \\:\\|\\#\\"\\!]', $userdata['nickname'])) {
            $this->error = _('Nickname contains illegal characters');

            return false;
        }

        /* Check that specified backend accept new entries */

        if (!$this->backends[$bnum]->writeable) {
            $this->error = _('Addressbook is read-only');

            return false;
        }

        /* Add address to backend */

        $res = $this->backends[$bnum]->add($userdata);

        if ($res) {
            return $bnum;
        }  

        $this->error = $this->backends[$bnum]->error;

        return false;

        return false;  // Not reached
    }

    /* end of add() */

    /*
     * Remove the user identified by $alias from backend $bnum
     * If $alias is an array, all users in the array are removed.
     */

    public function remove($alias, $bnum)
    {
        /* Check input */

        if (empty($alias)) {
            return true;
        }

        /* Convert string to single element array */

        if (!is_array($alias)) {
            $alias = [0 => $alias];
        }

        /* Check that specified backend is writable */

        if (!$this->backends[$bnum]->writeable) {
            $this->error = _('Addressbook is read-only');

            return false;
        }

        /* Remove user from backend */

        $res = $this->backends[$bnum]->remove($alias);

        if ($res) {
            return $bnum;
        }  

        $this->error = $this->backends[$bnum]->error;

        return false;

        return false;  /* Not reached */
    }

    /* end of remove() */

    /*
     * Remove the user identified by $alias from backend $bnum
     * If $alias is an array, all users in the array are removed.
     */

    public function modify($alias, $userdata, $bnum)
    {
        /* Check input */

        if (empty($alias) || !is_string($alias)) {
            return true;
        }

        /* Validate data */

        if (!is_array($userdata)) {
            $this->error = _('Invalid input data');

            return false;
        }

        if (empty($userdata['firstname']) && empty($userdata['lastname'])) {
            $this->error = _('Name is missing');

            return false;
        }

        if (empty($userdata['email'])) {
            $this->error = _('E-mail address is missing');

            return false;
        }

        if (eregi('[\\: \\|\\#"\\!]', $userdata['nickname'])) {
            $this->error = _('Nickname contains illegal characters');

            return false;
        }

        if (empty($userdata['nickname'])) {
            $userdata['nickname'] = $userdata['email'];
        }

        /* Check that specified backend is writable */

        if (!$this->backends[$bnum]->writeable) {
            $this->error = _('Addressbook is read-only');

            return false;
        }

        /* Modify user in backend */

        $res = $this->backends[$bnum]->modify($alias, $userdata);

        if ($res) {
            return $bnum;
        }  

        $this->error = $this->backends[$bnum]->error;

        return false;

        return false;  /* Not reached */
    }

    /* end of modify() */
} /* End of class Addressbook */

/*
 * Generic backend that all other backends extend
 */

class addressbook_backend
{
    /* Variables that all backends must provide. */

    public $btype = 'dummy';

    public $bname = 'dummy';

    public $sname = 'Dummy backend';

    /*
     * Variables common for all backends, but that
     * should not be changed by the backends.
     */

    public $bnum = -1;

    public $error = '';

    public $writeable = false;

    public function set_error($string)
    {
        $this->error = '[' . $this->sname . '] ' . $string;

        return false;
    }

    /* ========================== Public ======================== */

    public function search($expression)
    {
        $this->set_error('search not implemented');

        return false;
    }

    public function lookup($alias)
    {
        $this->set_error('lookup not implemented');

        return false;
    }

    public function list_addr()
    {
        $this->set_error('list_addr not implemented');

        return false;
    }

    public function add($userdata)
    {
        $this->set_error('add not implemented');

        return false;
    }

    public function remove($alias)
    {
        $this->set_error('delete not implemented');

        return false;
    }

    public function modify($alias, $newuserdata)
    {
        $this->set_error('modify not implemented');

        return false;
    }
}

/* Sort array by the key "name" */
function alistcmp($a, $b)
{
    if ($a['backend'] > $b['backend']) {
        return 1;
    }  

    if ($a['backend'] < $b['backend']) {
        return -1;
    }

    return (mb_strtolower($a['name']) > mb_strtolower($b['name'])) ? 1 : -1;
}
?>
