<?php
/***************************************************************************
 *                                  faq.php
 *                            -------------------
 *   begin                : Sunday, Jul 8, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: faq.php 4926 2004-07-11 16:46:20Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Start session management
//
$userdata = init_userprefs(PAGE_FAQ);
//
// End session management
//

// Set vars to prevent naughtiness
$faq = [];

//
// Load the appropriate faq file
//
if (isset($_GET[POST_MODE])) {
    switch ($_GET[POST_MODE]) {
        case 'bbcode':
            $lang_file = 'lang_bbcode';
            $l_title = $lang['BBCode_guide'];
            break;
        default:
            $lang_file = 'lang_faq';
            $l_title = $lang['FAQ'];
            break;
    }
} else {
    $lang_file = 'lang_faq';
    $l_title = $lang['FAQ'];
}

require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . $lang_file . '.php';

attach_faq_include($lang_file);

//
// Pull the array data from the lang pack
//
$j = 0;
$counter = 0;
$counter_2 = 0;
$faq_block = [];
$faq_block_titles = [];
$faq_count = count($faq);

for ($i = 0; $i < $faq_count; $i++) {
	if ($faq[$i][0] !== '--') {
		$faq_block[$j][$counter]['id'] = $counter_2;
		$faq_block[$j][$counter]['question'] = $faq[$i][0];
		$faq_block[$j][$counter]['answer'] = $faq[$i][1];

		$counter++;
		$counter_2++;
	} else {
		$j = $counter !== 0 ? $j + 1 : 0;

		$faq_block_titles[$j] = $faq[$i][1];

		$counter = 0;
	}
}

//
// Lets build a page ...
//
PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $l_title, $gen_simple_header);

$template->setFileNames(['body' => 'faq_body.tpl']);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'L_FAQ_TITLE'   => $l_title,
        'L_BACK_TO_TOP' => $lang['Back_to_top']
    ]
);

$faq_block_count = count($faq_block);

for ($i = 0; $i < $faq_block_count; $i++) {
    $faq_block_i_count = count($faq_block[$i]);

	if ($faq_block_i_count) {
        $template->assignBlockVars('faq_block', ['BLOCK_TITLE' => $faq_block_titles[$i]]);
        $template->assignBlockVars('faq_block_link', ['BLOCK_TITLE' => $faq_block_titles[$i]]);

        for ($j = 0; $j < $faq_block_i_count; $j++) {
			$rowColor = ($j % 2) ? $theme['td_color1'] : $theme['td_color2'];
			$rowClass = ($j % 2) ? $theme['td_class1'] : $theme['td_class2'];

			$faq_block_faq_row_data = [
                'ROW_COLOR'    => '#' . $rowColor,
                'ROW_CLASS'    => $rowClass,
                'FAQ_QUESTION' => $faq_block[$i][$j]['question'],
                'FAQ_ANSWER'   => $faq_block[$i][$j]['answer'],

                'U_FAQ_ID' => $faq_block[$i][$j]['id']
            ];

            $template->assignBlockVars('faq_block.faq_row', $faq_block_faq_row_data);

            $faq_block_link_faq_row_link_data = [
                'ROW_COLOR' => '#' . $rowColor,
                'ROW_CLASS' => $rowClass,
                'FAQ_LINK'  => $faq_block[$i][$j]['question'],

                'U_FAQ_LINK' => '#' . $faq_block[$i][$j]['id']
            ];

            $template->assignBlockVars('faq_block_link.faq_row_link', $faq_block_link_faq_row_link_data);
        }
    }
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>