
<h1>{L_DBMTNC_TITLE} - {L_DBMTNC_SUB_TITLE}</h1>

<p>{L_CONFIG_INFO}</p>

<form action="{S_CONFIG_ACTION}" method="post">
	<table width="99%" cellpadding="4" cellspacing="1" border="0" align="center" class="forumline">
		<tr>
			<th class="thHead" colspan="2">{L_GENERAL_CONFIG}</th>
		</tr>

		<tr>
			<td class="row1">
				{L_DISALLOW_POSTCOUNTER}
				<br/>
				<span class="gensmall">{L_DISALLOW_POSTCOUNTER_EXPLAIN}</span>
			</td>
			<td class="row2" nowrap="nowrap">
				<input type="radio" name="disallow_postcounter" id="disallow_postcounter_id_1" value="1" {DISALLOW_POSTCOUNTER_YES} />
				<label for="disallow_postcounter_id_1">{L_YES}</label>&nbsp;&nbsp;

				<input type="radio" name="disallow_postcounter" id="disallow_postcounter_0" value="0" {DISALLOW_POSTCOUNTER_NO} />
				<label for="disallow_postcounter_0">{L_NO}</label>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="disallow_rebuild">{L_DISALLOW_REBUILD}</label>
				<br/>
				<span class="gensmall">{L_DISALLOW_REBUILD_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input type="radio" name="disallow_rebuild" id="disallow_rebuild_1" value="1" {DISALLOW_REBUILD_YES} />
				<label for="disallow_rebuild_1">{L_YES}</label>&nbsp;&nbsp;

				<input type="radio" name="disallow_rebuild" id="disallow_rebuild_0" value="0" {DISALLOW_REBUILD_NO} />
				<label for="disallow_rebuild_0">{L_NO}</label>
			</td>
		</tr>

		<!-- BEGIN rebuild_settings -->
		<tr>
			<th class="thHead" colspan="2">{L_REBUILD_CONFIG}</th>
		</tr>

		<tr>
			<td class="row2" colspan="2"><span class="gensmall">{L_REBUILD_SETTINGS_EXPLAIN}</span></td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_timelimit">{L_REBUILDCFG_TIMELIMIT}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_TIMELIMIT_EXPLAIN}</span>
			</td>
			<td class="row2"><input class="post" type="text" maxlength="5" size="5" name="rebuildcfg_timelimit"
									id="rebuildcfg_timelimit" value="{REBUILDCFG_TIMELIMIT}"/></td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_timeoverwrite">{L_REBUILDCFG_TIMEOVERWRITE}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_TIMEOVERWRITE_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="5" size="5" name="rebuildcfg_timeoverwrite" id="rebuildcfg_timeoverwrite" value="{REBUILDCFG_TIMEOVERWRITE}"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_maxmemory">{L_REBUILDCFG_MAXMEMORY}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_MAXMEMORY_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="5" size="5" name="rebuildcfg_maxmemory" id="rebuildcfg_maxmemory" value="{REBUILDCFG_MAXMEMORY}"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_minposts">{L_REBUILDCFG_MINPOSTS}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_MINPOSTS_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="3" size="3" name="rebuildcfg_minposts" id="rebuildcfg_minposts" value="{REBUILDCFG_MINPOSTS}"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_php3only">{L_REBUILDCFG_PHP3ONLY}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_PHP3ONLY_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input type="radio" name="rebuildcfg_php3only" id="rebuildcfg_php3only" value="1" {REBUILDCFG_PHP3ONLY_YES} />
				<label for="rebuildcfg_php3only">{L_YES}</label>&nbsp;&nbsp;

				<input type="radio" name="rebuildcfg_php3only" value="0" id="rebuildcfg_php3only_off" {REBUILDCFG_PHP3ONLY_NO} />
				<label for="rebuildcfg_php3only_off">{L_NO}</label>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_php4pps">{L_REBUILDCFG_PHP4PPS}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_PHP4PPS_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="3" size="3" name="rebuildcfg_php4pps" id="rebuildcfg_php4pps" value="{REBUILDCFG_PHP4PPS}"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuildcfg_php3pps">{L_REBUILDCFG_PHP3PPS}</label>
				<br/>
				<span class="gensmall">{L_REBUILDCFG_PHP3PPS_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="3" size="3" name="rebuildcfg_php3pps" id="rebuildcfg_php3pps" value="{REBUILDCFG_PHP3PPS}"/>
			</td>
		</tr>
		<!-- END rebuild_settings -->
		<!-- BEGIN currentrebuild_settings -->
		<tr>
			<th class="thHead" colspan="2">{L_CURRENTREBUILD_CONFIG}</th>
		</tr>
		<tr>
			<td class="row2" colspan="2">
				<span class="gensmall">{L_CURRENTREBUILD_SETTINGS_EXPLAIN}</span>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuild_pos">{L_REBUILD_POS}</label>
				<br/>
				<span class="gensmall">{L_REBUILD_POS_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="10" size="8" name="rebuild_pos" id="rebuild_pos" value="{REBUILD_POS}"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="rebuild_end">{L_REBUILD_END}</label>
				<br/>
				<span class="gensmall">{L_REBUILD_END_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" maxlength="10" size="8" name="rebuild_end" id="rebuild_end" value="{REBUILD_END}"/>
			</td>
		</tr>
		<!-- END currentrebuild_settings -->

		<tr>
			<td class="catBottom" colspan="2" align="center">
				<input type="submit" name="submit" value="{L_SUBMIT}" class="mainoption"/>&nbsp;&nbsp;
				<input type="reset" value="{L_RESET}" class="liteoption"/>
			</td>
		</tr>
	</table>

	{S_HIDDEN_FIELDS}
</form>
