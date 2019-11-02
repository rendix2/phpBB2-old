<?php
/**
 *
 * @package attachment_mod
 * @version $Id: functions_selects.php,v 1.2 2005/12/14 13:08:23 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 * Functions to build select boxes ;)
 */

/**
 * select group
 */
function group_select($select_name, $default_group = 0)
{
    global $lang;

    $group_name = dibi::select(['group_id', 'group_name'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->orderBy('group_name')
        ->fetchAll();

    $num_rows = count($group_name);
    $group_select = '<select name="' . $select_name . '">';

    if ($num_rows > 0) {
        $group_name[$num_rows]['group_id'] = 0;
        $group_name[$num_rows]['group_name'] = $lang['Not_assigned'];

        for ($i = 0; $i < count($group_name); $i++) {
            if (!$default_group) {
                $selected = ($i == 0) ? ' selected="selected"' : '';
            } else {
                $selected = ($group_name[$i]['group_id'] == $default_group) ? ' selected="selected"' : '';
            }

            $group_select .= '<option value="' . $group_name[$i]['group_id'] . '"' . $selected . '>' . $group_name[$i]['group_name'] . '</option>';
        }
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * select download mode
 */
function download_select($select_name, $group_id = 0)
{
    global $types_download, $modes_download;

    if ($group_id) {
        $row = dibi::select('download_mode')
            ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
            ->where('[group_id] = %i', $group_id)
            ->fetch();

        if (!isset($row['download_mode'])) {
            return '';
        }

        $download_mode = $row['download_mode'];
    }

    $group_select = '<select name="' . $select_name . '">';

    for ($i = 0; $i < count($types_download); $i++) {
        if (!$group_id) {
            $selected = ($row['download_mode'] == $types_download[$i]) ? ' selected="selected"' : '';
        } else {
            $selected = ($types_download[$i] == INLINE_LINK) ? ' selected="selected"' : '';
        }

        $group_select .= '<option value="' . $types_download[$i] . '"' . $selected . '>' . $modes_download[$i] . '</option>';
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * select category types
 */
function category_select($select_name, $group_id = 0)
{
    global $types_category, $modes_category;

    $rows = dibi::select(['group_id', 'cat_id'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->fetchAll();

    foreach ($rows as $row) {
        if ($group_id == $row->group_id) {
            $category_type = $row->cat_id;
        }
    }

    $type_category = 0;

    $types = array(NONE_CAT);
    $modes = array('none');

    for ($i = 0; $i < count($types_category); $i++) {
        $types[] = $types_category[$i];
        $modes[] = $modes_category[$i];
    }

    $group_select = '<select name="' . $select_name . '" style="width:100px">';

    for ($i = 0; $i < count($types); $i++) {
        if ($group_id) {
            $selected = ($types[$i] == $category_type) ? ' selected="selected"' : '';
        } else {
            $selected = ($types[$i] == NONE_CAT) ? ' selected="selected"' : '';
        }

        $group_select .= '<option value="' . $types[$i] . '"' . $selected . '>' . $modes[$i] . '</option>';
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * Select size mode
 */
function size_select($select_name, $size_compare)
{
    global $lang;

    $size_types_text = array($lang['Bytes'], $lang['KB'], $lang['MB']);
    $size_types = array('b', 'kb', 'mb');

    $select_field = '<select name="' . $select_name . '">';

    for ($i = 0; $i < count($size_types_text); $i++) {
        $selected = ($size_compare == $size_types[$i]) ? ' selected="selected"' : '';
        $select_field .= '<option value="' . $size_types[$i] . '"' . $selected . '>' . $size_types_text[$i] . '</option>';
    }

    $select_field .= '</select>';

    return $select_field;
}

/**
 * select quota limit
 */
function quota_limit_select($select_name, $default_quota = 0)
{
    global $lang;

    $quota_select = '<select name="' . $select_name . '">';
    $quota_name[0]['quota_limit_id'] = 0;
    $quota_name[0]['quota_desc'] = $lang['Not_assigned'];

    $quota_names = dibi::select(['quota_limit_id', 'quota_desc'])
        ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
        ->orderBy('quota_limit', dibi::ASC)
        ->fetchAll();

    $quota_name = array_merge($quota_name, $quota_names);

    for ($i = 0; $i < count($quota_name); $i++) {
        $selected = ($quota_name[$i]['quota_limit_id'] == $default_quota) ? ' selected="selected"' : '';
        $quota_select .= '<option value="' . $quota_name[$i]['quota_limit_id'] . '"' . $selected . '>' . $quota_name[$i]['quota_desc'] . '</option>';
    }
    $quota_select .= '</select>';

    return $quota_select;
}

/**
 * select default quota limit
 */
function default_quota_limit_select($select_name, $default_quota = 0)
{
    global $lang;

    $quota_name_data = dibi::select(['quota_limit_id', 'quota_desc'])
        ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
        ->orderBy('quota_limit', dibi::ASC)
        ->fetchAll();

    $quota_select = '<select name="' . $select_name . '">';
    $quota_name[0]['quota_limit_id'] = 0;
    $quota_name[0]['quota_desc'] = $lang['No_quota_limit'];

    $quota_name = array_merge($quota_name, $quota_name_data);

    for ($i = 0; $i < count($quota_name); $i++) {
        $selected = ($quota_name[$i]['quota_limit_id'] == $default_quota) ? ' selected="selected"' : '';
        $quota_select .= '<option value="' . $quota_name[$i]['quota_limit_id'] . '"' . $selected . '>' . $quota_name[$i]['quota_desc'] . '</option>';
    }
    $quota_select .= '</select>';

    return $quota_select;
}

?>