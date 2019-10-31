<?php

use Dibi\Row;
use Latte\Engine;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

/**
 * Class LatteFactory
 *
 * @author rendix2 rendix2@seznam.cz
 */
class LatteFactory
{
    /**
     * @var array|Row $userData
     */
    private $userData;

    /**
     * @var Engine $latte
     */
    private $latte;

    /**
     * @var string $path
     */
    private $path;

    /**
     * @var IStorage $storage
     */
    private $storage;

    /**
     * LatteFactory constructor.
     *
     * @param IStorage  $storage
     * @param array|Row $userData
     */
    public function __construct(IStorage $storage, $userData)
    {
        $this->userData = $userData;

        $this->latte = new Engine();
        $sep = DIRECTORY_SEPARATOR;

        $this->path = __DIR__ . $sep . '..' . $sep;

        $this->latte->setTempDirectory($this->path . 'temp' . $sep . 'cache' . $sep . 'Latte.Latte');
        $this->storage = $storage;
    }

    /**
     * LatteFactory destructor.
     */
    public function __destruct()
    {
        $this->userData = null;
        $this->latte    = null;
        $this->path     = null;
        $this->storage  = null;
    }

    /**
     * @param string $name
     * @param array  $params
     */
    public function render($name, array $params)
    {
        $sep = DIRECTORY_SEPARATOR;

        $cache = new Cache($this->storage, Tables::THEMES_TABLE);
        $key   = Tables::THEMES_TABLE . '_'. $this->userData['user_style'];

        $cachedTheme = $cache->load($key);

        $this->latte->render($this->path . 'templates' . $sep . $cachedTheme->template_name  . $sep . $name, $params);
    }
}