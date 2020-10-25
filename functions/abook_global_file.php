<?php

/**
 * abook_local_file.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Backend for addressbook as a pipe separated file
 *
 * An array with the following elements must be passed to
 * the class constructor (elements marked ? are optional):
 *
 * NOTE. This class should not be used directly. Use the
 *       "AddressBook" class instead.
 *
 * Make sure you configure this before using it!
 *
 * $Id: abook_global_file.php,v 1.7 2002/12/31 12:49:30 kink Exp $
 */
class abook_global_file extends addressbook_backend
{
    public $btype = 'local';

    public $bname = 'global_file';

    public $filehandle = 0;

    /* ========================== Private ======================= */

    /* Constructor */

    public function __construct()
    {
        global $address_book_global_filename;

        $this->global_filename = $address_book_global_filename;

        $this->sname = _('Global address book');

        $this->open(true);
    }

    /* Open the addressbook file and store the file pointer.
     * Use $file as the file to open, or the class' own
     * filename property. If $param is empty and file is
     * open, do nothing. */

    public function open($new = false)
    {
        $this->error = '';

        /* Return true is file is open and $new is unset */

        if ($this->filehandle && !$new) {
            return true;
        }

        /* Check that new file exists */

        if (!file_exists($this->global_filename)
            || !is_readable($this->global_filename)) {
            return $this->set_error(
                $this->global_filename . ': ' . _('No such file or directory')
            );
        }

        /* Close old file, if any */

        if ($this->filehandle) {
            $this->close();
        }

        /* Open file, read only. */

        $fh = @fopen($this->global_filename, 'rb');

        $this->writeable = false;

        if (!$fh) {
            return $this->set_error(
                $this->global_filename . ': ' . _('Open failed')
            );
        }

        $this->filehandle = &$fh;

        return true;
    }

    /* Close the file and forget the filehandle */

    public function close()
    {
        @fclose($this->filehandle);

        $this->filehandle = 0;

        $this->global_filename = '';

        $this->writable = false;
    }

    /* ========================== Public ======================== */

    /* Search the file */

    public function search($expr)
    {
        /* To be replaced by advanded search expression parsing */

        if (is_array($expr)) {
            return;
        }

        /* Make regexp from glob'ed expression
         * May want to quote other special characters like (, ), -, [, ], etc. */

        $expr = str_replace('?', '.', $expr);

        $expr = str_replace('*', '.*', $expr);

        $res = [];

        if (!$this->open()) {
            return false;
        }

        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            $line = implode(' ', $row);

            if (eregi($expr, $line)) {
                $res[] = [
                    'nickname' => $row[0],
                    'name' => $row[1] . ' ' . $row[2],
                    'firstname' => $row[1],
                    'lastname' => $row[2],
                    'email' => $row[3],
                    'label' => $row[4],
                    'backend' => $this->bnum,
                    'source' => &$this->sname,
                ];
            }
        }

        return $res;
    }

    /* Lookup alias */

    public function lookup($alias)
    {
        if (empty($alias)) {
            return [];
        }

        $alias = mb_strtolower($alias);

        $this->open();

        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if (mb_strtolower($row[0]) == $alias) {
                return [
                    'nickname' => $row[0],
                    'name' => $row[1] . ' ' . $row[2],
                    'firstname' => $row[1],
                    'lastname' => $row[2],
                    'email' => $row[3],
                    'label' => $row[4],
                    'backend' => $this->bnum,
                    'source' => &$this->sname,
                ];
            }
        }

        return [];
    }

    /* List all addresses */

    public function list_addr()
    {
        $res = [];

        $this->open();

        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            $res[] = [
                'nickname' => $row[0],
                'name' => $row[1] . ' ' . $row[2],
                'firstname' => $row[1],
                'lastname' => $row[2],
                'email' => $row[3],
                'label' => $row[4],
                'backend' => $this->bnum,
                'source' => &$this->sname,
            ];
        }

        return $res;
    }

    /* Add address */

    public function add($userdata)
    {
        $this->set_error(_('Can not modify global address book'));

        return false;
    }

    /* Delete address */

    public function remove($alias)
    {
        $this->set_error(_('Can not modify global address book'));

        return false;
    }

    /* Modify address */

    public function modify($alias, $userdata)
    {
        $this->set_error(_('Can not modify global address book'));

        return false;
    }
} /* End of class abook_local_file */
