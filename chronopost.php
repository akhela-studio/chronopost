<?php

/**
 *
 *  Plugin Name: Chronopost
 *  Description: Expose Chronopost API to build Maps
 *  Version: 0.0.1
 *  Author: Akhela
 */

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

use Chronopost\Controller;
use Chronopost\Helper\Options;

define('CHRONO_PLUGIN_VERSION', '0.0.1');
define('CHRONO_PLUGIN_URL', trim(plugin_dir_url( __FILE__ ), '/'));

class Chronopost{

    private static $isConfigured;

    /**
     * initialize
     *
     * Sets up the Meta Steroids
     *
     * @return  void
     */
    function initialize() {

        include __DIR__.'/vendor/autoload.php';

        new Controller\Settings();
        new Controller\Webservices();
    }

    public static function isConfigured()
    {
        if( is_null(self::$isConfigured) )
            self::$isConfigured = Options::get('token') && Options::get('domain');

        return self::$isConfigured;
    }
}


function chronopost() {

    global $chronopost;

    if ( ! isset( $chronopost ) ) {

        $chronopost = new Chronopost();
        $chronopost->initialize();
    }

    return $chronopost;
}

if( ( defined('WP_INSTALLING') && WP_INSTALLING ) || !defined('WPINC') )
    return;

chronopost();

