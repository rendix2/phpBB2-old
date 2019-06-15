
<h1>{L_FORUM_TITLE}</h1>

<p>{L_FORUM_EXPLAIN}</p>

<form action="{S_FORUM_ACTION}" method="post">
  <table width="100%" cellpadding="4" cellspacing="1" border="0" class="forumline" align="center">
	<tr> 
	  <th class="thHead" colspan="2">{L_FORUM_SETTINGS}</th>
	</tr>
	<tr> 
	  <td class="row1">
		  <label for="forumname">{L_FORUM_NAME}</label>
	  </td>
	  <td class="row2"><input type="text" size="25" name="forumname" id="forumname" value="{FORUM_NAME}" class="post" /></td>
	</tr>
	<tr> 
	  <td class="row1">
		  <label for="forumdesc">{L_FORUM_DESCRIPTION}</label>
	  </td>
	  <td class="row2"><textarea rows="5" cols="45" wrap="virtual" name="forumdesc" id="forumdesc" class="post">{DESCRIPTION}</textarea></td>
	</tr>
	<tr> 
	  <td class="row1">{L_CATEGORY}</td>
	  <td class="row2"><select name="c">{S_CAT_LIST}</select></td>
	</tr>
	<tr> 
	  <td class="row1">
		  <label for="forumstatus">{L_FORUM_STATUS}</label>
	  </td>
	  <td class="row2"><select name="forumstatus" id="forumstatus">{S_STATUS_LIST}</select></td>
	</tr>
	<tr> 
	  <td class="row1">{L_AUTO_PRUNE}</td>
	  <td class="row2"><table cellspacing="0" cellpadding="1" border="0">
		  <tr> 
			<td align="right" valign="middle">
				<label for="prune_enable">{L_ENABLED}</label>
			</td>
			<td align="left" valign="middle"><input type="checkbox" name="prune_enable" id="prune_enable" value="1" {S_PRUNE_ENABLED} /></td>
		  </tr>
		  <tr> 
			<td align="right" valign="middle">
				<label for="prune_days">{L_PRUNE_DAYS}</label>
			</td>
			<td align="left" valign="middle">&nbsp;<input type="text" name="prune_days" id="prune_days" value="{PRUNE_DAYS}" size="5" class="post" />&nbsp;{L_DAYS}</td>
		  </tr>
		  <tr> 
			<td align="right" valign="middle">
				<label for="prune_freq">{L_PRUNE_FREQ}</label>
			</td>
			<td align="left" valign="middle">&nbsp;<input type="text" name="prune_freq" id="prune_freq" value="{PRUNE_FREQ}" size="5" class="post" />&nbsp;{L_DAYS}</td>
		  </tr>
	  </table></td>
	</tr>
	<tr> 
	  <td class="catBottom" colspan="2" align="center">{S_HIDDEN_FIELDS}<input type="submit" name="submit" value="{S_SUBMIT_VALUE}" class="mainoption" /></td>
	</tr>
  </table>
</form>
		
<br clear="all" />
