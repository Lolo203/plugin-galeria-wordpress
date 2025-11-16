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
        add_action('wp_ajax_validate_upload', array($this, 'validate_upload'));
    }
    
    /**
     * Subir foto con manejo de errores mejorado
     */
    public function upload_photo() {
        try {
            // Verificar nonce
            if (!check_ajax_referer('galeria_nonce', 'nonce', false)) {
                throw new Exception('Token de seguridad inválido');
            }
            
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception('No tienes permisos para realizar esta acción');
            }
            
            // Obtener y validar datos
            $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
            $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
            
            if (!$attachment_id) {
                throw new Exception('ID de archivo no proporcionado');
            }
            
            // Intentar subir la foto
            $result = $this->photo_manager->upload_photo($attachment_id, $folder_id);
            
            if (!$result) {
                throw new Exception('Error al procesar la imagen. Verifica que sea un archivo válido');
            }
            
            // Éxito - enviar respuesta detallada
            wp_send_json_success(array(
                'photo_id' => $result['photo_id'],
                'image_url' => $result['image_url'],
                'db_id' => $result['db_id'],
                'folder_id' => $result['folder_id'],
                'message' => 'Foto subida correctamente: #' . $result['photo_id']
            ));
            
        } catch (Exception $e) {
            // Log del error
            error_log('Galería WhatsApp - Error en upload_photo: ' . $e->getMessage());
            
            // Respuesta de error amigable - enviar solo el mensaje como string para evitar [object Object]
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Validar antes de subir (pre-validación)
     */
    public function validate_upload() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $attachment_ids = isset($_POST['attachment_ids']) ? $_POST['attachment_ids'] : array();
            
            if (empty($attachment_ids) || !is_array($attachment_ids)) {
                throw new Exception('No se proporcionaron archivos');
            }
            
            $validation_results = array();
            
            foreach ($attachment_ids as $attachment_id) {
                $attachment_id = intval($attachment_id);
                $validation_results[$attachment_id] = array(
                    'valid' => true,
                    'message' => 'OK'
                );
                
                // Validar que existe
                if (!get_post($attachment_id)) {
                    $validation_results[$attachment_id] = array(
                        'valid' => false,
                        'message' => 'Archivo no encontrado'
                    );
                    continue;
                }
                
                // Validar que es imagen
                if (!wp_attachment_is_image($attachment_id)) {
                    $validation_results[$attachment_id] = array(
                        'valid' => false,
                        'message' => 'No es una imagen válida'
                    );
                    continue;
                }
            }
            
            wp_send_json_success($validation_results);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtener fotos
     */
    public function get_photos() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
            $include_subfolders = isset($_POST['include_subfolders']) ? (bool)$_POST['include_subfolders'] : false;
            
            $photos = $this->photo_manager->get_photos($folder_id, $include_subfolders);
            
            wp_send_json_success(array(
                'photos' => $photos,
                'count' => count($photos),
                'folder_id' => $folder_id
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en get_photos: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Eliminar foto
     */
    public function delete_photo() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $db_id = isset($_POST['db_id']) ? intval($_POST['db_id']) : 0;
            
            if ($db_id <= 0) {
                throw new Exception('ID inválido');
            }
            
            $deleted = $this->photo_manager->delete_photo($db_id);
            
            if (!$deleted) {
                throw new Exception('No se pudo eliminar la foto');
            }
            
            wp_send_json_success(array(
                'message' => 'Foto eliminada correctamente',
                'db_id' => $db_id
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en delete_photo: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Eliminar múltiples fotos con mejor feedback
     */
    public function delete_multiple_photos() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $photo_ids = isset($_POST['photo_ids']) ? $_POST['photo_ids'] : array();
            
            if (empty($photo_ids) || !is_array($photo_ids)) {
                throw new Exception('No se especificaron fotos para eliminar');
            }
            
            $deleted_count = 0;
            $errors = array();
            $total = count($photo_ids);
            
            foreach ($photo_ids as $db_id) {
                $db_id = intval($db_id);
                
                if ($db_id <= 0) {
                    $errors[] = "ID inválido: $db_id";
                    continue;
                }
                
                if ($this->photo_manager->delete_photo($db_id)) {
                    $deleted_count++;
                } else {
                    $errors[] = "Error al eliminar ID: $db_id";
                }
            }
            
            if ($deleted_count === 0) {
                throw new Exception('No se pudo eliminar ninguna foto: ' . implode(', ', $errors));
            }
            
            wp_send_json_success(array(
                'message' => sprintf(
                    '%d de %d foto(s) eliminadas correctamente',
                    $deleted_count,
                    $total
                ),
                'deleted' => $deleted_count,
                'total' => $total,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en delete_multiple_photos: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Crear carpeta con validación
     */
    public function create_folder() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
            
            if (empty($folder_name)) {
                throw new Exception('El nombre de la carpeta no puede estar vacío');
            }
            
            if (strlen($folder_name) > 100) {
                throw new Exception('El nombre de la carpeta es demasiado largo (máximo 100 caracteres)');
            }
            
            $folder_id = $this->folder_manager->create_folder($folder_name, $parent_id);
            
            if (!$folder_id) {
                throw new Exception('Error al crear la carpeta en la base de datos');
            }
            
            wp_send_json_success(array(
                'folder_id' => $folder_id,
                'parent_id' => $parent_id,
                'folder_name' => $folder_name,
                'message' => 'Carpeta creada: ' . $folder_name
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en create_folder: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtener carpetas
     */
    public function get_folders() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $folders = $this->folder_manager->get_all_folders();
            
            wp_send_json_success(array(
                'folders' => $folders,
                'count' => count($folders)
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en get_folders: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Eliminar carpeta
     */
    public function delete_folder() {
        try {
            check_ajax_referer('galeria_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new Exception('Permisos insuficientes');
            }
            
            $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
            
            if ($folder_id <= 0) {
                throw new Exception('ID de carpeta inválido');
            }
            
            $deleted = $this->folder_manager->delete_folder($folder_id);
            
            if (!$deleted) {
                throw new Exception('No se pudo eliminar la carpeta');
            }
            
            wp_send_json_success(array(
                'message' => 'Carpeta y subcarpetas eliminadas',
                'folder_id' => $folder_id
            ));
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp - Error en delete_folder: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}