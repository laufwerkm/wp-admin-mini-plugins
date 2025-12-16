<?php
/**
 * Plugin Name:   Plugin Schnelllöschen
 * Description:   Fügt in der Plugin-Übersicht die Aktion „Deaktivieren & Löschen“ hinzu.
 * Version:       1.0
 * Author:        Roman Mahr - laufwerk:m | Programmierung
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Für is_plugin_active(), deactivate_plugins(), delete_plugins() usw.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// 1. Filter: Zusätzlichen Aktion-Link unter Plugins einfügen
add_filter( 'plugin_action_links', 'psd_add_deactivate_delete_link', 10, 4 );
function psd_add_deactivate_delete_link( $actions, $plugin_file, $plugin_data, $context ) {
    // Nur anzeigen, wenn das Plugin aktuell aktiv ist und der Benutzer Plugins löschen darf
    if ( is_plugin_active( $plugin_file ) && current_user_can( 'delete_plugins' ) ) {
        // URL zum eigenen Admin-Post-Handler (Nonce über _wpnonce)
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=psd_deactivate_delete&plugin=' . rawurlencode( $plugin_file ) ),
            'psd_deactivate_delete_' . $plugin_file
        );

        // Neuen Link in der Aktions-Liste hinzufügen
        $actions['deaktivieren_und_loeschen'] = sprintf(
            '<a href="%1$s" onclick="return confirm(\'Bist du sicher, dass du dieses Plugin deaktivieren und löschen möchtest?\');">%2$s</a>',
            esc_url( $url ),
            __( 'Deaktivieren & Löschen', 'textdomain' )
        );
    }

    return $actions;
}

// 2. Aktion: Handler, der das Plugin deaktiviert und löscht
add_action( 'admin_post_psd_deactivate_delete', 'psd_handle_deactivate_delete' );
function psd_handle_deactivate_delete() {
    // 2.1 Rechte prüfen
    if ( ! current_user_can( 'delete_plugins' ) ) {
        wp_die( __( 'Du hast keine ausreichenden Berechtigungen.', 'textdomain' ) );
    }

    // 2.2 Parameter prüfen
    if ( empty( $_GET['plugin'] ) ) {
        wp_die( __( 'Ungültige Anfrage.', 'textdomain' ) );
    }

    $plugin_file = (string) wp_unslash( $_GET['plugin'] );

    // 2.2.1 Nonce überprüfen
    check_admin_referer( 'psd_deactivate_delete_' . $plugin_file );

    // 2.3 Plugin deaktivieren
    deactivate_plugins( $plugin_file );

    // 2.4 Plugin löschen
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $result = delete_plugins( [ $plugin_file ] );
    if ( is_wp_error( $result ) ) {
        wp_die( $result );
    }

    // 2.5 Zurück zur Plugin-Seite mit Erfolgshinweis
    $redirect = add_query_arg(
        'psd_deleted',
        rawurlencode( $plugin_file ),
        admin_url( 'plugins.php' )
    );
    wp_safe_redirect( $redirect );
    exit;
}

// 3. Admin-Notice anzeigen, wenn ein Plugin über unsere Aktion gelöscht wurde
add_action( 'admin_notices', 'psd_delete_admin_notice' );
function psd_delete_admin_notice() {
    if ( ! empty( $_GET['psd_deleted'] ) ) {
        $plugin_file = sanitize_text_field( wp_unslash( $_GET['psd_deleted'] ) );
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo sprintf(
                    /* translators: 1: Plugin-Datei */
                    __( 'Plugin „%1$s“ wurde erfolgreich deaktiviert und gelöscht.', 'textdomain' ),
                    esc_html( $plugin_file )
                );
                ?>
            </p>
        </div>
        <?php
    }
}
?>
