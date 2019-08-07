<?php

/**
 * Class BaseTemplate
 *
 * @author rendix2
 */
abstract class BaseTemplate
{
    /**
     * @var string $className
     */
    protected $className;

    /**
     * Root template directory.
     *
     * @var string $root
     */
    protected $root;

    /**
     * variable that holds all the data we'll be substituting into
     * the compiled templates.
     * ...
     * This will end up being a multi-dimensional array like this:
     * $this->_tpldata[block.][iteration#][child.][iteration#][child2.][iteration#][variablename] == value
     * if it's a root-level variable, it'll be like this:
     * $this->_tpldata[.][0][varname] == value
     *
     * @var array $_tpldata
     */
    protected $_tpldata;

    /**
     * Hash of filenames for each template handle.
     *
     * @var array $files
     */
    protected $files;

    /**
     * this will hash handle names to the compiled code for that handle.
     *
     * @var array $compiledCode
     */
    protected $compiledCode;

    /**
     * This will hold the uncompiled code for that handle.
     *
     * @var array $uncompiledCode
     */
    protected $uncompiledCode;

    /**
     * Constructor. Simply sets the root dir and init other vars
     *
     * @param string $root
     */
    public function __construct($root = '.')
    {
        $this->setRootDir($root);

        $this->_tpldata       = [];
        $this->files          = [];
        $this->compiledCode   = [];
        $this->uncompiledCode = [];
    }

    /**
     * Destroys this template object. Should be called when you're done with it, in order
     * to clear out the template data so you can load/parse a new template set.
     */
    public function __destruct()
    {
        $this->className      = null;
        $this->_tpldata       = null;
        $this->files          = null;
        $this->root           = null;
        $this->compiledCode   = null;
        $this->uncompiledCode = null;
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
        if (!is_dir($dir)) {
            return false;
        }

        $this->root = $dir;

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
            $blockCount = count($blocks) - 1;
            $str = '$this->_tpldata';

            for ($i = 0; $i < $blockCount; $i++) {
                $str .= '[\'' . $blocks[$i] . '.\']';
                eval('$lastiteration = count(' . $str . ') - 1;');
                $str .= '[' . $lastiteration . ']';
            }

            // Now we add the block that we're actually assigning to.
            // We're adding a new iteration to this block with the given
            // variable assignments.
            $str .= '[\'' . $blocks[$blockCount] . '.\'][] = $vararray;';

            // Now we evaluate this assignment we've built up.
            eval($str);
        } else {
            // Top-level block.
            // Add a new iteration to this block with the variable assignments
            // we were given.
            $this->_tpldata[$blockName . '.'][] = $vararray;
        }

        return true;
    }

    /**
     * Generates a reference to the given variable inside the given (possibly nested)
     * block namespace. This is a string of the form:
     * ' . $this->_tpldata['parent'][$_parent_i]['$child1'][$_child1_i]['$child2'][$_child2_i]...['varname'] . '
     * It's ready to be inserted into an "echo" line in one of the templates.
     * NOTE: expects a trailing "." on the namespace.
     */
    protected function generateBlockVarRef($namespace, $varname)
    {
        // Strip the trailing period.
        $namespace = substr($namespace, 0, -1);

        // Get a reference to the data block for this namespace.
        $varref = $this->generateBlockDataRef($namespace, true);

        // Prepend the necessary code to stick this in an echo line.
        // Append the variable reference.
        $varref .= '[\'' . $varname . '\']';
        $varref = '\' . ( ( isset(' . $varref . ') ) ? ' . $varref . ' : \'\' ) . \'';

        return $varref;
    }

    /**
     * Root-level variable assignment. Adds to current assignments, overriding
     * any existing variable assignment with the same name.
     */
    public function assignVars($vararray)
    {
        foreach ($vararray as $key => $val) {
            $this->_tpldata['.'][0][$key] = $val;
        }

        return true;
    }

    /**
     * Root-level variable assignment. Adds to current assignments, overriding
     * any existing variable assignment with the same name.
     */
    public function assignVar($varname, $varval)
    {
        $this->_tpldata['.'][0][$varname] = $varval;

        return true;
    }

    /**
     * Generates a reference to the array of data values for the given
     * (possibly nested) block namespace. This is a string of the form:
     * $this->_tpldata['parent'][$_parent_i]['$child1'][$_child1_i]['$child2'][$_child2_i]...['$childN']
     *
     * If $include_last_iterator is true, then [$_childN_i] will be appended to the form shown above.
     * NOTE: does not expect a trailing "." on the blockname.
     */
    abstract protected function generateBlockDataRef($blockname, $include_last_iterator);
}
