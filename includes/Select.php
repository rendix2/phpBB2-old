<?php

use Nette\Utils\Finder;

/**
 * Class Select
 *
 * @author rendix2
 */
class Select
{
    /**
     * @param string $default
     * @param string $selectName
     *
     * @return string
     */
    public static function timezone($default, $selectName = 'timezone')
    {
        $timeZones = DateTimeZone::listIdentifiers();

        $timeZoneValues = '';

        foreach ($timeZones as $timeZone) {
            $selected = $timeZone === $default ? 'selected="selected"' : '';
            $timeZoneValues .= '<option value="' . $timeZone . '" ' . $selected . '>' . $timeZone . '</option>';
        }

        return '<select name="' . $selectName . '" id="' . $selectName . '">' . $timeZoneValues . '</select>';
    }

    /**
     * @param array  $lang
     * @param string $post_days
     *
     * @return string
     */
    public static function postDays(array $lang, $post_days)
    {
        $previousDays = [
            0   => $lang['All_Posts'],
            1   => $lang['1_Day'],
            7   => $lang['7_Days'],
            14  => $lang['2_Weeks'],
            30  => $lang['1_Month'],
            90  => $lang['3_Months'],
            180 => $lang['6_Months'],
            364 => $lang['1_Year']
        ];

        $postDayValues = '';

        foreach ($previousDays as $previous_day_key => $previous_days_value) {
            $selected = $post_days === $previous_day_key ? 'selected="selected"' : '';

            $postDayValues .= '<option value="' . $previous_day_key . '" ' . $selected . '>' . $previous_days_value . '</option>';
        }

        return '<select name="postdays" id="postdays">' . $postDayValues . '</select>';
    }

    /**
     * @param array  $lang
     * @param string $topic_days
     *
     * @return string
     */
    public static function topicDays(array $lang, $topic_days)
    {
        $previousDays = [
            0   => $lang['All_Posts'],
            1   => $lang['1_Day'],
            7   => $lang['7_Days'],
            14  => $lang['2_Weeks'],
            30  => $lang['1_Month'],
            90  => $lang['3_Months'],
            180 => $lang['6_Months'],
            364 => $lang['1_Year']
        ];

        $topicDaysValue = '';

        foreach ($previousDays as $previous_day_key => $previous_day_value) {
            $selected = $topic_days === $previous_day_key ? 'selected="selected"' : '';

            $topicDaysValue .= '<option value="' . $previous_day_key . '" ' . $selected . '>' . $previous_day_value . '</option>';
        }

        return '<select name="topicdays" id="topicdays">' . $topicDaysValue . '</select>';
    }

    /**
     * @param array $lang
     * @param array $disallowed
     *
     * @return string
     */
    public static function dissalow(array $lang, array $disallowed)
    {
        $disallowValues = '';

        if (count($disallowed)) {
            foreach ($disallowed as $disallow_id => $disallow_username) {
                $disallowValues .= '<option value="' . $disallow_id . '">' . htmlspecialchars($disallow_username, ENT_QUOTES) . '</option>';
            }
        } else {
            $disallowValues .= '<option value="-1">' . $lang['No_disallowed'] . '</option>';
        }

        return '<select name="disallowed_id" id="disallowed_id">' . $disallowValues . '</select>';
    }

    /**
     *
     * @return string
     */
    public static function groups()
    {
        $groups = dibi::select(['group_id', 'group_name'])
            ->from(GROUPS_TABLE)
            ->where('group_single_user <> %i', 1)
            ->orderBy('group_name')
            ->fetchPairs('group_id', 'group_name');

        $groupValues = '';

        foreach ($groups as $groupId => $groupName) {
            $groupValues .= '<option value="' . $groupId . '">' . htmlspecialchars($groupName, ENT_QUOTES) . '</option>';
        }

        return '<select name="' . POST_GROUPS_URL . '" id="' . POST_GROUPS_URL . '">' . $groupValues . '</select>';
    }

    /**
     * @param array  $lang
     * @param string $post_time_order
     *
     * @return string
     */
    public static function postOrder(array $lang, $post_time_order)
    {
        $postOrderValues = '';

        if ($post_time_order === 'ASC') {
            $postOrderValues .= '<option value="asc" selected="selected">' . $lang['Oldest_First'] . '</option><option value="desc">' . $lang['Newest_First'] . '</option>';
        } else {
            $postOrderValues .= '<option value="asc">' . $lang['Oldest_First'] . '</option><option value="desc" selected="selected">' . $lang['Newest_First'] . '</option>';
        }

        return '<select name="postorder" id="postorder">' . $postOrderValues . '</select>';
    }

    /**
     * Pick a template/theme combo,
     *
     * @param string $defaultStyle
     * @param string $selectName
     * @param string $dirname
     *
     * @return string
     */
    public static function style($defaultStyle, $selectName = 'style', $dirname = 'templates')
    {
        $themes = dibi::select(['themes_id', 'style_name'])
            ->from(THEMES_TABLE)
            ->orderBy('template_name')
            ->orderBy('themes_id')
            ->fetchPairs('themes_id', 'style_name');

        if (!count($themes)) {
            message_die(GENERAL_ERROR, 'Could not query themes table.');
        }

        $styleValues = '';

        foreach ($themes as $themesId => $themeName) {
            $selected = $themesId === $defaultStyle ? 'selected="selected"' : '';

            $styleValues .= '<option value="' . $themesId . '" ' . $selected . '>' . htmlspecialchars($themeName, ENT_QUOTES) . '</option>';
        }

        return'<select name="' . $selectName . '" id="' . $selectName . '">' . $styleValues . '</select>';
    }

    /**
     * @param string $phpbb_root_path
     * @param array  $lang
     * @param array  $boardConfig
     * @param string $selectName
     *
     * @return string
     */
    public static function styleFiles($phpbb_root_path, array $lang, $boardConfig, $selectName)
    {
        $templateDirs = Finder::findDirectories('*')->in($phpbb_root_path . 'templates/');

        if (!count($templateDirs)) {
            message_die(GENERAL_MESSAGE, $lang['No_template_dir']);
        }

        // this method is not used so much, so we can let here the new query
        // and fix bug in origin code
        $defaultTheme = dibi::select(['style_name'])
            ->from(THEMES_TABLE)
            ->where('themes_id = %i', $boardConfig['default_style'])
            ->fetchSingle();

        $templateOptions = '';

        /**
         * @var SplFileInfo $templateDir
         */
        foreach ($templateDirs as $templateDir) {
            if ($templateDir->getFilename() === $defaultTheme) {
                $templateOptions .= '<option value="' . $templateDir->getFilename() . '" selected="selected">' . htmlspecialchars($templateDir->getFilename(), ENT_QUOTES) . "</option>\n";
            } else {
                $templateOptions .= '<option value="' . $templateDir->getFilename() . '">' . htmlspecialchars($templateDir->getFilename(), ENT_QUOTES) . "</option>\n";
            }
        }

        return '<select name="'.$selectName.'" id="'.$selectName.'">' . $templateOptions . '</select>';
    }

    /**
     * Pick a language, any language ...
     *
     * @param string $phpbb_root_path
     * @param string $default
     * @param string $selectName
     *
     * @return string
     */
    public static function language($phpbb_root_path, $default, $selectName = 'language')
    {
        $resultLanguages = [];
        $languages = Finder::findDirectories('lang_*')->in($phpbb_root_path . 'language');

        /**
         * @var SplFileInfo $language
         */
        foreach ($languages as $language) {
            $filename = trim(str_replace('lang_', '', $language->getFilename()));

            $displayName = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
            $displayName = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayName);

            $resultLanguages[$displayName] = $filename;
        }

        @asort($resultLanguages);

        $default = strtolower($default);
        $langValues = '';

        foreach ($resultLanguages as $displayName => $filename) {
            $selected = $default === strtolower($filename) ? 'selected="selected"' : '';

            $langValues .= '<option value="' . $filename . '" ' . $selected . '>' . htmlspecialchars(ucwords($displayName), ENT_QUOTES) . '</option>';
        }

        return '<select name="' . $selectName . '" id="' . $selectName . '"">' . $langValues . '</select>';
    }

}
