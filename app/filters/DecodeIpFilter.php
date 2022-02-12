<?php
/**
 *
 * Created by PhpStorm.
 * Filename: DecodeIpFilter.php
 * User: Tomáš Babický
 * Date: 07.03.2021
 * Time: 16:36
 */

class DecodeIpFilter
{

    public function __invoke($ip)
    {
        return \phpBB2\App\Helpers\IpHelper::decode($ip);
    }

}