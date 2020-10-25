<?php

/**
 * abook_ldap_server.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Address book backend for LDAP server
 *
 * An array with the following elements must be passed to
 * the class constructor (elements marked ? are optional):
 *
 *    host      => LDAP server hostname/IP-address
 *    base      => LDAP server root (base dn). Empty string allowed.
 *  ? port      => LDAP server TCP port number (default: 389)
 *  ? charset   => LDAP server charset (default: utf-8)
 *  ? name      => Name for LDAP server (default "LDAP: hostname")
 *                 Used to tag the result data
 *  ? maxrows   => Maximum # of rows in search result
 *  ? timeout   => Timeout for LDAP operations (in seconds, default: 30)
 *                 Might not work for all LDAP libraries or servers.
 *
 * NOTE. This class should not be used directly. Use the
 *       "AddressBook" class instead.
 *
 * $Id: abook_ldap_server.php,v 1.18 2003/01/23 20:50:21 kink Exp $
 */
class abook_ldap_server extends addressbook_backend
{
    public $btype = 'remote';

    public $bname = 'ldap_server';

    /* Parameters changed by class */

    public $sname = 'LDAP';       /* Service name */

    public $server = '';           /* LDAP server name */

    public $port = 389;          /* LDAP server port */

    public $basedn = '';           /* LDAP base DN  */

    public $charset = 'utf-8';      /* LDAP server charset */

    public $linkid = false;        /* PHP LDAP link ID */

    public $bound = false;        /* True if LDAP server is bound */

    public $maxrows = 250;          /* Max rows in result */

    public $timeout = 30;           /* Timeout for LDAP operations (in seconds) */

    /* Constructor. Connects to database */

    public function __construct($param)
    {
        if (!function_exists('ldap_connect')) {
            $this->set_error('LDAP support missing from PHP');

            return;
        }

        if (is_array($param)) {
            $this->server = $param['host'];

            $this->basedn = $param['base'];

            if (!empty($param['port'])) {
                $this->port = $param['port'];
            }

            if (!empty($param['charset'])) {
                $this->charset = mb_strtolower($param['charset']);
            }

            if (isset($param['maxrows'])) {
                $this->maxrows = $param['maxrows'];
            }

            if (isset($param['timeout'])) {
                $this->timeout = $param['timeout'];
            }

            if (empty($param['name'])) {
                $this->sname = 'LDAP: ' . $param['host'];
            } else {
                $this->sname = $param['name'];
            }

            $this->open(true);
        } else {
            $this->set_error('Invalid argument to constructor');
        }
    }

    /* Open the LDAP server. New connection if $new is true */

    public function open($new = false)
    {
        $this->error = '';

        /* Connection is already open */

        if (false !== $this->linkid && !$new) {
            return true;
        }

        $this->linkid = @ldap_connect($this->server, $this->port);

        if (!$this->linkid) {
            if (function_exists('ldap_error')) {
                return $this->set_error(ldap_error($this->linkid));
            }
  

            return $this->set_error('ldap_connect failed');
        }

        if (!@ldap_bind($this->linkid)) {
            if (function_exists('ldap_error')) {
                return $this->set_error(ldap_error($this->linkid));
            }
  

            return $this->set_error('ldap_bind failed');
        }

        $this->bound = true;

        return true;
    }

    /* Encode iso8859-1 string to the charset used by this LDAP server */

    public function charset_encode($str)
    {
        if ('utf-8' == $this->charset) {
            if (function_exists('utf8_encode')) {
                return utf8_encode($str);
            }
  

            return $str;
        }
  

        return $str;
    }

    /* Decode from charset used by this LDAP server to iso8859-1 */

    public function charset_decode($str)
    {
        if ('utf-8' == $this->charset) {
            if (function_exists('utf8_decode')) {
                return utf8_decode($str);
            }
  

            return $str;
        }
  

        return $str;
    }

    /* ========================== Public ======================== */

    /* Search the LDAP server */

    public function search($expr)
    {
        /* To be replaced by advanded search expression parsing */

        if (is_array($expr)) {
            return false;
        }

        /* Encode the expression */

        $expr = $this->charset_encode($expr);

        if (false === mb_strstr($expr, '*')) {
            $expr = "*$expr*";
        }

        $expression = "cn=$expr";

        /* Make sure connection is there */

        if (!$this->open()) {
            return false;
        }

        $sret = @ldap_search(
            $this->linkid,
            $this->basedn,
            $expression,
            [
                'dn',
                'o',
                'ou',
                'sn',
                'givenname',
                'cn',
                'mail',
                'telephonenumber',
            ],
            0,
            $this->maxrows,
            $this->timeout
        );

        /* Should get error from server using the ldap_error() function,
         * but it only exist in the PHP LDAP documentation. */

        if (!$sret) {
            if (function_exists('ldap_error')) {
                return $this->set_error(ldap_error($this->linkid));
            }
  

            return $this->set_error('ldap_search failed');
        }

        if (@ldap_count_entries($this->linkid, $sret) <= 0) {
            return [];
        }

        /* Get results */

        $ret = [];

        $returned_rows = 0;

        $res = @ldap_get_entries($this->linkid, $sret);

        for ($i = 0; $i < $res['count']; $i++) {
            $row = $res[$i];

            /* Extract data common for all e-mail addresses
             * of an object. Use only the first name */

            $nickname = $this->charset_decode($row['dn']);

            $fullname = $this->charset_decode($row['cn'][0]);

            if (empty($row['telephonenumber'][0])) {
                $phone = '';
            } else {
                $phone = $this->charset_decode($row['telephonenumber'][0]);
            }

            if (!empty($row['ou'][0])) {
                $label = $this->charset_decode($row['ou'][0]);
            } elseif (!empty($row['o'][0])) {
                $label = $this->charset_decode($row['o'][0]);
            } else {
                $label = '';
            }

            if (empty($row['givenname'][0])) {
                $firstname = '';
            } else {
                $firstname = $this->charset_decode($row['givenname'][0]);
            }

            if (empty($row['sn'][0])) {
                $surname = '';
            } else {
                $surname = $this->charset_decode($row['sn'][0]);
            }

            /* Add one row to result for each e-mail address */

            if (isset($row['mail']['count'])) {
                for ($j = 0; $j < $row['mail']['count']; $j++) {
                    $ret[] = [
                        'nickname'  => $nickname,
                        'name'      => $fullname,
                        'firstname' => $firstname,
                        'lastname'  => $surname,
                        'email'     => $row['mail'][$j],
                        'label'     => $label,
                        'phone'     => $phone,
                        'backend'   => $this->bnum,
                        'source'    => &$this->sname,
                    ];

                    // Limit number of hits

                    $returned_rows++;

                    if (($returned_rows >= $this->maxrows)
                        && ($this->maxrows > 0)) {
                        ldap_free_result($sret);

                        return $ret;
                    }
                } // for($j ...)
            } // isset($row['mail']['count'])
        }

        ldap_free_result($sret);

        return $ret;
    }

    /* end search() */

    /* If you run a tiny LDAP server and you want the "List All" button
     * to show EVERYONE, then uncomment this tiny block of code:
     *
     * function list_addr() {
     *    return $this->search('*');
     * }
     *
     * Careful with this -- it could get quite large for big sites. */
}
