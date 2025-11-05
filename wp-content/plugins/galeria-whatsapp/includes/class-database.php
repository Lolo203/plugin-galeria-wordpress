<?php
/**
 * Gestión de base de datos
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp_Database {
    
    private $table_photos;
    private $table_folders;
    
    public function __construct() {
        global $wpdb;
        $this->table_photos = $wpdb->prefix . 'galeria_whatsapp';
        $this->table_folders = $wpdb->prefix . 'galeria_folders';
    }
    
    /**
     * Activación del plugin - Crear tablas
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql_photos = "CREATE TABLE IF NOT EXISTS {$this->table_photos} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            photo_id varchar(20) NOT NULL,
            attachment_id bigint(20) NOT NULL,
            image_url text NOT NULL,
            folder_id mediumint(9) DEFAULT 0,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY photo_id (photo_id),
            KEY folder_id (folder_id)
        ) $charset_collate;";
        
        $sql_folders = "CREATE TABLE IF NOT EXISTS {$this->table_folders} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            parent_id mediumint(9) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_photos);
        dbDelta($sql_folders);
        
        $this->check_structure();
    }
    
    /**
     * Verificar estructura de tablas
     */
    public function check_structure() {
        global $wpdb;
        
        // Verificar columna folder_id en tabla photos
        $column = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_photos} LIKE 'folder_id'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE {$this->table_photos} ADD COLUMN folder_id mediumint(9) DEFAULT 0 AFTER image_url");
            $wpdb->query("ALTER TABLE {$this->table_photos} ADD KEY folder_id (folder_id)");
            $wpdb->query("ALTER TABLE {$this->table_photos} MODIFY photo_id varchar(20) NOT NULL");
        }
        
        // Verificar columna parent_id en tabla folders
        $parent_column = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_folders} LIKE 'parent_id'");
        if (empty($parent_column)) {
            $wpdb->query("ALTER TABLE {$this->table_folders} ADD COLUMN parent_id mediumint(9) DEFAULT 0 AFTER name");
            $wpdb->query("ALTER TABLE {$this->table_folders} ADD KEY parent_id (parent_id)");
        }
    }
    
    /**
     * Obtener nombre de tabla de fotos
     */
    public function get_photos_table() {
        return $this->table_photos;
    }
    
    /**
     * Obtener nombre de tabla de carpetas
     */
    public function get_folders_table() {
        return $this->table_folders;
    }
}