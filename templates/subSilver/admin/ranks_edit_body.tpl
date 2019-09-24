
<h1>{L_RANKS_TITLE}</h1>

<p>{L_RANKS_TEXT}</p>

<form action="{S_RANK_ACTION}" method="post">
	<table class="forumline" cellpadding="4" cellspacing="1" border="0" align="center">
	<tr>
		<th class="thTop" colspan="2">{L_RANKS_TITLE}</th>
	</tr>
	<tr>
		<td class="row1" width="38%">
			<label for="title">
				<span class="gen">{L_RANK_TITLE}:</span>
			</label>
		</td>
		<td class="row2">
			<input class="post" type="text" name="title" id="title" size="35" maxlength="40" value="{RANK}" />
		</td>
	</tr>
		<tr>
			<td class="row1" width="38%">
				<label for="rank_desc">
					<span class="gen">{L_RANK_DESC}:</span>
				</label>
			</td>
			<td class="row2">
				<textarea name="rank_desc" id="rank_desc">{RANK_DESC}</textarea>
			</td>
		</tr>
	<tr>
		<td class="row1">
			<span class="gen">{L_RANK_SPECIAL}</span>
		</td>
		<td class="row2">
			<input type="radio" name="special_rank" id="special_rank_1" value="1" {SPECIAL_RANK} />
			<label for="special_rank_1">{L_YES}</label> &nbsp;&nbsp;

			<input type="radio" name="special_rank" id="special_rank_0" value="0" {NOT_SPECIAL_RANK} />
			<label for="special_rank_0">{L_NO}</label>
		</td>
	</tr>
	<tr>
		<td class="row1" width="38%">
			<label for="min_posts">
				<span class="gen">{L_RANK_MINIMUM}:</span>
			</label>
		</td>
		<td class="row2">
			<input class="post" type="text" name="min_posts" id="min_posts" size="5" maxlength="10" value="{MINIMUM}" />
		</td>
	</tr>
	<tr>
		<td class="row1" width="38%">
			<label for="rank_image">
				<span class="gen">{L_RANK_IMAGE}:</span>
			</label>
			<br />
			<span class="gensmall">{L_RANK_IMAGE_EXPLAIN}</span>
		</td>
		<td class="row2">
			<input class="post" type="text" name="rank_image" id="rank_image" size="40" maxlength="255" value="{IMAGE}" />
			<br />
			{IMAGE_DISPLAY}
		</td>
	</tr>
	<tr>
		<td class="catBottom" colspan="2" align="center"><input type="submit" name="submit" value="{L_SUBMIT}" class="mainoption" />&nbsp;&nbsp;<input type="reset" value="{L_RESET}" class="liteoption" /></td>
	</tr>
</table>
{S_HIDDEN_FIELDS}
</form>
