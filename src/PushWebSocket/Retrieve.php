<?php
/**
 * Created by PhpStorm.
 * User: ervin
 * Date: 6/8/18
 * Time: 10:04 PM
 */

namespace PushWebSocket;


class Retrieve
{

    /**
     * @param string $parameter
     * @return null|mixed
     */
    public static function get($parameter)
    {
        if (empty($parameter)){

            return null;
        }

        if (!isset($_GET[$parameter])){

            return null;
        }

        return $_GET[$parameter];
    }

    /**
     * @param string $parameter
     * @return null|mixed
     */
    public static function post($parameter)
    {
        if (empty($parameter)){

            return null;
        }

        if (!isset($_GET[$parameter])){

            return null;
        }

        return $_GET[$parameter];
    }
}