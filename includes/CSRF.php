<?php

/**
 * Class CSRF
 *
 * @author rendix2
 */
class CSRF
{
    /**
     * @var string
     */
    const TOKEN_NAME = '_token__';

    /**
     * @return bool
     */
    public static function checkToken()
    {
        if (!isset($_POST[self::TOKEN_NAME])) {
            return false;
        }

        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        return $_POST[self::TOKEN_NAME] === $_SESSION[self::TOKEN_NAME];
    }

    /**
     * @return void
     */
    public static function validate()
    {
        global $lang;

        if (!self::checkToken()) {
            message_die(GENERAL_MESSAGE, $lang['Session_invalid']);
        }

        self::resetSession();
    }

    /**
     * @return void
     */
    public static function resetSession()
    {
        unset($_SESSION[self::TOKEN_NAME]);
    }

    /**
     * @return string
     */
    public static function createToken()
    {
        $hash = hash('sha512', uniqid('afsdfhfg', true));
        $start = mt_rand(0, 5);
        $finish = mt_rand($start, $start + 25);
        $token =  substr($hash, $start, $finish);

        $_SESSION[self::TOKEN_NAME] = $token;

        return $token;
    }

    /**
     * @return string
     */
    public static function getInputHtml()
    {
        return '<input type="hidden" name="'.self::TOKEN_NAME.'" value="'.self::createToken().'">';
    }
}
