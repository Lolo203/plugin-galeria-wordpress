<?php
/**
 * Handlers AJAX del Admin
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Admin_Ajax {
    
    private $photo_manager;
    private $folder_manager;
    
    public function __construct() {
        $this->photo_manager = new Galeria_WhatsApp_Photo_Manager();
        $this->folder_manager = new Galeria_WhatsApp_Folder_Manager();
        
        // AJAX handlers
        add_action('wp_ajax_upload_gallery_photo', array($this, 'upload_photo'));
        add_action('wp_ajax_get_gallery_photos', array($this, 'get_photos'));
        add_action('wp_ajax_delete_gallery_photo', array($this, 'delete_photo'));
        add_action('wp_ajax_delete_multiple_gallery_photos', array($this, 'delete_multiple_photos'));
        add_action('wp_ajax_create_gallery_folder', array($this, 'create_folder'));
        add_action('wp_ajax_get_gallery_folders', array($this, 'get_folders'));
        add_action('wp_ajax_delete_gallery_folder', array($this, 'delete_folder'));
    }
    
    /**
     * Subir foto
     */
    public function upload_photo() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error('ID de archivo inválido');
            return;
        }
        
        // Log para debugging
        error_log('Galería WhatsApp: Subiendo attachment_id=' . $attachment_id . ' a folder_id=' . $folder_id);
        
        $result = $this->photo_manager->upload_photo($attachment_id, $folder_id);
        
        if ($result && is_array($result)) {
            error_log('Galería WhatsApp: Foto subida exitosamente - ID: ' . $result['photo_id']);
            wp_send_json_success($result);
        } else {
            error_log('Galería WhatsApp: Error al subir foto - attachment_id=' . $attachment_id);
            wp_send_json_error('Error al subir foto. Verifica que el archivo sea una imagen válida.');
        }
    }
    
    /**
     * Obtener fotos
     */
    public function get_photos() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
        $include_subfolders = isset($_POST['include_subfolders']) ? (bool)$_POST['include_subfolders'] : false;
        
        $photos = $this->photo_manager->get_photos($folder_id, $include_subfolders);
        wp_send_json_success($photos);
    }
    
    /**
     * Eliminar foto
     */
    public function delete_photo() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $db_id = isset($_POST['db_id']) ? intval($_POST['db_id']) : 0;
        
        $deleted = $this->photo_manager->delete_photo($db_id);
        
        if ($deleted) {
            wp_send_json_success(array('message' => 'Foto eliminada'));
        } else {
            wp_send_json_error('Error al eliminar foto');
        }
    }
    
    /**
     * Eliminar múltiples fotos
     */
    public function delete_multiple_photos() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $photo_ids = isset($_POST['photo_ids']) ? $_POST['photo_ids'] : array();
        
        if (empty($photo_ids) || !is_array($photo_ids)) {
            wp_send_json_error('No se especificaron fotos para eliminar');
            return;
        }
        
        $deleted_count = 0;
        $errors = 0;
        
        foreach ($photo_ids as $db_id) {
            $db_id = intval($db_id);
            if ($this->photo_manager->delete_photo($db_id)) {
                $deleted_count++;
            } else {
                $errors++;
            }
        }
        
        if ($deleted_count > 0) {
            wp_send_json_success(array(
                'message' => "$deleted_count foto(s) eliminadas",
                'deleted' => $deleted_count,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error('No se pudo eliminar ninguna foto');
        }
    }
    
    /**
     * Crear carpeta
     */
    public function create_folder() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        
        $folder_id = $this->folder_manager->create_folder($folder_name, $parent_id);
        
        if ($folder_id) {
            wp_send_json_success(array(
                'folder_id' => $folder_id,
                'parent_id' => $parent_id
            ));
        } else {
            wp_send_json_error('Error al crear carpeta');
        }
    }
    
    /**
     * Obtener carpetas
     */
    public function get_folders() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folders = $this->folder_manager->get_all_folders();
        wp_send_json_success($folders);
    }
    
    /**
     * Eliminar carpeta
     */
    public function delete_folder() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        $deleted = $this->folder_manager->delete_folder($folder_id);
        
        if ($deleted) {
            wp_send_json_success(array('deleted_count' => 1));
        } else {
            wp_send_json_error('Error al eliminar carpeta');
        }
    }
}