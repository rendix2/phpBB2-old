<?php
/***************************************************************************
 *                              template.inc
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: template.php,v 1.7 2002/01/28 19:12:37 psotfx Exp $
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
 * This class uses caches in database
 *
 */
class TemplateDatabase extends BaseTemplate
{
    /**
     * @var array $echoCompiled
     */
    private $echoCompiled;

    /**
     * Constructor. Simply sets the root dir and init other vars
     *
     * @param string $root
     */
    public function __construct($root = '.')
    {
        parent::__construct($root);

        $this->className = 'TemplateDatabase';
        $this->echoCompiled = [];
    }

	/**
	 * Destroys this template object. Should be called when you're done with it, in order
	 * to clear out the template data so you can load/parse a new template set.
	 */
	public function __destruct()
	{
        parent::__destruct();

        $this->echoCompiled   = null;
	}

    /**
     * Sets the template filenames for handles. $filename_array
     * should be a hash of handle => filename pairs.
     *
     * @param array $filename_array
     */
	public function setFileNames(array $filename_array)
	{
        foreach ($filename_array as $handle => $filename) {
            $this->files[$handle] = $this->makeFileName($filename);
        }

        $rows = dibi::select('*')
            ->from(Tables::TEMPLATE_CACHE_TABLE)
            ->where('template_name IN %in', $filename_array)
            ->fetchAll();

        foreach ($rows as $row) {
            if ($row->template_cached === filemtime($row->template_name)) {
                $this->compiledCode[$row->template_handle] = $row->template_compile;
                $this->echoCompiled[$row->template_handle] = $row->template_echo;
            }
        }
	}


	/**
	 * Load the file for the handle, compile the file,
	 * and run the compiled code. This will print out
	 * the results of executing the template.
	 */
    public function pparse($handle)
	{
		if (empty($this->compiledCode[$handle])) {
			if (!$this->loadFile($handle)) {
				die("Template->pparse(): Couldn't load template file for handle $handle");
			}

			//
			// Actually compile the code now.
			//
            $this->echoCompiled[$handle] = 1;
            $this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle]);

            $replace_data = [
                'template_name'    => $this->files[$handle],
                'template_handle'  => $handle,
                'template_cached'  => filemtime($this->files[$handle]),
                'template_compile' => $this->compiledCode[$handle]
            ];

            dibi::query('REPLACE INTO %n %v', Tables::TEMPLATE_CACHE_TABLE, $replace_data);
		}

		$_str = '';
		eval($this->compiledCode[$handle]);

		if ($_str !== '') {
			echo $_str;
		}

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
		if (empty($this->compiledCode[$handle])) {
			if (!$this->loadFile($handle)) {
				die("Template->pparse(): Couldn't load template file for handle $handle");
			}

			$code = $this->compile($this->uncompiledCode[$handle], true, '_str');

            $replace_data = [
                'template_name'    => $this->files[$handle],
                'template_handle'  => $handle,
                'template_echo'    => 0,
                'template_cached'  => filemtime($this->files[$handle]),
                'template_compile' => $code
            ];

            dibi::query('REPLACE INTO %n %v', Tables::TEMPLATE_CACHE_TABLE, $replace_data);
		} else {
			$code = $this->compiledCode[$handle];
		}

		// Compile It, With The "no Echo Statements" Option On.
		$_str = '';
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
        if (mb_substr($filename, 0, 1) !== '/') {
            $filename = $this->root . $sep . $filename;
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
	public function loadFile($handle)
	{
		// If the file for this handle is already loaded and compiled, do nothing.
        if (!empty($this->uncompiledCode[$handle])) {
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
                        $codeLine = '$_' . $a[1] . '_count = isset($this->_tpldata[\'' . $n[1] . '.\']) ?  count($this->_tpldata[\'' . $n[1] . '.\']) : 0;';
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
                        $codeLine = '$_' . $a[1] . '_count = isset(' . $varref . ') ? count(' . $varref . ') : 0;';
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
                        $codeLine = '$_' . $m[1] . '_count = isset($this->_tpldata[\'' . $m[1] . '.\']) ? count($this->_tpldata[\'' . $m[1] . '.\']) : 0;';
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
                        $codeLine = '$_' . $m[1] . '_count = isset(' . $varref . ') ? count(' . $varref . ') : 0;';
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
