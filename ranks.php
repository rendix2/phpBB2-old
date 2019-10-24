<?php

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Start session management
//

$userdata = init_userprefs(PAGE_RANKS);
//
// End session management
//

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Index'], $gen_simple_header);

$ranks = dibi::select('*')
    ->from(Tables::RANKS_TABLE)
    ->fetchAll();

if (!count($ranks)) {
    message_die(GENERAL_ERROR, $lang['No_ranks']);
}

$latte = new LatteFactory($storage, $userdata);

$params = [
    'D_RANKS' => $ranks,

    'L_RANK_NO_DESC' => $lang['rank_no_desc'],
    'L_INDEX' => sprintf($lang['Forum_Index'], $board_config['sitename']),

    'U_INDEX' => Session::appendSid('index.php'),
];

$latte->render('ranks.latte', $params);

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);