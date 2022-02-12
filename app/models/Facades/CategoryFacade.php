<?php
/**
 *
 * Created by PhpStorm.
 * Filename: CategoryFacade.php
 * User: Tomáš Babický
 * Date: 22.03.2021
 * Time: 15:31
 */

namespace phpBB2\App\Models\Facades;

use phpBB2\Models\CategoriesManager;
use phpBB2\Models\ForumsManager;

/**
 * Class CategoryFacade
 *
 * @package phpBB2\App\Models\Facades
 */
class CategoryFacade
{
    /**
     * @var CategoriesManager $categoriesManager
     */
    private $categoriesManager;

    /**
     * @var ForumsManager $forumsManager
     */
    private $forumsManager;


    /**
     * CategoryFacade constructor.
     *
     * @param CategoriesManager $categoriesManager
     * @param ForumsManager $forumsManager
     */
    public function __construct(
        CategoriesManager $categoriesManager,
        ForumsManager $forumsManager
    ) {
        $this->categoriesManager = $categoriesManager;
        $this->forumsManager = $forumsManager;
    }

    public function join($categories, array $forums)
    {
        foreach ($categories as $category) {
            $category->forums = [];

            foreach ($forums as $forum) {
                if ($category->cat_id === $forum->cat_id) {
                    $category->forums[] = $forum;
                }
            }
        }

        return $categories;
    }

    public function getAll()
    {
        $categories = $this->categoriesManager->getAll();
        $forums = $this->forumsManager->getAll();

        return $this->join($categories, $forums);
    }
}