<?php
/**
 * Archivo de desinstalación
 * Se ejecuta cuando el plugin es desinstalado
 *
 * @package Galeria_WhatsApp
 */

// Si no es una desinstalación, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Nombres de las tablas
$table_photos = $wpdb->prefix . 'galeria_whatsapp';
$table_folders = $wpdb->prefix . 'galeria_folders';

// Eliminar tablas
$wpdb->query("DROP TABLE IF EXISTS $table_photos");
$wpdb->query("DROP TABLE IF EXISTS $table_folders");

// Eliminar opciones si hubiera (para futuras versiones)
delete_option('galeria_whatsapp_version');
delete_option('galeria_whatsapp_settings');

// Limpiar transients si hubiera
delete_transient('galeria_whatsapp_cache');

// Log para debugging (opcional)
error_log('Galería WhatsApp: Plugin desinstalado correctamente');