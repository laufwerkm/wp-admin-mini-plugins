<?php
/**
 * Plugin Name:   Plugin Upload mit Direkt-Aktivierung
 * Description:   Fügt im Plugin-Upload-Bereich einen Button „Installieren und Aktivieren“ ein. 
 *                Sobald eine ZIP gewählt und der Button geklickt wird, lädt dieses Plugin 
 *                das Paket hoch, installiert es, aktiviert das Plugin sofort und leitet zur 
 *                Plugin-Übersicht weiter.
 * Version:       1.7
 * Author:        Roman Mahr - laufwerk:m | Programmierung
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Sicherheit: Direktzugriff verhindern
}

/**
 * 1) JS: Füge auf Plugins → Installieren → Plugin hochladen einen zweiten Button hinzu,
 *    der denselben Upload-Flow nutzt, aber als Name „install_and_activate“ trägt.
 */
add_action( 'admin_enqueue_scripts', 'iau_enqueue_scripts' );
function iau_enqueue_scripts( $hook ) {
    global $pagenow;

    // Sicherstellen, dass wir uns auf wp-admin/plugin-install.php?tab=upload befinden
    if ( $pagenow === 'plugin-install.php' && ! empty( $_GET['tab'] ) && $_GET['tab'] === 'upload' ) {
        add_action( 'admin_print_footer_scripts', 'iau_add_install_and_activate_button' );
    }
}

function iau_add_install_and_activate_button() {
    ?>
    <script type="text/javascript">
    jQuery( document ).ready( function( $ ) {
        var $uploadForm = $( 'form.wp-upload-form' );
        if ( ! $uploadForm.length ) {
            return;
        }

        var $fileInput  = $uploadForm.find( 'input#pluginzip' );
        var $installBtn = $uploadForm.find( 'input[name="install-plugin-submit"]' );

        if ( ! $fileInput.length || ! $installBtn.length ) {
            return;
        }

        // Erzeuge unseren zweiten Button (anfangs deaktiviert)
        var $newBtn = $( '<input/>', {
            type:     'submit',
            name:     'install_and_activate',
            value:    '<?php echo esc_js( __( 'Installieren und Aktivieren', 'iau-textdomain' ) ); ?>',
            class:    'button button-primary',
            disabled: true
        } );

        // Füge ihn direkt hinter dem Standard-Button ein
        $installBtn.after( ' ', $newBtn );

        // Alle Buttons bleiben disabled, solange keine Datei ausgewählt ist
        $fileInput.on( 'change', function() {
            if ( $( this ).val() ) {
                $installBtn.prop( 'disabled', false );
                $newBtn.prop( 'disabled', false );
            } else {
                $installBtn.prop( 'disabled', true );
                $newBtn.prop( 'disabled', true );
            }
        } );

        // Falls beim Laden bereits ein Wert in #pluginzip ist (z. B. Cache), Buttons aktivieren
        if ( $fileInput.val() ) {
            $installBtn.prop( 'disabled', false );
            $newBtn.prop( 'disabled', false );
        }
    } );
    </script>
    <?php
}

/**
 * 2) admin_init: Fange den Upload ab, wenn unser Button „install_and_activate“ gepostet wurde.
 *    Dann installiere und aktiviere das Plugin vollständig in PHP und leite zurück zur Plugin-Liste.
 */
add_action( 'admin_init', 'iau_handle_install_and_activate' );
function iau_handle_install_and_activate() {
    // 2.1: Nur im Adminbereich, bei Upload-Formular, wenn unser Button im POST existiert
    if ( ! is_admin() ) {
        return;
    }
    if ( empty( $_POST['install_and_activate'] ) ) {
        return;
    }
    if ( empty( $_FILES['pluginzip'] ) || ! is_uploaded_file( $_FILES['pluginzip']['tmp_name'] ) ) {
        return;
    }

    // 2.2: Berechtigungs‐Check: Wer Plugins installieren darf, darf auch aktivieren
    if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
        wp_die( __( 'Du hast keine ausreichenden Berechtigungen, um Plugins zu installieren und zu aktivieren.', 'iau-textdomain' ) );
    }

    // 2.3: Nonce‐Prüfung (Standard‐Nonce von WP Core für Plugin-Upload)
    check_admin_referer( 'install-plugin', 'install-plugin-nonce' );

    // 2.4: Erforderliche WP-Includes laden, damit Upgrader und Filesystem funktionieren
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

    // 2.5: WP_Filesystem initialisieren (notwendig für Plugin_Upgrader)
    WP_Filesystem();

    // 2.6: Plugin_Upgrader mit Ajax-Skin instanziieren (verhindert „echo” in der Ausgabe)
    $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

    // 2.7: Versuche, das Paket zu installieren
    $zip_path = $_FILES['pluginzip']['tmp_name'];
    $result   = $upgrader->install( $zip_path );

    if ( is_wp_error( $result ) ) {
        // Falls Fehler beim Entpacken/Installieren, zeige WP-Error an
        wp_die( $result );
    }

    /**
     * 2.8: Plugin-Datei ermitteln
     *
     * Plugin_Upgrader speichert nach erfolgreicher Installation in ->plugin_info()
     * die Hauptdatei (string), z. B. "mein-plugin/mein-plugin.php". Falls das leer ist,
     * versuchen wir, sie aus $upgrader->result() herauszulesen. 
     */
    $plugin_file = '';

    // a) ->plugin_info() liefert den relativen Plugin-Pfad
    if ( method_exists( $upgrader, 'plugin_info' ) ) {
        $info = $upgrader->plugin_info();
        if ( is_string( $info ) && ! empty( $info ) ) {
            $plugin_file = $info;
        }
    }

    // b) Fallback: $upgrader->result kann die installierte Plugin-Hauptdatei angeben
    if ( empty( $plugin_file ) && ! empty( $upgrader->result ) && is_array( $upgrader->result ) ) {
        // Bei manchen WP-Versionen liegt die Datei in ['destination_name']
        $dir_name = isset( $upgrader->result['destination_name'] ) ? $upgrader->result['destination_name'] : '';
        if ( ! empty( $dir_name ) ) {
            // Versuche, das erste PHP-File aus dem Plugin-Verzeichnis zu finden
            $all_plugins = get_plugins( '/' . $dir_name );
            if ( is_array( $all_plugins ) && ! empty( $all_plugins ) ) {
                // Nehme das erste Key (relative Hauptdatei)
                $keys = array_keys( $all_plugins );
                $plugin_file = $dir_name . '/' . $keys[0];
            }
        }
    }

    // 2.9: Plugin aktivieren, sofern wir einen gültigen $plugin_file-Pfad haben
    if ( ! empty( $plugin_file ) ) {
        $activate_result = activate_plugin( $plugin_file );

        if ( is_wp_error( $activate_result ) ) {
            // Aktivierungsfehler? Zeige WP-Error an und stoppe
            wp_die( $activate_result );
        }
    }

    // 2.10: Nun zurück zur Plugin-Übersicht (installiert & aktiviert)
    wp_safe_redirect( admin_url( 'plugins.php?installed=1&activated=' . rawurlencode( $plugin_file ) ) );
    exit;
}
