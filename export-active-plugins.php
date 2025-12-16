<?php

/**
 * Plugin Name:   Export Active Plugins as TXT
 * Description:   Fügt in der Plugin-Übersicht eine Bulk-Action hinzu, um untereinander die Namen ausgewählter aktiver Plugins als .txt herunterzuladen.
 * Version:       1.0
 * Author:        Roman Mahr - laufwerk:m | Programmierung
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bulk-Action nur für aktive Plugins registrieren
 */
add_filter('bulk_actions-plugins', 'eapt_register_export_action');
function eapt_register_export_action($bulk_actions)
{
    // Nur im Active-View anzeigen
    if (empty($_GET['plugin_status']) || $_GET['plugin_status'] !== 'active') {
        return $bulk_actions;
    }

    $bulk_actions['export_plugins_txt'] = __('Export als TXT', 'export-active-plugins');
    return $bulk_actions;
}

/**
 * Bulk-Action ausführen, wenn gewählt
 */
add_action('load-plugins.php', 'eapt_handle_export_action');
function eapt_handle_export_action()
{
    // Bestimmen, welche Bulk-Action ausgeführt wurde
    $action = '';
    if (! empty($_REQUEST['action']) && $_REQUEST['action'] !== '-1') {
        $action = $_REQUEST['action'];
    } elseif (! empty($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1') {
        $action = $_REQUEST['action2'];
    }

    if ($action !== 'export_plugins_txt') {
        return;
    }

    // Sicherstellen, dass wir uns im Active-View befinden
    if (empty($_GET['plugin_status']) || $_GET['plugin_status'] !== 'active') {
        wp_die(__('Export nur für aktive Plugins möglich.', 'export-active-plugins'));
    }

    // Berechtigung prüfen
    if (! current_user_can('activate_plugins')) {
        wp_die(__('Du hast keine Berechtigung für den Export.', 'export-active-plugins'));
    }

    // Nonce prüfen
    check_admin_referer('bulk-plugins');

    // Ausgewählte Plugins auslesen
    if (empty($_REQUEST['checked']) || ! is_array($_REQUEST['checked'])) {
        wp_die(__('Keine Plugins ausgewählt.', 'export-active-plugins'));
    }
    $selected = array_map('sanitize_text_field', $_REQUEST['checked']);

    // Plugin-Daten laden
    if (! function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();

    // Plugin-Namen sammeln
    $names = [];
    foreach ($selected as $plugin_file) {
        if (isset($all_plugins[$plugin_file])) {
            $names[] = $all_plugins[$plugin_file]['Name'];
        } else {
            $names[] = $plugin_file;
        }
    }

    // Download vorbereiten
    $filename = 'plugins-export-active-' . date('Y-m-d') . '.txt';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo implode("\n", $names);
    exit;
}
