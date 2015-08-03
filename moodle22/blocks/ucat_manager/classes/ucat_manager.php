<?php
namespace block_ucat_manager;

class ucat_manager {
    const COMPONENT = 'block_ucat_manager';

    const CAP_MANAGE = 'block/ucat_manager:manage';

    public static function str($identifier, $a = null) {
        return get_string($identifier, self::COMPONENT, $a);
    }

    public static function get_user_fields($tableprefix = null, $includepicture = false) {
        $o = '';

        if (function_exists('get_all_user_name_fields'))
            $o .= get_all_user_name_fields(true, $tableprefix);
        else {
            $prefixdot = '';
            if ($tableprefix)
                $prefixdot = $tableprefix . '.';

            $o .= $prefixdot . 'firstname, ' . $prefixdot . 'lastname';
        }

        if ($includepicture)
            $o .= ', ' . \user_picture::fields($tableprefix, null, 'pic_id');

        return $o;
    }
}
