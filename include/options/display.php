<?php

/**
 * options_display.php
 *
 * Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * Displays all optinos about display preferences
 *
 * $Id: display.php,v 1.4 2003/01/15 15:15:17 kink Exp $
 */

/* Define the group constants for the display options page. */
define('SMOPT_GRP_GENERAL', 0);
define('SMOPT_GRP_MAILBOX', 1);
define('SMOPT_GRP_MESSAGE', 2);

/* Define the optpage load function for the display options page. */
function load_optpage_data_display()
{
    global $theme, $language, $languages, $js_autodetect_results, $compose_new_win, $default_use_mdn, $squirrelmail_language, $allow_thread_sort;

    /* Build a simple array into which we will build options. */

    $optgrps = [];

    $optvals = [];

    /******************************************************/ /* LOAD EACH GROUP OF OPTIONS INTO THE OPTIONS ARRAY. */ /******************************************************/

    /*** Load the General Options into the array ***/

    $optgrps[SMOPT_GRP_GENERAL] = _('General Display Options');

    $optvals[SMOPT_GRP_GENERAL] = [];

    /* Load the theme option. */

    $theme_values = [];

    foreach ($theme as $theme_key => $theme_attributes) {
        $theme_values[$theme_attributes['NAME']] = $theme_attributes['PATH'];
    }

    ksort($theme_values);

    $theme_values = array_flip($theme_values);

    $optvals[SMOPT_GRP_GENERAL][] = [
        'name' => 'chosen_theme',
        'caption' => _('Theme'),
        'type' => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_ALL,
        'posvals' => $theme_values,
        'save' => 'save_option_theme',
    ];

    $css_values = ['none' => _('Default')];

    $handle = opendir('../themes/css/');

    while ($file = readdir($handle)) {
        if ('.css' == mb_substr($file, -4)) {
            $css_values[$file] = mb_substr($file, 0, -4);
        }
    }

    closedir($handle);

    if (count($css_values > 1)) {
        $optvals[SMOPT_GRP_GENERAL][] = [
            'name' => 'custom_css',
            'caption' => _('Custom Stylesheet'),
            'type' => SMOPT_TYPE_STRLIST,
            'refresh' => SMOPT_REFRESH_ALL,
            'posvals' => $css_values,
        ];
    }

    $language_values = [];

    foreach ($languages as $lang_key => $lang_attributes) {
        if (isset($lang_attributes['NAME'])) {
            $language_values[$lang_key] = $lang_attributes['NAME'];
        }
    }

    asort($language_values);

    $language_values = array_merge(['' => _('Default')], $language_values);

    $language = $squirrelmail_language;

    $optvals[SMOPT_GRP_GENERAL][] = [
        'name' => 'language',
        'caption' => _('Language'),
        'type' => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_ALL,
        'posvals' => $language_values,
    ];

    /* Set values for the "use javascript" option. */

    $optvals[SMOPT_GRP_GENERAL][] = [
        'name' => 'javascript_setting',
        'caption' => _('Use Javascript'),
        'type' => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_ALL,
        'posvals' => [
            SMPREF_JS_AUTODETECT => _('Autodetect'),
            SMPREF_JS_ON => _('Always'),
            SMPREF_JS_OFF => _('Never'),
        ],
    ];

    $js_autodetect_script = "<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\"><!--\n" . "document.forms[0].new_js_autodetect_results.value = '" . SMPREF_JS_ON . "';\n" . "// --></SCRIPT>\n";

    $js_autodetect_results = SMPREF_JS_OFF;

    $optvals[SMOPT_GRP_GENERAL][] = [
        'name' => 'js_autodetect_results',
        'caption' => '',
        'type' => SMOPT_TYPE_HIDDEN,
        'refresh' => SMOPT_REFRESH_NONE,
        'script' => $js_autodetect_script,
        'save' => 'save_option_javascript_autodetect',
    ];

    /*** Load the General Options into the array ***/

    $optgrps[SMOPT_GRP_MAILBOX] = _('Mailbox Display Options');

    $optvals[SMOPT_GRP_MAILBOX] = [];

    $optvals[SMOPT_GRP_MAILBOX][] = [
        'name' => 'show_num',
        'caption' => _('Number of Messages to Index'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_NONE,
        'size' => SMOPT_SIZE_TINY,
    ];

    $optvals[SMOPT_GRP_MAILBOX][] = [
        'name' => 'alt_index_colors',
        'caption' => _('Enable Alternating Row Colors'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MAILBOX][] = [
        'name' => 'page_selector',
        'caption' => _('Enable Page Selector'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MAILBOX][] = [
        'name' => 'page_selector_max',
        'caption' => _('Maximum Number of Pages to Show'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_NONE,
        'size' => SMOPT_SIZE_TINY,
    ];

    /*** Load the General Options into the array ***/

    $optgrps[SMOPT_GRP_MESSAGE] = _('Message Display and Composition');

    $optvals[SMOPT_GRP_MESSAGE] = [];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'wrap_at',
        'caption' => _('Wrap Incoming Text At'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_NONE,
        'size' => SMOPT_SIZE_TINY,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'editor_size',
        'caption' => _('Size of Editor Window'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_NONE,
        'size' => SMOPT_SIZE_TINY,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'location_of_buttons',
        'caption' => _('Location of Buttons when Composing'),
        'type' => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_NONE,
        'posvals' => [
            SMPREF_LOC_TOP => _('Before headers'),
            SMPREF_LOC_BETWEEN => _('Between headers and message body'),
            SMPREF_LOC_BOTTOM => _('After message body'),
        ],
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'use_javascript_addr_book',
        'caption' => _('Addressbook Display Format'),
        'type' => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_NONE,
        'posvals' => [
            '1' => _('Javascript'),
            '0' => _('HTML'),
        ],
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'show_html_default',
        'caption' => _('Show HTML Version by Default'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'enable_forward_as_attachment',
        'caption' => _('Enable Forward as Attachment'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'forward_cc',
        'caption' => _('Include CCs when Forwarding Messages'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'include_self_reply_all',
        'caption' => _('Include Me in CC when I Reply All'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'show_xmailer_default',
        'caption' => _('Enable Mailer Display'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'attachment_common_show_images',
        'caption' => _('Display Attached Images with Message'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'pf_cleandisplay',
        'caption' => _('Enable Printer Friendly Clean Display'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    if ($default_use_mdn) {
        $optvals[SMOPT_GRP_MESSAGE][] = [
            'name' => 'mdn_user_support',
            'caption' => _('Enable Mail Delivery Notification'),
            'type' => SMOPT_TYPE_BOOLEAN,
            'refresh' => SMOPT_REFRESH_NONE,
        ];
    }

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'compose_new_win',
        'caption' => _('Compose Messages in New Window'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_ALL,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'compose_width',
        'caption' => _('Width of Compose Window'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_ALL,
        'size' => SMOPT_SIZE_TINY,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'compose_height',
        'caption' => _('Height of Compose Window'),
        'type' => SMOPT_TYPE_INTEGER,
        'refresh' => SMOPT_REFRESH_ALL,
        'size' => SMOPT_SIZE_TINY,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'sig_first',
        'caption' => _('Append Signature before Reply/Forward Text'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE,
    ];

    $optvals[SMOPT_GRP_MESSAGE][] = [
        'name' => 'internal_date_sort',
        'caption' => _('Enable Sort by of Receive Date'),
        'type' => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_ALL,
    ];

    if (true === $allow_thread_sort) {
        $optvals[SMOPT_GRP_MESSAGE][] = [
            'name' => 'sort_by_ref',
            'caption' => _('Enable Thread Sort by References Header'),
            'type' => SMOPT_TYPE_BOOLEAN,
            'refresh' => SMOPT_REFRESH_ALL,
        ];
    }

    /* Assemble all this together and return it as our result. */

    $result = [
        'grps' => $optgrps,
        'vals' => $optvals,
    ];

    return ($result);
}

/******************************************************************/
/** Define any specialized save functions for this option page. **
 * @param $option
 */
/******************************************************************/

function save_option_theme($option)
{
    global $theme;

    /* Do checking to make sure $new_theme is in the array. */

    $theme_in_array = false;

    for ($i = 0, $iMax = count($theme); $i < $iMax; ++$i) {
        if ($theme[$i]['PATH'] == $option->new_value) {
            $theme_in_array = true;

            break;
        }
    }

    if (!$theme_in_array) {
        $option->new_value = '';
    }

    /* Save the option like normal. */

    save_option($option);
}

function save_option_javascript_autodetect($option)
{
    global $data_dir, $username, $new_javascript_setting;

    /* Set javascript either on or off. */

    if (SMPREF_JS_AUTODETECT == $new_javascript_setting) {
        if (SMPREF_JS_ON == $option->new_value) {
            setPref($data_dir, $username, 'javascript_on', SMPREF_JS_ON);
        } else {
            setPref($data_dir, $username, 'javascript_on', SMPREF_JS_OFF);
        }
    } else {
        setPref($data_dir, $username, 'javascript_on', $new_javascript_setting);
    }
}
