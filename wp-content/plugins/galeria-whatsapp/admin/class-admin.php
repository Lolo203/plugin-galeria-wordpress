<?php
/**
 * Funcionalidad del Admin
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Agregar menú en admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Galería WhatsApp',
            'Galería Fotos',
            'manage_options',
            'galeria-whatsapp',
            array($this, 'render_admin_page'),
            'dashicons-format-gallery',
            30
        );
    }
    
    /**
     * Cargar scripts y estilos del admin
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_galeria-whatsapp') {
            return;
        }
        
        // WordPress Media Uploader
        wp_enqueue_media();
        
        // CSS del admin
        wp_enqueue_style(
            'galeria-admin-style',
            GALERIA_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GALERIA_WHATSAPP_VERSION
        );
        
        // JS del admin
        wp_enqueue_script(
            'galeria-admin-script',
            GALERIA_WHATSAPP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GALERIA_WHATSAPP_VERSION,
            true
        );
        
        // Pasar datos a JavaScript
        wp_localize_script('galeria-admin-script', 'galeriaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('galeria_nonce'),
            'version' => GALERIA_WHATSAPP_VERSION
        ));
    }
    
    /**
     * Renderizar página del admin
     */
    public function render_admin_page() {
        include GALERIA_WHATSAPP_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
}