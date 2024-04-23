<?php

namespace Chronopost\Controller;

use Chronopost\Helper\Options;

class Settings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    private $fields;
    private $configured=false;

    /**
     * Start up
     */
    public function __construct()
    {
        $this->options = Options::get();

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    public function getFields(){
        
        $this->fields = [
            'enable'=>[
                'label'=>__('Settings', 'chronopost'),
                'fields'=>[
                    'max_pickup_relay_number'=>['label'=>__('Max pickup relay', 'chronopost'), 'type'=>'number', 'placeholder'=>"10"],
                    'max_distance_search'=>['label'=>__('Max distance relay', 'chronopost'), 'type'=>'number', 'placeholder'=>"1"],
                    'default_weight'=>['label'=>__('Default package weight', 'chronopost'), 'type'=>'number', 'placeholder'=>"2000"],
                ]
            ]
        ];

        if( !$this->configured ){

            $this->fields = array_merge(['chronopost'=>[
                'label'=>__('Contract', 'chronopost'),
                'fields'=>[
                    'account_number'=>['label'=>__('Account number', 'chronopost'), 'type'=>'text', 'placeholder'=>"19869502"],
                    'account_password'=>['label'=>__('Chronopost password', 'chronopost'), 'type'=>'text', 'placeholder'=>"255562"],
                    'account_name'=>['label'=>__('Chronopost password', 'chronopost'), 'type'=>'text', 'placeholder'=>"Test"]
                ]
            ]], $this->fields);
        }
    }

    /**
     * Add options page
     */
    public function admin_menu()
    {
        // This page will be under "Settings"
        add_options_page(
            __('Settings Admin', 'chronopost'),
            __('Chronopost', 'chronopost'),
            'manage_options',
            'chronopost',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <h1><?=__('Chronopost', 'chronopost')?></h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( 'chronopost' );
                do_settings_sections( 'chronopost-admin' );
                submit_button(__('Save'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function admin_init()
    {
        global $pagenow;

        if( ($_GET['page']??'') !== 'chronopost' && $pagenow != 'options.php' )
            return;

        $this->configured = \Chronopost::isConfigured();

        $this->getFields();

        register_setting(
            'chronopost', // Option group
            'chronopost', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section('chronopost', $this->connected?__('Connected', 'chronopost'):__('Connect', 'chronopost'), function(){

            if( $this->configured )
                echo __('Modify your <a href="https://help.shopify.com/en/manual/apps/private-apps" target="_blank">Chronopost credentials</a> below.', 'chronopost');
            else
                echo __('Enter your <a href="https://help.chronopost.com/en/articles/4325485-how-to-create-an-api-key" target="_blank">Chronopost credentials</a> below.', 'chronopost');

        },'chronopost-admin');

        foreach ($this->fields as $section=>$data){

            add_settings_section( 'chronopost_'.$section, $data['label'], function() use ($data, $section){

                foreach ($data['fields'] as $key=>$field){

                    if( $field['type'] == 'hidden' ){

                        $name = ($field['namespace']??true) ? 'chronopost['.$key.']' : $key;
                        $value = $field['value'] ?? (($field['namespace']??true) ? $this->options[$key]??'' : get_option($key));

                        echo '<input type="hidden" name="'.$name.'" value="'.$value.'"/>';
                    }
                    else{

                        add_settings_field('chronopost_'.$key, __($field['label']), function() use($key, $field)
                        {
                            $name = ($field['namespace']??true) ? 'chronopost['.$key.']' : $key;
                            $value = $field['value'] ?? (($field['namespace']??true) ? $this->options[$key]??'' : get_option($key));

                            if( $field['type'] == 'checkbox' ){

                                echo '<input type="checkbox" name="'.$name.'" '.(($field['required']??false)?'required':'').' value="1" '.($value?'checked':'').'/>';
                            }
                            elseif( $field['type'] == 'select' ){

                                echo '<select name="'.$name.'" '.(($field['required']??false)?'required':'').'>';
                                foreach ($field['options'] as $option)
                                    echo '<option value="'.$option['value'].'" '.($option['value']==$value?'selected':'').'>'.$option['name'].'</option>';

                                echo '</select>';
                            }
                            elseif($field['type'] == 'password'){

                                printf('<input type="password" placeholder="'.($field['placeholder']??'').'" '.($field['read_only']??false?'readonly':'').' name="'.$name.'" '.($field['required']??false?'required':'').' value="%s"/>', esc_attr($value));
                            }
                            elseif( $field['type'] == 'textarea'){

                                printf('<textarea placeholder="'.($field['placeholder']??'').'" '.($field['read_only']??false?'readonly':'').' name="'.$name.'" '.($field['required']??false?'required':'').'>%s</textarea>', esc_attr($value));
                            }
                            else{

                                printf('<input type="'.$field['type'].'" placeholder="'.($field['placeholder']??'').'" '.($field['read_only']??false?'readonly':'').' name="'.$name.'" '.($field['required']??false?'required':'').' value="%s"/>', esc_attr($value));
                            }
                        },'chronopost-admin','chronopost_'.$section);
                    }
                }
            },'chronopost-admin');
        }
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return array
     */
    public function sanitize( $input )
    {
        $new_input = array();

        foreach ($this->fields as $section=>$data){

            foreach ($data['fields'] as $key=>$field){

                if( isset( $input[$key] ) ){

                    if( $key == 'token' && empty($input[$key]) )
                        $input[$key] = $this->options[$key];

                    if( $key == 'single-use-token' && empty($input[$key]) )
                        $input[$key] = $this->options[$key];

                    $new_input[$key] = sanitize_text_field( $input[$key] );
                }
            }
        }

        return $new_input;
    }
}
