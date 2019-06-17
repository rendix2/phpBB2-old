
<h1>{L_SMILEY_TITLE}</h1>

<p>{L_SMILEY_EXPLAIN}</p>

<script language="javascript" type="text/javascript">
<!--
function update_smiley(newimage)
{
	document.smiley_image.src = "{S_SMILEY_BASEDIR}/" + newimage;
}
//-->
</script>

<form method="post" action="{S_SMILEY_ACTION}"><table class="forumline" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th class="thHead" colspan="2">{L_SMILEY_CONFIG}</th>
	</tr>
	<tr>
		<td class="row2">
			<label for="smile_code">{L_SMILEY_CODE}</label>
		</td>
		<td class="row2">
			<input class="post" type="text" name="smile_code" value="{SMILEY_CODE}" />
		</td>
	</tr>
	<tr>
		<td class="row1">
			<label for="smile_url">{L_SMILEY_URL}</label>
		</td>
		<td class="row1">
			<select name="smile_url" id="smile_url" onchange="update_smiley(this.options[selectedIndex].value);">{S_FILENAME_OPTIONS}</select>
			&nbsp; <img name="smiley_image" src="{SMILEY_IMG}" border="0" alt="" /> &nbsp;
		</td>
	</tr>
	<tr>
		<td class="row2">
			<label for="smile_emotion">{L_SMILEY_EMOTION}</label>
		</td>
		<td class="row2"><input class="post" type="text" name="smile_emotion" id="smile_emotion" value="{SMILEY_EMOTICON}" /></td>
	</tr>
	<tr>
		<td class="catBottom" colspan="2" align="center">{S_HIDDEN_FIELDS}<input class="mainoption" type="submit" value="{L_SUBMIT}" /></td>
	</tr>
</table></form>
