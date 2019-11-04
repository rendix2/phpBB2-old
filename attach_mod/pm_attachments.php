<?php
/**
 *
 * @package attachment_mod
 * @version $Id: pm_attachments.php,v 1.2 2005/11/06 18:35:43 acydburn Exp $
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
 * @param $mode
*/
function execute_privmsgs_attachment_handling($mode)
{
    global $attachment_mod;

    $attachment_mod['pm'] = new attach_pm();

    if ($mode !== 'read') {
        $attachment_mod['pm']->privmsgs_attachment_mod($mode);
    }
}
