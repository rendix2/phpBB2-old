<h1>{L_CONFIGURATION_TITLE}</h1>

<p>{L_CONFIGURATION_EXPLAIN}</p>

<form action="{S_CONFIG_ACTION}" method="post">
    <table width="99%" cellpadding="4" cellspacing="1" border="0" align="center" class="forumline">
        <tr>
            <th class="thHead" colspan="2">{L_GENERAL_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row1">
                <label for="server_name">{L_SERVER_NAME}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="255" size="40" name="server_name" id="server_name" value="{SERVER_NAME}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="server_port">{L_SERVER_PORT}</label>
                <br/>
                <span class="gensmall">{L_SERVER_PORT_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="5" size="5" name="server_port" id="server_port" value="{SERVER_PORT}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="script_path">{L_SCRIPT_PATH}</label>
                <br/>
                <span class="gensmall">{L_SCRIPT_PATH_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="255" name="script_path" id="script_path" value="{SCRIPT_PATH}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="sitename">{L_SITE_NAME}</label>
                <br/>
                <span class="gensmall">{L_SITE_NAME_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="25" maxlength="100" name="sitename" id="sitename" value="{SITENAME}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="site_desc">{L_SITE_DESCRIPTION}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" size="40" maxlength="255" name="site_desc" id="site_desc" value="{SITE_DESCRIPTION}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_DISABLE_BOARD}<br/><span class="gensmall">{L_DISABLE_BOARD_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="board_disable" id="board_disable_1" value="1" {S_DISABLE_BOARD_YES} />
                <label for="board_disable_1">{L_YES}</label>

                <input type="radio" name="board_disable" id="board_disable_0" value="0" {S_DISABLE_BOARD_NO} />
                <label for="board_disable_0">{L_NO}</label>
            </td>
        </tr>
        <tr>
            <td class="row1">{L_ACCT_ACTIVATION}</td>
            <td class="row2">
                <input type="radio" name="require_activation" id="require_activation_n" value="{ACTIVATION_NONE}" {ACTIVATION_NONE_CHECKED} />
                <label for="require_activation_n">{L_NONE}</label>

                <input type="radio" name="require_activation" id="require_activation_u" value="{ACTIVATION_USER}" {ACTIVATION_USER_CHECKED} />
                <label for="require_activation_u">{L_USER}</label>&nbsp; &nbsp;

                <input type="radio" name="require_activation" id="require_activation_a" value="{ACTIVATION_ADMIN}" {ACTIVATION_ADMIN_CHECKED} />
                <label for="require_activation_a">{L_ADMIN}</label>
            </td>
        </tr>
        <tr>
            <td class="row1">{L_VISUAL_CONFIRM}<br/><span class="gensmall">{L_VISUAL_CONFIRM_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="enable_confirm" id="enable_confirm_1" value="1" {CONFIRM_ENABLE} />
                <label for="enable_confirm_1">{L_YES}</label>&nbsp;
                &nbsp;
                <input type="radio" name="enable_confirm" id="enable_confirm_0" value="0" {CONFIRM_DISABLE} />
                <label for="enable_confirm_0">{L_NO}</label>
            </td>
        </tr>
        <tr>
            <td class="row1">{L_ALLOW_AUTOLOGIN}<br/><span class="gensmall">{L_ALLOW_AUTOLOGIN_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="allow_autologin" id="allow_autologin_1" value="1" {ALLOW_AUTOLOGIN_YES} />
                <label for="allow_autologin_1">{L_YES}</label>

                <input type="radio" name="allow_autologin" id="allow_autologin_0" value="0" {ALLOW_AUTOLOGIN_NO} />
                <label for="allow_autologin_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_autologin_time">{L_AUTOLOGIN_TIME}</label>
                <br/>
                <span class="gensmall">{L_AUTOLOGIN_TIME_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="max_autologin_time" id="max_autologin_time" value="{AUTOLOGIN_TIME}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_BOARD_EMAIL_FORM}<br/><span class="gensmall">{L_BOARD_EMAIL_FORM_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="board_email_form" id="board_email_form_1" value="1" {BOARD_EMAIL_FORM_ENABLE} />
                <label for="board_email_form_1">{L_ENABLED}</label>

                <input type="radio" name="board_email_form" id="board_email_form_0" value="0" {BOARD_EMAIL_FORM_DISABLE} />
                <label for="board_email_form_0">{L_DISABLED}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="flood_interval">{L_FLOOD_INTERVAL}</label>
                <br/>
                <span class="gensmall">{L_FLOOD_INTERVAL_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="flood_interval" id="flood_interval" value="{FLOOD_INTERVAL}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="search_flood_interval">{L_SEARCH_FLOOD_INTERVAL}</label>
                <br/>
                <span class="gensmall">{L_SEARCH_FLOOD_INTERVAL_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="search_flood_interval" id="search_flood_interval" value="{SEARCH_FLOOD_INTERVAL}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_login_attempts">{L_MAX_LOGIN_ATTEMPTS}</label>
                <br/>
                <span class="gensmall">{L_MAX_LOGIN_ATTEMPTS_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="max_login_attempts" id="max_login_attempts" value="{MAX_LOGIN_ATTEMPTS}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="login_reset_time">{L_LOGIN_RESET_TIME}</label>
                <br/>
                <span class="gensmall">{L_LOGIN_RESET_TIME_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="login_reset_time" id="login_reset_time" value="{LOGIN_RESET_TIME}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="topics_per_page">{L_TOPICS_PER_PAGE}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="topics_per_page" id="topics_per_page" size="3" maxlength="4" value="{TOPICS_PER_PAGE}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="posts_per_page">{L_POSTS_PER_PAGE}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="posts_per_page" id="posts_per_page" size="3" maxlength="4" value="{POSTS_PER_PAGE}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="members_per_page">{L_MEMBERS_PER_PAGE}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="members_per_page" id="members_per_page" size="3" maxlength="4" value="{MEMBERS_PER_PAGE}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="pm_per_page">{L_PM_PER_PAGE}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="pm_per_page" id="pm_per_page" size="3" maxlength="4" value="{PM_PER_PAGE}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="hot_threshold">{L_HOT_THRESHOLD}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="hot_threshold" id="hot_threshold" size="3" maxlength="4" value="{HOT_TOPIC}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="default_style">{L_DEFAULT_STYLE}</label>
            </td>
            <td class="row2">{STYLE_SELECT}</td>
        </tr>

        <tr>
            <td class="row1">
                {L_OVERRIDE_STYLE}
                <br/ >
                <span class="gensmall">{L_OVERRIDE_STYLE_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input type="radio" name="override_user_style" id="override_user_style_1" value="1" {OVERRIDE_STYLE_YES} />
                <label for="override_user_style_1">{L_YES}</label>

                <input type="radio" name="override_user_style" id="override_user_style_0" value="0" {OVERRIDE_STYLE_NO} />
                <label for="override_user_style_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="default_lang">{L_DEFAULT_LANGUAGE}</label>
            </td>
            <td class="row2">{LANG_SELECT}</td>
        </tr>

        <tr>
            <td class="row1">
                <label for="default_dateformat">{L_DATE_FORMAT}</label>
                <br/>
                <span class="gensmall">{L_DATE_FORMAT_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" name="default_dateformat" id="default_dateformat" value="{DEFAULT_DATEFORMAT}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="board_timezone">{L_SYSTEM_TIMEZONE}</label>
            </td>
            <td class="row2">{TIMEZONE_SELECT}</td>
        </tr>

        <tr>
            <td class="row1">{L_ENABLE_GZIP}</td>
            <td class="row2">
                <input type="radio" name="gzip_compress" id="gzip_compress_1" value="1" {GZIP_YES} />
                <label for="gzip_compress_1">{L_YES}</label>

                <input type="radio" name="gzip_compress" id="gzip_compress_0" value="0" {GZIP_NO} />
                <label for="gzip_compress_0">{L_NO}</label>

            </td>
        </tr>

        <tr>
            <td class="row1">{L_ENABLE_PRUNE}</td>
            <td class="row2">
                <input type="radio" name="prune_enable" id="prune_enable_1" value="1" {PRUNE_YES} />
                <label for="prune_enable_1">{L_YES}</label>

                <input type="radio" name="prune_enable" id="prune_enable_0" value="0" {PRUNE_NO} />
                <label for="prune_enable_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_COOKIE_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row2" colspan="2">
                <span class="gensmall">{L_COOKIE_SETTINGS_EXPLAIN}</span>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="cookie_domain">{L_COOKIE_DOMAIN}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="255" name="cookie_domain" id="cookie_domain" value="{COOKIE_DOMAIN}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="cookie_name">{L_COOKIE_NAME}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="16" name="cookie_name" id="cookie_name" value="{COOKIE_NAME}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="cookie_path">{L_COOKIE_PATH}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="255" name="cookie_path" id="cookie_path" value="{COOKIE_PATH}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_COOKIE_SECURE}<br/><span class="gensmall">{L_COOKIE_SECURE_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="cookie_secure" id="cookie_secure_1" value="0" {S_COOKIE_SECURE_DISABLED} />
                <label for="cookie_secure_1">{L_DISABLED}</label>

                <input type="radio" name="cookie_secure" id="cookie_secure_0" value="1" {S_COOKIE_SECURE_ENABLED} />
                <label for="cookie_secure_0">{L_ENABLED}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="session_length">{L_SESSION_LENGTH}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="5" size="5" name="session_length" id="session_length" value="{SESSION_LENGTH}"/>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_PRIVATE_MESSAGING}</th>
        </tr>

        <tr>
            <td class="row1">{L_DISABLE_PRIVATE_MESSAGING}</td>
            <td class="row2">
                <input type="radio" name="privmsg_disable" id="privmsg_disable_0" value="0" {S_PRIVMSG_ENABLED} />
                <label for="privmsg_disable_0">{L_ENABLED}</label>

                <input type="radio" name="privmsg_disable" id="privmsg_disable_1" value="1" {S_PRIVMSG_DISABLED} />
                <label for="privmsg_disable_1">{L_DISABLED}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_inbox_privmsgs">{L_INBOX_LIMIT}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="4" size="4" name="max_inbox_privmsgs" id="max_inbox_privmsgs" value="{INBOX_LIMIT}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_sentbox_privmsgs">{L_SENTBOX_LIMIT}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="4" size="4" name="max_sentbox_privmsgs" id="max_sentbox_privmsgs" value="{SENTBOX_LIMIT}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_savebox_privmsgs">{L_SAVEBOX_LIMIT}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" maxlength="4" size="4" name="max_savebox_privmsgs" id="max_savebox_privmsgs" value="{SAVEBOX_LIMIT}"/>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_ABILITIES_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_poll_options">{L_MAX_POLL_OPTIONS}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="max_poll_options" id="max_poll_options" size="4" maxlength="4" value="{MAX_POLL_OPTIONS}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_HTML}</td>
            <td class="row2">
                <input type="radio" name="allow_html" id="allow_html_1" value="1" {HTML_YES} />
                <label for="allow_html_1">{L_YES}</label>

                <input type="radio" name="allow_html" id="allow_html_0" value="0" {HTML_NO} />
                <label for="allow_html_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="allow_html_tags">{L_ALLOWED_TAGS}</label>
                <br/>
                <span class="gensmall">{L_ALLOWED_TAGS_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="30" maxlength="255" name="allow_html_tags" id="allow_html_tags" value="{HTML_TAGS}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_BBCODE}</td>
            <td class="row2">
                <input type="radio" name="allow_bbcode" id="allow_bbcode_1" value="1" {BBCODE_YES} />
                <label for="allow_bbcode_1">{L_YES}</label>

                <input type="radio" name="allow_bbcode" id="allow_bbcode_0" value="0" {BBCODE_NO} />
                <label for="allow_bbcode_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_SMILIES}</td>
            <td class="row2">
                <input type="radio" name="allow_smilies" id="allow_smilies_1" value="1" {SMILE_YES} />
                <label for="allow_smilies_1">{L_YES}</label>

                <input type="radio" name="allow_smilies" id="allow_smilies_0" value="0" {SMILE_NO} />
                <label for="allow_smilies_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="smilies_path">{L_SMILIES_PATH}</label>
                <br/>
                <span class="gensmall">{L_SMILIES_PATH_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="20" maxlength="255" name="smilies_path" id="smilies_path" value="{SMILIES_PATH}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_SIG}</td>
            <td class="row2">
                <input type="radio" name="allow_sig" id="allow_sig_1" value="1" {SIG_YES} />
                <label for="allow_sig_1">{L_YES}</label>

                <input type="radio" name="allow_sig" id="allow_sig_0" value="0" {SIG_NO} />
                <label for="allow_sig_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="max_sig_chars">{L_MAX_SIG_LENGTH}</label>
                <br/>
                <span class="gensmall">{L_MAX_SIG_LENGTH_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="5" maxlength="4" name="max_sig_chars" id="max_sig_chars" value="{SIG_SIZE}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_NAME_CHANGE}</td>
            <td class="row2">
                <input type="radio" name="allow_namechange" id="allow_namechange_1" value="1" {NAMECHANGE_YES} />
                <label for="allow_namechange_1">{L_YES}</label>

                <input type="radio" name="allow_namechange" id="allow_namechange_0" value="0" {NAMECHANGE_NO} />
                <label for="allow_namechange_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_AVATAR_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_LOCAL}</td>
            <td class="row2">
                <input type="radio" name="allow_avatar_local" id="allow_avatar_local_1" value="1" {AVATARS_LOCAL_YES} />
                <label for="allow_avatar_local_1">{L_YES}</label>

                <input type="radio" name="allow_avatar_local" id="allow_avatar_local_0" value="0" {AVATARS_LOCAL_NO} />
                <label for="allow_avatar_local_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">{L_ALLOW_REMOTE} <br/><span class="gensmall">{L_ALLOW_REMOTE_EXPLAIN}</span></td>
            <td class="row2">
                <input type="radio" name="allow_avatar_remote" id="allow_avatar_remote_1" value="1" {AVATARS_REMOTE_YES} />
                <label for="allow_avatar_remote_1">{L_YES}</label>

                <input type="radio" name="allow_avatar_remote" id="allow_avatar_remote_0" value="0" {AVATARS_REMOTE_NO} />
                <label for="allow_avatar_remote_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="script_path">{L_ALLOW_UPLOAD}</label>
            </td>
            <td class="row2">
                <input type="radio" name="allow_avatar_upload" id="allow_avatar_upload_1" value="1" {AVATARS_UPLOAD_YES} />
                <label for="allow_avatar_upload_1">{L_YES}</label>

                <input type="radio" name="allow_avatar_upload" id="allow_avatar_upload_0" value="0" {AVATARS_UPLOAD_NO} />
                <label for="allow_avatar_upload_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="avatar_filesize">{L_MAX_FILESIZE}</label>
                <br/>
                <span class="gensmall">{L_MAX_FILESIZE_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="10" maxlength="10" name="avatar_filesize" id="avatar_filesize" value="{AVATAR_FILESIZE}"/>
                ({BYTES})
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="avatar_max_height">{L_MAX_AVATAR_SIZE}</label>
                <br/>
                <span class="gensmall">{L_MAX_AVATAR_SIZE_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="3" maxlength="4" name="avatar_max_height" id="avatar_max_height" value="{AVATAR_MAX_HEIGHT}"/> x
                <input class="post" type="text" size="3" maxlength="4" name="avatar_max_width" value="{AVATAR_MAX_WIDTH}">
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="avatar_path">{L_AVATAR_STORAGE_PATH}</label>
                <br/>
                <span class="gensmall">{L_AVATAR_STORAGE_PATH_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="20" maxlength="255" name="avatar_path" id="avatar_path" value="{AVATAR_PATH}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="avatar_gallery_path">{L_AVATAR_GALLERY_PATH}</label>
                <br/>
                <span class="gensmall">{L_AVATAR_GALLERY_PATH_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" size="20" maxlength="255" name="avatar_gallery_path" id="avatar_gallery_path" value="{AVATAR_GALLERY_PATH}"/>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_COPPA_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row1">
                <label for="coppa_fax">{L_COPPA_FAX}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" size="25" maxlength="100" name="coppa_fax" id="coppa_fax" value="{COPPA_FAX}"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="coppa_mail">{L_COPPA_MAIL}</label>
                <br/>
                <span class="gensmall">{L_COPPA_MAIL_EXPLAIN}</span>
            </td>
            <td class="row2">
                <textarea name="coppa_mail" id="coppa_mail" rows="5" cols="30">{COPPA_MAIL}</textarea>
            </td>
        </tr>

        <tr>
            <th class="thHead" colspan="2">{L_EMAIL_SETTINGS}</th>
        </tr>

        <tr>
            <td class="row1">
                <label for="board_email">{L_ADMIN_EMAIL}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" size="25" maxlength="100" name="board_email" id="board_email" value="{EMAIL_FROM}"/></td>
        </tr>

        <tr>
            <td class="row1">
                <label for="board_email_sig">{L_EMAIL_SIG}</label>
                <br/>
                <span class="gensmall">{L_EMAIL_SIG_EXPLAIN}</span>
            </td>
            <td class="row2">
                <textarea name="board_email_sig" id="board_email_sig" rows="5" cols="30">{EMAIL_SIG}</textarea>
            </td>
        </tr>

        <tr>
            <td class="row1">
                {L_USE_SMTP}
                <br/>
                <span class="gensmall">{L_USE_SMTP_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input type="radio" name="smtp_delivery" id="smtp_delivery_1" value="1" {SMTP_YES} />
                <label for="smtp_delivery_1">{L_YES}</label>

                <input type="radio" name="smtp_delivery" id="smtp_delivery_0" value="0" {SMTP_NO} />
                <label for="smtp_delivery_0">{L_NO}</label>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="smtp_host">{L_SMTP_SERVER}</label>
            </td>
            <td class="row2">
                <input class="post" type="text" name="smtp_host" id="smtp_host" value="{SMTP_HOST}" size="25" maxlength="50"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="smtp_username">{L_SMTP_USERNAME}</label>
                <br/>
                <span class="gensmall">{L_SMTP_USERNAME_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="text" name="smtp_username" id="smtp_username" value="{SMTP_USERNAME}" size="25" maxlength="255"/>
            </td>
        </tr>

        <tr>
            <td class="row1">
                <label for="smtp_password">{L_SMTP_PASSWORD}</label>
                <br/>
                <span class="gensmall">{L_SMTP_PASSWORD_EXPLAIN}</span>
            </td>
            <td class="row2">
                <input class="post" type="password" name="smtp_password" id="smtp_password" value="{SMTP_PASSWORD}" size="25" maxlength="255"/>
            </td>
        </tr>

        <tr>
            <td class="catBottom" colspan="2" align="center">
                <input type="submit" name="submit" value="{L_SUBMIT}" class="mainoption"/>&nbsp;&nbsp;
                <input type="reset" value="{L_RESET}" class="liteoption"/>
            </td>
        </tr>
    </table>

    {S_HIDDEN_FIELDS}
</form>

<br clear="all"/>
