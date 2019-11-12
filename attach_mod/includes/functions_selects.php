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
 *
 * @param     $select_name
 * @param int $default_group
 *
 * @return string
 */
function group_select($select_name, $default_group = 0)
{
    global $lang;

    $group_name = dibi::select(['group_id', 'group_name'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->orderBy('group_name')
        ->fetchPairs('group_id', 'group_name');

    $num_rows = count($group_name);
    $group_select = '<select name="' . $select_name . '">';

    if ($num_rows > 0) {
        $group_name[$num_rows] = $lang['Not_assigned'];

        $i = 0;

        foreach ($group_name as $groupId => $groupName) {
            if ($default_group) {
                $selected = ($groupId === $default_group) ? ' selected="selected"' : '';
            } else {
                $selected = ($i === 0) ? ' selected="selected"' : '';
            }

            $group_select .= '<option value="' . $groupId. '"' . $selected . '>' . $groupName . '</option>';
            $i++;
        }
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * select download mode
 * @param     $select_name
 * @param int $group_id
 * @return string
*/
function download_select($select_name, $group_id = 0)
{
    global $types_download, $modes_download;

    if ($group_id) {
        $row = dibi::select('download_mode')
            ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
            ->where('[group_id] = %i', $group_id)
            ->fetch();

        if (!isset($row->download_mode)) {
            return '';
        }

        $download_mode = $row->download_mode;
    }

    $group_select = '<select name="' . $select_name . '">';

    foreach ($types_download as $i => $downLoadType) {
        if ($group_id) {
            $selected = ($downLoadType === INLINE_LINK) ? ' selected="selected"' : '';
        } else {
            $selected = ($row->download_mode === $downLoadType) ? ' selected="selected"' : '';
        }

        $group_select .= '<option value="' . $downLoadType . '"' . $selected . '>' . $modes_download[$i] . '</option>';
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * select category types
 * @param     $select_name
 * @param int $group_id
 * @return string
*/
function category_select($select_name, $group_id = 0)
{
    global $types_category, $modes_category;

    $rows = dibi::select(['group_id', 'cat_id'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->fetchAll();

    foreach ($rows as $row) {
        if ($group_id === $row->group_id) {
            $category_type = $row->cat_id;
        }
    }

    $type_category = 0;

    $types = [NONE_CAT];
    $modes = ['none'];

    foreach ($types_category as $i => $categoryType) {
        $types[] = $types_category[$i];
        $modes[] = $modes_category[$i];
    }

    $group_select = '<select name="' . $select_name . '" style="width:100px">';

    foreach ($types as $i => $type) {
        if ($group_id) {
            $selected = ($types[$i] === $category_type) ? ' selected="selected"' : '';
        } else {
            $selected = ($types[$i] === NONE_CAT) ? ' selected="selected"' : '';
        }

        $group_select .= '<option value="' . $types[$i] . '"' . $selected . '>' . $modes[$i] . '</option>';
    }

    $group_select .= '</select>';

    return $group_select;
}

/**
 * Select size mode
 * @param $select_name
 * @param $size_compare
 * @return string
*/
function size_select($select_name, $size_compare)
{
    global $lang;

    $size_types = [
        'b' => $lang['Bytes'],
        'kb' => $lang['KB'],
        'mb' => $lang['MB']
    ];

    $select_field = '<select name="' . $select_name . '">';

    foreach ($size_types as $type => $text) {
        $selected = $size_compare === $type ? ' selected="selected"' : '';
        $select_field .= '<option value="' . $type . '"' . $selected . '>' . $text . '</option>';
    }

    $select_field .= '</select>';

    return $select_field;
}

/**
 * select quota limit
 *
 * @param     $select_name
 * @param int $default_quota
 * @return string
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
        ->fetchPairs('quota_limit_id', 'quota_desc');

    $quota_name = array_merge($quota_name, $quota_names);

    foreach ($quota_name as $limit => $desc) {
        $selected = ($limit === $default_quota) ? ' selected="selected"' : '';
        $quota_select .= '<option value="' . $limit . '"' . $selected . '>' . $desc . '</option>';
    }
    $quota_select .= '</select>';

    return $quota_select;
}

/**
 * select default quota limit
 *
 * @param     $select_name
 * @param int $default_quota
 * @return string
*/
function default_quota_limit_select($select_name, $default_quota = 0)
{
    global $lang;

    $quota_name_data = dibi::select(['quota_limit_id', 'quota_desc'])
        ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
        ->orderBy('quota_limit', dibi::ASC)
        ->fetchPairs('quota_limit_id', 'quota_desc');

    $quota_select = '<select name="' . $select_name . '">';
    $quota_name[0]['quota_limit_id'] = 0;
    $quota_name[0]['quota_desc'] = $lang['No_quota_limit'];

    $quota_name = array_merge($quota_name, $quota_name_data);

    foreach ($quota_name as $limit => $desc) {
        $selected = ($limit === $default_quota) ? ' selected="selected"' : '';
        $quota_select .= '<option value="' . $limit . '"' . $selected . '>' . $desc . '</option>';
    }
    $quota_select .= '</select>';

    return $quota_select;
}

?>