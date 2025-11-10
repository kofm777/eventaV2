<?php

namespace App\Helpers;

class SessionHelper
{
    public static function get($key, $default = null)
    {
        return session()->get($key, $default);
    }
}
