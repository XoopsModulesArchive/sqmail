<?php

/**
 * day.php
 *
 * Copyright (c) 2002-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Originally contrubuted by Michal Szczotka <michal@tuxy.org>
 *
 * Displays the day page (day view).
 *
 * $Id: day.php,v 1.14 2002/12/31 12:49:34 kink Exp $
 */
define('SM_PATH', '../../');

/* Calender plugin required files. */
require_once SM_PATH . 'plugins/calendar/calendar_data.php';
require_once SM_PATH . 'plugins/calendar/functions.php';

/* SquirrelMail required files. */
require_once SM_PATH . 'include/validate.php';
require_once SM_PATH . 'functions/strings.php';
require_once SM_PATH . 'functions/date.php';
require_once SM_PATH . 'config/config.php';
require_once SM_PATH . 'functions/page_header.php';
require_once SM_PATH . 'include/load_prefs.php';
require_once SM_PATH . 'functions/html.php';

/* get globals */
if (isset($_GET['year'])) {
    $year = $_GET['year'];
} elseif (isset($_POST['year'])) {
    $year = $_POST['year'];
}
if (isset($_GET['month'])) {
    $month = $_GET['month'];
} elseif (isset($_POST['month'])) {
    $month = $_POST['month'];
}
if (isset($_GET['day'])) {
    $day = $_GET['day'];
} elseif (isset($_POST['day'])) {
    $day = $_POST['day'];
}

/* got 'em */

//displays head of day calendar view
function day_header()
{
    global $color, $month, $day, $year, $prev_year, $prev_month, $prev_day, $prev_date, $next_month, $next_day, $next_year, $next_date;

    echo html_tag('tr', '', '', $color[0]) . "\n" . html_tag('td', '', 'left') . html_tag('table', '', '', $color[0], 'width="100%" border="0" cellpadding="2" cellspacing="1"') . "\n" . html_tag(
        'tr',
        html_tag(
                'th',
                "<a href=\"day.php?year=$prev_year&amp;month=$prev_month&amp;day=$prev_day\">&lt;&nbsp;" . date_intl('D', $prev_date) . '</a>',
                'left'
            ) . html_tag(
                'th',
                date_intl(_('l, F j Y'), mktime(0, 0, 0, $month, $day, $year)),
                '',
                '',
                'width="75%"'
            ) . html_tag(
                'th',
                "<a href=\"day.php?year=$next_year&amp;month=$next_month&amp;day=$next_day\">" . date_intl('D', $next_date) . '&nbsp;&gt;</a>',
                'right'
            )
    );
}

//events for specific day  are inserted into "daily" array
function initialize_events()
{
    global $daily_events, $calendardata, $month, $day, $year;

    for ($i = 7; $i < 23; $i++) {
        if ($i < 10) {
            $evntime = '0' . $i . '00';
        } else {
            $evntime = $i . '00';
        }

        $daily_events[$evntime] = 'empty';
    }

    $cdate = $month . $day . $year;

    if (isset($calendardata[$cdate])) {
        while ($calfoo = each($calendardata[$cdate])) {
            $daily_events[(string)$calfoo[key]] = $calendardata[$cdate][$calfoo['key']];
        }
    }
}

//main loop for displaying daily events
function display_events()
{
    global $daily_events, $month, $day, $year, $color;

    ksort($daily_events, SORT_STRING);

    $eo = 0;

    while ($calfoo = each($daily_events)) {
        if (0 == $eo) {
            $eo = 4;
        } else {
            $eo = 0;
        }

        $ehour = mb_substr($calfoo['key'], 0, 2);

        $eminute = mb_substr($calfoo['key'], 2, 2);

        if (!is_array($calfoo['value'])) {
            echo html_tag(
                'tr',
                html_tag('td', $ehour . ':' . $eminute, 'left') . html_tag('td', '&nbsp;', 'left') . html_tag(
                    'td',
                    "<font size=\"-1\"><a href=\"event_create.php?year=$year&amp;month=$month&amp;day=$day&amp;hour=" . mb_substr($calfoo['key'], 0, 2) . '">' . _('ADD') . '</a></font>',
                    'center'
                ),
                '',
                $color[$eo]
            );
        } else {
            $calbar = $calfoo['value'];

            if (0 != $calbar['length']) {
                $elength = '-' . date('H:i', mktime($ehour, $eminute + $calbar['length'], 0, 1, 1, 0));
            } else {
                $elength = '';
            }

            echo html_tag('tr', '', '', $color[$eo]) . html_tag('td', $ehour . ':' . $eminute . $elength, 'left') . html_tag('td', '', 'left') . '[';

            echo (1 == $calbar['priority']) ? "<font color=\"$color[1]\">$calbar[title]</font>" : (string)$calbar[title];

            echo "] $calbar[message]&nbsp;" . html_tag(
                'td',
                "<font size=\"-1\"><nobr>\n"
                    . "<a href=\"event_edit.php?year=$year&amp;month=$month&amp;day=$day&amp;hour="
                    . mb_substr($calfoo['key'], 0, 2)
                    . '&amp;minute='
                    . mb_substr($calfoo['key'], 2, 2)
                    . '">'
                    . _('EDIT')
                    . "</a>&nbsp;|&nbsp;\n"
                    . "<a href=\"event_delete.php?dyear=$year&amp;dmonth=$month&amp;dday=$day&amp;dhour="
                    . mb_substr($calfoo['key'], 0, 2)
                    . '&amp;dminute='
                    . mb_substr($calfoo['key'], 2, 2)
                    . "&amp;year=$year&amp;month=$month&amp;day=$day\">"
                    . _('DEL')
                    . '</a>'
                    . "</nobr></font>\n",
                'center'
            );
        }
    }
}

if ($month <= 0) {
    $month = date('m');
}
if ($year <= 0) {
    $year = date('Y');
}
if ($day <= 0) {
    $day = date('d');
}

$prev_date = mktime(0, 0, 0, $month, $day - 1, $year);
$next_date = mktime(0, 0, 0, $month, $day + 1, $year);
$prev_day = date('d', $prev_date);
$prev_month = date('m', $prev_date);
$prev_year = date('Y', $prev_date);
$next_day = date('d', $next_date);
$next_month = date('m', $next_date);
$next_year = date('Y', $next_date);

$calself = basename($PHP_SELF);

$daily_events = [];

displayPageHeader($color, 'None');
calendar_header();
readcalendardata();
day_header();
initialize_events();
display_events();
?>
</table></td></tr></table>
</body></html>
