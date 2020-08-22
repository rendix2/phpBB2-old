<?php
/***************************************************************************
*                               admin_smilies.php
*                              -------------------
*     begin                : Thu May 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: admin_smilies.php 8377 2008-02-10 12:52:05Z acydburn $
*
****************************************************************************/

use Nette\Caching\Cache;
use Nette\Utils\Finder;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

/**************************************************************************
*	This file will be used for modifying the smiley settings for a board.
**************************************************************************/

define('IN_PHPBB', 1);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

$no_page_header = $cancel = isset($_POST['cancel']);

//
// Load default header
//
if ((!empty($_GET['export_pack']) && $_GET['export_pack'] === 'send') || (!empty($_GET['export_pack']) && $_GET['export_pack'] === 'send')) {
	$no_page_header = true;
}

require_once '.' . $sep . 'pagestart.php';

if ($cancel) {
	redirect('admin/' . Session::appendSid('admin_smilies.php', true));
}

//
// Check to see what mode we should operate in.
//
$mode = '';

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
	$mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
	$mode = htmlspecialchars($mode);
}

$delimeter  = '=+:';

//
// Read a listing of uploaded smilies for use in the add or edit smliey code...
// TODO we should do it only for situation when we need it, NO for all the time
//
$smilies = Finder::findFiles('*')->from($phpbb_root_path . $board_config['smilies_path']);

$smiley_images = [];
$smiley_paks = [];

/**
 * @var SplFileInfo $smile
 */
foreach ($smilies as $smile) {
    $img_size = @getimagesize($phpbb_root_path . $board_config['smilies_path'] . $sep . $smile->getFilename());

    if ($img_size[0] && $img_size[1]) {
        $smiley_images[] = $smile->getFilename();
    } elseif (preg_match('#.pak$#', $smile->getFilename())) {
        $smiley_paks[] = $smile->getFilename();
    }
}

//
// Select main mode
//
if (isset($_GET['import_pack']) || isset($_POST['import_pack'])) {
	//
	// Import a list a "Smiley Pack"
	//
	$smile_pak        = isset($_POST['smile_pak'])     ? $_POST['smile_pak']     : $_GET['smile_pak'];
	$clear_current    = isset($_POST['clear_current']) ? $_POST['clear_current'] : $_GET['clear_current'];
	$replace_existing = isset($_POST['replace'])       ? $_POST['replace']       : $_GET['replace'];

    if (!empty($smile_pak)) {
		//
		// The user has already selected a smile_pak file.. Import it.
		//
		if (!empty($clear_current)) {
		    // TODO really without where???!

            $cache = new Cache($storage, Tables::SMILEYS_TABLE);

            $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';
            $key_all = Tables::SMILEYS_TABLE . '_all';

            $cache->remove($key);
            $cache->remove($key_all);

		    dibi::delete(Tables::SMILEYS_TABLE)
                ->execute();
		} else {
            $smiles_dibi = dibi::select('code')
                ->from(Tables::SMILEYS_TABLE)
                ->fetchAll();

            $smiles = [];

            foreach ($smiles_dibi as $smilie) {
                $smiles[$smilie->code] = 1;
            }
		}

        $fcontents = @file($phpbb_root_path . $board_config['smilies_path'] . $sep . $smile_pak);

		if (empty($fcontents)) {
			message_die(GENERAL_ERROR, "Couldn't read smiley pak file", '', __LINE__, __FILE__);
		}

        $cache = new Cache($storage, Tables::SMILEYS_TABLE);

        $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';
        $key_all = Tables::SMILEYS_TABLE . '_all';

        $cache->remove($key);
        $cache->remove($key_all);

		foreach ($fcontents as $line) {
			$smile_data = explode($delimeter, trim(addslashes($line)));
            $smile_data_count = count($smile_data);

			for ($j = 2; $j < $smile_data_count; $j++) {
				//
				// Replace > and < with the proper html_entities for matching.
                // TODO use htmlspecialchars()
				//
				$smile_data[$j] = str_replace('<', '&lt;', $smile_data[$j]);
				$smile_data[$j] = str_replace('>', '&gt;', $smile_data[$j]);
				$k = $smile_data[$j];

				if ($smiles[$k] === 1) {
					if (!empty($replace_existing)) {
					    $update_data = [
                            'smile_url' => $smile_data[0],
                            'emoticon' => $smile_data[1],
                        ];

					    dibi::update(Tables::SMILEYS_TABLE, $update_data)
                            ->where('[code] = %s', $smile_data[$j])
                            ->execute();
					}
				} else {
				    $insert_data = [
				        'code' => $smile_data[$j],
                        'smile_url' => $smile_data[0],
                        'emoticon' => $smile_data[1]
                    ];

				    dibi::insert(Tables::SMILEYS_TABLE, $insert_data)->execute();
				}
			}
		}

		$message  = $lang['smiley_import_success'] . '<br /><br />';
		$message .= sprintf($lang['Click_return_smileadmin'], '<a href="' . Session::appendSid('admin_smilies.php') . '">', '</a>') . '<br /><br />';
		$message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} else {
		//
		// Display the script to get the smile_pak cfg file...
		//
		$smile_paks_select = "<select name='smile_pak'><option value=''>" . $lang['Select_pak'] . '</option>';

		foreach ($smiley_paks as $key => $value) {
			if (!empty($value)) {
				$smile_paks_select .= '<option>' . $value . '</option>';
			}
		}

		$smile_paks_select .= '</select>';

		$hidden_vars = "<input type='hidden' name='mode' value='import'>";

        $template->setFileNames(['body' => 'admin/smile_import_body.tpl']);

        $template->assignVars(
            [
                'L_SMILEY_TITLE'     => $lang['smiley_title'],
                'L_SMILEY_EXPLAIN'   => $lang['smiley_import_inst'],
                'L_SMILEY_IMPORT'    => $lang['smiley_import'],
                'L_SELECT_LBL'       => $lang['choose_smile_pak'],
                'L_IMPORT'           => $lang['import'],
                'L_CONFLICTS'        => $lang['smile_conflicts'],
                'L_DEL_EXISTING'     => $lang['del_existing_smileys'],
                'L_REPLACE_EXISTING' => $lang['replace_existing'],
                'L_KEEP_EXISTING'    => $lang['keep_existing'],

                'S_SMILEY_ACTION' => Session::appendSid('admin_smilies.php'),
                'S_SMILE_SELECT'  => $smile_paks_select,
                'S_HIDDEN_FIELDS' => $hidden_vars
            ]
        );

        $template->pparse('body');
	}
} elseif (isset($_POST['export_pack']) || isset($_GET['export_pack'])) {
	//
	// Export our smiley config as a smiley pak...
	//
	if ($_GET['export_pack'] === 'send') {
        $resultset = dibi::select('*')
            ->from(Tables::SMILEYS_TABLE)
            ->fetchAll();

		$smile_pak = '';

		foreach ($resultset as $value) {
            $smile_pak .= $value->smile_url . $delimeter;
            $smile_pak .= $value->emoticon . $delimeter;
            $smile_pak .= $value->code . "\n";
        }

		header('Content-Type: text/x-delimtext; name="smiles.pak"');
		header('Content-disposition: attachment; filename=smiles.pak');

		echo $smile_pak;

		exit;
	}

	$message  = sprintf($lang['export_smiles'], '<a href="' . Session::appendSid('admin_smilies.php?export_pack=send', true) . '">', '</a>') . '<br /><br />';
	$message .= sprintf($lang['Click_return_smileadmin'], '<a href="' . Session::appendSid('admin_smilies.php') . '">', '</a>') . '<br /><br />';
	$message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
} elseif (isset($_POST['add']) || isset($_GET['add'])) {
	//
	// Admin has selected to add a smiley.
	//
    $template->setFileNames(['body' => 'admin/smile_edit_body.tpl']);

    $filename_list = '';

	foreach ($smiley_images as $smiley_image) {
		$filename_list .= '<option value="' . $smiley_image . '">' . $smiley_image . '</option>';
	}

	$s_hidden_fields = '<input type="hidden" name="mode" value="savenew" />';

    $template->assignVars(
        [
            'L_SMILEY_TITLE'   => $lang['smiley_title'],
            'L_SMILEY_CONFIG'  => $lang['smiley_config'],
            'L_SMILEY_EXPLAIN' => $lang['smile_desc'],
            'L_SMILEY_CODE'    => $lang['smiley_code'],
            'L_SMILEY_URL'     => $lang['smiley_url'],
            'L_SMILEY_EMOTION' => $lang['smiley_emot'],
            'L_SUBMIT'         => $lang['Submit'],
            'L_RESET'          => $lang['Reset'],

            'SMILEY_IMG' => $phpbb_root_path . $board_config['smilies_path'] . $sep . $smiley_images[0],

            'S_SMILEY_ACTION'    => Session::appendSid('admin_smilies.php'),
            'S_HIDDEN_FIELDS'    => $s_hidden_fields,
            'S_FILENAME_OPTIONS' => $filename_list,
            'S_SMILEY_BASEDIR'   => $phpbb_root_path . $board_config['smilies_path']
        ]
    );

    $template->pparse('body');
} elseif ($mode !== '') {
	switch ($mode) {
		case 'delete':
			//
			// Admin has selected to delete a smiley.
			//

			$smiley_id = !empty($_POST['id']) ? $_POST['id'] : $_GET['id'];
			$smiley_id = (int)$smiley_id;

			$confirm = isset($_POST['confirm']);

			if ($confirm) {
                $cache = new Cache($storage, Tables::SMILEYS_TABLE);

                $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';
                $key_all = Tables::SMILEYS_TABLE . '_all';

                $cache->remove($key);
                $cache->remove($key_all);

			    dibi::delete(Tables::SMILEYS_TABLE)
                    ->where('[smilies_id] = %i', $smiley_id)
                    ->execute();

				$message  = $lang['smiley_del_success'] . '<br /><br />';
				$message .= sprintf($lang['Click_return_smileadmin'], '<a href="' . Session::appendSid('admin_smilies.php') . '">', '</a>') . '<br /><br />';
				$message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

				message_die(GENERAL_MESSAGE, $message);
			} else {
				// Present the confirmation screen to the user
                $template->setFileNames(['body' => 'admin/confirm_body.tpl']);

                $hidden_fields = '<input type="hidden" name="mode" value="delete" /><input type="hidden" name="id" value="' . $smiley_id . '" />';

                $template->assignVars(
                    [
                        'MESSAGE_TITLE' => $lang['Confirm'],
                        'MESSAGE_TEXT'  => $lang['Confirm_delete_smiley'],

                        'L_YES' => $lang['Yes'],
                        'L_NO'  => $lang['No'],

                        'S_CONFIRM_ACTION' => Session::appendSid('admin_smilies.php'),
                        'S_HIDDEN_FIELDS'  => $hidden_fields
                    ]
                );
                $template->pparse('body');
			}
			break;

		case 'edit':
			//
			// Admin has selected to edit a smiley.
			//

			$smiley_id = !empty($_POST['id']) ? $_POST['id'] : $_GET['id'];
			$smiley_id = (int)$smiley_id;

            $smile_data = dibi::select('*')
                ->from(Tables::SMILEYS_TABLE)
                ->where('[smilies_id] = %i', $smiley_id)
                ->fetch();

            if (!$smile_data) {
                message_die(GENERAL_ERROR, 'Could not obtain emoticon information');
            }

			$filename_list = '';

			foreach ($smiley_images as $smiley_mage) {
				if ($smiley_mage === $smile_data->smile_url) {
					$smiley_selected = 'selected="selected"';
					$smiley_edit_img = $smiley_mage;
				} else {
					$smiley_selected = '';
				}

				$filename_list .= '<option value="' . $smiley_mage . '"' . $smiley_selected . '>' . $smiley_mage . '</option>';
			}

            $template->setFileNames(['body' => 'admin/smile_edit_body.tpl']);

            $s_hidden_fields = '<input type="hidden" name="mode" value="save" /><input type="hidden" name="smile_id" value="' . $smile_data->smilies_id . '" />';

            $template->assignVars(
                [
                    'SMILEY_CODE'     => $smile_data->code,
                    'SMILEY_EMOTICON' => $smile_data->emoticon,

                    'L_SMILEY_TITLE'   => $lang['smiley_title'],
                    'L_SMILEY_CONFIG'  => $lang['smiley_config'],
                    'L_SMILEY_EXPLAIN' => $lang['smile_desc'],
                    'L_SMILEY_CODE'    => $lang['smiley_code'],
                    'L_SMILEY_URL'     => $lang['smiley_url'],
                    'L_SMILEY_EMOTION' => $lang['smiley_emot'],
                    'L_SUBMIT'         => $lang['Submit'],
                    'L_RESET'          => $lang['Reset'],

                    'SMILEY_IMG' => $phpbb_root_path . $board_config['smilies_path'] . $sep . $smiley_edit_img,

                    'S_SMILEY_ACTION'    => Session::appendSid('admin_smilies.php'),
                    'S_HIDDEN_FIELDS'    => $s_hidden_fields,
                    'S_FILENAME_OPTIONS' => $filename_list,
                    'S_SMILEY_BASEDIR'   => $phpbb_root_path . $board_config['smilies_path']
                ]
            );

            $template->pparse('body');
			break;

		case 'save':
			//
			// Admin has submitted changes while editing a smiley.
			//

			//
			// Get the submitted data, being careful to ensure that we only
			// accept the data we are looking for.
			//
			$smile_code = isset($_POST['smile_code']) ? trim($_POST['smile_code']) : '';
			$smile_url = isset($_POST['smile_url']) ? trim($_POST['smile_url']) : '';
			$smile_url = ltrim(basename($smile_url), "'");
			$smile_emotion = isset($_POST['smile_emotion']) ? htmlspecialchars(trim($_POST['smile_emotion'])) : '';
			$smile_id = isset($_POST['smile_id']) ? (int)$_POST['smile_id'] : 0;
			$smile_code = trim($smile_code);
			$smile_url = trim($smile_url);

			// If no code was entered complain ...
            if ($smile_code === '' || $smile_url === '') {
                message_die(GENERAL_MESSAGE, $lang['Fields_empty']);
            }

			//
			// Convert < and > to proper htmlentities for parsing.
			//
			$smile_code = str_replace('<', '&lt;', $smile_code);
			$smile_code = str_replace('>', '&gt;', $smile_code);

			//
			// Proceed with updating the smiley table.
			//

            $cache = new Cache($storage, Tables::SMILEYS_TABLE);

            $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';
            $key_all = Tables::SMILEYS_TABLE . '_all';

            $cache->remove($key);
            $cache->remove($key_all);

            $update_data = [
                'code' => $smile_code,
                'smile_url' => $smile_url,
                'emoticon' =>$smile_emotion
            ];

            dibi::update(Tables::SMILEYS_TABLE, $update_data)
                ->where('[smilies_id] = %i', $smile_id)
                ->execute();

			$message  = $lang['smiley_edit_success'] . '<br /><br />';
			$message .= sprintf($lang['Click_return_smileadmin'], '<a href="' . Session::appendSid('admin_smilies.php') . '">', '</a>') . '<br /><br />';
			$message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
			break;

		case 'savenew':
			//
			// Admin has submitted changes while adding a new smiley.
			//

			//
			// Get the submitted data being careful to ensure the the data
			// we recieve and process is only the data we are looking for.
			//
			$smile_code = isset($_POST['smile_code']) ? $_POST['smile_code'] : '';
			$smile_url = isset($_POST['smile_url']) ? $_POST['smile_url'] : '';
			$smile_url = ltrim(basename($smile_url), "'");
			$smile_emotion = isset($_POST['smile_emotion']) ? htmlspecialchars(trim($_POST['smile_emotion'])) : '';
			$smile_code = trim($smile_code);
			$smile_url = trim($smile_url);

			// If no code was entered complain ...
            if ($smile_code === '' || $smile_url === '') {
                message_die(GENERAL_MESSAGE, $lang['Fields_empty']);
            }

			//
			// Convert < and > to proper htmlentities for parsing.
			//
			$smile_code = str_replace('<', '&lt;', $smile_code);
			$smile_code = str_replace('>', '&gt;', $smile_code);

			//
			// Save the data to the smiley table.
			//

            $cache = new Cache($storage, Tables::SMILEYS_TABLE);

            $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';
            $key_all = Tables::SMILEYS_TABLE . '_all';

            $cache->remove($key);
            $cache->remove($key_all);

            $insert_data = [
                'code' => $smile_code,
                'smile_url' => $smile_url,
                'emoticon' => $smile_emotion
            ];

            dibi::insert(Tables::SMILEYS_TABLE, $insert_data)->execute();

			$message  = $lang['smiley_add_success'] . '<br /><br />';
			$message .= sprintf($lang['Click_return_smileadmin'], '<a href="' . Session::appendSid('admin_smilies.php') . '">', '</a>') . '<br /><br />';
			$message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
			break;
	}
} else {
	//
	// This is the main display of the page before the admin has selected
	// any options.
	//
    $smilies = dibi::select('*')
        ->from(Tables::SMILEYS_TABLE)
        ->fetchAll();

    $template->setFileNames(['body' => 'admin/smile_list_body.tpl']);

    $template->assignVars(
        [
            'L_ACTION'       => $lang['Action'],
            'L_SMILEY_TITLE' => $lang['smiley_title'],
            'L_SMILEY_TEXT'  => $lang['smile_desc'],
            'L_DELETE'       => $lang['Delete'],
            'L_EDIT'         => $lang['Edit'],
            'L_SMILEY_ADD'   => $lang['smile_add'],
            'L_CODE'         => $lang['Code'],
            'L_EMOT'         => $lang['Emotion'],
            'L_SMILE'        => $lang['Smile'],
            'L_IMPORT_PACK'  => $lang['import_smile_pack'],
            'L_EXPORT_PACK'  => $lang['export_smile_pack'],

            'S_HIDDEN_FIELDS' => $s_hidden_fields,
            'S_SMILEY_ACTION' => Session::appendSid('admin_smilies.php')
        ]
    );

    //
	// Loop throuh the rows of smilies setting block vars for the template.
	//
    foreach ($smilies as $i => $smiley) {
        //
        // Replace htmlentites for < and > with actual character.
        //
        $smiley->code = str_replace('&lt;', '<', $smiley->code);
        $smiley->code = str_replace('&gt;', '>', $smiley->code);

        $rowColor = ($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
        $rowClass = ($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('smiles',
            [
                'ROW_COLOR' => '#' . $rowColor,
                'ROW_CLASS' => $rowClass,

                'SMILEY_IMG' => $phpbb_root_path . $board_config['smilies_path'] . $sep . $smiley->smile_url,
                'CODE'       => $smiley->code,
                'EMOT'       => $smiley->emoticon,

                'U_SMILEY_EDIT'   => Session::appendSid('admin_smilies.php?mode=edit&amp;id=' . $smiley->smilies_id),
                'U_SMILEY_DELETE' => Session::appendSid('admin_smilies.php?mode=delete&amp;id=' . $smiley->smilies_id)
            ]
        );
    }

    //
	// Spit out the page.
	//
	$template->pparse('body');
}

//
// Page Footer
//
require_once '.' . $sep . 'page_footer_admin.php';

?>