<?php
/***************************************************************************
 *                              admin_users.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_users.php 6981 2007-02-10 12:14:24Z acydburn $
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

define('IN_PHPBB', 1);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';

$html_entities_match   = ['#<#', '#>#'];
$html_entities_replace = ['&lt;', '&gt;'];

//
// Set mode
//
if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
    $mode = htmlspecialchars($mode);
} else {
    $mode = '';
}

//
// Begin program
//
if ($mode === 'edit' || $mode === 'save' && (isset($_POST['username']) || isset($_GET[POST_USERS_URL]) || isset($_POST[POST_USERS_URL]))) {
	//
	// Ok, the profile has been modified and submitted, let's update
	//
    if (($mode === 'save' && isset($_POST['submit'])) || isset($_POST['avatargallery']) || isset($_POST['submitavatar']) || isset($_POST['cancelavatar'])) {
        $user_id = (int)$_POST['id'];

        $this_userdata = get_userdata($user_id);

        if (!$this_userdata) {
            message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
        }

		if ($_POST['deleteuser'] && ($userdata['user_id'] !== $user_id )) {
            $row =  dibi::select('g.group_id')
                ->from(USER_GROUP_TABLE)
                ->as('ug')
                ->innerJoin(GROUPS_TABLE)
                ->as('g')
                ->on('g.group_id = ug.group_id')
                ->where('ug.user_id = %i', $user_id)
                ->where('g.group_single_user = %i', 1)
                ->fetch();

            $update_data = [
                'poster_id'     => DELETED,
                'post_username' => $this_userdata->username
            ];

            dibi::update(POSTS_TABLE, $update_data)
                ->where('poster_id = %i', $user_id)
                ->execute();

            dibi::update(TOPICS_TABLE, ['topic_poster' => DELETED])
                ->where('topic_poster = %i', $user_id)
                ->execute();

            dibi::update(VOTE_USERS_TABLE, ['vote_user_id' => DELETED])
                ->where('vote_user_id = %i', $user_id)
                ->execute();

            dibi::update(GROUPS_TABLE, ['group_moderator' => DELETED])
                ->where('group_moderator = %i', $user_id)
                ->execute();

			dibi::delete(USERS_TABLE)
                ->where('user_id = %i', $user_id)
                ->execute();

            dibi::delete(USER_GROUP_TABLE)
                ->where('user_id = %i', $user_id)
                ->execute();

            dibi::delete(GROUPS_TABLE)
                ->where('group_id = %i', $row->group_id)
                ->execute();

            dibi::delete(AUTH_ACCESS_TABLE)
                ->where('group_id = %i', $row->group_id)
                ->execute();

            dibi::delete(TOPICS_WATCH_TABLE)
                ->where('user_id = %i', $user_id)
                ->execute();

            dibi::delete(BANLIST_TABLE)
                ->where('ban_userid = %i', $user_id)
                ->execute();

            dibi::delete(SESSIONS_TABLE)
                ->where('session_user_id = %i', $user_id)
                ->execute();

            dibi::delete(SESSIONS_KEYS_TABLE)
                ->where('user_id = %i', $user_id)
                ->execute();

            $privmsgs_ids = dibi::select('privmsgs_id')
                ->from(PRIVMSGS_TABLE)
                ->where('privmsgs_from_userid = %i OR privmsgs_to_userid = %i', $user_id, $user_id)
                ->fetchPairs(null, 'privmsgs_id');

            if (count($privmsgs_ids)) {
                dibi::delete(PRIVMSGS_TABLE)
                    ->where('privmsgs_id IN %in', $privmsgs_ids)
                    ->execute();

                dibi::delete(PRIVMSGS_TEXT_TABLE)
                    ->where('privmsgs_text_id IN %in', $privmsgs_ids)
                    ->execute();
            }

			$message = $lang['User_deleted'] . '<br /><br />' . sprintf($lang['Click_return_useradmin'], '<a href="' . Session::appendSid('admin_users.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
		}

		$username = !empty($_POST['username']) ? phpbb_clean_username($_POST['username']) : '';
		$email = !empty($_POST['email']) ? trim(strip_tags(htmlspecialchars($_POST['email'] ) )) : '';

		$password = !empty($_POST['password']) ? trim(strip_tags(htmlspecialchars($_POST['password'] ) )) : '';
		$password_confirm = !empty($_POST['password_confirm']) ? trim(strip_tags(htmlspecialchars($_POST['password_confirm'] ) )) : '';

        $acp_password = ( !empty($_POST['acp_password']) ) ? trim(strip_tags(htmlspecialchars( $_POST['acp_password'] ) )) : '';
        $acp_password_confirm = ( !empty($_POST['acp_password_confirm']) ) ? trim(strip_tags(htmlspecialchars( $_POST['acp_password_confirm'] ) )) : '';

        $website    = !empty($_POST['website'])    ? trim($_POST['website'])    : '';
        $location   = !empty($_POST['location'])   ? trim($_POST['location'])   : '';
        $occupation = !empty($_POST['occupation']) ? trim($_POST['occupation']) : '';
        $interests  = !empty($_POST['interests'])  ? trim($_POST['interests'])  : '';

        $signature = !empty($_POST['signature']) ? trim(str_replace('<br />', "\n", $_POST['signature'])) : '';

		Validator::optionalFields( $website, $location, $occupation, $interests, $signature);

		$viewemail       = isset($_POST['viewemail'])   ? (bool)$_POST['viewemail']   : 0;
        $allowviewonline = isset($_POST['hideonline'])  ? (bool)$_POST['hideonline']  : true;
        $notifyreply     = isset($_POST['notifyreply']) ? (bool)$_POST['notifyreply'] : 0;
        $notifypm        = isset($_POST['notifypm'])    ? (bool)$_POST['notifypm']    : true;
        $popuppm         = isset($_POST['popup_pm'])    ? (bool)$_POST['popup_pm']    : true;
        $attachsig       = isset($_POST['attachsig'])   ? (bool)$_POST['attachsig']   : 0;

        $allowhtml    = isset($_POST['allowhtml'])    ? (int)$_POST['allowhtml']    : $board_config['allow_html'];
        $allowbbcode  = isset($_POST['allowbbcode'])  ? (int)$_POST['allowbbcode']  : $board_config['allow_bbcode'];
        $allowsmilies = isset($_POST['allowsmilies']) ? (int)$_POST['allowsmilies'] : $board_config['allow_smilies'];

        $user_style      = isset($_POST['style'])    ? (int)$_POST['style']       : $board_config['default_style'];
        $user_lang       = $_POST['language']        ? $_POST['language']         : $board_config['default_lang'];
        $user_timezone   = isset($_POST['timezone']) ? $_POST['timezone']         : $board_config['board_timezone'];
        $user_dateformat = $_POST['dateformat']      ? trim($_POST['dateformat']) : $board_config['default_dateformat'];

        if (isset($_POST['avatarselect']) && !empty($_POST['submitavatar']) && $board_config['allow_avatar_local']) {
            $user_avatar_local = $_POST['avatarselect'];
        } else {
            $user_avatar_local = isset($_POST['avatarlocal']) ? $_POST['avatarlocal'] : '';
        }

		$user_avatar_category = ( isset($_POST['avatarcatname']) && $board_config['allow_avatar_local'] ) ? htmlspecialchars($_POST['avatarcatname']) : '' ;

        $user_avatar_remoteurl = !empty($_POST['avatarremoteurl'])          ? trim($_POST['avatarremoteurl']) : '';
        $user_avatar_url       = !empty($_POST['avatarurl'])                ? trim($_POST['avatarurl'])       : '';
        $user_avatar_loc       = ($_FILES['avatar']['tmp_name'] !== 'none') ? $_FILES['avatar']['tmp_name']   : '';
        $user_avatar_name      = !empty($_FILES['avatar']['name'])          ? $_FILES['avatar']['name']       : '';
        $user_avatar_size      = !empty($_FILES['avatar']['size'])          ? $_FILES['avatar']['size']       : 0;
        $user_avatar_filetype  = !empty($_FILES['avatar']['type'])          ? $_FILES['avatar']['type']       : '';

        $user_avatar      = empty($user_avatar_loc) ? $this_userdata->user_avatar      : '';
        $user_avatar_type = empty($user_avatar_loc) ? $this_userdata->user_avatar_type : '';

        $user_status      = !empty($_POST['user_status'])      ? (int)$_POST['user_status']      : 0;
        $user_allowpm     = !empty($_POST['user_allowpm'])     ? (int)$_POST['user_allowpm']     : 0;
        $user_rank        = !empty($_POST['user_rank'])        ? (int)$_POST['user_rank']        : 0;
        $user_allowavatar = !empty($_POST['user_allowavatar']) ? (int)$_POST['user_allowavatar'] : 0;

        if (isset($_POST['avatargallery']) || isset($_POST['submitavatar']) || isset($_POST['cancelavatar'])) {
			$username = stripslashes($username);
			$email = stripslashes($email);
			$password = '';
			$password_confirm = '';
            $acp_password = '';
            $acp_password_confirm = '';

            $website    = stripslashes($website);
            $location   = stripslashes($location);
            $occupation = stripslashes($occupation);
            $interests  = stripslashes($interests);
            $signature  = stripslashes($signature);

			$user_lang = stripslashes($user_lang);
			$user_dateformat = stripslashes($user_dateformat);

            if (!isset($_POST['cancelavatar'])) {
				$user_avatar = $user_avatar_category . $sep . $user_avatar_local;
				$user_avatar_type = USER_AVATAR_GALLERY;
			}
		}
	}

    if (isset($_POST['submit'])) {
        $error = false;

        $username_sql = [];

        if (stripslashes($username) !== $this_userdata['username']) {
            unset($rename_user);

            if (stripslashes(strtolower($username)) !== strtolower($this_userdata['username'])) {
                //TODO maybe user $this_userdata instead userdata!!
                $result = Validator::userName($username, $lang, $userdata);

                if ($result['error']) {
					$error = true;
					$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $result['error_msg'];
                } elseif (strtolower(str_replace("\\'", "''", $username)) === strtolower($userdata['username'])) {
					$error = true;
					$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Username_taken'];
				}
			}

			if (!$error) {
                $username_sql['username'] = $username;
				$rename_user = $username; // Used for renaming usergroup
			}
		}

		$passwd_sql = [];

        if ($board_config['complex_acp_pw'] && !empty($acp_password)) {
            if (!empty($password) && $password == $acp_password) {
                $error = true;
                $error_msg .= ((isset($error_msg)) ? '<br />' : '') . $lang['ACP_Password_match_pw'];
            } else {
                $row = dibi::select('user_password')
                    ->from(USERS_TABLE)
                    ->where('[user_id] = %i', $user_id)
                    ->fetch();

                if (!$row) {
                    message_die(GENERAL_ERROR, 'Could not read current password', '', __LINE__, __FILE__, $sql);
                }

                if (password_verify($acp_password, $row->user_password)) {
                    $error = true;
                    $error_msg .= ((isset($error_msg)) ? '<br />' : '') . $lang['ACP_Password_match_pw'];
                }
            }
        }

        //
        // Awww, the user wants to change their ACP password, isn't that cute..
        //
        if (!empty($acp_password) && !empty($acp_password_confirm)) {
            $passwordLength = mb_strlen($acp_password);

            if ($acp_password !== $acp_password_confirm) {
                $error = true;
                $error_msg .= ((isset($error_msg)) ? '<br />' : '') . $lang['ACP_Password_mismatch'];
            } elseif($passwordLength < USER_MIN_PASSWORD_LENGTH) {
                $error = true;
                $error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Password_short'];
            } elseif($passwordLength > USER_MAX_PASSWORD_LENGTH) {
                $error = true;
                $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Password_long'];
            } else {
                $passwd_sql['user_acp_password'] = password_hash($acp_password, PASSWORD_BCRYPT);
            }
        } else if (($acp_password && !$acp_password_confirm) || (!$acp_password && $acp_password_confirm)) {
            $error = true;
            $error_msg .= ((isset($error_msg)) ? '<br />' : '') . $lang['ACP_Password_mismatch'];
        }

        //
        // Awww, the user wants to change their normal password, isn't that cute..
        //
		if (!empty($password) && !empty($password_confirm)) {
            $passwordLength = mb_strlen($password);

            if ($password !== $password_confirm) {
                $error = true;
				$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Password_mismatch'];
			} elseif($passwordLength < USER_MIN_PASSWORD_LENGTH) {
                $error = true;
                $error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Password_short'];
            } elseif($passwordLength < USER_MAX_PASSWORD_LENGTH) {
                $error = true;
                $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Password_long'];
            } else {
                $passwd_sql['user_password'] = password_hash($password, PASSWORD_BCRYPT);
			}
        } elseif (($password && !$password_confirm) || (!$password && $password_confirm)) {
            $error = true;
			$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Password_mismatch'];
        }

		if ($signature !== '') {
			$sig_length_check = preg_replace('/(\[.*?)(=.*?)\]/is', '\\1]', stripslashes($signature));

            if ($allowhtml) {
				$sig_length_check = preg_replace('/(\<.*?)(=.*?)( .*?=.*?)?([ \/]?\>)/is', '\\1\\3\\4', $sig_length_check);
			}

			// Only create a new bbcode_uid when there was no uid yet.
            if ($allowbbcode) {
				$signature_bbcode_uid = make_bbcode_uid();
			}

			$signature = PostHelper::prepareMessage($signature, $allowhtml, $allowbbcode, $allowsmilies, $signature_bbcode_uid);

            if (mb_strlen($sig_length_check) > $board_config['max_sig_chars']) {
                $error = true;
				$error_msg .=  ( isset($error_msg) ? '<br />' : '' ) . $lang['Signature_too_long'];
			}
		}

		//
		// Avatar stuff
		//
		$avatar_sql = [];

		if (isset($_POST['avatardel'])) {
			if ($this_userdata['user_avatar_type'] === USER_AVATAR_UPLOAD && $this_userdata['user_avatar'] !== '') {
                $path = $phpbb_root_path . $board_config['avatar_path'] . $sep . $this_userdata['user_avatar'];

				if (@file_exists(@phpbb_realpath($path))) {
                    @unlink($path);
				}
			}

			$avatar_sql = ['user_avatar' => '', 'user_avatar_type' => USER_AVATAR_NONE];
		} elseif (( $user_avatar_loc !== '' || !empty($user_avatar_url) ) && !$error) {
			//
			// Only allow one type of upload, either a
			// filename or a URL
			//
			if (!empty($user_avatar_loc) && !empty($user_avatar_url)) {
                $error = true;

				if (isset($error_msg)) {
					$error_msg .= '<br />';
				}

				$error_msg .= $lang['Only_one_avatar'];
			}

			if ($user_avatar_loc !== null) {
				if (file_exists(@phpbb_realpath($user_avatar_loc)) && preg_match('#.jpg$|.gif$|.png$#', $user_avatar_name)) {
					if ($user_avatar_size <= $board_config['avatar_filesize'] && $user_avatar_size > 0) {
						$error_type = false;

						//
						// Opera appends the image name after the type, not big, not clever!
						//
						preg_match("'image\/[x\-]*([a-z]+)'", $user_avatar_filetype, $user_avatar_filetype);
						$user_avatar_filetype = $user_avatar_filetype[1];

						switch( $user_avatar_filetype) {
							case 'jpeg':
							case 'pjpeg':
							case 'jpg':
								$imgtype = '.jpg';
								break;
							case 'gif':
								$imgtype = '.gif';
								break;
							case 'png':
								$imgtype = '.png';
								break;
							default:
								$error = true;
								$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Avatar_filetype'] : $lang['Avatar_filetype'];
								break;
						}

                        if (!$error) {
							list($width, $height) = @getimagesize($user_avatar_loc);

							if ($width <= $board_config['avatar_max_width'] && $height <= $board_config['avatar_max_height']) {
								$user_id = $this_userdata['user_id'];

								$avatar_filename = $user_id . $imgtype;

								if ($this_userdata['user_avatar_type'] === USER_AVATAR_UPLOAD && $this_userdata['user_avatar'] !== '') {
                                    $path = $phpbb_root_path . $board_config['avatar_path'] . $sep . $this_userdata['user_avatar'];

									if (@file_exists(@phpbb_realpath($path))) {
										@unlink($path);
									}
								}
                                @copy($user_avatar_loc, $phpbb_root_path . $board_config['avatar_path'] . $sep . $avatar_filename);

                                $avatar_sql[] = ['user_avatar' => $avatar_filename, 'user_avatar_type' => USER_AVATAR_UPLOAD];
							} else {
								$l_avatar_size = sprintf($lang['Avatar_imagesize'], $board_config['avatar_max_width'], $board_config['avatar_max_height']);

								$error = true;
								$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
							}
						}
					} else {
						$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

						$error = true;
						$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
					}
				} else {
					$error = true;
					$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Avatar_filetype'] : $lang['Avatar_filetype'];
				}
			} elseif (!empty($user_avatar_url)) {
				//
				// First check what port we should connect
				// to, look for a :[xxxx]/ or, if that doesn't
				// exist assume port 80 (http)
				//
				preg_match("/^(http:\/\/)?([\w\-\.]+)\:?([0-9]*)\/(.*)$/", $user_avatar_url, $url_ary);

				if (!empty($url_ary[4])) {
					$port = !empty($url_ary[3]) ? $url_ary[3] : 80;

					$fsock = @fsockopen($url_ary[2], $port, $errno, $errstr);

                    if ($fsock) {
						$base_get = '/' . $url_ary[4];

						//
						// Uses HTTP 1.1, could use HTTP 1.0 ...
						//
						@fwrite($fsock, "GET $base_get HTTP/1.1\r\n");
						@fwrite($fsock, 'HOST: ' . $url_ary[2] . "\r\n");
						@fwrite($fsock, "Connection: close\r\n\r\n");

						unset($avatar_data);

                        while (!@feof($fsock)) {
							$avatar_data .= @fread($fsock, $board_config['avatar_filesize']);
						}
						@fclose($fsock);

						if (preg_match("/Content-Length\: ([0-9]+)[^\/ ][\s]+/i", $avatar_data, $file_data1) && preg_match("/Content-Type\: image\/[x\-]*([a-z]+)[\s]+/i", $avatar_data, $file_data2)) {
							$file_size = $file_data1[1]; 
							$file_type = $file_data2[1];

                            switch ($file_type) {
								case 'jpeg':
								case 'pjpeg':
								case 'jpg':
									$imgtype = '.jpg';
									break;
								case 'gif':
									$imgtype = '.gif';
									break;
								case 'png':
									$imgtype = '.png';
									break;
								default:
									$error = true;
									$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Avatar_filetype'] : $lang['Avatar_filetype'];
									break;
							}

							if (!$error && $file_size > 0 && $file_size < $board_config['avatar_filesize']) {
								$avatar_data = substr($avatar_data, mb_strlen($avatar_data) - $file_size, $file_size);

								$tmp_filename = tempnam ('/tmp', $this_userdata['user_id'] . '-');
								$fptr = @fopen($tmp_filename, 'wb');
								$bytes_written = @fwrite($fptr, $avatar_data, $file_size);
								@fclose($fptr);

								if ($bytes_written === $file_size) {
									list($width, $height) = @getimagesize($tmp_filename);

									if ($width <= $board_config['avatar_max_width'] && $height <= $board_config['avatar_max_height']) {
										$user_id = $this_userdata['user_id'];

										$avatar_filename = $user_id . $imgtype;

										if ($this_userdata['user_avatar_type'] === USER_AVATAR_UPLOAD &&
                                            $this_userdata['user_avatar'] !== '') {

										    $path = $phpbb_root_path . $board_config['avatar_path'] . $sep . $this_userdata['user_avatar'];


											if (file_exists(@phpbb_realpath($path))) {
												@unlink($path);
											}
										}
                                        @copy($tmp_filename, $phpbb_root_path . $board_config['avatar_path'] . $sep . $avatar_filename);
										@unlink($tmp_filename);

                                        $avatar_sql = ['user_avatar' => $avatar_filename, 'user_avatar_type' => USER_AVATAR_UPLOAD];
									} else {
										$l_avatar_size = sprintf($lang['Avatar_imagesize'], $board_config['avatar_max_width'], $board_config['avatar_max_height']);

										$error = true;
										$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
									}
								} else {
									//
									// Error writing file
									//
									@unlink($tmp_filename);
									message_die(GENERAL_ERROR, 'Could not write avatar file to local storage. Please contact the board administrator with this message', '', __LINE__, __FILE__);
								}
							}
						} else {
							//
							// No data
							//
							$error = true;
							$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['File_no_data'] : $lang['File_no_data'];
						}
					} else {
						//
						// No connection
						//
						$error = true;
						$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['No_connection_URL'] : $lang['No_connection_URL'];
					}
				} else {
					$error = true;
					$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Incomplete_URL'] : $lang['Incomplete_URL'];
				}
			} elseif (!empty($user_avatar_name)) {
				$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

				$error = true;
				$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $l_avatar_size : $l_avatar_size;
			}
		} elseif ($user_avatar_remoteurl !== '' && count($avatar_sql) === 0 && !$error) {
			if (!preg_match("#^http:\/\/#i", $user_avatar_remoteurl)) {
				$user_avatar_remoteurl = 'http://' . $user_avatar_remoteurl;
			}

			if (preg_match("#^(http:\/\/[a-z0-9\-]+?\.([a-z0-9\-]+\.)*[a-z]+\/.*?\.(gif|jpg|png)$)#is", $user_avatar_remoteurl)) {
                $avatar_sql = ['user_avatar' => $user_avatar_remoteurl, 'user_avatar_type' =>USER_AVATAR_REMOTE];
			} else {
				$error = true;
				$error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Wrong_remote_avatar_format'] : $lang['Wrong_remote_avatar_format'];
			}
		} elseif ($user_avatar_local !== '' && count($avatar_sql) === 0 && !$error) {
		    $avatar_sql = [
		        'user_avatar'      => ltrim(basename($user_avatar_category), "'") . '/' . ltrim(basename($user_avatar_local), "'"),
                'user_avatar_type' => USER_AVATAR_GALLERY
            ];
		}
	
		//
		// Update entry in DB
		//
        if (!$error) {
		    $update_data = [
                'user_email' => $email,
                'user_website' => $website,
                'user_occ'     => $occupation,
                'user_from'    => $location,
                'user_interests' => $interests,
                'user_sig'       => $signature,
                'user_viewemail' => $viewemail,
                'user_attachsig' => $attachsig,
                'user_sig_bbcode_uid' => $signature_bbcode_uid,
                'user_allowsmile' => $allowsmilies,
                'user_allowhtml'  => $allowhtml,
                'user_allowavatar' => $user_allowavatar,
                'user_allowbbcode' => $allowbbcode,
                'user_allow_viewonline' => $allowviewonline,
                'user_notify'           => $notifyreply,
                'user_allow_pm'         => $user_allowpm,
                'user_notify_pm'        => $notifypm,
                'user_popup_pm'         => $popuppm,
                'user_lang' => $user_lang,
                'user_style' => $user_style,
                'user_timezone' => $user_timezone,
                'user_dateformat' => $user_dateformat,
                'user_active' =>  $user_status,
                'user_rank' => $user_rank,
            ];

		    $update_data = array_merge($update_data, $username_sql, $passwd_sql, $avatar_sql);

		    $result = dibi::update(USERS_TABLE, $update_data)
                ->where('user_id = %i', $user_id)
                ->execute();

            if (isset($rename_user)) {
                dibi::update(GROUPS_TABLE, ['group_name' => $rename_user])
                    ->where('group_name = %s', $this_userdata['username'])
                    ->execute();
            }

            // Delete user session, to prevent the user navigating the forum (if logged in) when disabled
            if (!$user_status) {
                dibi::delete(SESSIONS_TABLE)
                    ->where('session_user_id = %i', $user_id)
                    ->execute();
            }

            // We remove all stored login keys since the password has been updated
            // and change the current one (if applicable)
            if (count($passwd_sql)) {
                Session::resetKeys($user_id, $user_ip);
            }

            $message = $lang['Admin_user_updated'];
			$message .= '<br /><br />' . sprintf($lang['Click_return_useradmin'], '<a href="' . Session::appendSid('admin_users.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
		} else {
            $template->setFileNames(['reg_header' => 'error_body.tpl']);

            $template->assignVars(['ERROR_MESSAGE' => $error_msg]);

            $template->assignVarFromHandle('ERROR_BOX', 'reg_header');

			$username = stripslashes($username);
			$email = stripslashes($email);
			$password = '';
			$password_confirm = '';
            $acp_password = '';
            $acp_password_confirm = '';

            $website    = stripslashes($website);
            $location   = stripslashes($location);
            $occupation = stripslashes($occupation);
            $interests  = stripslashes($interests);
            $signature  = stripslashes($signature);

			$user_lang = stripslashes($user_lang);
			$user_dateformat = stripslashes($user_dateformat);
		}
	} elseif (!isset( $_POST['submit'] ) && $mode !== 'save' && !isset( $_POST['avatargallery'] ) && !isset( $_POST['submitavatar'] ) && !isset( $_POST['cancelavatar'] )) {
		if (isset( $_GET[POST_USERS_URL]) || isset( $_POST[POST_USERS_URL])) {
            $user_id = isset($_POST[POST_USERS_URL]) ? (int)$_POST[POST_USERS_URL] : (int)$_GET[POST_USERS_URL];

            $this_userdata = get_userdata($user_id);
		} else {
			$this_userdata = get_userdata($_POST['username'], true);
		}

        if (!$this_userdata) {
            message_die(GENERAL_MESSAGE, $lang['No_user_id_specified'] );
        }

		//
		// Now parse and display it as a template
		//
		$user_id = $this_userdata->user_id;
		$username = $this_userdata->username;
		$email = $this_userdata->user_email;
		$password = '';
		$password_confirm = '';
        $acp_password = '';
        $acp_password_confirm = '';

		$website = $this_userdata->user_website;
		$location = $this_userdata->user_from;
		$occupation = $this_userdata->user_occ;
		$interests = $this_userdata->user_interests;

		$signature = ($this_userdata->user_sig_bbcode_uid !== '') ? preg_replace('#:' . $this_userdata->user_sig_bbcode_uid . '#si', '', $this_userdata->user_sig) : $this_userdata->user_sig;
		$signature = preg_replace(PostHelper::$htmlEntitiesMatch, PostHelper::$htmlEntitiesReplace, $signature);

		$viewemail = $this_userdata->user_viewemail;
		$notifypm = $this_userdata->user_notify_pm;
		$popuppm = $this_userdata->user_popup_pm;
		$notifyreply = $this_userdata->user_notify;
		$attachsig = $this_userdata->user_attachsig;
		$allowhtml = $this_userdata->user_allowhtml;
		$allowbbcode = $this_userdata->user_allowbbcode;
		$allowsmilies = $this_userdata->user_allowsmile;
		$allowviewonline = $this_userdata->user_allow_viewonline;

		$user_avatar = $this_userdata->user_avatar;
		$user_avatar_type = $this_userdata->user_avatar_type;
		$user_style = $this_userdata->user_style;
		$user_lang = $this_userdata->user_lang;
		$user_timezone = $this_userdata->user_timezone;
		$user_dateformat = htmlspecialchars($this_userdata->user_dateformat);
		
		$user_status = $this_userdata->user_active;
		$user_allowavatar = $this_userdata->user_allowavatar;
		$user_allowpm = $this_userdata->user_allow_pm;
		
		$COPPA = false;

		$html_status    = $this_userdata->user_allowhtml   ? $lang['HTML_is_ON']     : $lang['HTML_is_OFF'];
		$bbcode_status  = $this_userdata->user_allowbbcode ? $lang['BBCode_is_ON']   : $lang['BBCode_is_OFF'];
		$smilies_status = $this_userdata->user_allowsmile  ? $lang['Smilies_are_ON'] : $lang['Smilies_are_OFF'];
	}

	if (isset($_POST['avatargallery']) && !$error) {
		if (!$error) {
			$user_id = (int)$_POST['id'];
            $allowviewonline = !$allowviewonline;

            $template->setFileNames(['body' => 'admin/user_avatar_gallery.tpl']);

            AvatarHelper::displayAvatarGallery($mode, $_POST['avatargallery'], $user_id, $email, $current_email, $coppa, $username, $new_password, $cur_password, $password_confirm, $website, $location, $occupation, $interests, $signature, $viewemail, $notifypm, $popuppm, $notifyreply, $attachsig, $allowhtml, $allowbbcode, $allowsmilies, $allowviewonline, $user_style, $user_lang, $user_timezone, $user_dateformat, $userdata['session_id'], true, $template, $user_status, $user_allowavatar, $user_allowpm, $user_rank);
        }
    } else {
		$s_hidden_fields = '<input type="hidden" name="mode" value="save" /><input type="hidden" name="agreed" value="true" /><input type="hidden" name="coppa" value="' . $coppa . '" />';
		$s_hidden_fields .= '<input type="hidden" name="id" value="' . $this_userdata['user_id'] . '" />';

		if (!empty($user_avatar_local)) {
			$s_hidden_fields .= '<input type="hidden" name="avatarlocal" value="' . $user_avatar_local . '" /><input type="hidden" name="avatarcatname" value="' . $user_avatar_category . '" />';
		}

		if ($user_avatar_type) {
            switch ($user_avatar_type) {
				case USER_AVATAR_UPLOAD:
					$avatar = '<img src="../' . $board_config['avatar_path'] . '/' . $user_avatar . '" alt="" />';
					break;
				case USER_AVATAR_REMOTE:
					$avatar = '<img src="' . $user_avatar . '" alt="" />';
					break;
				case USER_AVATAR_GALLERY:
					$avatar = '<img src="../' . $board_config['avatar_gallery_path'] . '/' . $user_avatar . '" alt="" />';
					break;
			}
		} else {
			$avatar = '';
		}

		$ranks = dibi::select('*')
            ->from(RANKS_TABLE)
            ->where('rank_special = %i', 1)
            ->orderBy('rank_title')
            ->fetchAll();

		$rank_select_box = '<option value="0">' . $lang['No_assigned_rank'] . '</option>';

		foreach ($ranks as $rank) {
			$selected = $this_userdata['user_rank'] === $rank->rank_id ? 'selected="selected"' : '';
			$rank_select_box .= '<option value="' . $rank->rank_id . '" ' . $selected . '>' . htmlspecialchars($rank->rank_title, ENT_QUOTES) . '</option>';
		}

        $template->setFileNames(['body' => 'admin/user_edit_body.tpl']);

        //
		// Let's do an overall check for settings/versions which would prevent
		// us from doing file uploads....
		//
		$form_enctype = ( !@ini_get('file_uploads') || PHP_VERSION === '4.0.4pl1' || !$board_config['allow_avatar_upload'] || ( PHP_VERSION < '4.0.3' && @ini_get('open_basedir') !== '' ) ) ? '' : 'enctype="multipart/form-data"';

		$template->assignVars(
		    [
                'USERNAME' => $username,
                'EMAIL' => htmlspecialchars($email, ENT_QUOTES),
                'OCCUPATION' => htmlspecialchars($occupation, ENT_QUOTES),
                'INTERESTS' => htmlspecialchars($interests, ENT_QUOTES),
                'LOCATION' => htmlspecialchars($location, ENT_QUOTES),
                'WEBSITE' => htmlspecialchars($website, ENT_QUOTES),
                'SIGNATURE' => str_replace('<br />', "\n", $signature),
                'VIEW_EMAIL_YES' => $viewemail ? 'checked="checked"' : '',
                'VIEW_EMAIL_NO' => !$viewemail ? 'checked="checked"' : '',
                'HIDE_USER_YES' => !$allowviewonline ? 'checked="checked"' : '',
                'HIDE_USER_NO' => $allowviewonline ? 'checked="checked"' : '',
                'NOTIFY_PM_YES' => $notifypm ? 'checked="checked"' : '',
                'NOTIFY_PM_NO' => !$notifypm ? 'checked="checked"' : '',
                'POPUP_PM_YES' => $popuppm ? 'checked="checked"' : '',
                'POPUP_PM_NO' => !$popuppm ? 'checked="checked"' : '',
                'ALWAYS_ADD_SIGNATURE_YES' => $attachsig ? 'checked="checked"' : '',
                'ALWAYS_ADD_SIGNATURE_NO' => !$attachsig ? 'checked="checked"' : '',
                'NOTIFY_REPLY_YES' => $notifyreply ? 'checked="checked"' : '',
                'NOTIFY_REPLY_NO' => !$notifyreply ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_BBCODE_YES' => $allowbbcode ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_BBCODE_NO' => !$allowbbcode ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_HTML_YES' => $allowhtml ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_HTML_NO' => !$allowhtml ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_SMILIES_YES' => $allowsmilies ? 'checked="checked"' : '',
                'ALWAYS_ALLOW_SMILIES_NO' => !$allowsmilies ? 'checked="checked"' : '',
                'AVATAR' => $avatar,
                'LANGUAGE_SELECT' => Select::language($user_lang),
                'TIMEZONE_SELECT' => Select::timezone($user_timezone),
                'STYLE_SELECT' => Select::style($user_style),
                'DATE_FORMAT' => $user_dateformat,
                'ALLOW_PM_YES' => $user_allowpm ? 'checked="checked"' : '',
                'ALLOW_PM_NO' => !$user_allowpm ? 'checked="checked"' : '',
                'ALLOW_AVATAR_YES' => $user_allowavatar ? 'checked="checked"' : '',
                'ALLOW_AVATAR_NO' => !$user_allowavatar ? 'checked="checked"' : '',
                'USER_ACTIVE_YES' => $user_status ? 'checked="checked"' : '',
                'USER_ACTIVE_NO' => !$user_status ? 'checked="checked"' : '',
                'RANK_SELECT_BOX' => $rank_select_box,

                'L_USERNAME' => $lang['Username'],
                'L_USER_TITLE' => $lang['User_admin'],
                'L_USER_EXPLAIN' => $lang['User_admin_explain'],
                'L_NEW_PASSWORD' => $lang['New_password'],
                'L_PASSWORD_IF_CHANGED' => $lang['password_if_changed'],
                'L_CONFIRM_PASSWORD' => $lang['Confirm_password'],
                'L_PASSWORD_CONFIRM_IF_CHANGED' => $lang['password_confirm_if_changed'],
                'L_ACP_PASSWORD' => $lang['ACP_password'],
                'L_ACP_PASSWORD_CONFIRM' => $lang['ACP_password_confirm'],
                'L_ACP_PASSWORD_EXPLAIN' => $lang['ACP_password_explain'],
                'L_ACP_PASSWORD_COMPLEX' => ($board_config['complex_acp_pw']) ? $lang['ACP_password_complex'] : '',
                'L_SUBMIT' => $lang['Submit'],
                'L_RESET' => $lang['Reset'],
                'L_WEBSITE' => $lang['Website'],
                'L_LOCATION' => $lang['Location'],
                'L_OCCUPATION' => $lang['Occupation'],
                'L_BOARD_LANGUAGE' => $lang['Board_lang'],
                'L_BOARD_STYLE' => $lang['Board_style'],
                'L_TIMEZONE' => $lang['Timezone'],
                'L_DATE_FORMAT' => $lang['Date_format'],
                'L_DATE_FORMAT_EXPLAIN' => $lang['Date_format_explain'],
                'L_YES' => $lang['Yes'],
                'L_NO' => $lang['No'],
                'L_INTERESTS' => $lang['Interests'],
                'L_ALWAYS_ALLOW_SMILIES' => $lang['Always_smile'],
                'L_ALWAYS_ALLOW_BBCODE' => $lang['Always_bbcode'],
                'L_ALWAYS_ALLOW_HTML' => $lang['Always_html'],
                'L_HIDE_USER' => $lang['Hide_user'],
                'L_ALWAYS_ADD_SIGNATURE' => $lang['Always_add_sig'],

                'L_SPECIAL' => $lang['User_special'],
                'L_SPECIAL_EXPLAIN' => $lang['User_special_explain'],
                'L_USER_ACTIVE' => $lang['User_status'],
                'L_ALLOW_PM' => $lang['User_allowpm'],
                'L_ALLOW_AVATAR' => $lang['User_allowavatar'],

                'L_AVATAR_PANEL' => $lang['Avatar_panel'],
                'L_AVATAR_EXPLAIN' => $lang['Admin_avatar_explain'],
                'L_DELETE_AVATAR' => $lang['Delete_Image'],
                'L_CURRENT_IMAGE' => $lang['Current_Image'],
                'L_UPLOAD_AVATAR_FILE' => $lang['Upload_Avatar_file'],
                'L_UPLOAD_AVATAR_URL' => $lang['Upload_Avatar_URL'],
                'L_AVATAR_GALLERY' => $lang['Select_from_gallery'],
                'L_SHOW_GALLERY' => $lang['View_avatar_gallery'],
                'L_LINK_REMOTE_AVATAR' => $lang['Link_remote_Avatar'],

                'L_SIGNATURE' => $lang['Signature'],
                'L_SIGNATURE_EXPLAIN' => sprintf($lang['Signature_explain'], $board_config['max_sig_chars'] ),
                'L_NOTIFY_ON_PRIVMSG' => $lang['Notify_on_privmsg'],
                'L_NOTIFY_ON_REPLY' => $lang['Always_notify'],
                'L_POPUP_ON_PRIVMSG' => $lang['Popup_on_privmsg'],
                'L_PREFERENCES' => $lang['Preferences'],
                'L_PUBLIC_VIEW_EMAIL' => $lang['Public_view_email'],
                'L_ITEMS_REQUIRED' => $lang['Items_required'],
                'L_REGISTRATION_INFO' => $lang['Registration_info'],
                'L_PROFILE_INFO' => $lang['Profile_info'],
                'L_PROFILE_INFO_NOTICE' => $lang['Profile_info_warn'],
                'L_EMAIL_ADDRESS' => $lang['Email_address'],
                'S_FORM_ENCTYPE' => $form_enctype,

                'HTML_STATUS' => $html_status,
                'BBCODE_STATUS' => sprintf($bbcode_status, '<a href="../' . Session::appendSid('faq.php?mode=bbcode') . '" target="_phpbbcode">', '</a>'),
                'SMILIES_STATUS' => $smilies_status,

                'L_DELETE_USER' => $lang['User_delete'],
                'L_DELETE_USER_EXPLAIN' => $lang['User_delete_explain'],
                'L_SELECT_RANK' => $lang['Rank_title'],

                'S_HIDDEN_FIELDS' => $s_hidden_fields,
                'S_PROFILE_ACTION' => Session::appendSid('admin_users.php')
            ]
		);

        if (file_exists(@phpbb_realpath($phpbb_root_path . $board_config['avatar_path'])) && ($board_config['allow_avatar_upload'] === '1')) {
            if ($form_enctype !== '') {
                $template->assignBlockVars('avatar_local_upload', []);
            }

            $template->assignBlockVars('avatar_remote_upload', []);
        }

        if (file_exists(@phpbb_realpath($phpbb_root_path . $board_config['avatar_gallery_path'])) && ($board_config['allow_avatar_local'] === '1')) {
            $template->assignBlockVars('avatar_local_gallery', []);
        }

        if ($board_config['allow_avatar_remote'] === '1') {
            $template->assignBlockVars('avatar_remote_link', []);
        }
    }

    $template->pparse('body');
}
else
{
	//
	// Default user selection box
	//
    $template->setFileNames(['body' => 'admin/user_select_body.tpl']);

    $template->assignVars(
        [
            'L_USER_TITLE'    => $lang['User_admin'],
            'L_USER_EXPLAIN'  => $lang['User_admin_explain'],
            'L_USER_SELECT'   => $lang['Select_a_User'],
            'L_LOOK_UP'       => $lang['Look_up_user'],
            'L_FIND_USERNAME' => $lang['Find_username'],

            'U_SEARCH_USER' => Session::appendSid('./../search.php?mode=searchuser'),

            'S_USER_ACTION' => Session::appendSid('admin_users.php'),
            'S_USER_SELECT' => $select_list
        ]
    );
    $template->pparse('body');

}

require_once '.' . $sep . 'page_footer_admin.php';

?>