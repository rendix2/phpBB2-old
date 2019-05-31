<?php
/***************************************************************************
*                             sql_parse.php
*                              -------------------
*     begin                : Thu May 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: sql_parse.php 2328 2002-03-18 23:53:12Z psotfx $
*
****************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

/***************************************************************************
*
*	These functions are mainly for use in the db_utilities under the admin
*	however in order to make these functions available elsewhere, specifically
*	in the installation phase of phpBB I have seperated out a couple of 
*	functions into this file.  JLH
*
\***************************************************************************/

//
// remove_comments will strip the sql comment lines out of an uploaded sql file
// specifically for mssql and postgres type files in the install....
//
function remove_comments(&$output)
{
	$lines = explode("\n", $output);
	$output = "";

	// try to keep mem. use down
	$linecount = count($lines);

	$in_comment = false;
	foreach ($lines as $line) {
		if (preg_match("/^\/\*/", preg_quote($line)) ) {
			$in_comment = true;
		}

		if (!$in_comment ) {
			$output .= $line . "\n";
		}

		if (preg_match("/\*\/$/", preg_quote($line)) ) {
			$in_comment = false;
		}
	}

	unset($lines);
	return $output;
}

//
// remove_remarks will strip the sql comment lines out of an uploaded sql file
//
function remove_remarks($sql)
{
	$lines = explode("\n", $sql);
	
	// try to keep mem. use down
	$sql = "";
	
	$linecount = count($lines);
	$output = "";

	foreach ($lines as $i => $line) {
		if (($i != ($linecount - 1)) || (strlen($line) > 0)) {
			if ($line[0] != "#") {
				$output .= $line . "\n";
			} else {
				$output .= "\n";
			}
			// Trading a bit of speed for lower mem. use here.
			$lines[$i] = "";
		}
	}
	
	return $output;
	
}

//
// split_sql_file will split an uploaded sql file into single sql statements.
// Note: expects trim() to have already been run on $sql.
//
function split_sql_file($sql, $delimiter)
{
	// Split up our string into "possible" SQL statements.
	$tokens = explode($delimiter, $sql);

	// try to save mem.
	$sql = "";
	$output = [];
	
	// we don't actually care about the matches preg gives us.
	$matches = [];
	
	// this is faster than calling count($oktens) every time thru the loop.
	$token_count = count($tokens);
	foreach ($tokens as $i => $token) {
		// Don't wanna add an empty string as the last thing in the array.
		if (($i != ($token_count - 1)) || strlen($token > 0)) {
			// This is the total number of single quotes in the token.
			$total_quotes = preg_match_all("/'/", $token, $matches);
			// Counts single quotes that are preceded by an odd number of backslashes, 
			// which means they're escaped quotes.
			$escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $token, $matches);
			
			$unescaped_quotes = $total_quotes - $escaped_quotes;
			
			// If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
			if (($unescaped_quotes % 2) == 0) {
				// It's a complete sql statement.
				$output[] = $token;
				// save memory.
                $token = "";
			} else {
				// incomplete sql statement. keep adding tokens until we have a complete one.
				// $temp will hold what we have so far.
				$temp = $token . $delimiter;
				// save memory..
                $token = "";
				
				// Do we have a complete statement yet? 
				$complete_stmt = false;
				
				for ($j = $i + 1; !$complete_stmt && ($j < $token_count); $j++) {
					// This is the total number of single quotes in the token.
					$total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
					// Counts single quotes that are preceded by an odd number of backslashes, 
					// which means they're escaped quotes.
					$escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);
			
					$unescaped_quotes = $total_quotes - $escaped_quotes;
					
					if (($unescaped_quotes % 2) == 1) {
						// odd number of unescaped quotes. In combination with the previous incomplete
						// statement(s), we now have a complete statement. (2 odds always make an even)
						$output[] = $temp . $tokens[$j];

						// save memory.
						$tokens[$j] = "";
						$temp = "";
						
						// exit the loop.
						$complete_stmt = true;
						// make sure the outer loop continues at the right point.
						$i = $j;
					} else {
						// even number of unescaped quotes. We still don't have a complete statement. 
						// (1 odd and 1 even always make an odd)
						$temp .= $tokens[$j] . $delimiter;
						// save memory.
						$tokens[$j] = "";
					}
					
				} // for..
			} // else
		}
	}

	return $output;
}

?>