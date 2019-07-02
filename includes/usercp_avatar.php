<?php
/***************************************************************************
 *                             usercp_avatar.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_avatar.php 5962 2006-05-23 21:09:27Z grahamje $
 *
 *
 ***************************************************************************/

use Nette\Utils\Finder;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

/**
 * @param $type
 * @param $error
 * @param $error_msg
 *
 * @return bool|string
 */
function check_image_type(&$type, &$error, &$error_msg)
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
			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Avatar_filetype'] : $lang['Avatar_filetype'];
			break;
	}

	return false;
}

/**
 * @param $avatar_type
 * @param $avatar_file
 *
 * @return array
 */
function user_avatar_delete($avatar_type, $avatar_file)
{
	global $board_config, $userdata;

	$avatar_file = basename($avatar_file);

	$fileExists = @file_exists(@phpbb_realpath('./' . $board_config['avatar_path'] . '/' . $avatar_file));

    if ($avatar_type === USER_AVATAR_UPLOAD && $avatar_file !== '' && $fileExists) {
        @unlink('./' . $board_config['avatar_path'] . '/' . $avatar_file);
    }

	return ['user_avatar' => '', 'user_avatar_type' => USER_AVATAR_NONE];
}

/**
 * @param string $mode
 * @param $error
 * @param string $error_msg
 * @param string $avatarFileName
 * @param $avatarCategory
 * @return array
 */
function user_avatar_gallery($mode, &$error, &$error_msg, $avatarFileName, $avatarCategory)
{
	global $board_config;

	$avatarFileName = ltrim(basename($avatarFileName), "'");
	$avatarCategory = ltrim(basename($avatarCategory), "'");

    if (!preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $avatarFileName)) {
        return [];
    }

    if ($avatarFileName === '' || $avatarCategory === '') {
        return [];
    }

    $filePath   = $board_config['avatar_gallery_path'] . '/' . $avatarCategory . '/' . $avatarFileName;
    $fileExists = file_exists(@phpbb_realpath($filePath));

    if ($fileExists && $mode === 'editprofile') {
        return ['user_avatar' => $avatarCategory . '/' . $avatarFileName, 'user_avatar_type' => USER_AVATAR_GALLERY];
    } else {
        return [];
    }
}

function user_avatar_url($mode, &$error, &$error_msg, $avatar_filename)
{
	global $lang;

    if (!preg_match('#^(http)|(ftp):\/\/#i', $avatar_filename)) {
		$avatar_filename = 'http://' . $avatar_filename;
	}

	$avatar_filename = substr($avatar_filename, 0, 100);

    if (!preg_match("#^((ht|f)tp://)([^ \?&=\#\"\n\r\t<]*?(\.(jpg|jpeg|gif|png))$)#is", $avatar_filename)) {
		$error = true;
		$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Wrong_remote_avatar_format'] : $lang['Wrong_remote_avatar_format'];
		return [];
	}

	return $mode === 'editprofile' ? ['user_avatar' => $avatar_filename, 'user_avatar_type' => USER_AVATAR_REMOTE] : [];
}

function user_avatar_upload($mode, $avatar_mode, &$current_avatar, &$current_type, &$error, &$error_msg, $avatar_filename, $avatar_realname, $avatar_filesize, $avatar_filetype)
{
	global $board_config, $lang;

	$width = $height = 0;
	$type = '';

	if ($avatar_mode === 'remote' && preg_match('/^(http:\/\/)?([\w\-\.]+)\:?([0-9]*)\/([^ \?&=\#\"\n\r\t<]*?(\.(jpg|jpeg|gif|png)))$/', $avatar_filename, $url_ary)) {
        if (empty($url_ary[4])) {
			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Incomplete_URL'] : $lang['Incomplete_URL'];
			return [];
		}

		$base_get = '/' . $url_ary[4];
		$port = !empty($url_ary[3]) ? $url_ary[3] : 80;

        if (!($fsock = @fsockopen($url_ary[2], $port, $errno, $errstr))) {
			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['No_connection_URL'] : $lang['No_connection_URL'];
			return [];
		}

		@fwrite($fsock, "GET $base_get HTTP/1.1\r\n");
		@fwrite($fsock, 'HOST: ' . $url_ary[2] . "\r\n");
		@fwrite($fsock, "Connection: close\r\n\r\n");

		unset($avatar_data);

		while (!@feof($fsock)) {
			$avatar_data .= @fread($fsock, $board_config['avatar_filesize']);
		}

		@fclose($fsock);

		if (!preg_match('#Content-Length\: ([0-9]+)[^ /][\s]+#i', $avatar_data, $file_data1) || !preg_match('#Content-Type\: image/[x\-]*([a-z]+)[\s]+#i', $avatar_data, $file_data2)) {
			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['File_no_data'] : $lang['File_no_data'];
			return [];
		}

		$avatar_filesize = $file_data1[1];
		$avatar_filetype = $file_data2[1];

		if (!$error && $avatar_filesize > 0 && $avatar_filesize < $board_config['avatar_filesize']) {
			$avatar_data = substr($avatar_data, mb_strlen($avatar_data) - $avatar_filesize, $avatar_filesize);

			$tmp_path = './' . $board_config['avatar_path'] . '/tmp';
			$tmp_filename = tempnam($tmp_path, uniqid(rand()) . '-');

			$fptr = @fopen($tmp_filename, 'wb');
			$bytes_written = @fwrite($fptr, $avatar_data, $avatar_filesize);
			@fclose($fptr);

			if ($bytes_written !== $avatar_filesize) {
				@unlink($tmp_filename);
				message_die(GENERAL_ERROR, 'Could not write avatar file to local storage. Please contact the board administrator with this message', '', __LINE__, __FILE__);
			}

			list($width, $height, $type) = @getimagesize($tmp_filename);
		} else {
			$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
		}
	} elseif (file_exists(@phpbb_realpath($avatar_filename)) && preg_match('/\.(jpg|jpeg|gif|png)$/i', $avatar_realname)) {
        if ($avatar_filesize <= $board_config['avatar_filesize'] && $avatar_filesize > 0) {
			preg_match('#image\/[x\-]*([a-z]+)#', $avatar_filetype, $avatar_filetype);
			$avatar_filetype = $avatar_filetype[1];
		} else {
			$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

			$error = true;
			$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
			return [];
		}

		list($width, $height, $type) = @getimagesize($avatar_filename);
	}

    $imgType = check_image_type($avatar_filetype, $error, $error_msg);

    if (!$imgType) {
		return [];
	}

	switch ($type) {
		// GIF
		case 1:
			if ($imgType !== '.gif') {
				@unlink($tmp_filename);
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
				@unlink($tmp_filename);
				message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
			}
		break;

		// PNG
		case 3:
			if ($imgType !== '.png') {
				@unlink($tmp_filename);
				message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
			}
		break;

		default:
			@unlink($tmp_filename);
			message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
	}

	if ($width > 0 && $height > 0 && $width <= $board_config['avatar_max_width'] && $height <= $board_config['avatar_max_height']) {
		$new_filename = uniqid(rand()) . $imgType;

		if ($mode === 'editprofile' && $current_type === USER_AVATAR_UPLOAD && $current_avatar !== '') {
			user_avatar_delete($current_type, $current_avatar);
		}

		if ($avatar_mode === 'remote') {
			@copy($tmp_filename, './' . $board_config['avatar_path'] . "/$new_filename");
			@unlink($tmp_filename);
		} else {
            if (@ini_get('open_basedir') !== '') {
                if (PHP_VERSION < '4.0.3') {
					message_die(GENERAL_ERROR, 'open_basedir is set and your PHP version does not allow move_uploaded_file', '', __LINE__, __FILE__);
				}

				$move_file = 'move_uploaded_file';
			} else {
				$move_file = 'copy';
			}

            if (!is_uploaded_file($avatar_filename)) {
				message_die(GENERAL_ERROR, 'Unable to upload file', '', __LINE__, __FILE__);
			}
			$move_file($avatar_filename, './' . $board_config['avatar_path'] . "/$new_filename");
		}

		@chmod('./' . $board_config['avatar_path'] . "/$new_filename", 0777);

		return ['user_avatar' => $new_filename, 'user_avatar_type' => USER_AVATAR_UPLOAD];
	} else {
		$l_avatar_size = sprintf($lang['Avatar_imagesize'], $board_config['avatar_max_width'], $board_config['avatar_max_height']);

		$error = true;
		$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;

		return [];
	}
}

function display_avatar_gallery($mode, &$category, &$user_id, &$email, &$current_email, &$coppa, &$username, &$email, &$new_password, &$cur_password, &$password_confirm, &$website, &$location, &$occupation, &$interests, &$signature, &$viewemail, &$notifypm, &$popup_pm, &$notifyreply, &$attachsig, &$allowhtml, &$allowbbcode, &$allowsmilies, &$hideonline, &$style, &$language, &$timezone, &$dateformat, &$session_id, $isAdmin, Template $template, $user_status = null, $allow_avatar = null, $allow_pm = null, $user_rank = null)
{
	global $board_config, $lang;

	$avatar_images = [];

	if ($isAdmin) {
        $directories = Finder::findDirectories()->from('../' .$board_config['avatar_gallery_path']);
    } else {
        $directories = Finder::findDirectories()->from($board_config['avatar_gallery_path']);
    }

	$firstDir = '';

    /**
     * @var SplFileInfo $directory
     */
    foreach ($directories as $directory) {
        $files = Finder::findFiles('*.gif', '*.png', '*.jpg', '*.jpeg')->from($directory->getRealPath());

        $avatar_row_count = 0;
        $avatar_col_count = 0;

        /**
         * @var SplFileInfo $file
         */
        foreach ($files as $file) {
            if (!$firstDir) {
                $firstDir = $directory->getFilename();
            }

            $avatar_images[$directory->getFilename()][$avatar_row_count][$avatar_col_count] = $file->getFilename();
            $avatar_name[$directory->getFilename()][$avatar_row_count][$avatar_col_count]   = ucfirst(str_replace('_', ' ', preg_replace('/^(.*)\..*$/', '\1', $file->getFilename() . '.' . $file->getExtension())));

            $avatar_col_count++;

            if ($avatar_col_count === 5) {
                $avatar_row_count++;
                $avatar_col_count = 0;
            }
        }
    }

	@ksort($avatar_images);

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

	foreach ($avatar_images as $key => $value) {
		$selected = $key === $category ? ' selected="selected"' : '';

		if (count($avatar_images[$key])) {
            $options .= '<option value="' . $key . '"' . $selected . '>' . ucfirst($key) . '</option>';
		}
	}

    $s_categories = '<select name="avatarcategory" id="avatarcategory">' . $options . '</select>';

	$s_colspan = 0;

	foreach ($avatar_images[$category] as $i => $avatar_image) {
		$template->assignBlockVars('avatar_row', []);

		$s_colspan = max($s_colspan, count($avatar_image));

		foreach ($avatar_image as $j =>  $avatar_image_value) {
		    if ($isAdmin) {
                $path = '../' . $board_config['avatar_gallery_path'] . '/' . $category . '/' . $avatar_image_value;
            } else {
                $path = $board_config['avatar_gallery_path'] . '/' . $category . '/' . $avatar_image_value;
            }


            $template->assignBlockVars('avatar_row.avatar_column',
                [
                    'AVATAR_IMAGE' => $path,
                    'AVATAR_NAME'  => $avatar_name[$category][$i][$j]
                ]
            );

            $template->assignBlockVars('avatar_row.avatar_option_column',
                [
                    'S_OPTIONS_AVATAR' => $avatar_image_value
                ]
            );
        }
    }

    $params = [
        'coppa',
        'user_id',
        'username',
        'email',
        'current_email',
        'cur_password',
        'new_password',
        'password_confirm',
        'website',
        'location',
        'occupation',
        'interests',
        'signature',
        'viewemail',
        'notifypm',
        'popup_pm',
        'notifyreply',
        'attachsig',
        'allowhtml',
        'allowbbcode',
        'allowsmilies',
        'hideonline',
        'style',
        'language',
        'timezone',
        'dateformat',
        'user_status',
        'user_rank',
    ];

    $s_hidden_vars = '';

    if ($isAdmin) {
        $s_hidden_vars .= '<input type="hidden" name="mode" value="edit" />';
        $s_hidden_vars .= '<input type="hidden" name="coppa" value="' . $coppa . '" />';
        $s_hidden_vars .= '<input type="hidden" name="id" value="' . $user_id . '" />';
        $s_hidden_vars .= '<input type="hidden" name="user_allowavatar" value="' . $allow_avatar . '" />';
        $s_hidden_vars .= '<input type="hidden" name="user_allowpm" value="' . $allow_pm . '" />';
        $s_hidden_vars .= '<input type="hidden" name="popup_pm" value="' . $popup_pm . '" />';
        $s_hidden_vars .= '<input type="hidden" name="user_rank" value="' . $user_rank . '" />';
    } else {
        $s_hidden_vars .= '<input type="hidden" name="sid" value="' . $session_id . '" />';
    }

    $s_hidden_vars .= '<input type="hidden" name="agreed" value="true" />';
    $s_hidden_vars .= '<input type="hidden" name="avatarcatname" value="' . $category . '" />';
    $s_hidden_vars .= '<input type="hidden" name="user_active" value="' . $user_status . '" />';
    $s_hidden_vars .= CSRF::getInputHtml();

	foreach ($params as $param) {
		$s_hidden_vars .= '<input type="hidden" name="' . $param . '" value="' . str_replace('"', '&quot;', $$param) . '" />';
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
            'S_HIDDEN_FIELDS'   => $s_hidden_vars,

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

?>