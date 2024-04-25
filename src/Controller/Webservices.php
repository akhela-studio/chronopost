<?php

namespace Chronopost\Controller;

use Chronopost\Helper\Options;
use Chronopost\Service\Webservice;

class Webservices
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    /**
     * Start up
     */
    public function __construct()
    {
        $this->options = Options::get();

        $this->add_routes();
    }

    private function add_routes()
    {
        add_action('wp_ajax_get_relay_points', [$this, 'get_relay_points']);
        add_action('wp_ajax_nopriv_get_relay_points', [$this, 'get_relay_points']);
    }

    public function get_relay_points()
    {
        $args = [
            'shipping_method'=>sanitize_text_field($_POST['shipping_method']??'chronorelais13'),
            'zip_code'=>sanitize_text_field($_POST['zip_code']??''),
            'city'=>sanitize_text_field($_POST['city']??''),
            'address'=>sanitize_text_field($_POST['address']??''),
            'country_code'=>sanitize_text_field($_POST['country_code']??'FR')
        ];

        $ws = new Webservice();
        $pickup_relays = $ws->getRelayPointsByAddress($args);

        wp_send_json($pickup_relays);
    }
}
