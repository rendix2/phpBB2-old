<?php

namespace phpBB2\Models;

/**
 * Class ConfigManager
 *
 * @author  rendix2
 * @package phpBB2\Models
 */
class ConfigManager extends CrudManager
{
    public function getConfig()
    {
        return $this->getAllPairs('config_value');
    }

}
