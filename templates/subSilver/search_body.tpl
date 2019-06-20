<form action="{S_SEARCH_ACTION}" method="POST">
    <table width="100%" cellspacing="2" cellpadding="2" border="0" align="center">
        <tr>
            <td align="left"><span class="nav"><a href="{U_INDEX}" class="nav">{L_INDEX}</a></span></td>
        </tr>
    </table>

    <table class="forumline" width="100%" cellpadding="4" cellspacing="1" border="0">
        <tr>
            <th class="thHead" colspan="4" height="25">{L_SEARCH_QUERY}</th>
        </tr>
        <tr>
            <td class="row1" colspan="2" width="50%">
                <label for="search_keywords">
                    <span class="gen">{L_SEARCH_KEYWORDS}:</span>
                </label>
                <br/>
                <span class="gensmall">{L_SEARCH_KEYWORDS_EXPLAIN}</span>
            </td>
            <td class="row2" colspan="2" valign="top">
			<span class="genmed">
				<input type="text" style="width: 300px" class="post" name="search_keywords" id="search_keywords" size="30"/>
				<br/>

				<input type="radio" name="search_terms" id="search_terms_any" value="any" checked="checked"/>
                <label for="search_terms_any">{L_SEARCH_ANY_TERMS}</label>

                <br/>

				<input type="radio" name="search_terms" id="search_terms_all" value="all"/>
                <label for="search_terms_all">{L_SEARCH_ALL_TERMS}</label>
			</span>
            </td>
        </tr>
        <tr>
            <td class="row1" colspan="2">
                <label for="search_author">
                    <span class="gen">{L_SEARCH_AUTHOR}:</span>
                </label>

                <br/>

                <span class="gensmall">{L_SEARCH_AUTHOR_EXPLAIN}</span>
            </td>
            <td class="row2" colspan="2" valign="middle">
			<span class="genmed">
				<input type="text" style="width: 300px" class="post" name="search_author" id="search_author" size="30"/>
			</span>
            </td>
        </tr>
        <tr>
            <th class="thHead" colspan="4" height="25">{L_SEARCH_OPTIONS}</th>
        </tr>
        <tr>
            <td class="row1" align="right">
                <label for="search_forum">
                    <span class="gen">{L_FORUM}:&nbsp;</span>
                </label>
            </td>
            <td class="row2">
			<span class="genmed">
				<select class="post" name="search_forum" id="search_forum">{S_FORUM_OPTIONS}</select>
			</span>
            </td>
            <td class="row1" align="right" nowrap="nowrap">
                <label for="search_time">
                    <span class="gen">{L_SEARCH_PREVIOUS}:&nbsp;</span>
                </label>
            </td>
            <td class="row2" valign="middle">
			<span class="genmed">
				<select class="post" name="search_time" id="search_time">{S_TIME_OPTIONS}</select>

                <br/>

				<input type="radio" name="search_fields" id="search_fields_all" value="all" checked="checked"/>
				<label for="search_fields_all">{L_SEARCH_MESSAGE_TITLE}</label>

                <br/>

				<input type="radio" name="search_fields" value="msgonly" id="search_fields_msgonly"/>
				<label for="search_fields_msgonly">{L_SEARCH_MESSAGE_ONLY}</label>
			</span>
            </td>
        </tr>
        <tr>
            <td class="row1" align="right">
                <label for="search_cat">
                    <span class="gen">{L_CATEGORY}:&nbsp;</span>
                </label>
            </td>
            <td class="row2">
                <span class="genmed">
				    <select class="post" name="search_cat" id="search_cat">{S_CATEGORY_OPTIONS}</select>
			    </span>
            </td>
            <td class="row1" align="right">
                <label for="sort_by">
                    <span class="gen">{L_SORT_BY}:&nbsp;</span>
                </label>
            </td>
            <td class="row2" valign="middle" nowrap="nowrap">
			<span class="genmed">
				<select class="post" name="sort_by" id="sort_by">{S_SORT_OPTIONS}</select>

				<br/>

				<input type="radio" name="sort_dir" id="sort_dir_asc" value="ASC"/>
                <label for="sort_dir_asc">{L_SORT_ASCENDING}</label>

                <br/>

				<input type="radio" name="sort_dir" id="sort_dir_desc" value="DESC" checked="checked"/>
                <label for="sort_dir_desc">{L_SORT_DESCENDING}</label>

			</span>&nbsp;
            </td>
        </tr>
        <tr>
            <td class="row1" align="right" nowrap="nowrap">
                <span class="gen">{L_DISPLAY_RESULTS}:&nbsp;</span>
            </td>
            <td class="row2" nowrap="nowrap">
			    <span class="genmed">
    				<input type="radio" name="show_results" id="show_results_posts" value="posts"/>
                    <label for="show_results_posts">{L_POSTS}</label>


				    <input type="radio" name="show_results" id="show_results_topics" value="topics" checked="checked"/>
                    <label for="show_results_topics">{L_TOPICS}</label>
			    </span>
            </td>
            <td class="row1" align="right">
                <label for="return_chars">
                    <span class="gen">{L_RETURN_FIRST}</span>
                </label>
            </td>
            <td class="row2">
			<span class="genmed">
				<select class="post" name="return_chars" id="return_chars">{S_CHARACTER_OPTIONS}</select> {L_CHARACTERS}
			</span>
            </td>
        </tr>
        <tr>
            <td class="catBottom" colspan="4" align="center" height="28">{S_HIDDEN_FIELDS}
                <input class="liteoption" type="submit" value="{L_SEARCH}"/>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="2" cellpadding="2" border="0" align="center">
        <tr>
            <td align="right" valign="middle"><span class="gensmall">{S_TIMEZONE}</span></td>
        </tr>
    </table>

    {F_LOGIN_FORM_TOKEN}
</form>

<table width="100%" border="0">
    <tr>
        <td align="right" valign="top">{JUMPBOX}</td>
    </tr>
</table>
