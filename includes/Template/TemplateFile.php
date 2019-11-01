<?php
/***************************************************************************
 *                              template.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: template.php,v 1.7 2002/01/28 19:12:37 psotfx Exp $
 *
 ***************************************************************************/

use Nette\Utils\FileSystem;

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
 * Updated 9th June 2003 - psoTFX
 * Backported various aspects of 2.2 template class
 *
 * This class caches in files
 */
class TemplateFile extends BaseTemplate {

    /**
     * @var string $cacheRoot
     */
    private $cacheRoot;

    /**
     * @var array $filename
     */
    private $filename;

    /**
     * @var string $cacheDir
     */
    private $cacheDir;

    /**
     * Constructor. Simply sets the root dir and init other vars
     *
     * @param string $root
     */
    public function __construct($root = '.')
    {
        $sep = DIRECTORY_SEPARATOR;

        $this->cacheRoot = 'temp' . $sep . 'templates' . $sep;
        $this->className = 'TemplateFile';

        parent::__construct($root);
    }

    /**
     * Destroys this template object. Should be called when you're done with it, in order
     * to clear out the template data so you can load/parse a new template set.
     */
    public function __destruct()
    {
        parent::__destruct();

        $this->cacheDir  = null;
        $this->cacheRoot = null;
        $this->filename  = null;
    }

    /**
     * Sets the template root directory for this Template object.
     *
     * @param string $dir
     *
     * @return bool
     */
	public function setRootDir($dir)
	{
		global $phpbb_root_path;

		if (is_file($dir) || is_link($dir)) {
			return false;
		}

        $sep = DIRECTORY_SEPARATOR;
		$realCacheRootPath = phpbb_realpath($phpbb_root_path . $this->cacheRoot);

		// check if exists cache root dir
		if (!file_exists($phpbb_root_path . $this->cacheRoot)) {
            @umask(0);
		    FileSystem::createDir($phpbb_root_path . $this->cacheRoot);

		    // recount path
            $realCacheRootPath = phpbb_realpath($phpbb_root_path . $this->cacheRoot);
        }

		// last key in $dirs is template name
		$dirs = explode($sep, $dir);
		$dirsCount = count($dirs);

		$this->root     = phpbb_realpath($dir);
        $this->cacheDir = $realCacheRootPath . $sep . $dirs[$dirsCount - 1] . $sep;

		// check if exists template cache dir
		if (!file_exists($this->cacheDir)) {
            @umask(0);
		    FileSystem::createDir($this->cacheDir);
        }

		// check if exists admin dir in template cache dir
        if (!file_exists($this->cacheDir . 'admin' . $sep)) {
			@umask(0);

			FileSystem::createDir($this->cacheDir . 'admin' . $sep);
		}

		return true;
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
			$this->filename[$handle] = $filename;
			$this->files[$handle] = $this->makeFileName($filename);
		}
	}

	/**
	 * Load the file for the handle, compile the file,
	 * and run the compiled code. This will print out
	 * the results of executing the template.
	 */
	public function pparse($handle)
	{
		$cache_file = $this->cacheDir . $this->filename[$handle] . '.php';

		if (@filemtime($cache_file) === @filemtime($this->files[$handle])) {
			$_str = '';

            require_once $cache_file;

			if ($_str !== '') {
				echo $_str;
			}
		} else {
			if (!$this->loadFile($handle)) {
				die("Template->pparse(): Couldn't load template file for handle $handle");
			}

			// Actually compile the code now.
            $this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle]);

			$fp = fopen($cache_file, 'wb+');
			fwrite ($fp, '<?php' . "\n" . $this->compiledCode[$handle] . "\n?" . '>');
			fclose($fp);

			touch($cache_file, filemtime($this->files[$handle]));
			@chmod($cache_file, 0777);

			eval($this->compiledCode[$handle]);
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
		$cache_file = $this->cacheDir . $this->filename[$handle] . '.php';

		if (@filemtime($cache_file) === @filemtime($this->files[$handle])) {
			$_str = '';

			require_once $cache_file;
		} else {
			if (!$this->loadFile($handle)) {
				die("Template->pparse(): Couldn't load template file for handle $handle");
			}

			$code = $this->compile($this->uncompiledCode[$handle], true, '_str');

			$fp = fopen($cache_file, 'wb+');
			fwrite ($fp, '<?php' . "\n" . $code . "\n?" . '>');
			fclose($fp);

			touch($cache_file, filemtime($this->files[$handle]));
			@chmod($cache_file, 0777);

			// Compile It, With The "no Echo Statements" Option On.
			$_str = '';
			// evaluate the variable assignment.
			eval($code);
		}

		// assign the value of the generated variable to the given varname.
		$this->assignVar($varname, $_str);

		return true;
	}

	/**
	 * Block-level variable assignment. Adds a new block iteration with the given
	 * variable assignments. Note that this should only be called once per block
	 * iteration.
	 */
	public function assignBlockVars($blockName, $vararray)
	{
		if (false !== strpos($blockName, '.')) {
			// Nested block.
			$blocks = explode('.', $blockName);
			$blockcount = count($blocks) - 1;
			$str = &$this->_tpldata;

			for ($i = 0; $i < $blockcount; $i++) {
				$str = &$str[$blocks[$i]]; 
				$str = &$str[count($str) - 1]; 
			} 

			// Now we add the block that we're actually assigning to.
			// We're adding a new iteration to this block with the given
			// variable assignments.
			$str[$blocks[$blockcount]][] = $vararray;
		} else {
			// Top-level block.
			// Add a new iteration to this block with the variable assignments
			// we were given.
            $this->_tpldata[$blockName][] = $vararray;
		}

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
		if (substr($filename, 0, 1) !== '/') {
            $filename = phpbb_realpath($this->root . $sep . $filename);
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
		$concat = (!$do_not_echo) ? ',' : '.';

		// replace \ with \\ and then ' with \'.
		$code = str_replace('\\', '\\\\', $code);
		$code = str_replace('\'', '\\\'', $code);

		// change template varrefs into PHP varrefs

		// This one will handle varrefs WITH namespaces
		$varrefs = [];
		preg_match_all('#\{(([a-z0-9\-_]+?\.)+?)([a-z0-9\-_]+?)\}#is', $code, $varrefs);
		$varcount = count($varrefs[1]);

		for ($i = 0; $i < $varcount; $i++) {
			$namespace = $varrefs[1][$i];
			$varname = $varrefs[3][$i];
			$new = $this->generateBlockVarRef($namespace, $varname, $concat);

			$code = str_replace($varrefs[0][$i], $new, $code);
		}

		// This will handle the remaining root-level varrefs
		$code = preg_replace('#\{([a-z0-9\-_]*?)\}#is', "' $concat ((isset(\$this->_tpldata['.'][0]['\\1'])) ? \$this->_tpldata['.'][0]['\\1'] : '') $concat '", $code);

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
                        $codeLine = '$_' . $a[1] . '_count = (isset($this->_tpldata[\'' . $n[1] . '\'])) ?  count($this->_tpldata[\'' . $n[1] . '\']) : 0;';
                        $codeLine .= 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $codeLine .= '{';
					} else {
						// This block is nested.
						// Generate a namespace string for this block.
						$namespace = substr(implode('.', $block_names), 0, -1);

						// strip leading period from root level..
						$namespace = substr($namespace, 2);

						// Get a reference to the data array for this block that depends on the
						// current indices of all parent blocks.
						$varref = $this->generateBlockDataRef($namespace, false);

						// Create the for loop code to iterate over this block.
                        $codeLine = '$_' . $a[1] . '_count = (isset(' . $varref . ')) ? count(' . $varref . ') : 0;';
                        $codeLine .= 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $codeLine .= '{';
					}

					// We have the end of a block.
					unset($block_names[$block_nesting_level]);
					$block_nesting_level--;
					$codeLines[$i] .= '} // END ' . $n[1];
					$m[0] = $n[0];
					$m[1] = $n[1];
				} else {
					// We have the start of a block.
					$block_nesting_level++;
					$block_names[$block_nesting_level] = $m[1];

					if ($block_nesting_level < 2) {
						// Block is not nested.
                        $codeLine = '$_' . $m[1] . '_count = (isset($this->_tpldata[\'' . $m[1] . '\'])) ? count($this->_tpldata[\'' . $m[1] . '\']) : 0;';
                        $codeLine .= 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $codeLine .= '{';
					} else {
						// This block is nested.

						// Generate a namespace string for this block.
						$namespace = implode('.', $block_names);

						// strip leading period from root level..
						$namespace = substr($namespace, 2);

						// Get a reference to the data array for this block that depends on the
						// current indices of all parent blocks.
						$varref = $this->generateBlockDataRef($namespace, false);

						// Create the for loop code to iterate over this block.
                        $codeLine = '$_' . $m[1] . '_count = (isset(' . $varref . ')) ? count(' . $varref . ') : 0;';
                        $codeLine .= 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $codeLine.= '{';
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
                    $codeLine = '$' . $retvar . ".= '" . $codeLine . "\n';\n";
				} else {
                    $codeLine = "echo '" . $codeLine . "\n';\n";
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
	protected function generateBlockVarRef($namespace, $varname, $concat = null)
	{
		// Strip the trailing period.
		$namespace = substr($namespace, 0, -1);

		// Get a reference to the data block for this namespace.
		$varref = $this->generateBlockDataRef($namespace, true);

		// Prepend the necessary code to stick this in an echo line.
		// Append the variable reference.
		$varref .= "['$varname']";
		$varref = "' $concat ((isset($varref)) ? $varref : '') $concat '";

		return $varref;
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
			$varRef .= "['" . $blocks[$i] . "'][\$_" . $blocks[$i] . '_i]';
		}

		// Add the block reference for the last child.
		$varRef .= "['" . $blocks[$blockCount] . "']";

		// Add the iterator for the last child if required.
		if ($include_last_iterator) {
			$varRef .= '[$_' . $blocks[$blockCount] . '_i]';
		}

		return $varRef;
	}
}
