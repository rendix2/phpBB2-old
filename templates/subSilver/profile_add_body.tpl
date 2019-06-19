
<form action="{S_PROFILE_ACTION}" {S_FORM_ENCTYPE} method="post">

{ERROR_BOX}

<table width="100%" cellspacing="2" cellpadding="2" border="0" align="center">
	<tr> 
		<td align="left"><span class="nav"><a href="{U_INDEX}" class="nav">{L_INDEX}</a></span></td>
	</tr>
</table>

<table border="0" cellpadding="3" cellspacing="1" width="100%" class="forumline">
	<tr> 
		<th class="thHead" colspan="2" height="25" valign="middle">{L_REGISTRATION_INFO}</th>
	</tr>
	<tr> 
		<td class="row2" colspan="2">
			<span class="gensmall">{L_ITEMS_REQUIRED}</span>
		</td>
	</tr>
	<!-- BEGIN switch_namechange_disallowed -->
	<tr>
		<td class="row1" width="38%">
			<span class="gen">{L_USERNAME}: *</span>
		</td>
		<td class="row2">
			<input type="hidden" name="username" value="{USERNAME}" />
			<span class="gen">
				<b>{USERNAME}</b>
			</span>
		</td>
	</tr>
	<!-- END switch_namechange_disallowed -->
	<!-- BEGIN switch_namechange_allowed -->
	<tr> 
		<td class="row1" width="38%">
			<label for="username">
				<span class="gen">{L_USERNAME}: *</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width:200px" name="username" id="username" size="25" maxlength="25" value="{USERNAME}" />
		</td>
	</tr>
	<!-- END switch_namechange_allowed -->
	<tr>
		<td class="row1">
			<label for="email">
				<span class="gen">{L_EMAIL_ADDRESS}: *</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width:200px" name="email" id="email" size="25" maxlength="255" value="{EMAIL}"/>
		</td>
	</tr>
	<!-- BEGIN switch_edit_profile -->
	<tr> 
	  <td class="row1">
		  <label for="cur_password">
		  	<span class="gen">{L_CURRENT_PASSWORD}: *</span>
		  </label>
		  <br />
		<span class="gensmall">{L_CONFIRM_PASSWORD_EXPLAIN}</span></td>
	  <td class="row2"> 
		<input type="password" class="post" style="width: 200px" name="cur_password" id="cur_password" size="25" maxlength="32" value="{CUR_PASSWORD}" />
	  </td>
	</tr>
	<!-- END switch_edit_profile -->
	<tr>
		<td class="row1">
			<label for="new_password">
				<span class="gen">{L_NEW_PASSWORD}: *</span>
			</label>
			<br/>
			<span class="gensmall">{L_PASSWORD_IF_CHANGED}</span>
		</td>
		<td class="row2">
			<input type="password" class="post" style="width: 200px" name="new_password" id="new_password" size="25" maxlength="32" value="{NEW_PASSWORD}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="password_confirm">
				<span class="gen">{L_CONFIRM_PASSWORD}: * </span>
			</label>
			<br/>
			<span class="gensmall">{L_PASSWORD_CONFIRM_IF_CHANGED}</span>
		</td>
		<td class="row2">
			<input type="password" class="post" style="width: 200px" name="password_confirm" id="password_confirm" size="25" maxlength="32" value="{PASSWORD_CONFIRM}"/>
		</td>
	</tr>
	<!-- Visual Confirmation -->
	<!-- BEGIN switch_confirm -->
	<tr>
		<td class="row1" colspan="2" align="center"><span class="gensmall">{L_CONFIRM_CODE_IMPAIRED}</span><br /><br />{CONFIRM_IMG}<br /><br /></td>
	</tr>
	<tr>
		<td class="row1">
			<label for="confirm_code">
				<span class="gen">{L_CONFIRM_CODE}: * </span>
			</label>
			<br/>
			<span class="gensmall">{L_CONFIRM_CODE_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 200px" name="confirm_code" id="confirm_code" size="6" maxlength="6" value=""/>
		</td>
	</tr>
	<!-- END switch_confirm -->
	<tr> 
	  <td class="catSides" colspan="2" height="28">&nbsp;</td>
	</tr>
	<tr> 
	  <th class="thSides" colspan="2" height="25" valign="middle">{L_PROFILE_INFO}</th>
	</tr>
	<tr> 
	  <td class="row2" colspan="2"><span class="gensmall">{L_PROFILE_INFO_NOTICE}</span></td>
	</tr>
	<tr>
		<td class="row1">
			<label for="icq">
				<span class="gen">{L_ICQ_NUMBER}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" name="icq" id="icq" class="post" style="width: 100px" size="10" maxlength="15" value="{ICQ}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="aim">
				<span class="gen">{L_AIM}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 150px" name="aim" id="aim" size="20" maxlength="255" value="{AIM}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="msn">
				<span class="gen">{L_MESSENGER}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 150px" name="msn" id="msn" size="20" maxlength="255" value="{MSN}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="yim">
				<span class="gen">{L_YAHOO}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 150px" name="yim" id="yim" size="20" maxlength="255" value="{YIM}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="website">
				<span class="gen">{L_WEBSITE}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 200px" name="website" id="website" size="25" maxlength="255" value="{WEBSITE}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="location">
				<span class="gen">{L_LOCATION}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 200px" name="location" id="location" size="25" maxlength="100" value="{LOCATION}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="occupation">
				<span class="gen">{L_OCCUPATION}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 200px" name="occupation" id="occupation" size="25" maxlength="100" value="{OCCUPATION}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="interests">
				<span class="gen">{L_INTERESTS}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="text" class="post" style="width: 200px" name="interests" id="interests" size="35" maxlength="150" value="{INTERESTS}"/>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="signature">
				<span class="gen">{L_SIGNATURE}:</span>
			</label>
			<br/>
			<span class="gensmall">{L_SIGNATURE_EXPLAIN}<br/><br/>{HTML_STATUS}<br/>{BBCODE_STATUS}<br/>{SMILIES_STATUS}</span>
		</td>
		<td class="row2">
			<textarea name="signature" id="signature" style="width: 300px" rows="6" cols="30" class="post">{SIGNATURE}</textarea>
		</td>
	</tr>
	<tr>
		<td class="catSides" colspan="2" height="28">&nbsp;</td>
	</tr>
	<tr>
		<th class="thSides" colspan="2" height="25" valign="middle">{L_PREFERENCES}</th>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_PUBLIC_VIEW_EMAIL}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="viewemail" id="viewemail_1" value="1" {VIEW_EMAIL_YES} />
			<label for="viewemail_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="viewemail" id="viewemail_0" value="0" {VIEW_EMAIL_NO} />
			<label for="viewemail_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_HIDE_USER}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="hideonline" id="hideonline_1" value="1" {HIDE_USER_YES} />
			<label for="hideonline_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="hideonline" id="hideonline_0" value="0" {HIDE_USER_NO} />
			<label for="hideonline_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_NOTIFY_ON_REPLY}:</span>
			<br/>
			<span class="gensmall">{L_NOTIFY_ON_REPLY_EXPLAIN}</span></td>
		<td class="row2">
			<input type="radio" name="notifyreply" id="notifyreply_1" value="1" {NOTIFY_REPLY_YES} />
			<label for="notifyreply_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="notifyreply" id="notifyreply_0" value="0" {NOTIFY_REPLY_NO} />
			<label for="notifyreply_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_NOTIFY_ON_PRIVMSG}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="notifypm" id="notifypm_1" value="1" {NOTIFY_PM_YES} />
			<label for="notifypm_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="notifypm" id="notifypm_0" value="0" {NOTIFY_PM_NO} />
			<label for="notifypm_0">
				<span class="gen">{L_NO}</span>
			</label
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_POPUP_ON_PRIVMSG}:</span><br/>
			<span class="gensmall">{L_POPUP_ON_PRIVMSG_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input type="radio" name="popup_pm" id="popup_pm_1" value="1" {POPUP_PM_YES} />
			<label for="popup_pm_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="popup_pm" id="popup_pm_0" value="0" {POPUP_PM_NO} />
			<label for="popup_pm_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1"><span class="gen">{L_ALWAYS_ADD_SIGNATURE}:</span></td>
		<td class="row2">
			<input type="radio" name="attachsig" id="attachsig_1" value="1" {ALWAYS_ADD_SIGNATURE_YES} />
			<label for="attachsig_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="attachsig" id="attachsig_0" value="0" {ALWAYS_ADD_SIGNATURE_NO} />
			<label for="attachsig_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_ALWAYS_ALLOW_BBCODE}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="allowbbcode" id="allowbbcode_1" value="1" {ALWAYS_ALLOW_BBCODE_YES} />
			<label for="allowbbcode_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="allowbbcode" id="allowbbcode_0" value="0" {ALWAYS_ALLOW_BBCODE_NO} />
			<label for="allowbbcode_0">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_ALWAYS_ALLOW_HTML}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="allowhtml" id="allowhtml_1" value="1" {ALWAYS_ALLOW_HTML_YES} />
			<label for="allowhtml_1">
				<span class="gen">{L_YES}</span>&nbsp;&nbsp;
			</label>
			<input type="radio" name="allowhtml" id="allowhtml_2" value="0" {ALWAYS_ALLOW_HTML_NO} />
			<label for="allowhtml_2">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_ALWAYS_ALLOW_SMILIES}:</span>
		</td>
		<td class="row2">
			<input type="radio" name="allowsmilies" id="allowsmilies_1" value="1" {ALWAYS_ALLOW_SMILIES_YES} />
			<label for="allowsmilies_1">
				<span class="gen">{L_YES}</span>
			</label>
			<input type="radio" name="allowsmilies" id="allowsmilies_2" value="0" {ALWAYS_ALLOW_SMILIES_NO} />
			<label for="allowsmilies_2">
				<span class="gen">{L_NO}</span>
			</label>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="language">
				<span class="gen">{L_BOARD_LANGUAGE}:</span>
			</label>
		</td>
		<td class="row2">
			<span class="gensmall">{LANGUAGE_SELECT}</span>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="style">
				<span class="gen">{L_BOARD_STYLE}:</span>
			</label>
		</td>
		<td class="row2">
			<span class="gensmall">{STYLE_SELECT}</span>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="timezone">
				<span class="gen">{L_TIMEZONE}:</span>
			</label>
		</td>
		<td class="row2">
			<span class="gensmall">{TIMEZONE_SELECT}</span>
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="dateformat">
				<span class="gen">{L_DATE_FORMAT}:</span>
			</label>
			<br/>
			<span class="gensmall">{L_DATE_FORMAT_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input type="text" name="dateformat" id="dateformat" value="{DATE_FORMAT}" maxlength="14" class="post"/>
		</td>
	</tr>
	<!-- BEGIN switch_avatar_block -->
	<tr>
		<td class="catSides" colspan="2" height="28">&nbsp;</td>
	</tr>
	<tr>
		<th class="thSides" colspan="2" height="12" valign="middle">{L_AVATAR_PANEL}</th>
	</tr>
	<tr> 
		<td class="row1" colspan="2">
			<table width="70%" cellspacing="2" cellpadding="0" border="0" align="center">
				<tr>
					<td width="65%">
						<span class="gensmall">{L_AVATAR_EXPLAIN}</span>
					</td>
					<td align="center">
						<span class="gensmall">{L_CURRENT_IMAGE}</span>
						<br />{AVATAR}<br />
						<input type="checkbox" name="avatardel" id="avatardel" />&nbsp;
						<label for="avatardel">
							<span class="gensmall">{L_DELETE_AVATAR}</span>
						</label>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<!-- BEGIN switch_avatar_local_upload -->
	<tr> 
		<td class="row1">
			<label for="avatar">
				<span class="gen">{L_UPLOAD_AVATAR_FILE}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="hidden" name="MAX_FILE_SIZE" value="{AVATAR_SIZE}" />
			<input type="file" name="avatar" id="avatar" class="post" style="width:200px" />
		</td>
	</tr>
	<!-- END switch_avatar_local_upload -->
	<!-- BEGIN switch_avatar_remote_upload -->
	<tr> 
		<td class="row1">
			<label for="avatarurl">
				<span class="gen">{L_UPLOAD_AVATAR_URL}:</span>
			</label>
			<br />
			<span class="gensmall">{L_UPLOAD_AVATAR_URL_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input type="text" name="avatarurl" id="avatarurl" size="40" class="post" style="width:200px" />
		</td>
	</tr>
	<!-- END switch_avatar_remote_upload -->
	<!-- BEGIN switch_avatar_remote_link -->
	<tr>
		<td class="row1">
			<label for="avatarremoteurl">
				<span class="gen">{L_LINK_REMOTE_AVATAR}:</span>
			</label>
			<br/>
			<span class="gensmall">{L_LINK_REMOTE_AVATAR_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input type="text" name="avatarremoteurl" id="avatarremoteurl" size="40" class="post" style="width:200px"/>
		</td>
	</tr>
	<!-- END switch_avatar_remote_link -->
	<!-- BEGIN switch_avatar_local_gallery -->
	<tr>
		<td class="row1">
			<label for="avatargallery">
				<span class="gen">{L_AVATAR_GALLERY}:</span>
			</label>
		</td>
		<td class="row2">
			<input type="submit" name="avatargallery" id="avatargallery" value="{L_SHOW_GALLERY}" class="liteoption"/>
		</td>
	</tr>
	<!-- END switch_avatar_local_gallery -->
	<!-- END switch_avatar_block -->
	<tr>
		<td class="catBottom" colspan="2" align="center" height="28">{S_HIDDEN_FIELDS}<input type="submit" name="submit" value="{L_SUBMIT}" class="mainoption" />&nbsp;&nbsp;<input type="reset" value="{L_RESET}" name="reset" class="liteoption" /></td>
	</tr>
</table>

	{F_LOGIN_FORM_TOKEN}
</form>
