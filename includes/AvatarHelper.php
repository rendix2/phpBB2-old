<?php

use Nette\Utils\Finder;

/**
 * Class AvatarHelper
 *
 * @author rendix2
 */
class AvatarHelper
{
    /**
     * @param string $type
     * @param bool   $error
     * @param string $errorMesssge
     *
     * @return bool|string
     */
    public static function checkImageType(&$type, &$error, &$errorMesssge)
    {
        global $lang;

        switch( $type) {
            case 'jpeg':
            case 'pjpeg':
            case 'jpg':
                return '.jpg';
                break;
            case 'gif':
                return '.gif';
                break;
            case 'png':
                return '.png';
                break;
            default:
                $error        = true;
                $errorMesssge = !empty($errorMesssge) ? $errorMesssge . '<br />' . $lang['Avatar_filetype'] : $lang['Avatar_filetype'];
                break;
        }

        return false;
    }

    /**
     * @param $avatarType
     * @param $avatarFile
     *
     * @return array
     */
    public static function userAvatarDelete($avatarType, $avatarFile)
    {
        global $board_config;

        $sep = DIRECTORY_SEPARATOR;

        $avatarFile = basename($avatarFile);

        $fileExists = @file_exists(@realpath('.' . $sep . $board_config['avatar_path'] . $sep . $avatarFile));

        if ($avatarType === USER_AVATAR_UPLOAD && $avatarFile !== '' && $fileExists) {
            @unlink('.' . $sep . $board_config['avatar_path'] . $sep . $avatarFile);
        }

        return ['user_avatar' => '', 'user_avatar_type' => USER_AVATAR_NONE];
    }

    /**
     * @param string $mode
     * @param bool   $error
     * @param string $errorMessage
     * @param string $avatarFileName
     * @param string $avatarCategory
     *
     * @return array
     */
    public static function userAvatarGallery($mode, &$error, &$errorMessage, $avatarFileName, $avatarCategory)
    {
        global $board_config;

        $avatarFileName = ltrim(basename($avatarFileName), "'");
        $avatarCategory = ltrim(basename($avatarCategory), "'");

        $sep = DIRECTORY_SEPARATOR;

        if (!preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $avatarFileName)) {
            return [];
        }

        if ($avatarFileName === '' || $avatarCategory === '') {
            return [];
        }

        $filePath   = $board_config['avatar_gallery_path'] . $sep . $avatarCategory . $sep . $avatarFileName;
        $fileExists = file_exists(@realpath($filePath));

        if ($fileExists && $mode === 'editprofile') {
            return ['user_avatar' => $avatarCategory . $sep . $avatarFileName, 'user_avatar_type' => USER_AVATAR_GALLERY];
        } else {
            return [];
        }
    }

    /**
     * @param string $mode
     * @param bool   $error
     * @param string $errorMessage
     * @param string $avatarFileName
     *
     * @return array
     */
    public static function userAvatarUrl($mode, &$error, &$errorMessage, $avatarFileName)
    {
        global $lang;

        if (!preg_match('#^(http)|(ftp):\/\/#i', $avatarFileName)) {
            $avatarFileName = 'http://' . $avatarFileName;
        }

        $avatarFileName = substr($avatarFileName, 0, 100);

        if (!preg_match("#^((ht|f)tp://)([^ \?&=\#\"\n\r\t<]*?(\.(jpg|jpeg|gif|png))$)#is", $avatarFileName)) {
            $error        = true;
            $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $lang['Wrong_remote_avatar_format'] : $lang['Wrong_remote_avatar_format'];
            return [];
        }

        return $mode === 'editprofile' ? ['user_avatar' => $avatarFileName, 'user_avatar_type' => USER_AVATAR_REMOTE] : [];
    }

    /**
     * @param string $mode
     * @param string $avatarMode
     * @param string $currentAvatar
     * @param int    $currentType
     * @param bool   $error
     * @param string $errorMessage
     * @param string $avatarFileName
     * @param string $avatarRealName
     * @param string $avatarFileSize
     * @param string $avatarFileType
     *
     * @return array
     */
    public static function userAvatarUpload(
        $mode,
        $avatarMode,
        &$currentAvatar,
        &$currentType,
        &$error,
        &$errorMessage,
        $avatarFileName,
        $avatarRealName,
        $avatarFileSize,
        $avatarFileType
    ) {
        global $board_config, $lang;

        $width = $height = 0;
        $type = '';

        $sep = DIRECTORY_SEPARATOR;

        if ($avatarMode === 'remote' && preg_match('/^(http:\/\/)?([\w\-\.]+)\:?([0-9]*)\/([^ \?&=\#\"\n\r\t<]*?(\.(jpg|jpeg|gif|png)))$/', $avatarFileName, $url_ary)) {
            if (empty($url_ary[4])) {
                $error        = true;
                $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $lang['Incomplete_URL'] : $lang['Incomplete_URL'];
                return [];
            }

            $base_get = '/' . $url_ary[4];
            $port = !empty($url_ary[3]) ? $url_ary[3] : 80;

            if (!($fsock = @fsockopen($url_ary[2], $port, $errno, $errstr))) {
                $error        = true;
                $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $lang['No_connection_URL'] : $lang['No_connection_URL'];
                return [];
            }

            @fwrite($fsock, "GET $base_get HTTP/1.1\r\n");
            @fwrite($fsock, 'HOST: ' . $url_ary[2] . "\r\n");
            @fwrite($fsock, "Connection: close\r\n\r\n");

            unset($avatarData);

            while (!@feof($fsock)) {
                $avatarData .= @fread($fsock, $board_config['avatar_filesize']);
            }

            @fclose($fsock);

            if (!preg_match('#Content-Length\: ([0-9]+)[^ /][\s]+#i', $avatarData, $fileData1) || !preg_match('#Content-Type\: image/[x\-]*([a-z]+)[\s]+#i', $avatarData, $fileData2)) {
                $error        = true;
                $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $lang['File_no_data'] : $lang['File_no_data'];
                return [];
            }

            $avatarFileSize = $fileData1[1];
            $avatarFileType = $fileData2[1];

            if (!$error && $avatarFileSize > 0 && $avatarFileSize < $board_config['avatar_filesize']) {
                $avatarData = substr($avatarData, mb_strlen($avatarData) - $avatarFileSize, $avatarFileSize);

                $tmpPath = '.' . $sep . $board_config['avatar_path'] . $sep . 'tmp';
                $tmpFileName = tempnam($tmpPath, uniqid(rand(), true) . '-');

                $fptr = @fopen($tmpFileName, 'wb');
                $bytesWritten = @fwrite($fptr, $avatarData, $avatarFileSize);
                @fclose($fptr);

                if ($bytesWritten !== $avatarFileSize) {
                    @unlink($tmpFileName);
                    message_die(GENERAL_ERROR, 'Could not write avatar file to local storage. Please contact the board administrator with this message', '', __LINE__, __FILE__);
                }

                list($width, $height, $type) = @getimagesize($tmpFileName);
            } else {
                $l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

                $error        = true;
                $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $l_avatar_size : $l_avatar_size;
            }
        } elseif (file_exists(@realpath($avatarFileName)) && preg_match('/\.(jpg|jpeg|gif|png)$/i', $avatarRealName)) {
            if ($avatarFileSize <= $board_config['avatar_filesize'] && $avatarFileSize > 0) {
                preg_match('#image\/[x\-]*([a-z]+)#', $avatarFileType, $avatarFileType);
                $avatarFileType = $avatarFileType[1];
            } else {
                $l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

                $error        = true;
                $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $l_avatar_size : $l_avatar_size;
                return [];
            }

            list($width, $height, $type) = @getimagesize($avatarFileName);
        }

        $imgType = self::checkImageType($avatarFileType, $error, $errorMessage);

        if (!$imgType) {
            return [];
        }

        switch ($type) {
            // GIF
            case 1:
                if ($imgType !== '.gif') {
                    @unlink($tmpFileName);
                    message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
                }
                break;

            // JPG, JPC, JP2, JPX, JB2
            case 2:
            case 9:
            case 10:
            case 11:
            case 12:
                if ($imgType !== '.jpg' && $imgType !== '.jpeg') {
                    @unlink($tmpFileName);
                    message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
                }
                break;

            // PNG
            case 3:
                if ($imgType !== '.png') {
                    @unlink($tmpFileName);
                    message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
                }
                break;

            default:
                @unlink($tmpFileName);
                message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
        }

        if ($width > 0 && $height > 0 && $width <= $board_config['avatar_max_width'] && $height <= $board_config['avatar_max_height']) {
            $newFileName = uniqid(rand(), true) . $imgType;

            if ($mode === 'editprofile' && $currentType === USER_AVATAR_UPLOAD && $currentAvatar !== '') {
                self::userAvatarDelete($currentType, $currentAvatar);
            }

            if ($avatarMode === 'remote') {
                @copy($tmpFileName, '.' . $sep . $board_config['avatar_path'] . $sep . $newFileName);
                @unlink($tmpFileName);
            } else {
                if (@ini_get('open_basedir') !== '') {
                    if (PHP_VERSION < '4.0.3') {
                        message_die(GENERAL_ERROR, 'open_basedir is set and your PHP version does not allow move_uploaded_file', '', __LINE__, __FILE__);
                    }

                    $move_file = 'move_uploaded_file';
                } else {
                    $move_file = 'copy';
                }

                if (!is_uploaded_file($avatarFileName)) {
                    message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
                }
                $move_file($avatarFileName, '.' . $sep . $board_config['avatar_path'] . $sep . $newFileName);
            }

            @chmod('.' . $sep . $board_config['avatar_path'] . $sep . $newFileName, 0777);

            return ['user_avatar' => $newFileName, 'user_avatar_type' => USER_AVATAR_UPLOAD];
        } else {
            $l_avatar_size = sprintf($lang['Avatar_imagesize'], $board_config['avatar_max_width'], $board_config['avatar_max_height']);

            $error        = true;
            $errorMessage = !empty($errorMessage) ? $errorMessage . '<br />' . $l_avatar_size : $l_avatar_size;

            return [];
        }
    }

    /**
     * @param string       $mode
     * @param string       $category
     * @param int          $userId
     * @param string       $email
     * @param string       $currentEmail
     * @param              $coppa
     * @param string       $userName
     * @param string       $newPassword
     * @param string       $currentPassword
     * @param              $passwordConfirm
     * @param string       $website
     * @param string       $location
     * @param string       $occupation
     * @param string       $interests
     * @param string       $signature
     * @param bool         $notifyPm
     * @param bool         $popupPm
     * @param bool         $notifyReply
     * @param bool         $attachSignature
     * @param bool         $allowHtml
     * @param bool         $allowbbcode
     * @param bool         $allowSmileys
     * @param bool         $hideOnline
     * @param string       $style
     * @param string       $language
     * @param string       $timeZone
     * @param string       $dateFormat
     * @param string       $sessionId
     * @param bool         $isAdmin
     * @param BaseTemplate $template
     * @param null         $userStatus
     * @param bool         $allowAvatar
     * @param bool         $allowPm
     * @param string       $userRank
     */
    public static function displayAvatarGallery(
        $mode,
        &$category,
        &$userId,
        &$email,
        &$currentEmail,
        &$coppa,
        &$userName,
        &$newPassword,
        &$currentPassword,
        &$passwordConfirm,
        &$website,
        &$location,
        &$occupation,
        &$interests,
        &$signature,
        &$notifyPm,
        &$popupPm,
        &$notifyReply,
        &$attachSignature,
        &$allowHtml,
        &$allowbbcode,
        &$allowSmileys,
        &$hideOnline,
        &$style,
        &$language,
        &$timeZone,
        &$dateFormat,
        &$sessionId,
        $isAdmin,
        BaseTemplate $template,
        $userStatus = null,
        $allowAvatar = null,
        $allowPm = null,
        $userRank = null
    ) {
        global $board_config, $lang;

        $avatarImages = [];
        $avatarName = [];

        $sep = DIRECTORY_SEPARATOR;

        if ($isAdmin) {
            $directories = Finder::findDirectories()->from('..' . $sep . $board_config['avatar_gallery_path']);
        } else {
            $directories = Finder::findDirectories()->from($board_config['avatar_gallery_path']);
        }

        $firstDir = '';

        /**
         * @var SplFileInfo $directory
         */
        foreach ($directories as $directory) {
            $files = Finder::findFiles('*.gif', '*.png', '*.jpg', '*.jpeg')->from($directory->getRealPath());

            $avatarRowCount = 0;
            $avatarColumnCount = 0;

            /**
             * @var SplFileInfo $file
             */
            foreach ($files as $file) {
                if (!$firstDir) {
                    $firstDir = $directory->getFilename();
                }

                $avatarImages[$directory->getFilename()][$avatarRowCount][$avatarColumnCount] = $file->getFilename();
                $avatarName[$directory->getFilename()][$avatarRowCount][$avatarColumnCount]   = ucfirst(str_replace('_', ' ', preg_replace('/^(.*)\..*$/', '\1', $file->getFilename() . '.' . $file->getExtension())));

                $avatarColumnCount++;

                if ($avatarColumnCount === 5) {
                    $avatarRowCount++;
                    $avatarColumnCount = 0;
                }
            }
        }

        @ksort($avatarImages);

        /*
        if (empty($category)) {
            $category = $firstDir;
        }
        */

        if (isset($_POST['avatarcategory'])) {
            $category = htmlspecialchars($_POST['avatarcategory']);
        } else {
            $category = $firstDir;
        }

        $options = '';

        foreach ($avatarImages as $key => $value) {
            $selected = $key === $category ? 'selected="selected"' : '';

            if (count($avatarImages[$key])) {
                $options .= '<option value="' . $key . '" ' . $selected . '>' . htmlspecialchars(ucfirst($key), ENT_QUOTES) . '</option>';
            }
        }

        $s_categories = '<select name="avatarcategory" id="avatarcategory">' . $options . '</select>';

        $s_colspan = 0;

        foreach ($avatarImages[$category] as $i => $avatarImage) {
            $template->assignBlockVars('avatar_row', []);

            $s_colspan = max($s_colspan, count($avatarImage));

            foreach ($avatarImage as $j => $avatarImageValue) {
                if ($isAdmin) {
                    $path = '..' . $sep . $board_config['avatar_gallery_path'] . $sep . $category . $sep . $avatarImageValue;
                } else {
                    $path = $board_config['avatar_gallery_path'] . $sep . $category . $sep . $avatarImageValue;
                }

                $template->assignBlockVars('avatar_row.avatar_column',
                    [
                        'AVATAR_IMAGE' => $path,
                        'AVATAR_NAME'  => $avatarName[$category][$i][$j]
                    ]
                );

                $template->assignBlockVars('avatar_row.avatar_option_column',
                    [
                        'S_OPTIONS_AVATAR' => $avatarImageValue
                    ]
                );
            }
        }

        $params = [
            'coppa',
            'userId',
            'userName',
            'email',
            'currentEmail',
            'currentPassword',
            'newPassword',
            'passwordConfirm',
            'website',
            'location',
            'occupation',
            'interests',
            'signature',
            'notifyPm',
            'popupPm',
            'notifyReply',
            'attachSignature',
            'allowHtml',
            'allowbbcode',
            'allowSmileys',
            'hideOnline',
            'style',
            'language',
            'timezone',
            'dateFormat',
            'userStatus',
            'userRank',
        ];

        $systemHiddenVars = '';

        if ($isAdmin) {
            $systemHiddenVars .= '<input type="hidden" name="mode" value="edit" />';
            $systemHiddenVars .= '<input type="hidden" name="coppa" value="' . $coppa . '" />';
            $systemHiddenVars .= '<input type="hidden" name="id" value="' . $userId . '" />';
            $systemHiddenVars .= '<input type="hidden" name="user_allowavatar" value="' . $allowAvatar . '" />';
            $systemHiddenVars .= '<input type="hidden" name="user_allowpm" value="' . $allowPm . '" />';
            $systemHiddenVars .= '<input type="hidden" name="popup_pm" value="' . $popupPm . '" />';
            $systemHiddenVars .= '<input type="hidden" name="user_rank" value="' . $userRank . '" />';
        } else {
            $systemHiddenVars .= '<input type="hidden" name="sid" value="' . $sessionId . '" />';
        }

        $systemHiddenVars .= '<input type="hidden" name="agreed" value="true" />';
        $systemHiddenVars .= '<input type="hidden" name="avatarcatname" value="' . $category . '" />';
        $systemHiddenVars .= '<input type="hidden" name="user_active" value="' . $userStatus . '" />';
        $systemHiddenVars .= CSRF::getInputHtml();

        foreach ($params as $param) {
            $systemHiddenVars .= '<input type="hidden" name="' . $param . '" value="' . str_replace('"', '&quot;', $$param) . '" />';
        }

        $template->assignVars(
            [
                'L_USER_TITLE'     => $lang['User_admin'],
                'L_USER_EXPLAIN'   => $lang['User_admin_explain'],
                'L_GO'             => $lang['Go'],

                'L_AVATAR_GALLERY' => $lang['Avatar_gallery'],
                'L_SELECT_AVATAR'  => $lang['Select_avatar'],
                'L_RETURN_PROFILE' => $lang['Return_profile'],
                'L_CATEGORY'       => $lang['Select_category'],

                'S_CATEGORY_SELECT' => $s_categories,
                'S_COLSPAN'         => $s_colspan,
                'S_HIDDEN_FIELDS'   => $systemHiddenVars,

                'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),
            ]
        );

        if ($isAdmin) {
            $template->assignVars(
                [
                    'S_PROFILE_ACTION' => Session::appendSid("admin_users.php?mode=$mode")
                ]
            );
        } else {
            $template->assignVars(
                [
                    'S_PROFILE_ACTION' => Session::appendSid("profile.php?mode=$mode"),
                ]
            );
        }
    }
}
