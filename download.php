<?php
/**
 *
 * @package attachment_mod
 * @version $Id: download.php,v 1.6 2006/09/04 12:56:06 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */
if (defined('IN_PHPBB')) {
    die('Hacking attempt');
}

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Delete the / * to uncomment the block, and edit the values (read the comments) to
// enable additional security to your board (preventing third site linkage)
//
/*
define('ALLOWED_DENIED', 0);
define('DENIED_ALLOWED', 1);

//
// From this line on you are able to edit the stuff
//

// Possible Values:
// ALLOWED_DENIED <- First allow the listed sites, and then deny all others
// DENIED_ALLOWED <- First deny the listed sites, and then allow all others
$allow_deny_order = ALLOWED_DENIED;

//
// Allowed Syntax:
// Full Domain Name -> www.opentools.de
// Partial Domain Names -> opentools.de
//
$sites = array(
	$board_config['server_name'],	// This is your domain
	'opentools.de',
	'phpbb.com',
	'phpbbhacks.com',
	'phpbb.de'
);

// This is the message displayed, if someone links to this site...
$lang['Denied_Message'] = 'You are not authorized to view, download or link to this Site.';

// End of editable area

//
// Parse the order and evaluate the array
//

$site = explode('?', $HTTP_SERVER_VARS['HTTP_REFERER']);
$url = trim($site[0]);
//$url = $HTTP_HOST;

if ($url !== '')
{
	$allowed = ($allow_deny_order === ALLOWED_DENIED) ? false : true;
	
	for ($i = 0; $i < count($sites); $i++)
	{
		if (strstr($url, $sites[$i]))
		{
			$allowed = ($allow_deny_order === ALLOWED_DENIED) ? true : false;
			break;
		}
	}
}
else
{
	$allowed = true;
}

if ($allowed === false)
{
	message_die(GENERAL_MESSAGE, $lang['Denied_Message']);
}

// Delete the following line, to uncomment this block
*/

$download_id = get_var('id', 0);
$thumbnail = get_var('thumb', 0);

// Send file to browser
function send_file_to_browser($attachment, $upload_dir)
{
    global $HTTP_USER_AGENT, $lang, $attach_config;

    $filename = $upload_dir === '' ? $attachment->physical_filename : $upload_dir . '/' . $attachment->physical_filename;

    $gotit = false;

    if (!(int)$attach_config['allow_ftp_upload']) {
        if (@!file_exists(@amod_realpath($filename))) {
            message_die(GENERAL_ERROR, $lang['Error_no_attachment'] . '<br /><br /><b>404 File Not Found:</b> The File <i>' . $filename . '</i> does not exist.');
        } else {
            $gotit = true;
        }
    }

    //
    // Determine the Browser the User is using, because of some nasty incompatibilities.
    // Most of the methods used in this function are from phpMyAdmin. :)
    //
    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
    } else if (!isset($HTTP_USER_AGENT)) {
        $HTTP_USER_AGENT = '';
    }

    if (preg_match('#Opera(/| )([0-9].[0-9]{1,2})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[2];
        $browser_agent = 'opera';
    } else if (preg_match('#MSIE ([0-9].[0-9]{1,2})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[1];
        $browser_agent = 'ie';
    } else if (preg_match('#OmniWeb/([0-9].[0-9]{1,2})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[1];
        $browser_agent = 'omniweb';
    } else if (preg_match('#Netscape([0-9]{1})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[1];
        $browser_agent = 'netscape';
    } else if (preg_match('#Mozilla/([0-9].[0-9]{1,2})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[1];
        $browser_agent = 'mozilla';
    } else if (preg_match('#Konqueror/([0-9].[0-9]{1,2})#', $HTTP_USER_AGENT, $log_version)) {
        $browser_version = $log_version[1];
        $browser_agent = 'konqueror';
    } else {
        $browser_version = 0;
        $browser_agent = 'other';
    }

    // Correct the mime type - we force application/octetstream for all files, except images
    // Please do not change this, it is a security precaution
    if (!mb_strstr($attachment->mimetype, 'image')) {
        $attachment->mimetype = $browser_agent === 'ie' || $browser_agent === 'opera' ? 'application/octetstream' : 'application/octet-stream';
    }

    // Now the tricky part... let's dance
//	@ob_end_clean();
//	@ini_set('zlib.output_compression', 'Off');
    header('Pragma: public');
//	header('Content-Transfer-Encoding: none');

    $real_filename = html_entity_decode(basename($attachment->real_filename));

    // Send out the Headers
    header('Content-Type: ' . $attachment->mimetype . '; name="' . $real_filename . '"');
    header('Content-Disposition: attachment; filename="' . $real_filename . '"');

    unset($real_filename);

    //
    // Now send the File Contents to the Browser
    //
    if ($gotit) {
        $size = @filesize($filename);
        if ($size) {
            header("Content-length: $size");
        }
        readfile($filename);
    } else if (!$gotit && (int)$attach_config['allow_ftp_upload']) {
        $conn_id = attach_init_ftp();

        $tmp_path = !@ini_get('safe_mode') ? '/tmp' : $upload_dir;
        $tmp_filename = @tempnam($tmp_path, 't0000');

        @unlink($tmp_filename);

        $mode = FTP_BINARY;
        if ((preg_match('/text/i', $attachment->mimetype)) || (preg_match('/html/i', $attachment->mimetype))) {
            $mode = FTP_ASCII;
        }

        $result = @ftp_get($conn_id, $tmp_filename, $filename, $mode);

        if (!$result) {
            message_die(GENERAL_ERROR, $lang['Error_no_attachment'] . '<br /><br /><b>404 File Not Found:</b> The File <i>' . $filename . '</i> does not exist.');
        }

        @ftp_close($conn_id);

        $size = @filesize($tmp_filename);
        if ($size) {
            header("Content-length: $size");
        }
        readfile($tmp_filename);
        @unlink($tmp_filename);
    } else {
        message_die(GENERAL_ERROR, $lang['Error_no_attachment'] . '<br /><br /><b>404 File Not Found:</b> The File <i>' . $filename . '</i> does not exist.');
    }

    exit;
}

//
// End Functions
//

//
// Start Session Management
//

// TODO
$userdata = init_userprefs(PAGE_INDEX);

if (!$download_id) {
    message_die(GENERAL_ERROR, $lang['No_attachment_selected']);
}

if ($attach_config['disable_mod'] && $userdata['user_level'] !== ADMIN) {
    message_die(GENERAL_MESSAGE, $lang['Attachment_feature_disabled']);
}

$attachment = dibi::select('*')
    ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
    ->where('[attach_id] = %i', $download_id)
    ->fetch();

if (!$attachment) {
    message_die(GENERAL_MESSAGE, $lang['Error_no_attachment']);
}

$attachment->physical_filename = basename($attachment->physical_filename);

// get forum_id for attachment authorization or private message authorization
$authorised = false;

$auth_pages = dibi::select('*')
    ->from(Tables::ATTACH_ATTACHMENT_TABLE)
    ->where('[attach_id] = %i', $attachment->attach_id)
    ->fetchAll();

$num_auth_pages = count($auth_pages);

for ($i = 0; $i < $num_auth_pages && $authorised === false; $i++) {
    $auth_pages[$i]['post_id'] = (int)$auth_pages[$i]['post_id'];

    if ($auth_pages[$i]['post_id'] !== 0) {
        $row = dibi::select('forum_id')
            ->from(Tables::POSTS_TABLE)
            ->where('[post_id] = %i', $auth_pages[$i]['post_id'])
            ->fetch();

        $is_auth = Auth::authorize(Auth::AUTH_ALL, $row->forum_id, $userdata);

        if ($is_auth['auth_download']) {
            $authorised = true;
        }
    } else {
        if (((int)$attach_config['allow_pm_attach']) && (($userdata['user_id'] === $auth_pages[$i]['user_id_2']) || ($userdata['user_id'] === $auth_pages[$i]['user_id_1'])) || ($userdata['user_level'] === ADMIN)) {
            $authorised = true;
        }
    }
}

if (!$authorised) {
    message_die(GENERAL_MESSAGE, $lang['Sorry_auth_view_attach']);
}

//
// Get Information on currently allowed Extensions
//
$rows = dibi::select(['e.extension', 'g.download_mode'])
    ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
    ->as('g')
    ->innerJoin(Tables::ATTACH_EXTENSION_TABLE)
    ->as('e')
    ->on('[g.group_id] = [e.group_id]')
    ->where('[g.allow_group] = %i', 1)
    ->fetchAll();

$num_rows = count($rows);

$download_modes = [];

foreach ($rows as $row) {
    $extension = strtolower(trim($row->extension));
    $allowed_extensions[] = $extension;
    $download_modes[$extension] = $row->download_mode;
}

// disallowed ?
if (!in_array($attachment->extension, $allowed_extensions) && $userdata['user_level'] !== ADMIN) {
    message_die(GENERAL_MESSAGE, sprintf($lang['Extension_disabled_after_posting'], $attachment->extension));
}

$download_mode = (int)$download_modes[$attachment->extension];

if ($thumbnail) {
    $attachment->physical_filename = THUMB_DIR . '/t_' . $attachment->physical_filename;
}

// Update download count
if (!$thumbnail) {
    dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['download_count%sql' => 'download_count + 1'])
        ->where('[attach_id] = %i', $attachment->attach_id)
        ->execute();
}

// Determine the 'presenting'-method
if ($download_mode === PHYSICAL_LINK) {
    $server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
    $server_name = preg_replace('/^\/?(.*?)\/?$/', '\1', trim($board_config['server_name']));
    $server_port = $board_config['server_port'] !== 80 ? ':' . trim($board_config['server_port']) : '';
    $script_name = preg_replace('/^\/?(.*?)\/?$/', '/\1', trim($board_config['script_path']));

    if ($script_name[mb_strlen($script_name)] !== '/') {
        $script_name .= '/';
    }

    if ((int)$attach_config['allow_ftp_upload']) {
        if (trim($attach_config['download_path']) === '') {
            message_die(GENERAL_ERROR, 'Physical Download not possible with the current Attachment Setting');
        }

        $url = trim($attach_config['download_path']) . '/' . $attachment->physical_filename;
        $redirect_path = $url;
    } else {
        $url = $upload_dir . '/' . $attachment->physical_filename;
//		$url = preg_replace('/^\/?(.*?\/)?$/', '\1', trim($url));
        $redirect_path = $server_protocol . $server_name . $server_port . $script_name . $url;
    }

    // Redirect via an HTML form for PITA webservers
    if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) {
        header('Refresh: 0; URL=' . $redirect_path);
        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url=' . $redirect_path . '"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="' . $redirect_path . '">HERE</a> to be redirected</div></body></html>';
        exit;
    }

    // Behave as per HTTP/1.1 spec for others
    header('Location: ' . $redirect_path);
    exit;
} else {
    if ((int)$attach_config['allow_ftp_upload']) {
        // We do not need a download path, we are not downloading physically
        send_file_to_browser($attachment, '');
        exit;
    } else {
        send_file_to_browser($attachment, $upload_dir);
        exit;
    }
}

?>