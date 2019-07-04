
<h1>{L_GROUP_TITLE}</h1>

<form action="{S_GROUP_ACTION}" method="post" name="post"><table border="0" cellpadding="3" cellspacing="1" class="forumline" align="center">
	<tr> 
	  <th class="thHead" colspan="2">{L_GROUP_EDIT_DELETE}</th>
	</tr>
	<tr>
	  <td class="row1" colspan="2"><span class="gensmall">{L_ITEMS_REQUIRED}</span></td>
	</tr>
	<tr> 
	  <td class="row1" width="38%">
		  <label for="group_name">
			  <span class="gen">{L_GROUP_NAME}:</span>
		  </label>
	  </td>
	  <td class="row2" width="62%"> 
		<input class="post" type="text" name="group_name" id="group_name" size="35" maxlength="40" value="{GROUP_NAME}" />
	  </td>
	</tr>
	<tr> 
	  <td class="row1" width="38%">
		  <label for="group_description">
			  <span class="gen">{L_GROUP_DESCRIPTION}:</span>
		  </label>
	  </td>
	  <td class="row2" width="62%"> 
		<textarea class="post" name="group_description" id="group_description" rows="5" cols="51">{GROUP_DESCRIPTION}</textarea>
	  </td>
	</tr>
	<tr> 
		<td class="row1" width="38%">
			<label for="username">
				<span class="gen">{L_GROUP_MODERATOR}:</span>
			</label>
	  	</td>
	  	<td class="row2" width="62%"><input class="post" type="text" class="post" name="username" id="username" maxlength="50" size="20" value="{GROUP_MODERATOR}" /> &nbsp; <input type="submit" name="usersubmit" value="{L_FIND_USERNAME}" class="liteoption" onClick="window.open('{U_SEARCH_USER}', '_phpbbsearch', 'HEIGHT=250,resizable=yes,WIDTH=400');return false;" /></td>
	</tr>

	<tr> 
	  <td class="row1" width="38%"><span class="gen">{L_GROUP_STATUS}:</span></td>
	  <td class="row2" width="62%"> 
		<input type="radio" name="group_type" id="group_type_o" value="{S_GROUP_OPEN_TYPE}" {S_GROUP_OPEN_CHECKED} /> <label for="group_type_o">{L_GROUP_OPEN}</label> &nbsp;&nbsp;
		  <input type="radio" name="group_type" id="group_type_c" value="{S_GROUP_CLOSED_TYPE}" {S_GROUP_CLOSED_CHECKED} />	<label for="group_type_c">{L_GROUP_CLOSED}</label> &nbsp;&nbsp;
		  <input type="radio" name="group_type" id="group_type_h" value="{S_GROUP_HIDDEN_TYPE}" {S_GROUP_HIDDEN_CHECKED} />	<label for="group_type_h">{L_GROUP_HIDDEN}</label>
	  </td>
	</tr>
	<!-- BEGIN group_edit -->
	<tr> 
		<td class="row1" width="38%">
			<label for="delete_old_moderator">
				<span class="gen">{L_DELETE_MODERATOR}</span>
			</label>
	  		<br />
	  		<span class="gensmall">{L_DELETE_MODERATOR_EXPLAIN}</span>
		</td>
	  	<td class="row2" width="62%">
			<input type="checkbox" name="delete_old_moderator" id="delete_old_moderator" value="1">{L_YES}
		</td>
	</tr>
	<!-- END group_edit -->
	<tr> 
	  <td class="catBottom" colspan="2" align="center"><span class="cattitle">
		<input type="submit" name="group_update" value="{L_SUBMIT}" class="mainoption" />
		&nbsp;&nbsp; 
		<input type="reset" value="{L_RESET}" name="reset" class="liteoption" />
		</span></td>
	</tr>
</table>{S_HIDDEN_FIELDS}</form>
