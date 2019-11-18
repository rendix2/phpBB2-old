<?php
/***************************************************************************
 *                              admin_ranks.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_ranks.php 8377 2008-02-10 12:52:05Z acydburn $
 *
 ***************************************************************************/

use Nette\Caching\Cache;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', 1);

//
// Let's set the root dir for phpBB
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

$cancel = isset($_POST['cancel']);
$no_page_header = $cancel;

require_once '.' . $sep . 'pagestart.php';

if ($cancel) {
	redirect('admin/' . Session::appendSid('admin_ranks.php', true));
}

if (isset($_GET[POST_MODE]) || isset($_POST[POST_MODE])) {
	$mode = isset($_GET[POST_MODE]) ? $_GET[POST_MODE] : $_POST[POST_MODE];
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

if ($mode === 'edit') {// OK, lets edit this ranks
	$rankId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

	if (empty($rankId)) {
		message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
	}

	$rankInfo = dibi::select('*')
		->from(Tables::RANKS_TABLE)
		->where('rank_id = %i', $rankId)
		->fetch();

	if (!$rankInfo) {
		message_die(GENERAL_ERROR, "Couldn't obtain rank data");
	}

	$s_hidden_fields = '<input type="hidden" name="mode" value="save" /><input type="hidden" name="id" value="' . $rankId . '" />';

	$rankIsSpecial = $rankInfo->rank_special ? 'checked="checked"' : '';
	$rankIsNotSpecial = !$rankInfo->rank_special ? 'checked="checked"' : '';

	$template->setFileNames(['body' => 'admin/ranks_edit_body.tpl']);

	$template->assignVars(
		[
			'RANK' => $rankInfo->rank_title,
			'RANK_DESC' => $rankInfo->rank_desc,
			'SPECIAL_RANK' => $rankIsSpecial,
			'NOT_SPECIAL_RANK' => $rankIsNotSpecial,
			'MINIMUM' => $rankIsSpecial ? '' : $rankInfo->rank_min,
			'IMAGE' => $rankInfo->rank_image,
			'IMAGE_DISPLAY' => $rankInfo->rank_image !== '' ? '<img src="../' . $rankInfo->rank_image . '" />' : '',

			'L_RANKS_TITLE' => $lang['Ranks_title'],
			'L_RANKS_TEXT' => $lang['Ranks_explain'],

			'L_RANK_DESC' => $lang['Ranks_desc'],
			'L_RANK_TITLE' => $lang['Rank_title'],
			'L_RANK_SPECIAL' => $lang['Rank_special'],
			'L_RANK_MINIMUM' => $lang['Rank_minimum'],
			'L_RANK_IMAGE' => $lang['Rank_image'],
			'L_RANK_IMAGE_EXPLAIN' => $lang['Rank_image_explain'],
			'L_SUBMIT' => $lang['Submit'],
			'L_RESET' => $lang['Reset'],
			'L_YES' => $lang['Yes'],
			'L_NO' => $lang['No'],

			'S_RANK_ACTION' => Session::appendSid('admin_ranks.php'),
			'S_HIDDEN_FIELDS' => $s_hidden_fields
		]
	);
} elseif ($mode === 'add') {// OK, lets add this rank
	$template->setFileNames(['body' => 'admin/ranks_edit_body.tpl']);

	$template->assignVars(
		[
			'RANK' => '',
			'RANK_DESC' => '',
			'SPECIAL_RANK' => 0,
			'NOT_SPECIAL_RANK' => 1,
			'MINIMUM' => '',
			'IMAGE' => '',
			'IMAGE_DISPLAY' => '',

			'L_RANKS_TITLE' => $lang['Ranks_title'],
			'L_RANKS_TEXT' => $lang['Ranks_explain'],

			'L_RANK_DESC' => $lang['Ranks_desc'],
			'L_RANK_TITLE' => $lang['Rank_title'],
			'L_RANK_SPECIAL' => $lang['Rank_special'],
			'L_RANK_MINIMUM' => $lang['Rank_minimum'],
			'L_RANK_IMAGE' => $lang['Rank_image'],
			'L_RANK_IMAGE_EXPLAIN' => $lang['Rank_image_explain'],
			'L_SUBMIT' => $lang['Submit'],
			'L_RESET' => $lang['Reset'],
			'L_YES' => $lang['Yes'],
			'L_NO' => $lang['No'],

			'S_RANK_ACTION' => Session::appendSid('admin_ranks.php'),
			'S_HIDDEN_FIELDS' => '<input type="hidden" name="mode" value="save" /> <input type="hidden" name="id" value="null" />'
		]
	);
} elseif ($mode === 'save') {// Ok, they sent us our info, let's update it.
	$rankId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	$rankTitle = isset($_POST['title']) ? trim($_POST['title']) : '';
	$rankDesc = isset($_POST['rank_desc']) ? trim($_POST['rank_desc']) : '';
	$minPosts = isset($_POST['min_posts']) ? (int)$_POST['min_posts'] : -1;
	$rankImage = isset($_POST['rank_image']) ? trim($_POST['rank_image']) : '';

	$specialRank = $_POST['special_rank'] === '1';

	if ($rankTitle === '') {
		message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
	}

	if ($specialRank === 1) {
		$maxPosts = -1;
		$minPosts = -1;
	}

	//
	// The rank image has to be a jpg, gif or png
	//
	if (($rankImage !== '') && !preg_match("/(\.gif|\.png|\.jpg)$/is", $rankImage)) {
		$rankImage = '';
	}

	$cache = new Cache($storage, Tables::RANKS_TABLE);
	$key = Tables::RANKS_TABLE . '_ordered_by_rank_special_rank_min';

	$cache->remove($key);

	if ($rankId) {
		if (!$specialRank) {
			dibi::update(Tables::USERS_TABLE, ['user_rank' => 0])
				->where('user_rank = %i', $rankId)
				->execute();
		}

		$updateData = [
			'rank_title' => $rankTitle,
			'rank_desc' => $rankDesc,
			'rank_special' => $specialRank,
			'rank_min' => $minPosts,
			'rank_image' => $rankImage
		];

		dibi::update(Tables::RANKS_TABLE, $updateData)
			->where('rank_id = %i', $rankId)
			->execute();

		$message = $lang['Rank_updated'];
	} else {
		$insertData = [
			'rank_title' => $rankTitle,
			'rank_desc' => $rankDesc,
			'rank_special' => $specialRank,
			'rank_min' => $minPosts,
			'rank_image' => $rankImage
		];

		dibi::insert(Tables::RANKS_TABLE, $insertData)->execute();

		$message = $lang['Rank_added'];
	}

	$message .= '<br /><br />' . sprintf($lang['Click_return_rankadmin'], '<a href="' . Session::appendSid('admin_ranks.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);

} elseif ($mode === 'delete') {// Ok, they want to delete their rank
	if (isset($_POST['id']) || isset($_GET['id'])) {
		$rankId = isset($_POST['id']) ? (int)$_POST['id'] : (int)$_GET['id'];
	} else {
		$rankId = 0;
	}

	$confirm = isset($_POST['confirm']);

	if ($rankId && $confirm) {
		$cache = new Cache($storage, Tables::RANKS_TABLE);
		$key = Tables::RANKS_TABLE . '_ordered_by_rank_special_rank_min';

		$cache->remove($key);

		dibi::delete(Tables::RANKS_TABLE)
			->where('rank_id = %i', $rankId)
			->execute();

		$result = dibi::update(Tables::USERS_TABLE, ['user_rank' => 0])
			->where('user_rank = %i', $rankId)
			->execute();

		$message = $lang['Rank_removed'] . '<br /><br />' . sprintf($lang['Click_return_rankadmin'], '<a href="' . Session::appendSid('admin_ranks.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);

	} elseif ($rankId && !$confirm) {
		// Present the confirmation screen to the user
		$template->setFileNames(['body' => 'admin/confirm_body.tpl']);

		$hidden_fields = '<input type="hidden" name="mode" value="delete" /><input type="hidden" name="id" value="' . $rankId . '" />';

		$template->assignVars(
			[
				'MESSAGE_TITLE' => $lang['Confirm'],
				'MESSAGE_TEXT' => $lang['Confirm_delete_rank'],

				'L_YES' => $lang['Yes'],
				'L_NO' => $lang['No'],

				'S_CONFIRM_ACTION' => Session::appendSid('admin_ranks.php'),
				'S_HIDDEN_FIELDS' => $hidden_fields
			]
		);
	} else {
		message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
	}
} elseif ($mode === '') {// Show the default page
	$template->setFileNames(['body' => 'admin/ranks_list_body.tpl']);

	$ranks = dibi::select('*')
		->from(Tables::RANKS_TABLE)
		->orderBy('rank_min', dibi::ASC)
		->orderBy('rank_special', dibi::ASC)
		->fetchAll();

	$rank_count = count($ranks);

	$template->assignVars(
		[
			'L_RANKS_TITLE' => $lang['Ranks_title'],
			'L_RANKS_TEXT' => $lang['Ranks_explain'],
			'L_RANK' => $lang['Rank_title'],
			'L_RANK_MINIMUM' => $lang['Rank_minimum'],
			'L_SPECIAL_RANK' => $lang['Rank_special'],
			'L_EDIT' => $lang['Edit'],
			'L_DELETE' => $lang['Delete'],
			'L_ADD_RANK' => $lang['Add_new_rank'],
			'L_ACTION' => $lang['Action'],

			'S_RANKS_ACTION' => Session::appendSid('admin_ranks.php')
		]
	);

	foreach ($ranks as $i => $rank) {
		$specialRank = $rank->rank_special;
		$rankId = $rank->rank_id;
		$rankMin = $rank->rank_min;

		if ($specialRank === 1) {
			$rankMin = $rankMax = '-';
		}

		$rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
		$rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

		$rankIsSpecial = $specialRank ? $lang['Yes'] : $lang['No'];

		$template->assignBlockVars('ranks',
			[
				'ROW_COLOR' => '#' . $rowColor,
				'ROW_CLASS' => $rowClass,
				'RANK' => htmlspecialchars($rank->rank_title, ENT_QUOTES),
				'SPECIAL_RANK' => $rankIsSpecial,
				'RANK_MIN' => $rankMin,

				'U_RANK_EDIT' => Session::appendSid("admin_ranks.php?mode=edit&amp;id=$rankId"),
				'U_RANK_DELETE' => Session::appendSid("admin_ranks.php?mode=delete&amp;id=$rankId")
			]
		);
	}
}

$template->pparse('body');

require_once '.' . $sep . 'page_footer_admin.php';
?>
