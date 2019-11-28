<?php
/***************************************************************************
 *                                install.php
 *                            -------------------
 *   begin                : Tuesday, Sept 11, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: install.php 5392 2005-12-29 11:51:13Z acydburn $
 *
 ***************************************************************************/

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Finder;
use Nette\Utils\Validators;
use Tracy\Debugger;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

$sep = DIRECTORY_SEPARATOR;

// ---------
// FUNCTIONS
//
function page_header($text, $form_action = false)
{
	global $lang;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $lang['ENCODING']; ?>">
<meta http-equiv="Content-Style-Type" content="text/css">
<title><?php echo $lang['Welcome_install'];?></title>
<link rel="stylesheet" href="../templates/subSilver/subSilver.css" type="text/css">
<style type="text/css">
<!--
th			{ background-image: url('../templates/subSilver/images/cellpic3.gif') }
td.cat		{ background-image: url('../templates/subSilver/images/cellpic1.gif') }
td.rowpic	{ background-image: url('../templates/subSilver/images/cellpic2.jpg'); background-repeat: repeat-y }
td.catHead,td.catSides,td.catLeft,td.catRight,td.catBottom { background-image: url('../templates/subSilver/images/cellpic1.gif') }

/* Import the fancy styles for IE only (NS4.x doesn't use the @import function) */
@import url("../templates/subSilver/formIE.css"); 
//-->
</style>
</head>
<body bgcolor="#E5E5E5" text="#000000" link="#006699" vlink="#5584AA">

<table width="100%" border="0" cellspacing="0" cellpadding="10" align="center"> 
	<tr>
		<td class="bodyline" width="100%"><table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td><table width="100%" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><img src="../templates/subSilver/images/logo_phpBB.gif" border="0" alt="Forum Home" vspace="1" /></td>
						<td align="center" width="100%" valign="middle"><span class="maintitle"><?php echo $lang['Welcome_install'];?></span></td>
					</tr>
				</table></td>
			</tr>
			<tr>
				<td><br /><br /></td>
			</tr>
			<tr>
				<td colspan="2"><table width="90%" border="0" align="center" cellspacing="0" cellpadding="0">
					<tr>
						<td><span class="gen"><?php echo $text; ?></span></td>
					</tr>
				</table></td>
			</tr>
			<tr>
				<td><br /><br /></td>
			</tr>
			<tr>
				<td width="100%"><table width="100%" cellpadding="2" cellspacing="1" border="0" class="forumline"><form action="<?php echo $form_action ? $form_action : 'install.php?install=1'; ?>" name="install" method="post">
<?php
            CSRF::getInputHtml();
}

function page_footer()
{

?>
				</table></form></td>
			</tr>
		</table></td>
	</tr>
</table>

</body>
</html>
<?php

}

function page_common_form($hidden, $submit)
{

?>
					<tr>
					  <td class="catBottom" align="center" colspan="2"><?php echo $hidden; ?><input class="mainoption" type="submit" name="install" value="<?php echo $submit; ?>" /></td>
					</tr>
<?php

}

function page_upgrade_form()
{
	global $lang;

?>
					<tr>
						<td class="catBottom" align="center" colspan="2"><?php echo $lang['continue_upgrade']; ?></td>
					</tr>
					<tr>
						<td class="catBottom" align="center" colspan="2"><input type="submit" name="upgrade_now" value="<?php echo $lang['upgrade_submit']; ?>" /></td>
					</tr>
<?php 

}

function page_error($error_title, $error)
{

?>
					<tr>
						<th><?php echo $error_title; ?></th>
					</tr>
					<tr>
						<td class="row1" align="center"><span class="gen"><?php echo $error; ?></span></td>
					</tr>
<?php

}

// Guess an initial language ... borrowed from phpBB 2.2 it's not perfect, 
// really it should do a straight match first pass and then try a "fuzzy"
// match on a second pass instead of a straight "fuzzy" match.
function guess_lang()
{
	global $phpbb_root_path, $_SERVER;

	$sep = DIRECTORY_SEPARATOR;

	// The order here _is_ important, at least for major_minor
	// matches. Don't go moving these around without checking with
	// me first - psoTFX
    $match_lang = [
        'arabic'                     => 'ar([_-][a-z]+)?',
        'bulgarian'                  => 'bg',
        'catalan'                    => 'ca',
        'czech'                      => 'cs',
        'danish'                     => 'da',
        'german'                     => 'de([_-][a-z]+)?',
        'english'                    => 'en([_-][a-z]+)?',
        'estonian'                   => 'et',
        'finnish'                    => 'fi',
        'french'                     => 'fr([_-][a-z]+)?',
        'greek'                      => 'el',
        'spanish_argentina'          => 'es[_-]ar',
        'spanish'                    => 'es([_-][a-z]+)?',
        'gaelic'                     => 'gd',
        'galego'                     => 'gl',
        'gujarati'                   => 'gu',
        'hebrew'                     => 'he',
        'hindi'                      => 'hi',
        'croatian'                   => 'hr',
        'hungarian'                  => 'hu',
        'icelandic'                  => 'is',
        'indonesian'                 => 'id([_-][a-z]+)?',
        'italian'                    => 'it([_-][a-z]+)?',
        'japanese'                   => 'ja([_-][a-z]+)?',
        'korean'                     => 'ko([_-][a-z]+)?',
        'latvian'                    => 'lv',
        'lithuanian'                 => 'lt',
        'macedonian'                 => 'mk',
        'dutch'                      => 'nl([_-][a-z]+)?',
        'norwegian'                  => 'no',
        'punjabi'                    => 'pa',
        'polish'                     => 'pl',
        'portuguese_brazil'          => 'pt[_-]br',
        'portuguese'                 => 'pt([_-][a-z]+)?',
        'romanian'                   => 'ro([_-][a-z]+)?',
        'russian'                    => 'ru([_-][a-z]+)?',
        'slovenian'                  => 'sl([_-][a-z]+)?',
        'albanian'                   => 'sq',
        'serbian'                    => 'sr([_-][a-z]+)?',
        'slovak'                     => 'sv([_-][a-z]+)?',
        'swedish'                    => 'sv([_-][a-z]+)?',
        'thai'                       => 'th([_-][a-z]+)?',
        'turkish'                    => 'tr([_-][a-z]+)?',
        'ukranian'                   => 'uk([_-][a-z]+)?',
        'urdu'                       => 'ur',
        'viatnamese'                 => 'vi',
        'chinese_traditional_taiwan' => 'zh[_-]tw',
        'chinese_simplified'         => 'zh',
    ];

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$accept_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

		foreach ($accept_languages as $accept_language) {
			foreach ($match_lang as $lang => $match) {
				if (preg_match('#' . $match . '#i', trim($accept_language))) {
                    if (file_exists(@realpath($phpbb_root_path . 'language' . $sep . 'lang_' . $lang))) {
						return $lang;
					}
				}
			}
		}
	}

	return 'english';
	
}
//
// FUNCTIONS
// ---------

// Begin
error_reporting  (E_ERROR | E_WARNING | E_PARSE); // This will NOT report uninitialized variables

// Begin main prog
define('IN_PHPBB', true);
// Uncomment the following line to completely disable the ftp option...
// define('NO_FTP', true);
$phpbb_root_path = '.' . $sep . '..' . $sep;

// Initialise some basic arrays
$userdata = [];
$lang = [];
$error = false;
$databaseError = false;
$validated = false;

require_once $phpbb_root_path . 'vendor' . $sep . 'autoload.php';

$loader = new Nette\Loaders\RobotLoader;

// Add directories for RobotLoader to index
$loader->addDirectory($phpbb_root_path . $sep . 'includes');

// And set caching to the 'temp' directory
$loader->setTempDirectory($phpbb_root_path . $sep . 'temp');
$loader->register(); // Run the RobotLoader

Debugger::enable();

// Include some required functions
//require_once $phpbb_root_path . 'includes' . $sep . 'constants.php';
define('USER_MIN_PASSWORD_LENGTH', 8);
define('USER_MAX_PASSWORD_LENGTH', 32);

require_once $phpbb_root_path . 'includes' . $sep . 'functions.php';

// Define schema info
$available_dbms_temp = [
    'mysql'      => [
        'LABEL'       => 'MySQL',
        'SCHEMA'      => 'mysql',
        'DELIM'       => ';',
        'DELIM_BASIC' => ';',
        'COMMENTS'    => 'remove_remarks'
    ],
    'postgres'   => [
        'LABEL'       => 'PostgreSQL 7.x',
        'SCHEMA'      => 'postgres',
        'DELIM'       => ';',
        'DELIM_BASIC' => ';',
        'COMMENTS'    => 'remove_comments'
    ],
    'mssql'      => [
        'LABEL'       => 'MS SQL Server 7/2000',
        'SCHEMA'      => 'mssql',
        'DELIM'       => 'GO',
        'DELIM_BASIC' => ';',
        'COMMENTS'    => 'remove_comments'
    ],
    'msaccess'   => [
        'LABEL'       => 'MS Access [ ODBC ]',
        'SCHEMA'      => '',
        'DELIM'       => '',
        'DELIM_BASIC' => ';',
        'COMMENTS'    => ''
    ],
    'mssql-odbc' => [
        'LABEL'       => 'MS SQL Server [ ODBC ]',
        'SCHEMA'      => 'mssql',
        'DELIM'       => 'GO',
        'DELIM_BASIC' => ';',
        'COMMENTS'    => 'remove_comments'
    ]
];

$available_dbms = [];
$available_drivers = PDO::getAvailableDrivers();

foreach ($available_drivers as $driver) {
    if (isset($available_dbms_temp[$driver])) {
        $available_dbms[$driver] = $available_dbms_temp[$driver];
    }
}

// Obtain various vars
$confirm = isset($_POST['confirm']);
$cancel = isset($_POST['cancel']);

if (isset($_POST['install_step']) || isset($_GET['install_step'])) {
    $install_step = isset($_POST['install_step']) ? $_POST['install_step'] : $_GET['install_step'];
} else {
    $install_step = '';
}

$upgrade = !empty($_POST['upgrade']) ? $_POST['upgrade']: '';
$upgrade_now = !empty($_POST['upgrade_now']) ? $_POST['upgrade_now']:'';

$dbms = isset($_POST['dbms']) ? $_POST['dbms'] : '';

$dbhost = !empty($_POST['dbhost']) ? $_POST['dbhost'] : 'localhost';
$dbuser = !empty($_POST['dbuser']) ? $_POST['dbuser'] : '';
$dbpasswd = !empty($_POST['dbpasswd']) ? $_POST['dbpasswd'] : '';
$dbname = !empty($_POST['dbname']) ? $_POST['dbname'] : '';

$table_prefix = !empty($_POST['prefix']) ? $_POST['prefix'] : '';

$admin_name = !empty($_POST['admin_name']) ? $_POST['admin_name'] : '';
$admin_pass1 = !empty($_POST['admin_pass1']) ? $_POST['admin_pass1'] : '';
$admin_pass2 = !empty($_POST['admin_pass2']) ? $_POST['admin_pass2'] : '';

$admin_acp_pass1 = !empty($_POST['admin_acp_pass1']) ? $_POST['admin_acp_pass1'] : '';
$admin_acp_pass2 = !empty($_POST['admin_acp_pass1']) ? $_POST['admin_acp_pass2'] : '';

$ftp_path = !empty($_POST['ftp_path']) ? $_POST['ftp_path'] : '';
$ftp_user = !empty($_POST['ftp_user']) ? $_POST['ftp_user'] : '';
$ftp_pass = !empty($_POST['ftp_pass']) ? $_POST['ftp_pass'] : '';

if (isset($_POST['lang']) && preg_match('#^[a-z_]+$#', $_POST['lang']))
{
	$boardLanguage = strip_tags($_POST['lang']);
}
else
{
    $boardLanguage = guess_lang();
}

$board_email = !empty($_POST['board_email']) ? $_POST['board_email'] : '';
$script_path = !empty($_POST['script_path']) ? $_POST['script_path'] : str_replace('install', '', dirname($_SERVER['PHP_SELF']));

if (!empty($_POST['server_name'])) {
    $server_name = $_POST['server_name'];
} else {
    // Guess at some basic info used for install..
    if (!empty($_SERVER['SERVER_NAME']) || !empty($_ENV['SERVER_NAME'])) {
        $server_name = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME'];
    } elseif (!empty($_SERVER['HTTP_HOST']) || !empty($_ENV['HTTP_HOST'])) {
        $server_name = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST'];
    } else {
        $server_name = '';
    }
}

if (!empty($_POST['server_port'])) {
    $server_port = $_POST['server_port'];
} else {
    if (!empty($_SERVER['SERVER_PORT']) || !empty($_ENV['SERVER_PORT'])) {
        $server_port = !empty($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : $_ENV['SERVER_PORT'];
    } else {
        $server_port = '80';
    }
}

// Open Config.php ... if it exists
if (@file_exists(@realpath('Config.php'))) {
    require_once $phpbb_root_path . 'Config.php';
}

// Is phpBB already installed? Yes? Redirect to the index
if (defined('PHPBB_INSTALLED')) {
    redirect('../index.php');
}

// Import language file, setup template ...
require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $boardLanguage . $sep . 'lang_main.php';
require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $boardLanguage . $sep . 'lang_admin.php';

// Ok for the time being I'm commenting this out whilst I'm working on
// better integration of the install with upgrade as per Bart's request
// JLH
if ($upgrade === 1) {
    // require('upgrade.php');
    $install_step = 1;
}

// What do we need to do?
if (!empty($_POST['send_file']) && $_POST['send_file'] === 1 && empty($_POST['upgrade_now'])) {
    header('Content-Type: text/x-delimtext; name="Config.php"');
    header('Content-disposition: attachment; filename="Config.php"');

    // We need to stripslashes no matter what the setting of magic_quotes_gpc is
    // because we add slashes at the top if its off, and they are added automaticlly
    // if it is on.
    echo stripslashes($_POST['config_data']);

    exit;
} elseif (!empty($_POST['send_file']) && $_POST['send_file'] == 2) {
    $s_hidden_fields = '<input type="hidden" name="config_data" value="' . htmlspecialchars(stripslashes($_POST['config_data'])) . '" />';
    $s_hidden_fields .= '<input type="hidden" name="ftp_file" value="1" />';

    if ($upgrade == 1) {
        $s_hidden_fields .= '<input type="hidden" name="upgrade" value="1" />';
    }

	page_header($lang['ftp_instructs']);

?>
					<tr>
						<th colspan="2"><?php echo $lang['ftp_info']; ?></th>
					</tr>
					<tr>
						<td class="row1" align="right"><span class="gen"><?php echo $lang['ftp_path']; ?></span></td>
						<td class="row2"><input type="text" name="ftp_dir"></td>
					</tr>
					<tr>
						<td class="row1" align="right"><span class="gen"><?php echo $lang['ftp_username']; ?></span></td>
						<td class="row2"><input type="text" name="ftp_user"></td>
					</tr>
					<tr>
						<td class="row1" align="right"><span class="gen"><?php echo $lang['ftp_password']; ?></span></td>
						<td class="row2"><input type="password" name="ftp_pass"></td>
					</tr>
<?php

	page_common_form($s_hidden_fields, $lang['Transfer_config']);
	page_footer();
	exit;

} elseif (!empty($_POST['ftp_file'])) {
	// Try to connect ...
	$conn_id = @ftp_connect('localhost');
	$login_result = @ftp_login($conn_id, (string)$ftp_user, (string)$ftp_pass);

	if (!$conn_id || !$login_result) {
		page_header($lang['NoFTP_config']);

		// Error couldn't get connected... Go back to option to send file...
		$s_hidden_fields = '<input type="hidden" name="config_data" value="' . htmlspecialchars(stripslashes($_POST['config_data'])) . '" />';
		$s_hidden_fields .= '<input type="hidden" name="send_file" value="1" />';

		// If we're upgrading ...
        if ($upgrade === 1) {
            $s_hidden_fields .= '<input type="hidden" name="upgrade" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="dbms" value="' . $dbms . '" />';
            $s_hidden_fields .= '<input type="hidden" name="prefix" value="' . $table_prefix . '" />';
            $s_hidden_fields .= '<input type="hidden" name="dbhost" value="' . $dbhost . '" />';
            $s_hidden_fields .= '<input type="hidden" name="dbname" value="' . $dbname . '" />';
            $s_hidden_fields .= '<input type="hidden" name="dbuser" value="' . $dbuser . '" />';
            $s_hidden_fields .= '<input type="hidden" name="dbpasswd" value="' . $dbpasswd . '" />';
            $s_hidden_fields .= '<input type="hidden" name="install_step" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="admin_pass1" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="admin_pass2" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="admin_acp_pass1" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="admin_acpt_pass2" value="1" />';
            $s_hidden_fields .= '<input type="hidden" name="server_port" value="' . $server_port . '" />';
            $s_hidden_fields .= '<input type="hidden" name="server_name" value="' . $server_name . '" />';
            $s_hidden_fields .= '<input type="hidden" name="script_path" value="' . $script_path . '" />';
            $s_hidden_fields .= '<input type="hidden" name="board_email" value="' . $board_email . '" />';

            page_upgrade_form();
        } else {
            page_common_form($s_hidden_fields, $lang['Download_config']);
        }

		page_footer();
		exit;
	} else {
		// Write out a temp file...
		$tmpfname = @tempnam('/tmp', 'cfg');

		@unlink($tmpfname); // unlink for safety on php4.0.3+

		$fp = @fopen($tmpfname, 'wb');

		@fwrite($fp, stripslashes($_POST['config_data']));

		@fclose($fp);

		// Now ftp it across.
		@ftp_chdir($conn_id, $ftp_dir);

		$res = ftp_put($conn_id, 'Config.php', $tmpfname, FTP_ASCII);

		@ftp_close($conn_id);

		unlink($tmpfname);

		if ($upgrade == 1) {
            require_once 'upgrade.php';
			exit;
		}

		// Ok we are basically done with the install process let's go on 
		// and let the user configure their board now. We are going to do 
		// this by calling the admin_board.php from the normal board admin
		// section.
		$s_hidden_fields = '<input type="hidden" name="username" value="' . $admin_name . '" />';
		$s_hidden_fields .= '<input type="hidden" name="password" value="' . $admin_pass1 . '" />';
		$s_hidden_fields .= '<input type="hidden" name="acp_password" value="' . $admin_acp_pass1 . '" />';
		$s_hidden_fields .= '<input type="hidden" name="redirect" value="../admin/index.php" />';
		$s_hidden_fields .= '<input type="hidden" name="submit" value="' . $lang['Login'] . '" />';

		page_header($lang['Inst_Step_2']);
		page_common_form($s_hidden_fields, $lang['Finish_Install']);
		page_footer();
		exit();
	}
} elseif ($validated === false) {
    // Ok we haven't installed before so lets work our way through the various
    // steps of the install process.  This could turn out to be quite a lengty
    // process.

    // Step 0 gather the pertinant info for database setup...
    // Namely dbms, dbhost, dbname, dbuser, and dbpasswd.
    $instruction_text = $lang['Inst_Step_0'];

    //if (empty($install_step)) {
    if (empty($_POST['dbhost'])) {
        $databaseError[] = $lang['Empty_db_host'];
    }

    if (empty($_POST['dbname'])) {
        $databaseError[] = $lang['Empty_db_name'];
    }

    if (empty($_POST['dbuser'])) {
        $databaseError[] = $lang['Empty_db_user_name'];
    }

    if (empty($_POST['admin_name'])) {
        $error[] = $lang['Empty_user_name'];
    }

    if (empty($_POST['board_email'])) {
        $error[] = $lang['Empty_user_email'];
    } else {
        if (!Validators::isEmail($_POST['board_email'])) {
            $error[] = $lang['User_email_invalid'];
        }
    }

    if (empty($_POST['admin_pass1'])) {
        $error[] = $lang['Empty_user_password'];
    }

    if (empty($_POST['admin_pass2'])) {
        $error[] = $lang['Empty_confirm_user_password'];
    }

    if ($_POST['admin_pass1'] === $_POST['admin_pass2']) {
        if (mb_strlen($_POST['admin_pass1']) < USER_MIN_PASSWORD_LENGTH) {
            $error[] = $lang['Password_short'];
        }
    } else {
        $error[] = $lang['Password_mismatch'];
    }

    if (empty($_POST['admin_acp_pass1'])) {
        $error[] = $lang['ACP_password_empty'];
    }

    if (empty($_POST['admin_acp_pass2'])) {
        $error[] = $lang['ACP_confirm_password_empty'];
    }

    if ($_POST['admin_acp_pass1'] === $_POST['admin_acp_pass2']) {
        if (mb_strlen($_POST['admin_acp_pass1']) < USER_MIN_PASSWORD_LENGTH) {
            $error[] = $lang['Password_short'];
        }
    } else {
        $error[] = $lang['Password_mismatch'];
    }

    if ($_POST['admin_pass1'] === $_POST['admin_acp_pass1']) {
        $error[] = $lang['ACP_Password_match_pw'];
    }

    if ($error === false && $databaseError === false) {
        $validated = true;
    }

    $lang_options = [];
    $languages = Finder::findDirectories('lang_*')->in($phpbb_root_path . 'language');

    /**
     * @var SplFileInfo $language
     */
    foreach ($languages as $language) {
        $filename = trim(str_replace('lang_', '', $language->getFilename()));

        $displayName = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
        $displayName = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayName);

        $lang_options[$displayName] = $filename;
    }

    @asort($lang_options);

    $lang_select = '<select name="lang" onchange="this.form.submit()">';

    foreach ($lang_options as $displayname => $filename) {
        $selected = ($boardLanguage === $filename) ? ' selected="selected"' : '';
        $lang_select .= '<option value="' . $filename . '"' . $selected . '>' . ucwords($displayname) . '</option>';
    }

    $lang_select .= '</select>';

    $dbms_select = '<select name="dbms" onchange="if (this.form.upgrade.options[this.form.upgrade.selectedIndex].value == 1){ this.selectedIndex = 0;}">';

    foreach ($available_dbms as $dbms_name => $details) {
        $selected = ($dbms_name === $dbms) ? 'selected="selected"' : '';
        $dbms_select .= '<option value="' . $dbms_name . '">' . htmlspecialchars($details['LABEL'], ENT_QUOTES) . '</option>';
    }

    $dbms_select .= '</select>';

    $upgrade_option = '<select name="upgrade"';
    $upgrade_option .= 'onchange="if (this.options[this.selectedIndex].value == 1) { this.form.dbms.selectedIndex = 0; }">';
    $upgrade_option .= '<option value="0">' . $lang['Install'] . '</option>';
    $upgrade_option .= '<option value="1">' . $lang['Upgrade'] . '</option></select>';

    $s_hidden_fields = '<input type="hidden" name="install_step" value="1" /><input type="hidden" name="cur_lang" value="' . $boardLanguage . '" />';

    page_header($instruction_text);

    ?>
    <tr>
        <th colspan="2"><?php echo $lang['Initial_config']; ?></th>
    </tr>
    <tr>
        <td class="row1" align="right" width="30%"><span class="gen"><?php echo $lang['Default_lang']; ?>: </span></td>
        <td class="row2"><?php echo $lang_select; ?></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['dbms']; ?>: </span></td>
        <td class="row2"><?php echo $dbms_select; ?></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Install_Method']; ?>:</span></td>
        <td class="row2"><?php echo $upgrade_option; ?></td>
    </tr>
    <tr>
        <th colspan="2"><?php echo $lang['DB_config']; ?></th>
    </tr>

    <?php
    if ($databaseError) {
        ?>
        <tr>
            <td class="row1" colspan="2">
                            <span class="gen" style="color:red">
                                <ul>
<?php

foreach ($databaseError as $value) {
    echo '<li>' . $value . '</li>';
}
?>
                                </ul>
                            </span>
            </td>
        </tr>
        <?php
    }
    ?>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['DB_Host']; ?>: </span></td>
        <td class="row2"><input type="text" name="dbhost" value="<?php echo $dbhost; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['DB_Name']; ?>: </span></td>
        <td class="row2"><input type="text" name="dbname" value="<?php echo $dbname; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['DB_Username']; ?>: </span></td>
        <td class="row2"><input type="text" name="dbuser" value="<?php echo $dbuser; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['DB_Password']; ?>: </span></td>
        <td class="row2"><input type="password" name="dbpasswd" autocomplete="off"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Table_Prefix']; ?>: </span></td>
        <td class="row2"><input type="text" name="prefix"
                                value="<?php echo !empty($table_prefix) ? $table_prefix : 'phpbb_'; ?>"/></td>
    </tr>
    <tr>
        <th colspan="2"><?php echo $lang['Admin_config']; ?></th>
    </tr>
    <?php

    if ($error) {
        ?>
        <tr>
            <td class="row1" colspan="2"><span class="gen" style="color:red">

                                <ul>
<?php

foreach ($error as $value) {
    echo '<li>' . $value . '</li>';
}
?>
                            </ul>


                            </span></td>
        </tr>
        <?php

    }
    ?>

    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Admin_Username']; ?>: </span></td>
        <td class="row2"><input type="text" name="admin_name" value="<?php echo $admin_name; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Admin_email']; ?>: </span></td>
        <td class="row2"><input type="text" name="board_email" value="<?php echo $board_email; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Admin_Password']; ?>: </span></td>
        <td class="row2"><input type="password" name="admin_pass1" autocomplete="off"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Admin_Password_confirm']; ?>: </span></td>
        <td class="row2"><input type="password" name="admin_pass2" autocomplete="off"/></td>
    </tr>

    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['ACP_password']; ?>: </span></td>
        <td class="row2"><input type="password" name="admin_acp_pass1" autocomplete="off"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['ACP_password_confirm']; ?>: </span></td>
        <td class="row2"><input type="password" name="admin_acp_pass2" autocomplete="off"/></td>
    </tr>

    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Server_name']; ?>: </span></td>
        <td class="row2"><input type="text" name="server_name" value="<?php echo $server_name; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Server_port']; ?>: </span></td>
        <td class="row2"><input type="text" name="server_port" value="<?php echo $server_port; ?>"/></td>
    </tr>
    <tr>
        <td class="row1" align="right"><span class="gen"><?php echo $lang['Script_path']; ?>: </span></td>
        <td class="row2"><input type="text" name="script_path" value="<?php echo $script_path; ?>"/></td>
    </tr>
    <?php

    page_common_form($s_hidden_fields, $lang['Start_Install']);
    page_footer();
}


if ($_GET['install'] == 1 && $validated) {
//} else {

	// Go ahead and create the DB, then populate it
	//
	// MS Access is slightly different in that a pre-built, pre-
	// populated DB is supplied, all we need do here is update
	// the relevant entries

    $connection = dibi::connect([
        'driver'   => 'PDO',
        'username' => $dbuser,
        'password' => $dbpasswd,
        'dsn'      => $dbms.':host='.$dbhost.';dbname='.$dbname.';charset=utf8'
    ]);

    $connection->connect();

    $dbms_schema = 'schemas' . $sep . $available_dbms[$dbms]['SCHEMA'] . '_schema.sql';
    $dbms_basic = 'schemas' . $sep . $available_dbms[$dbms]['SCHEMA'] . '_basic.sql';

	$remove_remarks = $available_dbms[$dbms]['COMMENTS'];
	$delimiter = $available_dbms[$dbms]['DELIM']; 
	$delimiter_basic = $available_dbms[$dbms]['DELIM_BASIC'];

	if ($install_step == 1) {
		if ($upgrade != 1) {
			if ($dbms != 'msaccess') {
				// Load in the sql parser
                require_once $phpbb_root_path . 'includes' . $sep . 'sql_parse.php';

				// Ok we have the db info go ahead and read in the relevant schema
				// and work on building the table.. probably ought to provide some
				// kind of feedback to the user as we are working here in order
				// to let them know we are actually doing something.
				$sql_queries = @fread(@fopen($dbms_schema, 'rb'), @filesize($dbms_schema));
                $sql_queries = preg_replace('/phpbb_/', $table_prefix, $sql_queries);

                $sql_queries = $remove_remarks($sql_queries);
                $sql_queries = split_sql_file($sql_queries, $delimiter);

				foreach ($sql_queries as $sql_query) {
					if (trim($sql_query) !== '') {
					    $result = dibi::query($sql_query);
					}
				}

				// Ok tables have been built, let's fill in the basic information
                $lines = file($dbms_basic);

                $templine = '';

                foreach ($lines as $line) {
                    if (substr($line, 0, 2) === '--' || $line === '') {
                        continue;
                    }

                    $templine .= $line;

                    if (substr(trim($line), -1, 1) === ';') {
                        // Perform the query
                        dibi::query($templine);
                        // Reset temp variable to empty
                        $templine = '';
                    }
                }
			}

			// Ok at this point they have entered their admin password, let's go
			// ahead and create the admin account with some basic default information
			// that they can customize later, and write out the config file.  After
			// this we are going to pass them over to the admin_forum.php script
			// to set up their forum defaults.
			$error = '';

			// Update the default admin user with their information.
            dibi::insert($table_prefix.'config', ['config_name' => 'board_startdate', 'config_value' => time()])->execute();
            dibi::insert($table_prefix.'config', ['config_name' => 'default_lang', 'config_value' => $boardLanguage])->execute();

            $update_config = [
                'board_email' => $board_email,
                'script_path' => $script_path,
                'server_port' => $server_port,
                'server_name' => $server_name,
            ];

            foreach ($update_config as $config_name => $config_value) {
                dibi::update($table_prefix . 'config' , ['config_value' => $config_value])
                    ->where('config_name = %s',  $config_name)
                    ->execute();
			}

			$admin_pass_bcrypt = ($confirm && $userdata['user_level'] === ADMIN) ? $admin_pass1 : password_hash($admin_pass1, PASSWORD_BCRYPT);

            $update_data = [
                'username'      => $admin_name,
                'user_password' => $admin_pass_bcrypt,
                'user_acp_password' => $admin_pass_bcrypt,
                'user_lang'     => $boardLanguage,
                'user_email'    => $board_email,
            ];

            dibi::update($table_prefix . 'users', $update_data)
                ->where('username = %s', 'Admin')
                ->execute();

            dibi::update($table_prefix . 'users', ['user_regdate' => time()])
            ->execute();

            $lang_options = [];
            $languages = Finder::findDirectories('lang_*')->in($phpbb_root_path . 'language');

            /**
             * @var SplFileInfo $language
             */
            foreach ($languages as $language) {
                $filename = trim(str_replace('lang_', '', $language->getFilename()));

                $displayName = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
                $displayName = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayName);

                $lang_options[$displayName] = $filename;
            }

            @asort($lang_options);

            foreach ($lang_options as $key => $value) {
                $exists = dibi::select('1')
                    ->from($table_prefix . 'languages')
                    ->where('[lang_name] = %s', $key)
                    ->fetchSingle();

                if (!$exists) {
                    dibi::insert($table_prefix . 'languages', ['lang_name' => $key])->execute();
                }
            }

            $dbLanguages = dibi::select('*')
                ->from($table_prefix . 'languages')
                ->fetchPairs('lang_id', 'lang_name');

            $fileLanguages = array_keys($lang_options);

            // remove languages which are not present in language folder
            foreach ($dbLanguages as $dbLanguage) {
                if (!in_array($dbLanguage, $fileLanguages, true)) {
                    dibi::delete($table_prefix . 'languages')
                        ->where('[lang_name] = %s', $dbLanguage)
                        ->execute();
                }
            }

			if ($error !== '') {
				page_header($lang['Install'], '');
				page_error($lang['Installer_Error'], $lang['Install_db_error'] . '<br /><br />' . $error);
				page_footer();
				exit;
			}
		}

		if (!$upgrade_now) {
		    $dns = $dbms . ':host=' . $dbhost .';dbname=' . $dbname . ';charset=utf8';

		    $phpFile = new PhpFile();

		    $configClass = new ClassType('Config');
		    $configClass->setFinal();
		    $configClass->addComment('Config class of phpBB2');
		    $configClass->addConstant('TABLE_PREFIX', $table_prefix);
		    $configClass->addConstant('DBMS', $dbms);
		    $configClass->addConstant('DATABASE_HOST', $dbhost);
		    $configClass->addConstant('DATABASE_USER', $dbuser);
		    $configClass->addConstant('DATABASE_PASSWORD', $dbpasswd);
		    $configClass->addConstant('DATABASE_NAME', $dbname);
		    $configClass->addConstant('DATABASE_DNS', $dns);
		    $configClass->addConstant('DATABASE_DRIVER', 'PDO');
		    $configClass->addConstant('DATABASE_LAZY', true);
		    $configClass->addConstant('INSTALLED', true);

			@umask(0111);
            $no_open = false;

			// Unable to open the file writeable do something here as an attempt
			// to get around that...
			if (!($fp = @fopen($phpbb_root_path . 'Config.php', 'wb'))) {
				$s_hidden_fields = '<input type="hidden" name="config_data" value="' . htmlspecialchars($phpFile . $configClass) . '" />';

				if (@extension_loaded('ftp') && !defined('NO_FTP')) {
					page_header($lang['Unwriteable_config'] . '<p>' . $lang['ftp_option'] . '</p>');

?>
					<tr>
						<th colspan="2"><?php echo $lang['ftp_choose']; ?></th>
					</tr>
					<tr>
						<td class="row1" align="right" width="50%"><span class="gen"><?php echo $lang['Attempt_ftp']; ?></span></td>
						<td class="row2"><input type="radio" name="send_file" value="2"></td>
					</tr>
					<tr>
						<td class="row1" align="right" width="50%"><span class="gen"><?php echo $lang['Send_file']; ?></span></td>
						<td class="row2"><input type="radio" name="send_file" value="1"></td>
					</tr>
<?php

				} else {
					page_header($lang['Unwriteable_config']);
					$s_hidden_fields .= '<input type="hidden" name="send_file" value="1" />';
				}

				if ($upgrade === 1) {
                    $s_hidden_fields .= '<input type="hidden" name="upgrade" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="dbms" value="' . $dbms . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="prefix" value="' . $table_prefix . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="dbhost" value="' . $dbhost . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="dbname" value="' . $dbname . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="dbuser" value="' . $dbuser . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="dbpasswd" value="' . $dbpasswd . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="install_step" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="admin_pass1" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="admin_pass2" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="admin_acp_pass1" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="admin_acp_pass2" value="1" />';
                    $s_hidden_fields .= '<input type="hidden" name="server_port" value="' . $server_port . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="server_name" value="' . $server_name . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="script_path" value="' . $script_path . '" />';
                    $s_hidden_fields .= '<input type="hidden" name="board_email" value="' . $board_email . '" />';

					page_upgrade_form();
				} else {
					page_common_form($s_hidden_fields, $lang['Download_config']);
				}

				page_footer();
				exit;
			}

			$result = @fwrite($fp, $phpFile . $configClass, mb_strlen($phpFile . $configClass));

			@fclose($fp);
			$upgrade_now = $lang['upgrade_submit'];
		}

		// First off let's check and see if we are supposed to be doing an upgrade.
		if ($upgrade === 1 && $upgrade_now === $lang['upgrade_submit']) {
			define('INSTALLING', true);

            require_once 'upgrade.php';
			exit;
		}

		// Ok we are basically done with the install process let's go on
		// and let the user configure their board now. We are going to do
		// this by calling the admin_board.php from the normal board admin
		// section.
		$s_hidden_fields = '<input type="hidden" name="username" value="' . $admin_name . '" />';
		$s_hidden_fields .= '<input type="hidden" name="password" value="' . $admin_pass1 . '" />';
		$s_hidden_fields .= '<input type="hidden" name="acp_password" value="' . $admin_acp_pass1 . '" />';
		$s_hidden_fields .= '<input type="hidden" name="redirect" value="admin/index.php" />';
		$s_hidden_fields .= '<input type="hidden" name="login" value="true" />';
		$s_hidden_fields .= CSRF::getInputHtml();

		page_header($lang['Inst_Step_2'], '../login.php');
		page_common_form($s_hidden_fields, $lang['Finish_Install']);
		page_footer();
		exit;
	}
}

?>
