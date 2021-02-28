<?php

use Nette\Caching\IStorage;

/**
 * Class AutoLoginPresenter
 */
class AutoLoginPresenter
{
    /**
     * @var array $lang
     */
    private $lang;

    /**
     * @var IStorage $storage
     */
    private $storage;

    /**
     * @var array $userData
     */
    private $userData;

    /**
     * AutoLoginPresenter constructor.
     *
     * @param IStorage $storage
     * @param $userData
     * @param array $lang
     */
    public function __construct(IStorage $storage, $userData, array $lang)
    {
        $this->lang = $lang;
        $this->storage = $storage;
        $this->userData = $userData;
    }

    public function renderDefault()
    {
        global $SID;

        $sessions = dibi::select('*')
            ->from(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
            ->as('s')
            ->innerJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('[u.user_id] = [s.user_id]')
            ->fetchAll();

        $latte = new LatteFactory($this->storage, $this->userData);

        $parameters = [
            'C_SESSION_ID' => POST_SESSION_USER_URL,
            'C_MODE' => POST_MODE,
            'C_USER_ID' => POST_USERS_URL,

            'sessions' => $sessions,

            'L_USER_ID' => $this->lang['User_id'],
            'L_USER_NAME' => $this->lang['Username'],
            'L_USER_LAST_LOGIN' => $this->lang['Last_login'],
            'L_USER_IP' => $this->lang['IP_Address'],
            'L_INSTALL' => $this->lang['Install'],

            'L_YES' => $this->lang['Yes'],
            'L_NO' => $this->lang['No'],
            'L_DELETE' => $this->lang['Delete'],
            'L_INFO' => $this->lang['Info'],

            'S_SID' => $SID,
        ];

        $latte->render('admin/Autologin/default.latte', $parameters);
    }

    public function actionDelete($id)
    {
        dibi::delete(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
            ->where('[key_id] = %s', $id)
            ->execute();

        $message  = $this->lang['Delete_auto_login'] . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_auto_login.php') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

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

$autoLoginPresenter = new AutoLoginPresenter($storage, $userdata, $lang);

switch ($mode) {
    case 'delete':
        $autoLoginPresenter->actionDelete($_GET[POST_SESSION_USER_URL]);
        break;

    case '':
    default:
        $autoLoginPresenter->renderDefault();
        break;

}
