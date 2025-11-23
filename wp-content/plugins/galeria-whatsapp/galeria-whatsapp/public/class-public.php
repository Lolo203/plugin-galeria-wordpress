<?php
/**
 * Funcionalidad del Frontend
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Public {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('galeria_whatsapp', array($this, 'gallery_shortcode'));
    }
    
    /**
     * Cargar scripts y estilos del frontend
     */
    public function enqueue_scripts() {
        // CSS del frontend
        wp_enqueue_style(
            'galeria-public-style',
            GALERIA_WHATSAPP_PLUGIN_URL . 'assets/css/public.css',
            array(),
            GALERIA_WHATSAPP_VERSION
        );
        
        // JS del frontend
        wp_enqueue_script(
            'galeria-public-script',
            GALERIA_WHATSAPP_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            GALERIA_WHATSAPP_VERSION,
            true
        );
        
        // Pasar datos a JavaScript
        wp_localize_script('galeria-public-script', 'galeriaPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'whatsappNumber' => GALERIA_WHATSAPP_NUMBER,
            'whatsappMessage' => GALERIA_WHATSAPP_MESSAGE,
            'version' => GALERIA_WHATSAPP_VERSION
        ));
    }
    
    /**
     * Shortcode de la galería
     */
    public function gallery_shortcode($atts) {
        ob_start();
        include GALERIA_WHATSAPP_PLUGIN_DIR . 'public/views/gallery-shortcode.php';
        return ob_get_clean();
    }
}