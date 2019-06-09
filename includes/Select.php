<?php

/**
 * Class Select
 *
 * @author rendix2
 */
class Select
{

    public static function timezone($default, $select_name = 'timezone')
    {
        $timeZones = DateTimeZone::listIdentifiers();

        $tz_select = '<select name="' . $select_name . '">';

        foreach ($timeZones as $timeZone) {
            $selected = ( $timeZone === $default ) ? ' selected="selected"' : '';
            $tz_select .= '<option value="' . $timeZone . '"' . $selected . '>' . $timeZone . '</option>';
        }

        $tz_select .= '</select>';

        return $tz_select;
    }

    public static function postDays(array $lang, $post_days)
    {
        $previous_days = [
            0   => $lang['All_Posts'],
            1   => $lang['1_Day'],
            7   => $lang['7_Days'],
            14  => $lang['2_Weeks'],
            30  => $lang['1_Month'],
            90  => $lang['3_Months'],
            180 => $lang['6_Months'],
            364 => $lang['1_Year']
        ];

        $select_post_days = '<select name="postdays">';

        foreach ($previous_days as $previous_day_key => $previous_days_value) {
            $selected = $post_days === $previous_day_key ? ' selected="selected"' : '';

            $select_post_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_days_value . '</option>';
        }

        $select_post_days .= '</select>';

        return $select_post_days;
    }

    public static function topicDays(array $lang, $topic_days)
    {
        $previous_days = [
            0   => $lang['All_Posts'],
            1   => $lang['1_Day'],
            7   => $lang['7_Days'],
            14  => $lang['2_Weeks'],
            30  => $lang['1_Month'],
            90  => $lang['3_Months'],
            180 => $lang['6_Months'],
            364 => $lang['1_Year']
        ];

        $select_topic_days = '<select name="topicdays">';

        foreach ($previous_days as $previous_day_key => $previous_day_value) {
            $selected = $topic_days === $previous_day_key ? ' selected="selected"' : '';

            $select_topic_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_day_value . '</option>';
        }

        $select_topic_days .= '</select>';

        return $select_topic_days;
    }

    public static function dissalow(array $lang, $disallowed)
    {
        $disallow_select = '<select name="disallowed_id">';

        if (count($disallowed)) {
            foreach ($disallowed as $disallow_id => $disallow_username) {
                $disallow_select .= '<option value="' . $disallow_id . '">' . $disallow_username . '</option>';
            }
        } else {
            $disallow_select .= '<option value="">' . $lang['no_disallowed'] . '</option>';
        }

        $disallow_select .= '</select>';

        return $disallow_select;
    }

    public static function groups(array $groups)
    {
        $select_list = '<select name="' . POST_GROUPS_URL . '">';

        foreach ($groups as $group_id => $group_name) {
            $select_list .= '<option value="' . $group_id . '">' . $group_name . '</option>';
        }

        $select_list .= '</select>';

        return $select_list;
    }

    public static function postOrder(array $lang, $post_time_order)
    {
        $select_post_order = '<select name="postorder">';

        if ($post_time_order === 'ASC') {
            $select_post_order .= '<option value="asc" selected="selected">' . $lang['Oldest_First'] . '</option><option value="desc">' . $lang['Newest_First'] . '</option>';
        } else {
            $select_post_order .= '<option value="asc">' . $lang['Oldest_First'] . '</option><option value="desc" selected="selected">' . $lang['Newest_First'] . '</option>';
        }

        $select_post_order .= '</select>';

        return $select_post_order;
    }


}
