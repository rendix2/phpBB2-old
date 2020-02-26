
<script language="javascript" type="text/javascript">
<!--
function refresh_username(selected_username)
{
	if (selected_username === '-1') {
		return false;
	}

	opener.document.forms['post'].username.value = selected_username;
	opener.focus();
	window.close();
}
//-->
</script>

<form method="post" name="search" action="{S_SEARCH_ACTION}">
<table width="100%" border="0" cellspacing="0" cellpadding="10">
	<tr>
		<td>
			<table width="100%" class="forumline" cellpadding="4" cellspacing="1" border="0">
				<tr>
					<th class="thHead" height="25">
						<label for="search_username">{L_SEARCH_USERNAME}</label>
					</th>
				</tr>
				<tr>
					<td valign="top" class="row1">
						<span class="genmed">
							<br />
							<input type="text" name="search_username" id="search_username" value="{USERNAME}" class="post" />&nbsp;
							<input type="submit" name="search" value="{L_SEARCH}" class="liteoption" />
						</span>
						<br />
						<span class="gensmall">{L_SEARCH_EXPLAIN}</span>
						<br />

						<!-- BEGIN switch_select_name -->
						<span class="genmed">
							<label for="username_list">{L_UPDATE_USERNAME}</label>
							<br />
							<select name="username_list" id="username_list">{S_USERNAME_OPTIONS}</select>&nbsp
							<input type="submit" class="liteoption" onClick="refresh_username(this.form.username_list.options[this.form.username_list.selectedIndex].value);return false;" name="use" value="{L_SELECT}" />
						</span>
						<br />
						<!-- END switch_select_name -->

						<br />
						<span class="genmed">
							<a href="javascript:window.close();" class="genmed">{L_CLOSE_WINDOW}</a>
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	{F_LOGIN_FORM_TOKEN}
</table>
</form>
