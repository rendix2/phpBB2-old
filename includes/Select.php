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

    public static function postDays($previous_days, $post_days)
    {
        $select_post_days = '<select name="postdays">';

        foreach ($previous_days as $previous_day_key => $previous_days_value) {
            $selected = ($post_days === $previous_day_key) ? ' selected="selected"' : '';

            $select_post_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_days_value . '</option>';
        }

        $select_post_days .= '</select>';

        return $select_post_days;
    }

}
