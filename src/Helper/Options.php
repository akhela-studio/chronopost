<?php

namespace Chronopost\Helper;

class Options
{
	public static $data;

	public static function set($option, $value){

        $data = self::get();
        $data[$option] = $value;

		return update_option('chronopost', $data);
	}

	public static function delete($option){

        $data = self::get();
        unset($data[$option]);

        return update_option('chronopost', $data);
	}

    /**
     * @param $option
     * @param $default
     * @return false|array
     */
    public static function get($option=false, $default=false){

        if( !self::$data ){

            $data = get_option('chronopost');

            self::$data = $data;
        }

		if( $option ){

			return self::$data[$option]??$default;
		}
		else{

			return self::$data;
		}
	}
}
