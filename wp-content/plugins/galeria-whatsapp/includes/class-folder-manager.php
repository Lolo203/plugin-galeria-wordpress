<?php
/**
 * Gestión de carpetas
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Folder_Manager {
    
    private $table_folders;
    private $table_photos;
    
    public function __construct() {
        global $wpdb;
        $this->table_folders = $wpdb->prefix . 'galeria_folders';
        $this->table_photos = $wpdb->prefix . 'galeria_whatsapp';
    }
    
    /**
     * Crear carpeta
     */
    public function create_folder($folder_name, $parent_id = 0) {
        global $wpdb;
        
        if (empty($folder_name)) {
            return false;
        }
        
        $inserted = $wpdb->insert(
            $this->table_folders,
            array(
                'name' => sanitize_text_field($folder_name),
                'parent_id' => intval($parent_id)
            ),
            array('%s', '%d')
        );
        
        return $inserted ? $wpdb->insert_id : false;
    }
    
    /**
     * Obtener todas las carpetas
     */
    public function get_all_folders() {
        global $wpdb;
        
        $folders = $wpdb->get_results("
            SELECT f.id, f.name, f.parent_id, f.created_date
            FROM {$this->table_folders} f 
            ORDER BY f.parent_id ASC, f.name ASC
        ", ARRAY_A);
        
        // Calcular conteo de fotos incluyendo subcarpetas
        if ($folders) {
            foreach ($folders as &$folder) {
                $subfolder_ids = $this->get_all_subfolder_ids($folder['id']);
                $ids_string = implode(',', array_map('intval', $subfolder_ids));
                
                $count = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$this->table_photos} 
                    WHERE folder_id IN ($ids_string)
                ");
                
                $folder['photo_count'] = $count ? intval($count) : 0;
            }
        }
        
        return $folders ? $folders : array();
    }
    
    /**
     * Eliminar carpeta y sus subcarpetas
     */
    public function delete_folder($folder_id) {
        global $wpdb;
        
        if ($folder_id <= 0) {
            return false;
        }
        
        // Obtener todas las subcarpetas
        $all_folder_ids = $this->get_all_subfolder_ids($folder_id);
        $ids_string = implode(',', array_map('intval', $all_folder_ids));
        
        // Mover fotos a raíz
        $wpdb->query("UPDATE {$this->table_photos} SET folder_id = 0 WHERE folder_id IN ($ids_string)");
        
        // Eliminar carpetas
        $deleted = $wpdb->query("DELETE FROM {$this->table_folders} WHERE id IN ($ids_string)");
        
        return $deleted !== false;
    }
    
    /**
     * Obtener IDs de carpeta y todas sus subcarpetas (recursivo)
     */
    public function get_all_subfolder_ids($folder_id) {
        global $wpdb;
        
        $ids = array($folder_id);
        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->table_folders} WHERE parent_id = %d",
            $folder_id
        ));
        
        foreach ($children as $child_id) {
            $ids = array_merge($ids, $this->get_all_subfolder_ids($child_id));
        }
        
        return $ids;
    }
    
    /**
     * Obtener path completo de una carpeta
     */
    public function get_folder_path($folder_id) {
        global $wpdb;
        
        if ($folder_id == 0) {
            return '';
        }
        
        $path = array();
        $current_id = $folder_id;
        $max_depth = 10;
        $depth = 0;
        
        while ($current_id > 0 && $depth < $max_depth) {
            $folder = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, parent_id FROM {$this->table_folders} WHERE id = %d",
                $current_id
            ));
            
            if (!$folder) break;
            
            array_unshift($path, $folder->name);
            $current_id = $folder->parent_id;
            $depth++;
        }
        
        return implode(' › ', $path);
    }
}