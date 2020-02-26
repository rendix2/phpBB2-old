<?php

define('IN_PHPBB', 1);

//
// Load default header
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

$mode = '';

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
}

switch ($mode) {
    case 'delete':
        dibi::delete(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
            ->where('[key_id] = %s', $_GET[POST_SESSION_USER_URL])
            ->execute();

        $message = $lang['Delete_auto_login'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_auto_login.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
        break;

    case '':
    default:

    $databaseLanguages = dibi::select('*')
        ->from(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
        ->as('s')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u')
        ->on('u.user_id = s.user_id')
        ->fetchAll();

    $latte = new LatteFactory($storage, $userdata);

    $parameters = [
        'C_SESSION_ID' => POST_SESSION_USER_URL,
        'C_MODE' => POST_MODE,
        'C_USER_ID' => POST_USERS_URL,

        'D_SESSIONS' => $databaseLanguages,

        'L_USER_ID' => $lang['User_id'],
        'L_USER_NAME' => $lang['Username'],
        'L_USER_LAST_LOGIN' => $lang['Last_login'],
        'L_USER_IP' => $lang['IP_Address'],
        'L_INSTALL' => $lang['Install'],

        'L_YES' => $lang['Yes'],
        'L_NO' => $lang['No'],
        'L_DELETE' => $lang['Delete'],
        'L_INFO' => $lang['Info'],

        'S_SID' => $SID,
    ];

    $latte->render('admin/auto_login_default.latte', $parameters);
}
