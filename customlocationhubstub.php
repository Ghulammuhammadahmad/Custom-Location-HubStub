<?php

/* 
Plugin Name: Custom Location Hub Stub
Description: A stub for the Custom Location Hub plugin.
Version: 1.0.13
Author: Ghulam Ahmad
Author URI: https://genxintegratedsystems.com
*/


// Register admin menu for the plugin and its subpages
function clhs_register_admin_menu() {
    // Main Menu
    add_menu_page(
        'Custom Location Hub Stub',       // Page title
        'Location Hub Stub',              // Menu title
        'manage_options',                 // Capability
        'clhs-main-menu',                 // Menu slug
        'clhs_admin_page_content',        // Function to display the page content
        'dashicons-location-alt',         // Icon URL (use a dashicon)
        80                                // Position
    );

    // Sub Menu: Generator Page (as a subpage of 'clhs-main-menu')
    add_submenu_page(
        'clhs-main-menu',                 // Parent slug
        'Generator Page',                 // Page title
        'Generator Page',                 // Submenu title
        'manage_options',                 // Capability
        'generator-page-admin',           // Menu slug
        function() {                      // Callback function
            echo do_shortcode('[clhs-generatorpage]');
        }
        // Icon and Position parameters are ignored for submenus
    );
}
add_action('admin_menu', 'clhs_register_admin_menu');

// Register settings fields
function clhs_register_settings() {
    register_setting('clhs_settings_group', 'clhs_openai_api_key', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('clhs_settings_group', 'clhs_openai_model', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('clhs_settings_group', 'clhs_elementor_template_id', array(
        'sanitize_callback' => 'absint',
    ));
    register_setting('clhs_settings_group', 'clhs_page_name', array(
        'sanitize_callback' => 'sanitize_textarea_field',
    ));
    register_setting('clhs_settings_group', 'clhs_parent_page_id', array(
        'sanitize_callback' => 'absint',
    ));

    add_settings_section(
        'clhs_main_section',
        'Settings',
        function () {
            echo '<p>Configure OpenAI and page generation options.</p>';
        },
        'clhs-main-menu'
    );

    add_settings_field(
        'clhs_openai_api_key',
        'OpenAI API Key',
        function () {
            $value = esc_attr(get_option('clhs_openai_api_key', ''));
            echo '<input type="text" name="clhs_openai_api_key" value="' . $value . '" class="regular-text" />';
        },
        'clhs-main-menu',
        'clhs_main_section'
    );

    add_settings_field(
        'clhs_openai_model',
        'OpenAI Model Name',
        function () {
            $value = esc_attr(get_option('clhs_openai_model', 'gpt-5'));
            $options = array(
                'gpt-5' => 'gpt-5',
                'gpt-5.2' => 'gpt-5.2',
                'gpt-5-mini' => 'gpt-5-mini',
            );
            echo '<select name="clhs_openai_model">';
            foreach ($options as $opt_value => $label) {
                echo '<option value="' . esc_attr($opt_value) . '"' . selected($value, $opt_value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        },
        'clhs-main-menu',
        'clhs_main_section'
    );

    add_settings_field(
        'clhs_elementor_template_id',
        'Elementor Template',
        function () {
            $selected = absint(get_option('clhs_elementor_template_id', 0));
            $templates = get_posts(array(
                'post_type'      => 'elementor_library',
                'posts_per_page' => -1,
                'tax_query'      => array(
                    array(
                        'taxonomy' => 'elementor_library_category',
                        'field'    => 'slug',
                        'terms'    => 'page-automation',
                    ),
                ),
            ));

            echo '<select name="clhs_elementor_template_id">';
            echo '<option value="0"' . selected($selected, 0, false) . '>— Select Template —</option>';
            foreach ($templates as $template) {
                echo '<option value="' . esc_attr($template->ID) . '"' . selected($selected, $template->ID, false) . '>' . esc_html($template->post_title) . '</option>';
            }
            echo '</select>';
        },
        'clhs-main-menu',
        'clhs_main_section'
    );

    add_settings_field(
        'clhs_page_name',
        'Instruction',
        function () {
            $value = esc_textarea(get_option('clhs_page_name', ''));
            echo '<textarea id="clhs_page_name" name="clhs_page_name" rows="6" class="large-text" placeholder="Enter instruction...">' . $value . '</textarea>';
            echo '<p class="description">Enter comma-separated page names. Each name will be used to generate a page.</p>';
        },
        'clhs-main-menu',
        'clhs_main_section'
    );

    add_settings_field(
        'clhs_parent_page_id',
        'Parent Page',
        function () {
            $selected = absint(get_option('clhs_parent_page_id', 0));
            $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));

            echo '<select name="clhs_parent_page_id">';
            echo '<option value="0"' . selected($selected, 0, false) . '>— No Parent —</option>';
            foreach ($pages as $page) {
                echo '<option value="' . esc_attr($page->ID) . '"' . selected($selected, $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
            }
            echo '</select>';
        },
        'clhs-main-menu',
        'clhs_main_section'
    );
}
add_action('admin_init', 'clhs_register_settings');

// Enqueue shared assets for admin pages and shortcode output
function clhs_enqueue_common_assets() {
    $script_path = plugin_dir_path(__FILE__) . 'assets/js/clhs-admin.js';
    $script_url  = plugin_dir_url(__FILE__) . 'assets/js/clhs-admin.js';
    $version     = file_exists($script_path) ? filemtime($script_path) : '1.0.0';

    wp_enqueue_script(
        'clhs-admin-js',
        $script_url,
        array(),
        $version,
        true
    );
}

// Enqueue assets on admin pages
function clhs_enqueue_admin_assets($hook) {
    // Allow assets for both main menu and generator page subpage
    if ($hook !== 'toplevel_page_clhs-main-menu' && $hook !== 'custom-location-hub-stub_page_generator-page-admin') {
        return;
    }

    clhs_enqueue_common_assets();
}
add_action('admin_enqueue_scripts', 'clhs_enqueue_admin_assets');

// Callback to display content on the menu page
function clhs_admin_page_content() {
    ob_start();
    ?>
    <div class="wrap">
        <h1>Custom Location Hub Stub</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('clhs_settings_group');
            do_settings_sections('clhs-main-menu');
            submit_button();
            ?>
        </form>
    </div>
    <?php
    echo ob_get_clean();
}

require_once plugin_dir_path(__FILE__) . 'includes/generatorpage.php';
require_once plugin_dir_path(__FILE__) . 'includes/clhb_shortcodes.php';
