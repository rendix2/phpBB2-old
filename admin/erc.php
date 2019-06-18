<?php
/***************************************************************************
 *                                  erc.php
 *                            -------------------
 *   begin                : Fri Feb 07, 2003
 *   copyright            : (C) 2004 Philipp Kordowich
 *                          Parts: (C) 2002 The phpBB Group
 *
 *   part of DB Maintenance Mod 1.3.8
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
$phpbb_root_path = './../';
include$phpbb_root_path . 'common.php';
include$phpbb_root_path . 'includes/functions_dbmtnc.php';

//
// addslashes to vars if magic_quotes_gpc is off
// this is a security precaution to prevent someone
// trying to break out of a SQL statement.
//
$mode = isset($_POST['mode']) ? htmlspecialchars($_POST['mode']) : (isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'start');
$option = isset($_POST['option']) ? htmlspecialchars($_POST['option']) : '';

// Before doing anything else send config.php if requested
if ( $mode === 'download' ) {
	// Get and convert Variables
	$new_dbms         = isset($_GET['ndbms']) ? $_GET['ndbms'] : '';
	$new_dbhost       = isset($_GET['ndbh'])  ? $_GET['ndbh']  : '';
	$new_dbname       = isset($_GET['ndbn'])  ? $_GET['ndbn']  : '';
	$new_dbuser       = isset($_GET['ndbu'])  ? $_GET['ndbu']  : '';
	$new_dbpasswd     = isset($_GET['ndbp'])  ? $_GET['ndbp']  : '';
	$new_table_prefix = isset($_GET['ntp'])   ? $_GET['ntp']   : '';

	$var_array = ['new_dbms', 'new_dbhost', 'new_dbname', 'new_dbuser', 'new_dbpasswd', 'new_table_prefix'];

	foreach ($var_array as $var) {
		$$var = stripslashes($$var);
		$$var = str_replace("'", "\\'", str_replace("\\", "\\\\", $$var));
	}

	// Create the config.php
	$data = "<?php\n" . 
		"\n" .
		"//\n" .
		"// phpBB 2.x auto-generated config file\n" .
		"// Do not change anything in this file!\n" .
		"//\n" .
		"\n" .
		"\$dbms = '$new_dbms';\n" .
		"\n" .
		"\$dbhost = '$new_dbhost';\n" .
		"\$dbname = '$new_dbname';\n" .
		"\$dbuser = '$new_dbuser';\n" .
		"\$dbpasswd = '$new_dbpasswd';\n" .
		"\n" .
		"\$table_prefix = '$new_table_prefix';\n" .
		"\n" .
		"define('PHPBB_INSTALLED', true);\n" .
		"\n" .
		'?>';
	header('Content-type: text/plain');
	header('Content-Disposition: attachment; filename=config.php');
	echo $data;
	exit;
}

// Load a language if one was selected
if ( isset($_POST['lg']) || isset($_GET['lg']) ) {
	$lg = isset($_POST['lg']) ? htmlspecialchars($_POST['lg']) : htmlspecialchars($_GET['lg']);
	if ( file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $lg . '/lang_dbmtnc.php')) ) {
		include$phpbb_root_path . 'language/lang_' . $lg . '/lang_dbmtnc.php';
		include$phpbb_root_path . 'language/lang_' . $lg . '/lang_main.php';
	} else {
		$lg = '';
	}
} else {
	$lg = '';
}

// If no language was selected, check for available languages
if ($lg === '') {
	$dirname = 'language';
	$dir = opendir($phpbb_root_path . $dirname);
	$lang_list = Array();

	while ( $file = readdir($dir) )	{
		if (preg_match('#^lang_#i', $file) && !is_file(@phpbb_realpath($phpbb_root_path . $dirname . '/' . $file)) && !is_link(@phpbb_realpath($phpbb_root_path . $dirname . '/' . $file)) && is_file(@phpbb_realpath($phpbb_root_path . $dirname . '/' . $file . '/lang_dbmtnc.php'))){
			$filename = trim(str_replace('lang_', '', $file));
			$lang_list[] = $filename;
		}
	}
	closedir($dir);
	if (count($lang_list) === 1) {
		$lg = $lang_list[0];
		include$phpbb_root_path . 'language/lang_' . $lg . '/lang_dbmtnc.php';
		include$phpbb_root_path . 'language/lang_' . $lg . '/lang_main.php';
	} else { // Try to load english language
		if ( file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_english/lang_dbmtnc.php')) ) {
			include$phpbb_root_path . 'language/lang_english/lang_dbmtnc.php';
			include$phpbb_root_path . 'language/lang_english/lang_main.php';
			$mode = 'select_lang';
		} else {
			$lang['Forum_Home'] = 'Forum Home';
			$lang['ERC'] = 'Emergency Recovery Console';
			$lang['Submit_text'] = 'Send';
			$lang['Select_Language'] = 'Select a language';
			$lang['no_selectable_language'] = 'No selectable language exist';
			$mode = 'select_lang';
		}
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html;">
<meta http-equiv="Content-Style-Type" content="text/css">
<title><?php echo $lang['ERC']; ?></title>
<style type="text/css">
<!--

font,th,td,p,body { font-family: "Trebuchet MS",Verdana, Arial, Helvetica, sans-serif; font-size: 10pt }
th,td { vertical-align: top }
a:link,a:active,a:visited { color : #006699; }
a:hover		{ text-decoration: underline; color : #DD6900;}

hr	{ height: 0px; border: solid #D1D7DC 0px; border-top-width: 1px;}

.maintitle,h1,h2	{font-weight: bold; font-size: 22px; font-family: "Trebuchet MS",Verdana, Arial, Helvetica, sans-serif; text-decoration: none; line-height : 120%; color : #000000;}

/* Import the fancy styles for IE only (NS4.x doesn't use the @import function) */
@import url("../templates/subSilver/formIE.css");
-->
</style>
</head>
<body bgcolor="#FFFFFF" text="#000000" link="#006699" vlink="#5584AA">

<table width="100%" border="0" cellspacing="0" cellpadding="10" align="center">
	<tr>
		<td><table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td><img src="../templates/subSilver/images/logo_phpBB.gif" border="0" alt="<?php echo $lang['Forum_Home']; ?>" vspace="1" /></td>
				<td align="center" width="100%" valign="middle"><span class="maintitle"><?php echo $lang['ERC']; ?></span><br />
					<?php echo ($option === '') ? '' : $lang[$option] ?></td>
			</tr>
		</table></td>
	</tr>
</table>

<br clear="all" />

<?php
switch($mode)
{
	case 'select_lang':
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<table border="0" cellspacing="0" cellpadding="10">
	<tr>
		<td><table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td><b><?php echo $lang['Select_Language']; ?>:</b></td>
				<td width="10">&nbsp;</td>
				<td><?php echo language_select('english', 'lg', 'dbmtnc'); ?>&nbsp;<input type="submit" value="<?php echo $lang['Submit_text']; ?>" /></td>
			</tr>
		</table></td>
	</tr>
</table>
</form>
<?php
		break;
	case 'start':
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<table border="0" cellspacing="0" cellpadding="10">
	<tr>
		<td><table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td nowrap="nowrap"><b><?php echo $lang['Select_Option']; ?>:</b></td>
				<td width="10">&nbsp;</td>
				<td><input type="hidden" name="lg" value="<?php echo $lg ?>" />
					<input type="hidden" name="mode" value="datainput" />
					<select size="1" name="option">
					<option value="cls"><?php echo $lang['cls']; ?></option>
<?php
	if ( check_mysql_version() ) {
?>
					<option value="rdb"><?php echo $lang['rdb']; ?></option>
<?php
	}
?>
					<option value="cct"><?php echo $lang['cct']; ?></option>
					<option value="rpd"><?php echo $lang['rpd']; ?></option>
					<option value="rcd"><?php echo $lang['rcd']; ?></option>
					<option value="rld"><?php echo $lang['rld']; ?></option>
					<option value="rtd"><?php echo $lang['rtd']; ?></option>
					<option value="dgc"><?php echo $lang['dgc']; ?></option>
					<option value="cbl"><?php echo $lang['cbl']; ?></option>
					<option value="raa"><?php echo $lang['raa']; ?></option>
					<option value="mua"><?php echo $lang['mua']; ?></option>
					<option value="rcp"><?php echo $lang['rcp']; ?></option>
				</select>&nbsp;<input type="submit" value="<?php echo $lang['Submit_text']; ?>" /></td>
			</tr>
			<tr>
				<td colspan="3">&nbsp;</td>
			</tr>
			<tr>
				<td nowrap="nowrap"><b><?php echo $lang['Option_Help']; ?>:</td>
				<td>&nbsp;</td>
				<td><?php echo $lang['Option_Help_Text']; ?></td>
			</tr>
		</table></td>
	</tr>
</table>
</form>
<?php
		break;
	case 'datainput':
		if ($option !== 'rcp') {
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<table border="0" cellspacing="0" cellpadding="10">
<?php
			if ($option !== 'rld' && $option !== 'rtd') {
?>
	<tr>
		<td><b><?php echo $lang['Authenticate_methods']; ?>:</b></td>
	</tr>
	<tr>
		<td><?php echo $lang['Authenticate_methods_help_text']; ?></td>
	</tr>
<?php
			} else {
?>
	<tr>
		<td><b><?php echo $lang['Authenticate_user_only']; ?>:</b></td>
	</tr>
	<tr>
		<td><?php echo $lang['Authenticate_user_only_help_text']; ?></td>
	</tr>
<?php
			}
?>
	<tr>
		<td>
			<table border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td><b><?php echo $lang['Admin_Account']; ?></b></td>
<?php
			if ($option !== 'rld' && $option !== 'rtd') {
?>
					<td width="20">&nbsp;</td>
					<td><b><?php echo $lang['Database_Login']; ?></b></td>
<?php
			}
?>
				</tr>
				<tr>
					<td>
						<table border="0" cellspacing="0" cellpadding="2">
							<tr>
								<td><input type="radio" name="auth_method" value="board" checked="checked" /></td>
								<td><?php echo $lang['Username']; ?>:</td>
								<td><input type="text" name="board_user" size="30" maxlength="25" /></td>
							</tr>
							<tr>
								<td>&nbsp;</td>
								<td><?php echo $lang['Password']; ?>:</td>
								<td><input type="password" name="board_password" size="30" maxlength="100" /></td>
							</tr>
						</table>
					</td>
<?php
			if ($option !== 'rld' && $option !== 'rtd') {
?>
					<td>&nbsp;</td>
					<td>
						<table border="0" cellspacing="0" cellpadding="2">
							<tr>
								<td><input type="radio" name="auth_method" value="db" /></td>
								<td><?php echo $lang['Username']; ?>:</td>
								<td><input type="text" name="db_user" size="30" /></td>
							</tr>
							<tr>
								<td>&nbsp;</td>
								<td><?php echo $lang['Password']; ?>:</td>
								<td><input type="password" name="db_password" size="30" /></td>
							</tr>
						</table>
					</td>
<?php
			}
?>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
	</tr>
<?php
		} else {
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<table border="0" cellspacing="0" cellpadding="10">
<?php
		}

		switch ($option) {
			case 'cls': // Clear Sessions
?>
	<tr>
		<td><?php echo $lang['cls_info']; ?></td>
	</tr>
<?php
				break;
			case 'rdb': // Repair Database
?>
	<tr>
		<td><?php echo $lang['rdb_info']; ?></td>
	</tr>
<?php
				break;
			case 'cct': // Check config table
?>
	<tr>
		<td><?php echo $lang['cct_info']; ?></td>
	</tr>
<?php
				break;
			case 'rpd': // Reset path data
				// Get path information
				$secure_cur = get_config_data('cookie_secure');

				if (!empty($_SERVER['SERVER_PROTOCOL']) || !empty($_ENV['SERVER_PROTOCOL'])) {
					$protocol = !empty($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : $_ENV['SERVER_PROTOCOL'];
					$secure_rec = ( strtolower(substr($protocol, 0 , 5)) == 'https' ) ? '1' : '0';
				} else {
					$secure_rec = '0';
				}

				$domain_cur = get_config_data('server_name');

				if (!empty($_SERVER['SERVER_NAME']) || !empty($_ENV['SERVER_NAME'])) {
					$domain_rec = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME'];
				} else if (!empty($_SERVER['HTTP_HOST']) || !empty($_ENV['HTTP_HOST'])) {
					$domain_rec = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST'];
				} else {
					$domain_rec = '';
				}

				$port_cur = get_config_data('server_port');

				if (!empty($_SERVER['SERVER_PORT']) || !empty($_ENV['SERVER_PORT'])) {
					$port_rec = !empty($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : $_ENV['SERVER_PORT'];
				} else {
					$port_rec = '80';
				}

				$path_cur = get_config_data('script_path');
				$path_rec = str_replace('admin', '', dirname($_SERVER['PHP_SELF']));
?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
				<tr>
					<td>&nbsp;</td>
					<td colspan="2"><b><?php echo $lang['cur_setting']; ?></b></td>
					<td width="10">&nbsp;</td>
					<td colspan="2"><b><?php echo $lang['rec_setting']; ?></b></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['secure']; ?></b></td>
					<td><input type="radio" name="secure_select" value="0"<?php echo ( $secure_cur === $secure_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><?php echo $lang[($secure_cur == '1') ? 'secure_yes' : 'secure_no' ]; ?></td>
					<td>&nbsp;</td>
					<td><input type="radio" name="secure_select" value="1"<?php echo ( $secure_cur !== $secure_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><input type="radio" name="secure" value="1"<?php echo ( $secure_rec == '1' ) ? ' checked="checked"' : '' ?> /><?php echo $lang['secure_yes']; ?><input type="radio" name="secure" value="0"<?php echo ( $secure_rec == '0' ) ? ' checked="checked"' : '' ?> /><?php echo $lang['secure_no']; ?></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['domain']; ?></b></td>
					<td><input type="radio" name="domain_select" value="0"<?php echo ( $domain_cur === $domain_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><?php echo htmlspecialchars($domain_cur); ?></td>
					<td>&nbsp;</td>
					<td><input type="radio" name="domain_select" value="1"<?php echo ( $domain_cur !== $domain_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><input type="input" name="domain" value="<?php echo htmlspecialchars($domain_rec); ?>" maxlength="255" size="40" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['port']; ?></b></td>
					<td><input type="radio" name="port_select" value="0"<?php echo ( $port_cur === $port_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><?php echo htmlspecialchars($port_cur); ?></td>
					<td>&nbsp;</td>
					<td><input type="radio" name="port_select" value="1"<?php echo ( $port_cur !== $port_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><input type="input" name="port" value="<?php echo htmlspecialchars($port_rec); ?>" maxlength="5" size="5" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['path']; ?></b></td>
					<td><input type="radio" name="path_select" value="0"<?php echo ( $path_cur === $path_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><?php echo htmlspecialchars($path_cur); ?></td>
					<td>&nbsp;</td>
					<td><input type="radio" name="path_select" value="1"<?php echo ( $path_cur !== $path_rec ) ? ' checked="checked"' : '' ?> /></td>
					<td><input type="input" name="path" value="<?php echo htmlspecialchars($path_rec); ?>" maxlength="255" size="40" /></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['rpd_info']; ?></td>
	</tr>
<?php
				break;
			case 'rcd': // Reset cookie data
				// Get cookie information
				$cookie_domain = get_config_data('cookie_domain');
				$cookie_name = get_config_data('cookie_name');
				$cookie_path = get_config_data('cookie_path');
?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
				<tr>
					<td><b><?php echo $lang['Cookie_domain']; ?></b></td>
					<td><input type="input" name="cookie_domain" value="<?php echo htmlspecialchars($cookie_domain); ?>" maxlength="255" size="40" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['Cookie_name']; ?></b></td>
					<td><input type="input" name="cookie_name" value="<?php echo htmlspecialchars($cookie_name); ?>" maxlength="16" size="40" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['Cookie_path']; ?></b></td>
					<td><input type="input" name="cookie_path" value="<?php echo htmlspecialchars($cookie_path); ?>" maxlength="255" size="40" /></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['rcd_info']; ?></td>
	</tr>
<?php
				break;
			case 'rld': // Reset language data
?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
				<tr>
					<td><b><?php echo $lang['select_language']; ?>:</b></td>
					<td><?php echo language_select('english', 'new_lang'); ?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['rld_info']; ?></td>
	</tr>
<?php
				break;
			case 'rtd': // Reset template data

			    $themes_count = dibi::select('COUNT(*)')
			    ->as('themes_count')
			    ->from(THEMES_TABLE)
			    ->fetchSingle();
?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
<?php
				if ($themes_count !== 0) {
?>
				<tr>
					<td><input type="radio" name="method" value="select_theme" checked="checked" /></td>
					<td><?php echo $lang['select_theme']; ?></td>
					<td><?php echo Select::style('', 'new_style'); ?></td>
				</tr>
<?php
				}
?>
				<tr>
					<td><input type="radio" name="method" value="recreate_theme"<?php echo ( $themes_count === 0 ) ? ' checked="checked"' : '' ?> /></td>
					<td colspan="2"><?php echo $lang['reset_thmeme']; ?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo ($themes_count !== 0) ? $lang['rtd_info'] : $lang['rtd_info_no_theme'] ; ?></td>
	</tr>
<?php
				break;
			case 'dgc': // disable GZip compression 
?>
	<tr>
		<td><?php echo $lang['dgc_info']; ?></td>
	</tr>
<?php
				break;
			case 'cbl': // Clear ban list 
?>
	<tr>
		<td><?php echo $lang['cbl_info']; ?></td>
	</tr>
<?php
				break;
			case 'raa': // Remove all administrators
?>
	<tr>
		<td><?php echo $lang['raa_info']; ?></td>
	</tr>
<?php
				break;
			case 'mua': // Grant user admin privileges
?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
				<tr>
					<td><b><?php echo $lang['new_admin_user']; ?>:</b></td>
					<td><input type="input" name="username" maxlength="30" size="25" /></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['mua_info']; ?></td>
	</tr>
<?php
				break;
			case 'rcp': // Recreate config.php
				$available_dbms = [
					'mysql'      => ['LABEL'=> 'MySQL 3.x'],
					'mysql4'     => ['LABEL'=> 'MySQL 4.x'],
					'postgres'   => ['LABEL'=> 'PostgreSQL 7.x'],
					'mssql'      => ['LABEL'=> 'MS SQL Server 7/2000'],
					'msaccess'   => ['LABEL'=> 'MS Access [ ODBC ]'],
					'mssql-odbc' =>	['LABEL'=> 'MS SQL Server [ ODBC ]']
				];

				$dbms_select = '<select name="new_dbms">';

				foreach ($available_dbms as $dbms_name => $details) {
					$dbms_select .= '<option value="' . $dbms_name . '">' . $details['LABEL'] . '</option>';
				}

				$dbms_select .= '</select>';

?>
	<tr>
		<td>
			<table border="0" cellspacing="2" cellpadding="0">
				<tr>
					<td><b><?php echo $lang['dbms']; ?>:</b></td>
					<td><?php echo $dbms_select; ?></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['DB_Host']; ?>:</b></td>
					<td><input type="input" name="new_dbhost" size="30" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['DB_Name']; ?>:</b></td>
					<td><input type="input" name="new_dbname" size="30" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['DB_Username']; ?>:</b></td>
					<td><input type="input" name="new_dbuser" size="30" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['DB_Password']; ?>:</b></td>
					<td><input type="password" name="new_dbpasswd" size="30" /></td>
				</tr>
				<tr>
					<td><b><?php echo $lang['Table_Prefix']; ?>:</b></td>
					<td><input type="input" name="new_table_prefix" size="30" /></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['rcp_info']; ?></td>
	</tr>
<?php
				break;
			default:
?>
</table>
</form>
<p><b>Invalid Option</b></p>
</body>
</html>
<?php
				die();
		}
?>
	<tr>
		<td>
			<input type="hidden" name="option" value="<?php echo $option; ?>" />
			<input type="hidden" name="mode" value="execute" />
			<input type="hidden" name="lg" value="<?php echo $lg ?>" />
			<input type="submit" value="<?php echo $lang['Submit_text']; ?>" />
			- <a href="<?php echo $_SERVER['PHP_SELF'] . '?lg=' . $lg; ?>"><?php echo $lang['Cancel']; ?></a>
		</td>
	</tr>
</table>
</form>
<?php
		break;
	case 'execute':
		switch ($option) {
			case 'cls': // Clear Sessions
				check_authorisation();

				dibi::query('TRUNCATE TABLE %n', SESSIONS_TABLE);
				dibi::query('TRUNCATE TABLE %n', SEARCH_TABLE);

				success_message($lang['cls_success']);
				break;
			case 'rdb': // Clear Sessions
				check_authorisation();
				if (!check_mysql_version()) {
?>
	<p><span style="color:red"><?php echo $lang['Old_MySQL_Version'] ?></span></p>
<?php
				}
				else
				{
?>
	<p><?php echo $lang['Repairing_tables'] ?>:</p>
	<ul>
<?php
					foreach ($tables as $table) {
						$tablename = $table_prefix . $table;

						$row = dibi::query('REPAIR TABLE %n', $tablename)->fetch();


						if ($row) {
							if ($row['Msg_type'] == 'status') {
?>
		<li><?php echo "$tablename: " . $lang['Table_OK']?></li>
<?php
							} else //  We got an error
							{
								// Check whether the error results from HEAP-table type

								$row2 = dibi::query('SHOW TABLE STATUS LIKE %~like~', $tablename)->fetch();

								if ( (isset($row2['Type']) && $row2['Type'] === 'HEAP') || (isset($row2['Engine']) && ($row2['Engine'] === 'HEAP' || $row2['Engine'] === 'MEMORY')) ) {
									// Table is from HEAP-table type
?>
		<li><?php echo "$tablename: " . $lang['Table_HEAP_info']?></li>
<?php
								} else {
?>
		<li><?php echo "<b>$tablename:</b> " . htmlspecialchars($row['Msg_text'])?></li>
<?php
								}
							}
						}
					}
?>
	</ul>
<?php
					success_message($lang['rdb_success']);
				}
				break;
			case 'cct': // Check config table
				check_authorisation();

				// Update config data to match current configuration
				if (!empty($_SERVER['SERVER_PROTOCOL']) || !empty($_ENV['SERVER_PROTOCOL'])) {
					$protocol = !empty($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : $_ENV['SERVER_PROTOCOL'];

					if ( strtolower(substr($protocol, 0 , 5)) === 'https' ) {
						$default_config['cookie_secure'] = '1';
					}
				}

				if (!empty($_SERVER['SERVER_NAME']) || !empty($_ENV['SERVER_NAME'])) {
					$default_config['server_name'] = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME'];
				} else if (!empty($_SERVER['HTTP_HOST']) || !empty($_ENV['HTTP_HOST'])) {
					$default_config['server_name'] = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST'];
				}

				if (!empty($_SERVER['SERVER_PORT']) || !empty($_ENV['SERVER_PORT'])) {
					$default_config['server_port'] = !empty($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : $_ENV['SERVER_PORT'];
				}

				$default_config['script_path'] = str_replace('admin', '', dirname($_SERVER['PHP_SELF']));

				$row = dibi::select('MIN(topic_time)')
				    ->as('startdate')
				    ->from(TOPICS_TABLE)
				    ->fetch();

				if ($row && $row->startdate > 0) {
				    $default_config['board_startdate'] = $row['startdate'];
				}

				// Start the job				
?>
	<p><?php echo $lang['Restoring_config'] . ':'; ?></p>
	<ul>
<?php
				foreach ($default_config as $key => $value) {
				    $row = dibi::select('config_value')
				    ->from(CONFIG_TABLE)
				    ->where('config_name = %s', $key)
				    ->fetch();

					if (!$row){
						echo("<li><b>$key:</b> $value</li>\n");

						dibi::insert(CONFIG_TABLE, ['config_name' => $key, 'config_value' => $value])->execute();
					}
				}
?>
	</ul>
<?php
				success_message($lang['cct_success']);
				break;
			case 'rpd': // Reset path data
				check_authorisation();
				// Get variables
				$secure_select = isset($_POST['secure_select']) ? (int) $_POST['secure_select'] : 1;
				$domain_select = isset($_POST['domain_select']) ? (int) $_POST['domain_select'] : 1;
				$port_select = isset($_POST['port_select']) ? (int) $_POST['port_select'] : 1;
				$path_select = isset($_POST['path_select']) ? (int) $_POST['path_select'] : 1;
				$secure = isset($_POST['secure']) ? (int) $_POST['secure'] : 0;
				$domain = isset($_POST['domain']) ? str_replace("\\'", "''", $_POST['domain']) : '';
				$port = isset($_POST['port']) ? str_replace("\\'", "''", $_POST['port']) : '';
				$path = isset($_POST['path']) ? str_replace("\\'", "''", $_POST['path']) : '';
				
				if ($secure_select === 1) {
				    dibi::update(CONFIG_TABLE, ['config_value' => $secure])
				        ->where('config_name = %s', 'cookie_secure')
				        ->execute();
				}

				if ($domain_select === 1) {
				    dibi::update(CONFIG_TABLE, ['config_value' => $domain])
				        ->where('config_name = %s', 'server_name')
				        ->execute();
				}

				if ($port_select === 1) {
				    dibi::update(CONFIG_TABLE, ['config_value' => $port])
				        ->where('config_name = %s', 'server_port')
				        ->execute();
				}

				if ($path_select === 1) {
				    dibi::update(CONFIG_TABLE, ['config_value' => $path])
				        ->where('config_name = %s', 'script_path')
				        ->execute();
				}

				success_message($lang['rpd_success']);
				break;
			case 'rcd': // Reset cookie data
				check_authorisation();
				// Get variables
				$cookie_domain = isset($_POST['cookie_domain']) ? str_replace("\\'", "''", $_POST['cookie_domain']) : '';
				$cookie_name = isset($_POST['cookie_name']) ? str_replace("\\'", "''", $_POST['cookie_name']) : '';
				$cookie_path = isset($_POST['cookie_path']) ? str_replace("\\'", "''", $_POST['cookie_path']) : '';

				dibi::update(CONFIG_TABLE, ['config_value' => $cookie_domain])
				->where('config_name = %s', 'cookie_domain')
				->execute();

				dibi::update(CONFIG_TABLE, ['config_value' => $cookie_name])
				->where('config_name = %s', 'cookie_name')
				->execute();

				dibi::update(CONFIG_TABLE, ['config_value' => $cookie_path])
				->where('config_name = %s', 'cookie_path')
				->execute();

				success_message($lang['rcd_success']);
				break;
			case 'rld': // Reset language data
				check_authorisation();
				$new_lang   = isset($_POST['new_lang']) ? str_replace("\\'", "''", $_POST['new_lang']) : '';
				$board_user = isset($_POST['board_user']) ? trim(htmlspecialchars($_POST['board_user'])) : '';
				$board_user = substr(str_replace("\\'", "'", $board_user), 0, 25);
				$board_user = str_replace("'", "\\'", $board_user);

				if ( is_file(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $new_lang . '/lang_main.php')) && is_file(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $new_lang . '/lang_admin.php')) ){
				    dibi::update(USERS_TABLE, ['user_lang' => $new_lang])
				     ->where('username = %s', $board_user)
				      ->execute();

				    dibi::update(CONFIG_TABLE, ['config_value' => $new_lang])
				        ->where('config_name = %s', 'default_lang')
				        ->execute();

					success_message($lang['rld_success']);
				} else {
					success_message($lang['rld_failed']);
				}

				break;
			case 'rtd': // Reset template data
				check_authorisation();
				$method = isset($_POST['method']) ? htmlspecialchars($_POST['method']) : '';
				$new_style = isset($_POST['new_style']) ? (int) $_POST['new_style'] : 0;
				$board_user = isset($_POST['board_user']) ? trim(htmlspecialchars($_POST['board_user'])) : '';
				$board_user = substr(str_replace("\\'", "'", $board_user), 0, 25);
				$board_user = str_replace("'", "\\'", $board_user);

				if ($method === 'recreate_theme') {
				    $insertData = [
				       'template_name' => 'subSilver',
				       'style_name'    => 'subSilver',
				       'head_stylesheet' =>    'subSilver.css',
				       'body_background' => '',
				       'body_bgcolor' => 'E5E5E5',
				       'body_text' => '000000',
				       'body_link' => '006699',
				       'body_vlink' => '5493B4',
				       'body_alink' => '',
				       'body_hlink' => 'DD6900',

				       'tr_color1' => 'EFEFEF',
				       'tr_color2' => 'DEE3E7',
				       'tr_color3' => 'D1D7DC',

				       'tr_class1' => '',
				       'tr_class2' => '',
				       'tr_class3' => '',

				       'th_color1' => '98AAB1',
				       'th_color2' => '006699',
				       'th_color3' => 'FFFFFF',

				       'th_class1' => 'cellpic1.gif',
				       'th_class2' =>  'cellpic3.gif',
				       'th_class3' => 'cellpic2.jpg',

				       'td_color1' => 'FAFAFA',
				       'td_color2' => 'FFFFFF',
				       'td_color3' => '',

				       'td_class1' => 'row1',
				       'td_class2' => 'row2',
				       'td_class3' => '',

				       'fontface1' => 'Verdana, Arial, Helvetica, sans-serif',
				       'fontface2' => 'Trebuchet MS',
				       'fontface3' => "Courier, 'Courier New', sans-serif",

				       'fontsize1' => 10,
				       'fontsize2' => 11,
				       'fontsize3' => 12,

				       'fontcolor1' => '444444',
				       'fontcolor2' => '006600',
				       'fontcolor3' => 'FFA34F',

				       'span_class1' => '',
				       'span_class2' => '',
				       'span_class3' => '',

				       'img_size_poll' => null,
				       'img_size_privmsg' => null,

                    ];

				    $new_style = dibi::insert(THEMES_TABLE, $insertData)->execute(dibi::IDENTIFIER);

					$method = 'select_theme';
?>
	<p><?php echo $lang['rtd_restore_success'];?></p>
<?php
				}

				if ($method === 'select_theme') {
				    dibi::update(USERS_TABLE, ['user_style' => $new_style])
				    ->where('username = %s', $board_user)
				    ->execute();

				    dibi::update(CONFIG_TABLE, ['config_value' => $new_style])
				    ->where('config_name = %s', 'default_style')
				    ->execute();

					success_message($lang['rtd_success']);
				}
				break;
			case 'dgc': // Disable GZip compression 
				check_authorisation();

				dibi::update(CONFIG_TABLE, ['config_value' => 0])
				->where('config_name = %s', 'gzip_compress')
				->execute();

				success_message($lang['dgc_success']);
				break;
			case 'cbl': // Clear ban list 
				check_authorisation();

				dibi::query('TRUNCATE TABLE %n', BANLIST_TABLE);
				dibi::query('TRUNCATE TABLE %n', DISALLOW_TABLE);

				$row = dibi::select('user_id')
				->from(USERS_TABLE)
				->where('user_id = %i', ANONYMOUS)
				->fetch();

				if ($row) { // anonymous user exists
					success_message($lang['cbl_success']);
				} else { // anonymous user does not exist
					// Recreate entry
					$insertData = [
                        'user_id' => ANONYMOUS,
                        'username' => 'Anonymous',
                        'user_level' => 0,
                        'user_regdate' => 0,
                        'user_password' => '',
                        'user_email'    => '',
                        'user_icq'      => '',
                        'user_website'  => '',
                        'user_occ'   => '',
                        'user_from' => '',
                        'user_interests' => '',
                        'user_sig' => '',
                        'user_viewemail' => 0,
                        'user_style' => null,
                        'user_aim' =>'',
                        'user_yim' =>'',
                        'user_msnm' =>'',
                        'user_posts' => 0,
                        'user_topics' => 0,
                        'user_attachsig' => 0,
                        'user_allowsmile' => 1,
                        'user_allowhtml' => 1,
                        'user_allowbbcode' => 1,
                        'user_allow_pm' => 0,
                        'user_notify_pm' => 1,
                        'user_allow_viewonline' => 1,
                        'user_rank' => 0,
                        'user_avatar' => '',
                        'user_lang' => '',
                        'user_timezone' => '',
                        'user_dateformat' => '',
                        'user_actkey' => '',
                        'user_newpasswd' => '',
                        'user_notify' => 0,
                        'user_active' => 0
                    ];

                    dibi::insert(USERS_TABLE, $insertData)->execute();

					success_message($lang['cbl_success_anonymous']);
				}
				break;
			case 'raa': // Remove all administrators
				check_authorisation();
				// Get userdata to check for current user
				$auth_method = isset($_POST['auth_method']) ? htmlspecialchars($_POST['auth_method']) : '';
				$board_user = isset($_POST['board_user']) ? trim(htmlspecialchars($_POST['board_user'])) : '';
				$board_user = substr(str_replace("\\'", "'", $board_user), 0, 25);

				$rows = dibi::select(['user_id', 'username'])
				->from(USERS_TABLE)
				->where('user_level = %i', ADMIN)
				->fetchAll();
?>
	<p><?php echo $lang['Removing_admins'] . ':'; ?></p>
	<ul>
<?php
				foreach ($rows as $row) {
					if ( $auth_method !== 'board' || $board_user !== $row->username ) {
						// Checking whether user is a moderator

						$row2 = dibi::select('ug.user_id')
						    ->from(USER_GROUP_TABLE)
						    ->as('ug')
						    ->innerJoin(AUTH_ACCESS_TABLE)
						    ->as('aa')
						    ->on('ug.group_id = aa.group_id')
						    ->where('ug.user_id = %i', $row->user_id)
						    ->where('ug.user_pending <> %i', 1)
						    ->where('aa.auth_mod = %i', 1)
						    ->fetch();

						$new_state = ($row2) ? MOD : USER;

						dibi::update(USERS_TABLE, ['user_level' => $new_state])
						    ->where('user_id = %i', $row['user_id'])
						    ->execute();

?>
	<li><?php echo htmlspecialchars($row['username']) ?></li>
<?php
					}
				}
?>
	</ul>
<?php
				success_message($lang['raa_success']);
				break;
			case 'mua': // Grant user admin privileges
				check_authorisation();
				$username = isset($_POST['username']) ? str_replace("\\'", "''", $_POST['username']) : '';

				$affected_rows1 = dibi::update(USERS_TABLE, ['user_active' => 1, 'user_level' => ADMIN])
				->where('username = %s', $username)
				->where('user_id <> %i', ANONYMOUS)
				->execute(dibi::AFFECTED_ROWS);

				// Try to update the login data
				$affected_rows2 = dibi::update(USERS_TABLE, ['user_login_tries' => 0, 'user_last_login_try' => 0])
				->where('username = %s', $username)
				->execute(dibi::AFFECTED_ROWS);

				$affected_rows = max($affected_rows1, $affected_rows2);

				if ($affected_rows === 0) {
					success_message($lang['mua_failed']);
				}else {
					success_message($lang['mua_success']);
				}
				break;
			case 'rcp': // Recreate config.php
				// Get Variables
				$var_array = ['new_dbms', 'new_dbhost', 'new_dbname', 'new_dbuser', 'new_dbpasswd', 'new_table_prefix'];

				foreach ($var_array as $var) {
					$$var = isset($_POST[$var]) ? stripslashes($_POST[$var]) : '';
				}

?>
	<p><b><?php echo $lang['New_config_php']; ?>:</b></p>
	<table border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="20">&nbsp;</td>
			<td>
				&lt;?php<br />
				<br />
				//<br />
				// phpBB 2.x auto-generated config file<br />
				// Do not change anything in this file!<br />
				//<br />
				<br />
				$dbms = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_dbms))); ?>';<br />
				<br />
				$dbhost = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_dbhost))); ?>';<br />
				$dbname = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_dbname))); ?>';<br />
				$dbuser = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_dbuser))); ?>';<br />
				$dbpasswd = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_dbpasswd))); ?>';<br />
				<br />
				$table_prefix = '<?php echo htmlspecialchars(str_replace("'", "\\'", str_replace("\\", "\\\\", $new_table_prefix))); ?>';<br />
				<br />
				define('PHPBB_INSTALLED', true);<br />
				<br />
				?&gt;
			</td>
		</tr>
	</table>
<?php
				$ndbms = urlencode($new_dbms);
				$ndbh = urlencode($new_dbhost);
				$ndbn = urlencode($new_dbname);
				$ndbu = urlencode($new_dbuser);
				$ndbp = urlencode($new_dbpasswd);
				$ntp = urlencode($new_table_prefix);
				success_message(sprintf($lang['rcp_success'], '<a href="' . $_SERVER['PHP_SELF'] . "?mode=download&ndbms=$ndbms&ndbh=$ndbh&ndbn=$ndbn&ndbu=$ndbu&ndbp=$ndbp&ntp=$ntp\">", '</a>'));
				break;
			default:
?>
<p><b>Invalid Option</b></p>
</body>
</html>
<?php
				die();
		}
		break;
	default:
?>
<p><b>Invalid Option</b></p>
</body>
</html>
<?php
		die();
}
?>

<br clear="all" />

</body>
</html>
