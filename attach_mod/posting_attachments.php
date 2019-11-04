<?php
/**
 *
 * @package attachment_mod
 * @version $Id: posting_attachments.php,v 1.12 2006/09/06 14:26:29 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */
if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

/**
 * Entry Point
 */
function execute_posting_attachment_handling()
{
    global $attachment_mod;

    $attachment_mod['posting'] = new attach_posting();
    $attachment_mod['posting']->posting_attachment_mod();
}

?>