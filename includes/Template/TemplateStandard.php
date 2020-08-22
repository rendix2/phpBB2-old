<?php
/***************************************************************************
 *                              template.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: template.php 5142 2005-05-06 20:50:13Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

/**
 * Template class. By Nathan Codding of the phpBB group.
 * The interface was originally inspired by PHPLib templates,
 * and the template file formats are quite similar.
 *
 * This class does not use cache
 *
 */
class TemplateStandard extends BaseTemplate
{
    /**
     * Constructor. Simply sets the root dir and init other vars
     *
     * @param string $root
     */
	public function __construct($root = '.')
	{
        parent::__construct($root);

		$this->className = 'Template';
	}

    /**
     * Sets the template filenames for handles. $filename_array
     * should be a hash of handle => filename pairs.
     *
     * @param array $filename_array
     *
     * @return bool
     */
	public function setFileNames(array $filename_array)
	{
		foreach ($filename_array as $handle => $filename) {
			$this->files[$handle] = $this->makeFileName($filename);
		}

		return true;
	}

	/**
	 * Load the file for the handle, compile the file,
	 * and run the compiled code. This will print out
	 * the results of executing the template.
	 */
	public function pparse($handle)
	{
		if (!$this->loadFile($handle)) {
			die("Template->pparse(): Couldn't load template file for handle $handle");
		}

		// actually compile the template now.
		if (!isset($this->compiledCode[$handle]) || empty($this->compiledCode[$handle])) {
			// Actually compile the code now.
            $this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle]);
		}

		// Run the compiled code.
		eval($this->compiledCode[$handle]);

		return true;
	}

	/**
	 * Inserts the uncompiled code for $handle as the
	 * value of $varname in the root-level. This can be used
	 * to effectively include a template in the middle of another
	 * template.
	 * Note that all desired assignments to the variables in $handle should be done
	 * BEFORE calling this function.
	 */
	public function assignVarFromHandle($varname, $handle)
	{
		if (!$this->loadFile($handle)) {
			die("Template->assignVarFromHandle(): Couldn't load template file for handle $handle");
		}

		// Compile it, with the "no echo statements" option on.
		$_str = '';
		$code = $this->compile($this->uncompiledCode[$handle], true, '_str');

		// evaluate the variable assignment.
		eval($code);
		// assign the value of the generated variable to the given varname.
		$this->assignVar($varname, $_str);

		return true;
	}

	/**
	 * Generates a full path+filename for the given filename, which can either
	 * be an absolute name, or a name relative to the rootdir for this Template
	 * object.
	 */
	public function makeFileName($filename)
	{
	    $sep = DIRECTORY_SEPARATOR;

		// Check if it's an absolute or relative path.
		if (mb_substr($filename, 0, 1) !== DIRECTORY_SEPARATOR) {
       		$filename = ($rp_filename = realpath($this->root . $sep . $filename)) ? $rp_filename : $filename;
		}

		if (!file_exists($filename)) {
			die("Template->makeFileName(): Error - file $filename does not exist");
		}

		return $filename;
	}

	/**
	 * If not already done, load the file for the given handle and populate
	 * the uncompiled_code[] hash with its code. Do not compile.
	 */
	private function loadFile($handle)
	{
		// If the file for this handle is already loaded and compiled, do nothing.
		if (isset($this->uncompiledCode[$handle]) && !empty($this->uncompiledCode[$handle])) {
			return true;
		}

		// If we don't have a file assigned to this handle, die.
		if (!isset($this->files[$handle])) {
			die("Template->loadFile(): No file specified for handle $handle");
		}

		$filename = $this->files[$handle];
		$str = implode('', @file($filename));

		if (empty($str)) {
			die("Template->loadFile(): File $filename for handle $handle is empty");
		}

        $this->uncompiledCode[$handle] = $str;

		return true;
	}

	/**
	 * Compiles the given string of code, and returns
	 * the result in a string.
	 * If "do_not_echo" is true, the returned code will not be directly
	 * executable, but can be used as part of a variable assignment
	 * for use in assign_code_from_handle().
	 */
	private function compile($code, $do_not_echo = false, $retvar = '')
	{
		// replace \ with \\ and then ' with \'.
		$code = str_replace('\\', '\\\\', $code);
		$code = str_replace('\'', '\\\'', $code);

		// change template varrefs into PHP varrefs

		// This one will handle varrefs WITH namespaces
		$varrefs = [];
		preg_match_all('#\{(([a-z0-9\-_]+?\.)+?)([a-z0-9\-_]+?)\}#is', $code, $varrefs);
		$varCount = count($varrefs[1]);

		for ($i = 0; $i < $varCount; $i++) {
			$namespace = $varrefs[1][$i];
			$varname = $varrefs[3][$i];
			$new = $this->generateBlockVarRef($namespace, $varname);

			$code = str_replace($varrefs[0][$i], $new, $code);
		}

		// This will handle the remaining root-level varrefs
		$code = preg_replace('#\{([a-z0-9\-_]*?)\}#is', '\' . ( ( isset($this->_tpldata[\'.\'][0][\'\1\']) ) ? $this->_tpldata[\'.\'][0][\'\1\'] : \'\' ) . \'', $code);

		// Break it up into lines.
		$codeLines = explode("\n", $code);

		$block_nesting_level = 0;
		$block_names = [];
		$block_names[0] = '.';

		// Second: prepend echo ', append ' . "\n"; to each line.

		foreach ($codeLines as &$codeLine) {
            $codeLine = rtrim($codeLine);

			if (preg_match('#<!-- BEGIN (.*?) -->#', $codeLine, $m)) {
				$n[0] = $m[0];
				$n[1] = $m[1];

				// Added: dougk_ff7-Keeps templates from bombing if begin is on the same line as end.. I think. :)
				if (preg_match('#<!-- END (.*?) -->#', $codeLine, $n)) {
					$block_nesting_level++;
					$block_names[$block_nesting_level] = $m[1];

					if ($block_nesting_level < 2) {
						// Block is not nested.
                        $codeLine = '$_' . $n[1] . '_count = ( isset($this->_tpldata[\'' . $n[1] . '.\']) ) ?  count($this->_tpldata[\'' . $n[1] . '.\']) : 0;';
                        $codeLine .= "\n" . 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $codeLine .= "\n" . '{';
					} else {
						// This block is nested.
						// Generate a namespace string for this block.
						$namespace = implode('.', $block_names);

						// strip leading period from root level..
						$namespace = mb_substr($namespace, 2);

						// Get a reference to the data array for this block that depends on the
						// current indices of all parent blocks.
						$varref = $this->generateBlockDataRef($namespace, false);

						// Create the for loop code to iterate over this block.
                        $codeLine = '$_' . $n[1] . '_count = ( isset(' . $varref . ') ) ? count(' . $varref . ') : 0;';
                        $codeLine .= "\n" . 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $codeLine .= "\n" . '{';
					}

					// We have the end of a block.
					unset($block_names[$block_nesting_level]);
					$block_nesting_level--;
                    $codeLine .= '} // END ' . $n[1];
					$m[0] = $n[0];
					$m[1] = $n[1];
				} else {
					// We have the start of a block.
					$block_nesting_level++;
					$block_names[$block_nesting_level] = $m[1];

					if ($block_nesting_level < 2) {
						// Block is not nested.
                        $codeLine = '$_' . $m[1] . '_count = ( isset($this->_tpldata[\'' . $m[1] . '.\']) ) ? count($this->_tpldata[\'' . $m[1] . '.\']) : 0;';
                        $codeLine .= "\n" . 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $codeLine .= "\n" . '{';
					} else {
						// This block is nested.

						// Generate a namespace string for this block.
						$namespace = implode('.', $block_names);

						// strip leading period from root level..
						$namespace = mb_substr($namespace, 2);

						// Get a reference to the data array for this block that depends on the
						// current indices of all parent blocks.
						$varref = $this->generateBlockDataRef($namespace, false);

						// Create the for loop code to iterate over this block.
                        $codeLine = '$_' . $m[1] . '_count = ( isset(' . $varref . ') ) ? count(' . $varref . ') : 0;';
                        $codeLine .= "\n" . 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $codeLine .= "\n" . '{';
					}
				}
			} elseif (preg_match('#<!-- END (.*?) -->#', $codeLine, $m)) {
				// We have the end of a block.
				unset($block_names[$block_nesting_level]);
				$block_nesting_level--;
                $codeLine = '} // END ' . $m[1];
			} else {
				// We have an ordinary line of code.
                if ($do_not_echo) {
                    $codeLine = '$' . $retvar . '.= \'' . $codeLine . '\' . "\\n";';
                } else {
                    $codeLine = 'echo \'' . $codeLine . '\' . "\\n";';
                }
			}
		}

        unset($codeLine);

		// Bring it back into a single string of lines of code.
		return implode("\n", $codeLines);
	}

    /**
     * @inheritDoc
     */
	protected function generateBlockDataRef($blockname, $include_last_iterator)
	{
		// Get an array of the blocks involved.
		$blocks = explode('.', $blockname);
		$blockCount = count($blocks) - 1;
		$varRef = '$this->_tpldata';

		// Build up the string with everything but the last child.
		for ($i = 0; $i < $blockCount; $i++) {
			$varRef .= '[\'' . $blocks[$i] . '.\'][$_' . $blocks[$i] . '_i]';
		}

		// Add the block reference for the last child.
		$varRef .= '[\'' . $blocks[$blockCount] . '.\']';

		// Add the iterator for the last child if required.
		if ($include_last_iterator) {
			$varRef .= '[$_' . $blocks[$blockCount] . '_i]';
		}

		return $varRef;
	}
}
