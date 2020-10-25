<?php

/**
 * setup.php
 *
 * Copyright (c) 2002-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Originally contrubuted by Michal Szczotka <michal@tuxy.org>
 *
 * init plugin into squirrelmail
 *
 * $Id: setup.php,v 1.5 2002/12/31 12:49:35 kink Exp $
 */
function squirrelmail_plugin_init_calendar()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['menuline']['calendar'] = 'calendar';
}

function calendar()
{
    /* Add Calendar link to upper menu */

    displayInternalLink('plugins/calendar/calendar.php', _('Calendar'), 'right');

    echo "&nbsp;&nbsp;\n";
}
