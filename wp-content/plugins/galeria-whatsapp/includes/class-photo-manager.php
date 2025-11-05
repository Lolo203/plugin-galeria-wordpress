<?php
/**
 * Gestión de fotos
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Photo_Manager {
    
    private $table_photos;
    private $table_folders;
    private $folder_manager;
    
    public function __construct() {
        global $wpdb;
        $this->table_photos = $wpdb->prefix . 'galeria_whatsapp';
        $this->table_folders = $wpdb->prefix . 'galeria_folders';
        $this->folder_manager = new Galeria_WhatsApp_Folder_Manager();
    }
    
    /**
     * Subir foto
     */
    public function upload_photo($attachment_id, $folder_id = 0) {
        global $wpdb;
        
        if (!$attachment_id) {
            return false;
        }
        
        // Generar ID único para la foto
        $photo_id = $this->generate_unique_photo_id();
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return false;
        }
        
        $inserted = $wpdb->insert(
            $this->table_photos,
            array(
                'photo_id' => $photo_id,
                'attachment_id' => intval($attachment_id),
                'image_url' => esc_url_raw($image_url),
                'folder_id' => intval($folder_id)
            ),
            array('%s', '%d', '%s', '%d')
        );
        
        if ($inserted) {
            return array(
                'photo_id' => $photo_id,
                'image_url' => $image_url,
                'db_id' => $wpdb->insert_id,
                'folder_id' => $folder_id
            );
        }
        
        return false;
    }
    
    /**
     * Generar ID único para foto (formato: YYYYMMDD-XXX)
     */
    private function generate_unique_photo_id() {
        global $wpdb;
        
        $today = date('Ymd');
        
        $last_id = $wpdb->get_var($wpdb->prepare(
            "SELECT photo_id FROM {$this->table_photos} WHERE photo_id LIKE %s ORDER BY photo_id DESC LIMIT 1",
            $today . '-%'
        ));
        
        if ($last_id) {
            $parts = explode('-', $last_id);
            $day_number = intval($parts[1]) + 1;
        } else {
            $day_number = 1;
        }
        
        return $today . '-' . str_pad($day_number, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Obtener fotos
     */
    public function get_photos($folder_id = null, $include_subfolders = false) {
        global $wpdb;
        
        // Si es carpeta raíz (0 o null), mostrar todas las fotos
        if ($folder_id === null || $folder_id === 0) {
            $photos = $wpdb->get_results("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM {$this->table_photos} p 
                LEFT JOIN {$this->table_folders} f ON p.folder_id = f.id 
                ORDER BY p.photo_id DESC
            ", ARRAY_A);
        } 
        // Si debe incluir subcarpetas
        elseif ($include_subfolders) {
            $subfolder_ids = $this->folder_manager->get_all_subfolder_ids($folder_id);
            $ids_string = implode(',', array_map('intval', $subfolder_ids));
            
            $photos = $wpdb->get_results("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM {$this->table_photos} p 
                LEFT JOIN {$this->table_folders} f ON p.folder_id = f.id 
                WHERE p.folder_id IN ($ids_string)
                ORDER BY p.photo_id DESC
            ", ARRAY_A);
        }
        // Solo carpeta específica
        else {
            $photos = $wpdb->get_results($wpdb->prepare("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM {$this->table_photos} p 
                LEFT JOIN {$this->table_folders} f ON p.folder_id = f.id 
                WHERE p.folder_id = %d 
                ORDER BY p.photo_id DESC
            ", $folder_id), ARRAY_A);
        }
        
        // Agregar path completo de carpeta
        if ($photos) {
            foreach ($photos as &$photo) {
                if ($photo['folder_id'] > 0) {
                    $photo['folder_path'] = $this->folder_manager->get_folder_path($photo['folder_id']);
                } else {
                    $photo['folder_path'] = '';
                }
            }
        }
        
        return $photos ? $photos : array();
    }
    
    /**
     * Eliminar foto
     */
    public function delete_photo($db_id) {
        global $wpdb;
        
        if ($db_id <= 0) {
            return false;
        }
        
        $deleted = $wpdb->delete(
            $this->table_photos,
            array('id' => intval($db_id)),
            array('%d')
        );
        
        return $deleted !== false;
    }
    
    /**
     * Eliminar múltiples fotos
     * 
     * @param array $photo_ids Array de IDs de fotos a eliminar
     * @return array Resultado con contadores de éxito y fallos
     */
    public function delete_multiple_photos($photo_ids) {
        global $wpdb;
        
        if (!is_array($photo_ids) || empty($photo_ids)) {
            return array(
                'success' => false,
                'deleted_count' => 0,
                'failed_count' => 0
            );
        }
        
        $deleted_count = 0;
        $failed_count = 0;
        
        foreach ($photo_ids as $photo_id) {
            $photo_id = intval($photo_id);
            
            if ($photo_id <= 0) {
                $failed_count++;
                continue;
            }
            
            $result = $wpdb->delete(
                $this->table_photos,
                array('id' => $photo_id),
                array('%d')
            );
            
            if ($result !== false && $result > 0) {
                $deleted_count++;
            } else {
                $failed_count++;
            }
        }
        
        return array(
            'success' => $deleted_count > 0,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count
        );
    }
}