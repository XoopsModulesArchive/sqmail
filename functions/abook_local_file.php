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
 *    filename  => path to addressbook file
 *  ? create    => if true: file is created if it does not exist.
 *  ? umask     => umask set before opening file.
 *
 * NOTE. This class should not be used directly. Use the
 *       "AddressBook" class instead.
 *
 * $Id: abook_local_file.php,v 1.22 2002/12/31 12:49:31 kink Exp $
 */
class abook_local_file extends addressbook_backend
{
    public $btype = 'local';

    public $bname = 'local_file';

    public $filename = '';

    public $filehandle = 0;

    public $create = false;

    public $umask;

    /* ========================== Private ======================= */

    /* Constructor */

    public function __construct($param)
    {
        $this->sname = _('Personal address book');

        $this->umask = umask();

        if (is_array($param)) {
            if (empty($param['filename'])) {
                return $this->set_error('Invalid parameters');
            }

            if (!is_string($param['filename'])) {
                return $this->set_error(
                    $param['filename'] . ': ' . _('Not a file name')
                );
            }

            $this->filename = $param['filename'];

            if ($param['create']) {
                $this->create = true;
            }

            if (isset($param['umask'])) {
                $this->umask = $param['umask'];
            }

            if (!empty($param['name'])) {
                $this->sname = $param['name'];
            }

            $this->open(true);
        } else {
            $this->set_error('Invalid argument to constructor');
        }
    }

    /* Open the addressbook file and store the file pointer.
     * Use $file as the file to open, or the class' own
     * filename property. If $param is empty and file is
     * open, do nothing. */

    public function open($new = false)
    {
        $this->error = '';

        $file = $this->filename;

        $create = $this->create;

        /* Return true is file is open and $new is unset */

        if ($this->filehandle && !$new) {
            return true;
        }

        /* Check that new file exitsts */

        if ((!(file_exists($file) && is_readable($file))) && !$create) {
            return $this->set_error("$file: " . _('No such file or directory'));
        }

        /* Close old file, if any */

        if ($this->filehandle) {
            $this->close();
        }

        /* Open file. First try to open for reading and writing,
         * but fall back to read only. */

        umask($this->umask);

        $fh = @fopen($file, 'a+b');

        if ($fh) {
            $this->filehandle = &$fh;

            $this->filename = $file;

            $this->writeable = true;
        } else {
            $fh = @fopen($file, 'rb');

            if ($fh) {
                $this->filehandle = &$fh;

                $this->filename = $file;

                $this->writeable = false;
            } else {
                return $this->set_error("$file: " . _('Open failed'));
            }
        }

        return true;
    }

    /* Close the file and forget the filehandle */

    public function close()
    {
        @fclose($this->filehandle);

        $this->filehandle = 0;

        $this->filename = '';

        $this->writable = false;
    }

    /* Lock the datafile - try 20 times in 5 seconds */

    public function lock()
    {
        for ($i = 0; $i < 20; $i++) {
            if (flock($this->filehandle, 2 + 4)) {
                return true;
            }  

            usleep(250000);
        }

        return false;
    }

    /* Lock the datafile */

    public function unlock()
    {
        return flock($this->filehandle, 3);
    }

    /* Overwrite the file with data from $rows
     * NOTE! Previous locks are broken by this function */

    public function overwrite(&$rows)
    {
        $this->unlock();

        $newfh = @fopen($this->filename . '.tmp', 'wb');

        if (!$newfh) {
            return $this->set_error($this->filename . '.tmp:' . _('Open failed'));
        }

        for ($i = 0, $cnt = count($rows); $i < $cnt; $i++) {
            if (is_array($rows[$i])) {
                for ($j = 0, $cnt_part = count($rows[$i]); $j < $cnt_part; $j++) {
                    $rows[$i][$j] = $this->quotevalue($rows[$i][$j]);
                }

                $tmpwrite = @fwrite($newfh, implode('|', $rows[$i]) . "\n");

                if (-1 == $tmpwrite) {
                    return $this->set_error($this->filename . '.tmp:' . _('Write failed'));
                }
            }
        }

        fclose($newfh);

        if (!@copy($this->filename . '.tmp', $this->filename)) {
            return $this->set_error($this->filename . ':' . _('Unable to update'));
        }

        @unlink($this->filename . '.tmp');

        $this->unlock();

        $this->open(true);

        return true;
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
                    'nickname'  => $row[0],
                    'name'      => $row[1] . ' ' . $row[2],
                    'firstname' => $row[1],
                    'lastname'  => $row[2],
                    'email'     => $row[3],
                    'label'     => $row[4],
                    'backend'   => $this->bnum,
                    'source'    => &$this->sname,
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
                'nickname'  => $row[0],
                'name'      => $row[1] . ' ' . $row[2],
                'firstname' => $row[1],
                'lastname'  => $row[2],
                'email'     => $row[3],
                'label'     => $row[4],
                'backend'   => $this->bnum,
                'source'    => &$this->sname,
            ];
        }

        return $res;
    }

    /* Add address */

    public function add($userdata)
    {
        if (!$this->writeable) {
            return $this->set_error(_('Addressbook is read-only'));
        }

        /* See if user exists already */

        $ret = $this->lookup($userdata['nickname']);

        if (!empty($ret)) {
            return $this->set_error(
                sprintf(
                    _("User '%s' already exist"),
                    $ret['nickname']
                )
            );
        }

        /* Here is the data to write */

        $data = $this->quotevalue($userdata['nickname']) . '|' . $this->quotevalue($userdata['firstname']) . '|' . $this->quotevalue($userdata['lastname']) . '|' . $this->quotevalue($userdata['email']) . '|' . $this->quotevalue($userdata['label']);

        /* Strip linefeeds */

        $data = preg_replace("[\r\n]", ' ', $data);

        /* Add linefeed at end */

        $data .= "\n";

        /* Reopen file, just to be sure */

        $this->open(true);

        if (!$this->writeable) {
            return $this->set_error(_('Addressbook is read-only'));
        }

        /* Lock the file */

        if (!$this->lock()) {
            return $this->set_error(_('Could not lock datafile'));
        }

        /* Write */

        $r = fwrite($this->filehandle, $data);

        /* Unlock file */

        $this->unlock();

        /* Test write result and exit if OK */

        if ($r > 0) {
            return true;
        }

        /* Fail */

        $this->set_error(_('Write to addressbook failed'));

        return false;
    }

    /* Delete address */

    public function remove($alias)
    {
        if (!$this->writeable) {
            return $this->set_error(_('Addressbook is read-only'));
        }

        /* Lock the file to make sure we're the only process working
         * on it. */

        if (!$this->lock()) {
            return $this->set_error(_('Could not lock datafile'));
        }

        /* Read file into memory, ignoring nicknames to delete */

        @rewind($this->filehandle);

        $i = 0;

        $rows = [];

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if (!in_array($row[0], $alias, true)) {
                $rows[$i++] = $row;
            }
        }

        /* Write data back */

        if (!$this->overwrite($rows)) {
            $this->unlock();

            return false;
        }

        $this->unlock();

        return true;
    }

    /* Modify address */

    public function modify($alias, $userdata)
    {
        if (!$this->writeable) {
            return $this->set_error(_('Addressbook is read-only'));
        }

        /* See if user exists */

        $ret = $this->lookup($alias);

        if (empty($ret)) {
            return $this->set_error(
                sprintf(
                    _("User '%s' does not exist"),
                    $alias
                )
            );
        }

        /* Lock the file to make sure we're the only process working
         * on it. */

        if (!$this->lock()) {
            return $this->set_error(_('Could not lock datafile'));
        }

        /* Read file into memory, modifying the data for the
         * user identified by $alias */

        $this->open(true);

        @rewind($this->filehandle);

        $i = 0;

        $rows = [];

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if (mb_strtolower($row[0]) != mb_strtolower($alias)) {
                $rows[$i++] = $row;
            } else {
                $rows[$i++] = [
                    0 => $userdata['nickname'],
                    1 => $userdata['firstname'],
                    2 => $userdata['lastname'],
                    3 => $userdata['email'],
                    4 => $userdata['label'],
                ];
            }
        }

        /* Write data back */

        if (!$this->overwrite($rows)) {
            $this->unlock();

            return false;
        }

        $this->unlock();

        return true;
    }

    /* Function for quoting values before saving */

    public function quotevalue($value)
    {
        /* Quote the field if it contains | or ". Double quotes need to
         * be replaced with "" */

        if (preg_match('[|"]', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
} /* End of class abook_local_file */
