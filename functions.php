<?php
/**
 * Load cunstom stylesheet
 */
add_action('wp_enqueue_scripts', 'funky_enqueue_styles');
function funky_enqueue_styles() {
    // Parent style (Storefront)
    wp_enqueue_style('storefront-style', get_template_directory_uri() . '/style.css');

    // Child style (depends on parent style)
    wp_enqueue_style('storefront-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('storefront-style'),
        wp_get_theme()->get('Version')
    );
}

/**
 * Disable the Search Box in the Storefront Theme
 */
add_action( 'init', 'jk_remove_storefront_header_search' );
function jk_remove_storefront_header_search() {
    remove_action( 'storefront_header', 'storefront_product_search', 40 );
}

/**
 * Remove Breadcrumbs
 */
add_action( 'init', 'bbloomer_remove_storefront_breadcrumbs' );
function bbloomer_remove_storefront_breadcrumbs() {
   remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
}
