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
        
        // Public AJAX handlers (sin autenticación)
        add_action('wp_ajax_nopriv_get_gallery_photos', array($this, 'get_photos'));
        add_action('wp_ajax_nopriv_get_gallery_folders', array($this, 'get_folders'));
    }
    
    /**
     * Obtener fotos (público)
     */
    public function get_photos() {
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
        $include_subfolders = isset($_POST['include_subfolders']) ? (bool)$_POST['include_subfolders'] : false;
        
        $photos = $this->photo_manager->get_photos($folder_id, $include_subfolders);
        wp_send_json_success($photos);
    }
    
    /**
     * Obtener carpetas (público)
     */
    public function get_folders() {
        $folders = $this->folder_manager->get_all_folders();
        wp_send_json_success($folders);
    }
}