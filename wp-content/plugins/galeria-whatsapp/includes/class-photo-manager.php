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
     * Subir foto con validaciones mejoradas
     */
    public function upload_photo($attachment_id, $folder_id = 0) {
        global $wpdb;
        
        try {
            // Validación 1: Attachment ID válido
            if (!$attachment_id || $attachment_id <= 0) {
                throw new Exception('ID de archivo inválido');
            }
            
            // Validación 2: El attachment existe
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                throw new Exception('El archivo no existe en la biblioteca de medios');
            }
            
            // Validación 3: Es una imagen
            if (!wp_attachment_is_image($attachment_id)) {
                throw new Exception('El archivo no es una imagen válida');
            }
            
            // Validación 4: La carpeta existe (si no es raíz)
            if ($folder_id > 0 && !$this->folder_exists($folder_id)) {
                error_log("Galería WhatsApp: Carpeta $folder_id no existe, usando raíz");
                $folder_id = 0;
            }
            
            // Validación 5: No está duplicada
            if ($this->is_attachment_already_uploaded($attachment_id)) {
                throw new Exception('Esta imagen ya está en la galería');
            }
            
            // Obtener información del archivo
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                throw new Exception('No se puede acceder al archivo físico');
            }
            
            $file_name = pathinfo($file_path, PATHINFO_FILENAME);
            
            // Generar ID único
            $photo_id = $this->generate_photo_id_from_filename($file_name);
            
            // Obtener URL
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                throw new Exception('No se pudo obtener la URL de la imagen');
            }
            
            // Insertar en base de datos
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
            
            if (!$inserted) {
                throw new Exception('Error al guardar en la base de datos: ' . $wpdb->last_error);
            }
            
            // Log de éxito
            error_log(sprintf(
                'Galería WhatsApp: Foto subida - ID: %s, Attachment: %d, Carpeta: %d',
                $photo_id,
                $attachment_id,
                $folder_id
            ));
            
            return array(
                'photo_id' => $photo_id,
                'image_url' => $image_url,
                'db_id' => $wpdb->insert_id,
                'folder_id' => $folder_id,
                'attachment_id' => $attachment_id
            );
            
        } catch (Exception $e) {
            error_log('Galería WhatsApp Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar si un attachment ya fue subido
     */
    private function is_attachment_already_uploaded($attachment_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_photos} WHERE attachment_id = %d",
            intval($attachment_id)
        ));
        
        return $exists > 0;
    }
    
    /**
     * Verificar si una carpeta existe
     */
    private function folder_exists($folder_id) {
        global $wpdb;
        
        if ($folder_id <= 0) {
            return true; // Carpeta raíz siempre existe
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_folders} WHERE id = %d",
            intval($folder_id)
        ));
        
        return $exists > 0;
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
                ORDER BY p.upload_date DESC, p.photo_id DESC
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
                ORDER BY p.upload_date DESC, p.photo_id DESC
            ", ARRAY_A);
        }
        // Solo carpeta específica
        else {
            $photos = $wpdb->get_results($wpdb->prepare("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM {$this->table_photos} p 
                LEFT JOIN {$this->table_folders} f ON p.folder_id = f.id 
                WHERE p.folder_id = %d 
                ORDER BY p.upload_date DESC, p.photo_id DESC
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
     * Obtener información de una foto por ID
     */
    public function get_photo_by_id($photo_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_photos} WHERE photo_id = %s",
            $photo_id
        ), ARRAY_A);
    }
    
    /**
     * Obtener estadísticas de la galería
     */
    public function get_gallery_stats() {
        global $wpdb;
        
        return array(
            'total_photos' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_photos}"),
            'photos_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_photos} WHERE DATE(upload_date) = CURDATE()"),
            'photos_this_week' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_photos} WHERE YEARWEEK(upload_date) = YEARWEEK(NOW())"),
            'photos_this_month' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_photos} WHERE YEAR(upload_date) = YEAR(NOW()) AND MONTH(upload_date) = MONTH(NOW())")
        );
    }
}