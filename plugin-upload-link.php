<?php
/**
 * Plugin Name: Plugin-Upload-Verknüpfung
 * Description: Fügt im Dashboard unter “Plugins” einen Link “Plugin hochladen” ein.
 * Version:     1.0
 * Author:      Roman Mahr - laufwerk:m | Programmierung
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'puv_add_upload_link' );
function puv_add_upload_link() {
    // Eigener Slug + Callback (robuster als Querystrings als Menu-Slug)
    add_submenu_page(
        'plugins.php',
        'Plugin hochladen',
        'Plugin hochladen',
        'install_plugins',
        'puv-plugin-upload',
        'puv_redirect_to_upload_tab'
    );
}

function puv_redirect_to_upload_tab() {
    if ( ! current_user_can( 'install_plugins' ) ) {
        wp_die( __( 'Du hast keine ausreichenden Berechtigungen.', 'puv-textdomain' ) );
    }

    wp_safe_redirect( admin_url( 'plugin-install.php?tab=upload' ) );
    exit;
}
