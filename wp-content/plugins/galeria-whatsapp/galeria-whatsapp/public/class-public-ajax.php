<?php
/**
 * Handlers AJAX del Frontend
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Public_Ajax {
    
    private $photo_manager;
    private $folder_manager;
    
    public function __construct() {
        $this->photo_manager = new Galeria_WhatsApp_Photo_Manager();
        $this->folder_manager = new Galeria_WhatsApp_Folder_Manager();
        
        // Registrar acciones AJAX directamente (más temprano que init)
        // Acciones AJAX públicas (sin autenticación) - deben registrarse antes de que se hagan las peticiones
        add_action('wp_ajax_nopriv_get_gallery_photos', array($this, 'get_photos'));
        add_action('wp_ajax_nopriv_get_gallery_folders', array($this, 'get_folders'));
        
        // También registrar para usuarios autenticados (por compatibilidad)
        // Usar prioridad alta (1) para que se ejecuten antes que las del admin
        // Esto evita conflictos si un usuario autenticado visita la galería
        add_action('wp_ajax_get_gallery_photos', array($this, 'get_photos'), 1);
        add_action('wp_ajax_get_gallery_folders', array($this, 'get_folders'), 1);
    }
    
    /**
     * Obtener fotos (público)
     */
    public function get_photos() {
        // Si la petición viene del admin (tiene nonce), dejar que el admin lo maneje
        // Solo procesar si NO es una petición del admin
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'galeria_nonce')) {
            // Esta es una petición del admin, dejar que el admin la maneje
            return;
        }
        
        // Asegurar que no haya output previo
        if (ob_get_length()) {
            ob_clean();
        }
        
        try {
            $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
            $include_subfolders = isset($_POST['include_subfolders']) ? (bool)$_POST['include_subfolders'] : false;
            
            $photos = $this->photo_manager->get_photos($folder_id, $include_subfolders);
            
            // Asegurar que la respuesta sea JSON válida
            wp_send_json_success($photos);
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en get_photos (público): ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error al cargar las fotos: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Obtener carpetas (público)
     */
    public function get_folders() {
        // Si la petición viene del admin (tiene nonce), dejar que el admin lo maneje
        // Solo procesar si NO es una petición del admin
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'galeria_nonce')) {
            // Esta es una petición del admin, dejar que el admin la maneje
            return;
        }
        
        // Asegurar que no haya output previo
        if (ob_get_length()) {
            ob_clean();
        }
        
        try {
            $folders = $this->folder_manager->get_all_folders();
            
            // Asegurar que la respuesta sea JSON válida
            wp_send_json_success($folders);
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en get_folders (público): ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error al cargar las carpetas: ' . $e->getMessage()
            ));
        }
    }
}