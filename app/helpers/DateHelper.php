<?php
/**
 *
 * Created by PhpStorm.
 * Filename: DateHelper.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 15:47
 */

namespace phpBB2\App\Helpers;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Class DateHelper
 *
 * @package phpBB2\App\Helpers
 */
class DateHelper
{

    /**
     * Create date/time from format and timezone
     *
     * @param string $format
     * @param int    $time
     * @param string $time_zone
     *
     * @return string
     * @throws Exception
     */
    public static function createDate($format, $time, $time_zone)
    {
        $started = new DateTime('now', new DateTimeZone($time_zone));
        $started->setTimestamp((int)$time);
        return $started->format($format);
    }
}
