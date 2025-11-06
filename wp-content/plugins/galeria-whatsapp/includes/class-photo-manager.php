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
        
        // Obtener nombre original del archivo
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return false;
        }
        
        // Obtener el nombre del archivo sin extensión
        $file_path = get_attached_file($attachment_id);
        $file_name = pathinfo($file_path, PATHINFO_FILENAME);
        
        // Generar ID basado en el nombre del archivo
        $photo_id = $this->generate_photo_id_from_filename($file_name);
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
     * Generar ID de foto basado en nombre de archivo
     */
    private function generate_photo_id_from_filename($filename) {
        global $wpdb;
        
        // Sanitizar nombre del archivo
        $clean_name = $this->sanitize_photo_id($filename);
        
        // Si el nombre está vacío después de sanitizar, usar timestamp
        if (empty($clean_name)) {
            $clean_name = 'photo-' . time();
        }
        
        // Verificar si ya existe
        $original_name = $clean_name;
        $counter = 1;
        
        while ($this->photo_id_exists($clean_name)) {
            $clean_name = $original_name . '-' . $counter;
            $counter++;
            
            // Prevenir loops infinitos
            if ($counter > 1000) {
                $clean_name = $original_name . '-' . uniqid();
                break;
            }
        }
        
        return $clean_name;
    }
    
    /**
     * Sanitizar ID de foto
     */
    private function sanitize_photo_id($name) {
        // Convertir a minúsculas
        $name = strtolower($name);
        
        // Reemplazar espacios y guiones bajos con guiones
        $name = str_replace(array(' ', '_'), '-', $name);
        
        // Eliminar caracteres especiales, mantener solo alfanuméricos y guiones
        $name = preg_replace('/[^a-z0-9-]/', '', $name);
        
        // Eliminar múltiples guiones consecutivos
        $name = preg_replace('/-+/', '-', $name);
        
        // Eliminar guiones al inicio y final
        $name = trim($name, '-');
        
        // Limitar longitud a 50 caracteres
        if (strlen($name) > 50) {
            $name = substr($name, 0, 50);
            $name = rtrim($name, '-');
        }
        
        return $name;
    }
    
    /**
     * Verificar si existe un photo_id
     */
    private function photo_id_exists($photo_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_photos} WHERE photo_id = %s",
            $photo_id
        ));
        
        return $exists > 0;
    }
    
    /**
     * Generar ID único para foto (formato antiguo: YYYYMMDD-XXX)
     * DEPRECATED: Mantenido por compatibilidad
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
}