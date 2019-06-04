<?php
/***************************************************************************
 *                            admin_user_ban.php
 *                            -------------------
 *   begin                : Tuesday, Jul 31, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_user_ban.php 5283 2005-10-30 15:17:14Z acydburn $
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

if ( !empty($setmodules) ) {
	$filename = basename(__FILE__);
	$module['Users']['Ban_Management'] = $filename;

	return;
}

//
// Load default header
//
$phpbb_root_path = './../';

require './pagestart.php';

//
// Start program
//
if ( isset($_POST['submit']) ) {
	$user_bansql = '';
	$email_bansql = '';
	$ip_bansql = '';

	$user_list = [];

	if ( !empty($_POST['username']) ) {
		$this_userdata = get_userdata($_POST['username'], true);

		if (!$this_userdata ) {
			message_die(GENERAL_MESSAGE, $lang['No_user_id_specified'] );
		}

		$user_list[] = $this_userdata['user_id'];
	}

	$ip_list = [];

	if ( isset($_POST['ban_ip']) ) {
		$ip_list_temp = explode(',', $_POST['ban_ip']);

		foreach ($ip_list_temp as $i => $ip_value_tmp) {
			if ( preg_match('/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})[ ]*\-[ ]*([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/', trim($ip_value_tmp), $ip_range_explode) ) {
				//
				// Don't ask about all this, just don't ask ... !
				//
				$ip_1_counter = $ip_range_explode[1];
				$ip_1_end = $ip_range_explode[5];

				while ( $ip_1_counter <= $ip_1_end ) {
					$ip_2_counter = ( $ip_1_counter === $ip_range_explode[1] ) ? $ip_range_explode[2] : 0;
					$ip_2_end = ( $ip_1_counter < $ip_1_end ) ? 254 : $ip_range_explode[6];

					if ( $ip_2_counter === 0 && $ip_2_end === 254 ) {
						$ip_2_counter = 255;
						$ip_2_fragment = 255;

						$ip_list[] = encode_ip("$ip_1_counter.255.255.255");
					}

					while ( $ip_2_counter <= $ip_2_end ) {
						$ip_3_counter = ( $ip_2_counter === $ip_range_explode[2] && $ip_1_counter === $ip_range_explode[1] ) ? $ip_range_explode[3] : 0;
						$ip_3_end = ( $ip_2_counter < $ip_2_end || $ip_1_counter < $ip_1_end ) ? 254 : $ip_range_explode[7];

						if ( $ip_3_counter === 0 && $ip_3_end === 254 ) {
							$ip_3_counter = 255;
							$ip_3_fragment = 255;

							$ip_list[] = encode_ip("$ip_1_counter.$ip_2_counter.255.255");
						}

						while ( $ip_3_counter <= $ip_3_end ) {
							$ip_4_counter = ( $ip_3_counter === $ip_range_explode[3] && $ip_2_counter === $ip_range_explode[2] && $ip_1_counter === $ip_range_explode[1] ) ? $ip_range_explode[4] : 0;
							$ip_4_end = ( $ip_3_counter < $ip_3_end || $ip_2_counter < $ip_2_end ) ? 254 : $ip_range_explode[8];

							if ( $ip_4_counter === 0 && $ip_4_end === 254 ) {
								$ip_4_counter = 255;
								$ip_4_fragment = 255;

								$ip_list[] = encode_ip("$ip_1_counter.$ip_2_counter.$ip_3_counter.255");
							}

							while ( $ip_4_counter <= $ip_4_end ) {
								$ip_list[] = encode_ip("$ip_1_counter.$ip_2_counter.$ip_3_counter.$ip_4_counter");
								$ip_4_counter++;
							}

							$ip_3_counter++;
						}

						$ip_2_counter++;
					}

					$ip_1_counter++;
				}
			} elseif ( preg_match('/^([\w\-_]\.?){2,}$/is', trim($ip_value_tmp)) ) {
				$ips = gethostbynamel(trim($ip_value_tmp));

                foreach ($ips as $ip) {
                    if (!empty($ip)) {
                        $ip_list[] = encode_ip($ip);
                    }
                }
			} elseif ( preg_match('/^([0-9]{1,3})\.([0-9\*]{1,3})\.([0-9\*]{1,3})\.([0-9\*]{1,3})$/', trim($ip_value_tmp)) ) {
				$ip_list[] = encode_ip(str_replace('*', '255', trim($ip_value_tmp)));
			}
		}
	}

	$email_list = [];
	if ( isset($_POST['ban_email']) ) {
		$email_list_temp = explode(',', $_POST['ban_email']);

		foreach ($email_list_temp as $email_value_tmp) {
			//
			// This ereg match is based on one by php@unreelpro.com
			// contained in the annotated php manual at php.com (ereg
			// section)
			//
			if (preg_match('/^(([a-z0-9&\'\.\-_\+])|(\*))+@(([a-z0-9\-])|(\*))+\.([a-z0-9\-]+\.)*?[a-z]+$/is', trim($email_value_tmp))) {
				$email_list[] = trim($email_value_tmp);
			}
		}
	}

    $bans = dibi::select('*')
        ->from(BANLIST_TABLE)
        ->fetchAll();

	$kill_session_sql = '';

	foreach ($user_list as $user_value) {
		$in_banlist = false;

		foreach ($bans as $ban) {
            if ($user_value === $ban->ban_userid) {
                $in_banlist = true;
            }
        }

		if ( !$in_banlist ) {
			$kill_session_sql .= ( ( $kill_session_sql !== '' ) ? ' OR ' : '' ) . "session_user_id = " . $user_value;

			dibi::insert(BANLIST_TABLE, ['ban_userid' => $user_value ])
                ->execute();
		}
	}

	foreach ($ip_list as $ip_value) {
		$in_banlist = false;

		foreach ($bans as $ban) {
		    if ($ip_value === $ban->ban_ip) {
		        $in_banlist = true;
            }
        }

		if ( !$in_banlist ) {
			if ( preg_match('/(ff\.)|(\.ff)/is', chunk_split($ip_value, 2, '.')) ) {
				$kill_ip_sql = "session_ip LIKE '" . str_replace('.', '', preg_replace('/(ff\.)|(\.ff)/is', '%', chunk_split($ip_value, 2, "."))) . "'";
			} else {
				$kill_ip_sql = "session_ip = '" . $ip_value . "'";
			}

			$kill_session_sql .= ( ( $kill_session_sql !== '' ) ? ' OR ' : '' ) . $kill_ip_sql;

			dibi::insert(BANLIST_TABLE, ['ban_ip' => $ip_value])->execute();
		}
	}

	//
	// Now we'll delete all entries from the session table with any of the banned
	// user or IP info just entered into the ban table ... this will force a session
	// initialisation resulting in an instant ban
	//
	if ( $kill_session_sql !== '' ) {
	    dibi::delete(SESSIONS_TABLE)
            ->where($kill_session_sql)
            ->execute();
	}

	foreach ($email_list as $email_value) {
		$in_banlist = false;

		foreach ($bans as $ban) {
            if ( $email_value === $ban->ban_email ) {
                $in_banlist = true;
            }
        }

		if ( !$in_banlist ) {
		    dibi::insert(BANLIST_TABLE, ['ban_email' => $email_value])
                ->execute();
		}
	}

	$where_sql = [];

    if (isset($_POST['unban_user'])) {
        $user_list = $_POST['unban_user'];

        foreach ($user_list as $user_value) {
            if ($user_value !== -1) {
                $where_sql[] = (int)$user_value;
            }
        }
    }

    if (isset($_POST['unban_ip'])) {
        $ip_list = $_POST['unban_ip'];

        foreach ($ip_list as $ip_value) {
            if ($ip_value !== -1) {
                $where_sql[] = $ip_value;
            }
        }
    }

    if (isset($_POST['unban_email'])) {
        $email_list = $_POST['unban_email'];

        foreach ($email_list as $email_value) {
            if ($email_value !== -1) {
                $where_sql[] = $email_value;
            }
        }
    }

    if (count($where_sql)) {
        dibi::delete(BANLIST_TABLE)
            ->where('ban_id IN %in', $where_sql)
            ->execute();
    }

	$message = $lang['Ban_update_sucessful'] . '<br /><br />' . sprintf($lang['Click_return_banadmin'], '<a href="' . append_sid("admin_user_ban.php") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . append_sid("index.php?pane=right") . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);

} else {
    $template->set_filenames(['body' => 'admin/user_ban_body.tpl']);

    $template->assign_vars([
            'L_BAN_TITLE'        => $lang['Ban_control'],
            'L_BAN_EXPLAIN'      => $lang['Ban_explain'],
            'L_BAN_EXPLAIN_WARN' => $lang['Ban_explain_warn'],
            'L_IP_OR_HOSTNAME'   => $lang['IP_hostname'],
            'L_EMAIL_ADDRESS'    => $lang['Email_address'],
            'L_SUBMIT'           => $lang['Submit'],
            'L_RESET'            => $lang['Reset'],

            'S_BANLIST_ACTION' => append_sid("admin_user_ban.php")
        ]);

    $template->assign_vars(
        [
            'L_BAN_USER'          => $lang['Ban_username'],
            'L_BAN_USER_EXPLAIN'  => $lang['Ban_username_explain'],
            'L_BAN_IP'            => $lang['Ban_IP'],
            'L_BAN_IP_EXPLAIN'    => $lang['Ban_IP_explain'],
            'L_BAN_EMAIL'         => $lang['Ban_email'],
            'L_BAN_EMAIL_EXPLAIN' => $lang['Ban_email_explain']
        ]
    );

	$userban_count = 0;
	$ipban_count = 0;
	$emailban_count = 0;

    $user_list = dibi::select(['b.ban_id', 'u.user_id', 'u.username'])
        ->from(BANLIST_TABLE)
        ->as('b')
        ->from(USERS_TABLE)
        ->as('u')
        ->where('u.user_id = b.ban_userid')
        ->where('b.ban_userid <> 0')
        ->where('u.user_id <> %i', ANONYMOUS)
        ->orderBy('u.user_id', dibi::ASC)
        ->fetchAll();

    $userban_count = count($user_list);
	$select_userlist = '';

	foreach ($user_list as $user_item) {
		$select_userlist .= '<option value="' . $user_item->ban_id . '">' . $user_item->username . '</option>';
	}

	if ($select_userlist === '' ) {
		$select_userlist = '<option value="-1">' . $lang['No_banned_users'] . '</option>';
	}

	$select_userlist = '<select name="unban_user[]" multiple="multiple" size="5">' . $select_userlist . '</select>';

    $banlist = dibi::select(['ban_id', 'ban_ip', 'ban_email'])
        ->from(BANLIST_TABLE)
        ->fetchAll();

	$select_iplist = '';
	$select_emaillist = '';

	foreach ($banlist as $ban) {
		$ban_id = $ban->ban_id;

		if ( !empty($ban->ban_ip) ) {
			$ban_ip = str_replace('255', '*', decode_ip($ban->ban_ip));
			$select_iplist .= '<option value="' . $ban_id . '">' . $ban_ip . '</option>';
			$ipban_count++;
		} elseif ( !empty($ban->ban_email) ) {
			$ban_email = $ban->ban_email;
			$select_emaillist .= '<option value="' . $ban_id . '">' . $ban_email . '</option>';
			$emailban_count++;
		}
	}

	if ( $select_iplist === '' ) {
		$select_iplist = '<option value="-1">' . $lang['No_banned_ip'] . '</option>';
	}

	if ( $select_emaillist === '' ) {
		$select_emaillist = '<option value="-1">' . $lang['No_banned_email'] . '</option>';
	}

	$select_iplist = '<select name="unban_ip[]" multiple="multiple" size="5">' . $select_iplist . '</select>';
	$select_emaillist = '<select name="unban_email[]" multiple="multiple" size="5">' . $select_emaillist . '</select>';

    $template->assign_vars(
        [
            'L_UNBAN_USER'          => $lang['Unban_username'],
            'L_UNBAN_USER_EXPLAIN'  => $lang['Unban_username_explain'],
            'L_UNBAN_IP'            => $lang['Unban_IP'],
            'L_UNBAN_IP_EXPLAIN'    => $lang['Unban_IP_explain'],
            'L_UNBAN_EMAIL'         => $lang['Unban_email'],
            'L_UNBAN_EMAIL_EXPLAIN' => $lang['Unban_email_explain'],
            'L_USERNAME'            => $lang['Username'],
            'L_LOOK_UP'             => $lang['Look_up_User'],
            'L_FIND_USERNAME'       => $lang['Find_username'],

            'U_SEARCH_USER'            => append_sid("./../search.php?mode=searchuser"),
            'S_UNBAN_USERLIST_SELECT'  => $select_userlist,
            'S_UNBAN_IPLIST_SELECT'    => $select_iplist,
            'S_UNBAN_EMAILLIST_SELECT' => $select_emaillist,
            'S_BAN_ACTION'             => append_sid("admin_user_ban.php")
        ]
    );
}

$template->pparse('body');

include './page_footer_admin.php';

?>