<?php
/**
 *
 * Created by PhpStorm.
 * Filename: RouterFactory.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 13:43
 */

namespace phpBB2\App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    use Nette\StaticClass;

    /**
     * @return Nette\Application\IRouter
     */
    public static function createRouter()
    {
        $router = new RouteList;
        $router[] = new Route('<presenter>[/<id \d+>]/<action>', 'Admin:Homepage:default');
        $router[] = new Route('<presenter>[/<id>]/<action>', 'Admin:Homepage:default');
        return $router;
    }
}
