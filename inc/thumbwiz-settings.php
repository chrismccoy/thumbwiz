<?php

class Thumbwiz_Settings {

    private $settings_api;

    function __construct() {
        $this->settings_api = new Thumbwiz_Settings_API;

        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );
    }

    function admin_init() {
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );
        $this->settings_api->admin_init();
    }

    function admin_menu() {
        add_options_page( 'Thumbwiz', 'Thumbwiz', 'delete_posts', 'thumbwiz_settings', array($this, 'plugin_page') );
    }

    function get_settings_sections() {
        $sections = array(
            array(
                'id'    => 'thumbwiz_generate_settings',
                'title' => __( 'Thumbwiz Settings', 'thumbwiz' )
            )
        );
        return $sections;
    }

    function get_settings_fields() {
        $settings_fields = array(
            'thumbwiz_generate_settings' => array(
                array(
                    'name'              => 'default_thumbs_to_generate',
                    'label'             => __( 'Number of thumbnails', 'thumbwiz' ),
                    'desc'              => __( 'Number of thumbnails to generate by default', 'thumbwiz' ),
                    'placeholder'       => __( '4', 'thumbwiz' ),
                    'min'               => 1,
                    'max'               => 12,
                    'step'              => '1',
                    'type'              => 'number',
                    'default'           => 4,
                    'sanitize_callback' => 'floatval'
                )
            )
        );
        return $settings_fields;
    }

    function plugin_page() {
        echo '<div class="wrap">';
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();
        echo '</div>';
    }

}

function thumbwiz_get_thumbs_option() {
    $option = 'default_thumbs_to_generate';
    $options = get_option( 'thumbwiz_generate_settings');
    if ( isset( $options[$option] ) ) {
        return $options[$option];
    }
    else {
	return 4;
    }
}

