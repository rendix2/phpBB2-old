<?php
/**
 *
 * Created by PhpStorm.
 * Filename: IpHelper.php
 * User: Tomáš Babický
 * Date: 07.03.2021
 * Time: 16:40
 */

namespace phpBB2\App\Helpers;


class IpHelper
{

    public static function encode($dotquad_ip)
    {
        $ip_sep = explode('.', $dotquad_ip);
        return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
    }

    public static function decode($int_ip)
    {
        $hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
        return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
    }

}