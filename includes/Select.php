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
}
