<?php
/***************************************************************************
 *                              admin_words.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_words.php 8377 2008-02-10 12:52:05Z acydburn $
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

if (!empty($setmodules) ) {
	$file = basename(__FILE__);
	$module['General']['Word_Censor'] = $file;
	return;
}

define('IN_PHPBB', 1);

//
// Load default header
//
$phpbb_root_path = './../';

$cancel = isset($_POST['cancel']);
$no_page_header = $cancel;

require './pagestart.php';

if ($cancel) {
    redirect('admin/' . Session::appendSid('admin_words.php', true));
}

if (isset($_GET['mode']) || isset($_POST['mode'])) {
    $mode = isset($_GET['mode']) ? $_GET['mode'] : $_POST['mode'];
    $mode = htmlspecialchars($mode);
} else {
    //
    // These could be entered via a form button
    //
    if (isset($_POST['add'])) {
        $mode = 'add';
    } elseif (isset($_POST['save'])) {
        $mode = 'save';
    } else {
        $mode = '';
    }
}

// Restrict mode input to valid options
$mode = in_array($mode, ['add', 'edit', 'save', 'delete'], true) ? $mode : '';

if ($mode !== '')
{
	if ($mode === 'edit' || $mode === 'add')
	{
		$word_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $template->setFileNames(['body' => 'admin/words_edit_body.tpl']);

        $word_info = ['word' => '', 'replacement' => ''];
        $s_hidden_fields = '';

		if ($mode === 'edit') {
			if ($word_id ) {
                $word_info = dibi::select('*')
                    ->from(WORDS_TABLE)
                    ->where('word_id = %i', $word_id)
                    ->fetch();

				$s_hidden_fields .= '<input type="hidden" name="id" value="' . $word_id . '" />';
			} else {
				message_die(GENERAL_MESSAGE, $lang['No_word_selected']);
			}
		}

        $template->assignVars(
            [
                'WORD'        => htmlspecialchars($word_info['word']),
                'REPLACEMENT' => htmlspecialchars($word_info['replacement']),

                'L_WORDS_TITLE' => $lang['Words_title'],
                'L_WORDS_TEXT'  => $lang['Words_explain'],
                'L_WORD_CENSOR' => $lang['Edit_word_censor'],
                'L_WORD'        => $lang['Word'],
                'L_REPLACEMENT' => $lang['Replacement'],
                'L_SUBMIT'      => $lang['Submit'],

                'S_WORDS_ACTION'  => Session::appendSid('admin_words.php'),
                'S_HIDDEN_FIELDS' => $s_hidden_fields
            ]
        );

        $template->pparse('body');

		include './page_footer_admin.php';
	} elseif ($mode === 'save') {
		$word_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$word = isset($_POST['word']) ? trim($_POST['word']) : '';
		$replacement = isset($_POST['replacement']) ? trim($_POST['replacement']) : '';

		if ($word === '' || $replacement === '') {
			message_die(GENERAL_MESSAGE, $lang['Must_enter_word']);
		}

		if ($word_id ) {
		    $update_data = [
		        'word' => $word,
                'replacement' => $replacement
            ];

            dibi::update(WORDS_TABLE, $update_data)
                ->where('word_id = %i', $word_id)
                ->execute();

			$message = $lang['Word_updated'];
		} else {
		    $insert_data = [
		        'word' => $word,
                'replacement' => $replacement
            ];

		    dibi::insert(WORDS_TABLE, $insert_data)->execute();

			$message = $lang['Word_added'];
		}

		$message .= '<br /><br />' . sprintf($lang['Click_return_wordadmin'], '<a href="' . Session::appendSid('admin_words.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} elseif ($mode === 'delete') {
        if (isset($_POST['id']) || isset($_GET['id'])) {
            $word_id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];
            $word_id = (int)$word_id;
        } else {
            $word_id = 0;
        }

		$confirm = isset($_POST['confirm']);

		if ($word_id && $confirm ) {
		    dibi::delete(WORDS_TABLE)
                ->where('word_id = %i', $word_id)
                ->execute();

			$message = $lang['Word_removed'] . '<br /><br />' . sprintf($lang['Click_return_wordadmin'], '<a href="' . Session::appendSid('admin_words.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
		} elseif ($word_id && !$confirm) {
			// Present the confirmation screen to the user
            $template->setFileNames(['body' => 'admin/confirm_body.tpl']);

            $hidden_fields = '<input type="hidden" name="mode" value="delete" /><input type="hidden" name="id" value="' . $word_id . '" />';

            $template->assignVars(
                [
                    'MESSAGE_TITLE' => $lang['Confirm'],
                    'MESSAGE_TEXT'  => $lang['Confirm_delete_word'],

                    'L_YES' => $lang['Yes'],
                    'L_NO'  => $lang['No'],

                    'S_CONFIRM_ACTION' => Session::appendSid('admin_words.php'),
                    'S_HIDDEN_FIELDS'  => $hidden_fields
                ]
            );
        } else {
			message_die(GENERAL_MESSAGE, $lang['No_word_selected']);
		}
	}
} else {
    $template->setFileNames(['body' => 'admin/words_list_body.tpl']);

     $words = dibi::select('*')
         ->from(WORDS_TABLE)
         ->orderBy('word')
         ->fetchAll();

    $template->assignVars(
        [
            'L_WORDS_TITLE' => $lang['Words_title'],
            'L_WORDS_TEXT'  => $lang['Words_explain'],
            'L_WORD'        => $lang['Word'],
            'L_REPLACEMENT' => $lang['Replacement'],
            'L_EDIT'        => $lang['Edit'],
            'L_DELETE'      => $lang['Delete'],
            'L_ADD_WORD'    => $lang['Add_new_word'],
            'L_ACTION'      => $lang['Action'],

            'S_WORDS_ACTION'  => Session::appendSid('admin_words.php'),
            'S_HIDDEN_FIELDS' => ''
        ]
    );

    foreach ($words as $word) {
		$word_id = $word->word_id;

		$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
		$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('words',
            [
                'ROW_COLOR'   => '#' . $row_color,
                'ROW_CLASS'   => $row_class,
                'WORD'        => htmlspecialchars($word->word),
                'REPLACEMENT' => htmlspecialchars($word->replacement),

                'U_WORD_EDIT'   => Session::appendSid("admin_words.php?mode=edit&amp;id=$word_id"),
                'U_WORD_DELETE' => Session::appendSid("admin_words.php?mode=delete&amp;id=$word_id")
            ]
        );
    }
}

$template->pparse('body');

include './page_footer_admin.php';

?>