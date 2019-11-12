<?php
/**
 *
 * @package attachment_mod
 * @version $Id: constants.php,v 1.7 2006/09/05 15:04:01 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */
if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

// Attachment Debug Mode
define('ATTACH_DEBUG', 0);        // Attachment Mod Debugging off
//define('ATTACH_DEBUG', 1);	// Attachment Mod Debugging on

//define('ATTACH_QUERY_DEBUG', 1);

// Download Modes
define('INLINE_LINK', 1);
define('PHYSICAL_LINK', 2);

// Categories
define('NONE_CAT', 0);
define('IMAGE_CAT', 1);
define('STREAM_CAT', 2);
define('SWF_CAT', 3);

// Pages
define('PAGE_UACP', -1210);
define('PAGE_RULES', -1214);

// Misc
define('MEGABYTE', 1024);
define('ADMIN_MAX_ATTACHMENTS', 50); // Maximum Attachments in Posts or PM's for Admin Users
define('THUMB_DIR', 'thumbs');
define('MODE_THUMBNAIL', 1);

// Forum Extension Group Permissions
define('GPERM_ALL', 0); // ALL FORUMS

// Quota Types
define('QUOTA_UPLOAD_LIMIT', 1);
define('QUOTA_PM_LIMIT', 2);

define('ATTACH_VERSION', '2.4.5');


// Additional Constants


?>