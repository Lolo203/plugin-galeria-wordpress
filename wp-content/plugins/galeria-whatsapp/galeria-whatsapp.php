<?php
/**
 * Plugin Name: Galería con WhatsApp
 * Plugin URI: https://tusitio.com
 * Description: Galería simple para subir fotos con ID único y botón de WhatsApp (con carpetas anidadas)
 * Version: 2.6.0
 * Author: Lorenzo Sayes
 * Text Domain: galeria-whatsapp
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Galeria_WhatsApp {
    
    private $whatsapp_number = '5491153461105';
    private $whatsapp_message = 'Hola, me interesa la foto #%s digital';
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // Verificar estructura al cargar admin
        add_action('admin_init', array($this, 'check_database_structure'));
        
        // AJAX handlers
        add_action('wp_ajax_upload_gallery_photo', array($this, 'upload_photo'));
        add_action('wp_ajax_get_gallery_photos', array($this, 'get_photos'));
        add_action('wp_ajax_delete_gallery_photo', array($this, 'delete_photo'));
        add_action('wp_ajax_create_gallery_folder', array($this, 'create_folder'));
        add_action('wp_ajax_get_gallery_folders', array($this, 'get_folders'));
        add_action('wp_ajax_delete_gallery_folder', array($this, 'delete_folder'));
        
        // Public AJAX - para el frontend
        add_action('wp_ajax_nopriv_get_gallery_photos', array($this, 'get_photos'));
        add_action('wp_ajax_nopriv_get_gallery_folders', array($this, 'get_folders'));
        
        // Shortcode
        add_shortcode('galeria_whatsapp', array($this, 'gallery_shortcode'));
    }
    
    public function check_database_structure() {
        global $wpdb;
        $table_photos = $wpdb->prefix . 'galeria_whatsapp';
        $table_folders = $wpdb->prefix . 'galeria_folders';
        
        $column = $wpdb->get_results("SHOW COLUMNS FROM $table_photos LIKE 'folder_id'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE $table_photos ADD COLUMN folder_id mediumint(9) DEFAULT 0 AFTER image_url");
            $wpdb->query("ALTER TABLE $table_photos ADD KEY folder_id (folder_id)");
            $wpdb->query("ALTER TABLE $table_photos MODIFY photo_id varchar(20) NOT NULL");
        }
        
        $parent_column = $wpdb->get_results("SHOW COLUMNS FROM $table_folders LIKE 'parent_id'");
        if (empty($parent_column)) {
            $wpdb->query("ALTER TABLE $table_folders ADD COLUMN parent_id mediumint(9) DEFAULT 0 AFTER name");
            $wpdb->query("ALTER TABLE $table_folders ADD KEY parent_id (parent_id)");
        }
    }
    
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_photos = $wpdb->prefix . 'galeria_whatsapp';
        $sql_photos = "CREATE TABLE IF NOT EXISTS $table_photos (
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
        
        $table_folders = $wpdb->prefix . 'galeria_folders';
        $sql_folders = "CREATE TABLE IF NOT EXISTS $table_folders (
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
        
        $this->check_database_structure();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Galería WhatsApp',
            'Galería Fotos',
            'manage_options',
            'galeria-whatsapp',
            array($this, 'admin_page'),
            'dashicons-format-gallery',
            30
        );
    }
    
    public function admin_scripts($hook) {
        if ($hook !== 'toplevel_page_galeria-whatsapp') {
            return;
        }
        
        wp_enqueue_media();
        
        $admin_css = "
        .galeria-admin .top-controls { display: flex; gap: 20px; margin: 20px 0; align-items: flex-start; flex-wrap: wrap; }
        .galeria-admin .upload-section, .galeria-admin .folder-section { flex: 1; min-width: 300px; }
        .galeria-admin .folder-section { background: #f0f0f1; padding: 20px; border-radius: 8px; }
        .galeria-admin #upload-photos-btn { font-size: 16px; padding: 10px 30px; height: auto; }
        .galeria-admin #upload-photos-btn .dashicons { margin-top: 4px; }
        .galeria-admin .folder-controls { display: flex; gap: 10px; margin-top: 10px; }
        .galeria-admin #new-folder-name { flex: 1; padding: 8px; font-size: 14px; }
        .galeria-admin #create-folder-btn { padding: 8px 20px; }
        .folders-list { margin-top: 15px; max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; }
        .folder-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f0f0f1; cursor: pointer; transition: background 0.2s; }
        .folder-item:last-child { border-bottom: none; }
        .folder-item:hover { background: #f9f9f9; }
        .folder-item.active { background: #2271b1; color: white; }
        .folder-item.level-1 { padding-left: 30px; }
        .folder-item.level-2 { padding-left: 50px; }
        .folder-item.level-3 { padding-left: 70px; }
        .folder-item .folder-name { font-weight: 500; flex: 1; display: flex; align-items: center; gap: 8px; }
        .folder-item .folder-toggle { cursor: pointer; padding: 0 5px; font-weight: bold; color: #666; user-select: none; }
        .folder-item.active .folder-toggle { color: white; }
        .folder-item .folder-count { color: #666; font-size: 12px; margin-left: 10px; padding: 2px 8px; background: #f0f0f1; border-radius: 3px; }
        .folder-item.active .folder-count { color: #2271b1; background: white; }
        .folder-actions { display: flex; gap: 5px; align-items: center; }
        .folder-item .add-subfolder { color: #0073aa; cursor: pointer; padding: 2px 8px; font-weight: bold; font-size: 14px; }
        .folder-item.active .add-subfolder { color: #a8d8ff; }
        .folder-item .delete-folder { color: #dc3232; cursor: pointer; padding: 2px 8px; font-weight: bold; }
        .folder-item.active .delete-folder { color: #ffcccc; }
        .photos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .photo-item { position: relative; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #f9f9f9; }
        .photo-item img { width: 100%; height: 200px; object-fit: cover; display: block; }
        .photo-info { padding: 10px; background: white; }
        .photo-id { font-weight: bold; font-size: 16px; color: #2271b1; display: block; margin-bottom: 5px; }
        .photo-folder { font-size: 11px; color: #666; background: #f0f0f1; padding: 3px 8px; border-radius: 3px; display: inline-block; margin-bottom: 5px; }
        .photo-date { font-size: 12px; color: #666; display: block; }
        .delete-photo { position: absolute; top: 5px; right: 5px; background: #dc3232; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; font-size: 18px; line-height: 1; }
        .photo-item:hover .delete-photo { opacity: 1; }
        .delete-photo:hover { background: #a00; }
        .shortcode-info { background: #f0f0f1; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .shortcode-box { background: white; padding: 10px 20px; border: 2px dashed #2271b1; border-radius: 4px; font-size: 16px; display: inline-block; margin-top: 10px; user-select: all; }
        .current-folder-info { margin: 15px 0; padding: 10px; background: #d7f0ff; border-left: 4px solid #2271b1; border-radius: 4px; font-weight: 500; }
        .breadcrumb { display: flex; align-items: center; gap: 5px; margin-bottom: 10px; flex-wrap: wrap; }
        .breadcrumb-item { color: #2271b1; cursor: pointer; }
        .breadcrumb-item:hover { text-decoration: underline; }
        .breadcrumb-separator { color: #666; }
        ";
        
        wp_register_style('galeria-admin-style', false);
        wp_enqueue_style('galeria-admin-style');
        wp_add_inline_style('galeria-admin-style', $admin_css);
        
        wp_enqueue_script('jquery');
        
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('galeria_nonce');
        
        $admin_js = "
        jQuery(document).ready(function($) {
            var mediaUploader;
            var currentFolder = 0;
            var currentFolderName = 'Todas las fotos';
            var currentParentFolder = 0;
            var folderPath = [];
            
            console.log('📂 Galería WhatsApp Admin v2.6 - FIXED');
            
            loadFolders();
            loadPhotos();
            
            function updateCurrentFolderInfo() {
                var breadcrumb = '<div class=\"breadcrumb\">';
                if (folderPath.length === 0) {
                    breadcrumb += '<span class=\"breadcrumb-item\" data-id=\"0\">📁 Raíz</span>';
                } else {
                    breadcrumb += '<span class=\"breadcrumb-item\" data-id=\"0\">📁 Raíz</span>';
                    folderPath.forEach(function(folder) {
                        breadcrumb += '<span class=\"breadcrumb-separator\">›</span>';
                        breadcrumb += '<span class=\"breadcrumb-item\" data-id=\"' + folder.id + '\">' + folder.name + '</span>';
                    });
                }
                breadcrumb += '</div>';
                
                var infoText = breadcrumb + '📁 Carpeta actual: <strong>' + currentFolderName + '</strong>';
                $('.current-folder-info').remove();
                $('.upload-section').append('<div class=\"current-folder-info\">' + infoText + '</div>');
            }
            
            updateCurrentFolderInfo();
            
            $(document).on('click', '.breadcrumb-item', function() {
                var folderId = parseInt($(this).data('id'));
                
                if (folderId === 0) {
                    currentFolder = 0;
                    currentFolderName = 'Todas las fotos';
                    currentParentFolder = 0;
                    folderPath = [];
                } else {
                    var index = folderPath.findIndex(f => f.id === folderId);
                    if (index !== -1) {
                        currentFolder = folderId;
                        currentFolderName = folderPath[index].name;
                        currentParentFolder = index > 0 ? folderPath[index - 1].id : 0;
                        folderPath = folderPath.slice(0, index + 1);
                    }
                }
                
                $('.folder-item').removeClass('active');
                $('.folder-item[data-id=\"' + currentFolder + '\"]').addClass('active');
                updateCurrentFolderInfo();
                loadPhotos();
            });
            
            $('#upload-photos-btn').on('click', function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: 'Seleccionar Fotos para ' + currentFolderName,
                    button: { text: 'Agregar a la Galería' },
                    multiple: true,
                    library: { type: 'image' }
                });
                
                mediaUploader.on('select', function() {
                    var attachments = mediaUploader.state().get('selection').toJSON();
                    var totalToUpload = attachments.length;
                    var totalUploaded = 0;
                    var errors = 0;
                    
                    attachments.forEach(function(attachment) {
                        $.ajax({
                            url: '" . $ajax_url . "',
                            type: 'POST',
                            data: {
                                action: 'upload_gallery_photo',
                                nonce: '" . $nonce . "',
                                attachment_id: attachment.id,
                                folder_id: currentFolder
                            },
                            success: function(response) {
                                if (response.success) {
                                    totalUploaded++;
                                } else {
                                    errors++;
                                }
                                
                                if (totalUploaded + errors === totalToUpload) {
                                    loadPhotos();
                                    loadFolders();
                                    alert('✅ ' + totalUploaded + ' foto(s) subidas!');
                                }
                            }
                        });
                    });
                });
                
                mediaUploader.open();
            });
            
            $('#create-folder-btn').on('click', function() {
                var folderName = $('#new-folder-name').val().trim();
                
                if (!folderName) {
                    alert('⚠️ Ingresa un nombre');
                    return;
                }
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'create_gallery_folder',
                        nonce: '" . $nonce . "',
                        folder_name: folderName,
                        parent_id: currentParentFolder
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#new-folder-name').val('');
                            loadFolders();
                            alert('✅ Carpeta creada!');
                        }
                    }
                });
            });
            
            $(document).on('click', '.add-subfolder', function(e) {
                e.stopPropagation();
                
                var parentId = $(this).data('id');
                var parentName = $(this).closest('.folder-item').find('.folder-name').text().trim();
                
                var subfolderName = prompt('Nombre de la subcarpeta:');
                
                if (!subfolderName || !subfolderName.trim()) return;
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'create_gallery_folder',
                        nonce: '" . $nonce . "',
                        folder_name: subfolderName.trim(),
                        parent_id: parentId
                    },
                    success: function(response) {
                        if (response.success) {
                            loadFolders();
                            alert('✅ Subcarpeta creada!');
                        }
                    }
                });
            });
            
            function loadFolders() {
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'get_gallery_folders',
                        nonce: '" . $nonce . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayFolders(response.data);
                        }
                    }
                });
            }
            
            function displayFolders(folders) {
                var list = $('.folders-list');
                list.empty();
                
                var allHtml = '<div class=\"folder-item ' + (currentFolder === 0 ? 'active' : '') + '\" data-id=\"0\" data-parent=\"0\">' +
                    '<span class=\"folder-name\">📁 Todas las fotos</span>' +
                    '<div class=\"folder-actions\"><span class=\"folder-count\"></span></div>' +
                    '</div>';
                list.append(allHtml);
                
                function buildTree(parentId, level) {
                    var children = folders.filter(f => parseInt(f.parent_id) === parentId);
                    
                    children.forEach(function(folder) {
                        var isActive = currentFolder == folder.id;
                        var hasChildren = folders.some(f => parseInt(f.parent_id) === parseInt(folder.id));
                        var toggle = hasChildren ? '<span class=\"folder-toggle\">▼</span>' : '<span style=\"width: 15px; display: inline-block;\"></span>';
                        
                        var folderHtml = '<div class=\"folder-item level-' + level + ' ' + (isActive ? 'active' : '') + '\" ' +
                            'data-id=\"' + folder.id + '\" ' +
                            'data-parent=\"' + folder.parent_id + '\" ' +
                            'data-name=\"' + folder.name + '\">' +
                            '<span class=\"folder-name\">' + toggle + ' 📂 ' + folder.name + '</span>' +
                            '<div class=\"folder-actions\">' +
                            '<span class=\"folder-count\">(' + (folder.photo_count || 0) + ')</span>' +
                            '<span class=\"add-subfolder\" data-id=\"' + folder.id + '\">+</span>' +
                            '<span class=\"delete-folder\" data-id=\"' + folder.id + '\">✕</span>' +
                            '</div></div>';
                        
                        list.append(folderHtml);
                        
                        if (hasChildren) {
                            buildTree(parseInt(folder.id), level + 1);
                        }
                    });
                }
                
                buildTree(0, 1);
            }
            
            $(document).on('click', '.folder-toggle', function(e) {
                e.stopPropagation();
                var folderItem = $(this).closest('.folder-item');
                var folderId = folderItem.data('id');
                var level = parseInt(folderItem.attr('class').match(/level-(\\d+)/)[1]);
                
                if ($(this).text() === '▼') {
                    $(this).text('▶');
                    folderItem.nextUntil('.level-' + level + ', .level-0').hide();
                } else {
                    $(this).text('▼');
                    var nextLevel = level + 1;
                    folderItem.nextAll('.level-' + nextLevel).each(function() {
                        if ($(this).data('parent') == folderId) {
                            $(this).show();
                        } else {
                            return false;
                        }
                    });
                }
            });
            
            $(document).on('click', '.folder-name', function(e) {
                if ($(e.target).hasClass('folder-toggle')) return;
                
                var folderItem = $(this).closest('.folder-item');
                currentFolder = parseInt(folderItem.data('id'));
                currentFolderName = folderItem.data('name') || 'Todas las fotos';
                currentParentFolder = parseInt(folderItem.data('parent')) || 0;
                
                folderPath = [];
                if (currentFolder !== 0) {
                    buildPath(currentFolder);
                }
                
                $('.folder-item').removeClass('active');
                folderItem.addClass('active');
                updateCurrentFolderInfo();
                loadPhotos();
            });
            
            function buildPath(folderId) {
                $('.folder-item[data-id=\"' + folderId + '\"]').each(function() {
                    var name = $(this).data('name');
                    var parentId = parseInt($(this).data('parent'));
                    
                    folderPath.unshift({
                        id: folderId,
                        name: name,
                        parent: parentId
                    });
                    
                    if (parentId > 0) {
                        buildPath(parentId);
                    }
                });
            }
            
            $(document).on('click', '.delete-folder', function(e) {
                e.stopPropagation();
                
                if (!confirm('❌ ¿Eliminar carpeta y subcarpetas?')) return;
                
                var folderId = $(this).data('id');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'delete_gallery_folder',
                        nonce: '" . $nonce . "',
                        folder_id: folderId
                    },
                    success: function(response) {
                        if (response.success) {
                            currentFolder = 0;
                            currentFolderName = 'Todas las fotos';
                            currentParentFolder = 0;
                            folderPath = [];
                            updateCurrentFolderInfo();
                            loadFolders();
                            loadPhotos();
                        }
                    }
                });
            });
            
            function loadPhotos() {
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'get_gallery_photos',
                        nonce: '" . $nonce . "',
                        folder_id: currentFolder,
                        include_subfolders: true
                    },
                    success: function(response) {
                        console.log('📷 Fotos recibidas:', response);
                        if (response.success) {
                            displayPhotos(response.data);
                        }
                    }
                });
            }
            
            function displayPhotos(photos) {
                var grid = $('#photos-grid');
                grid.empty();
                
                if (!photos || photos.length === 0) {
                    grid.html('<p style=\"grid-column: 1/-1; text-align: center; padding: 40px;\">No hay fotos.</p>');
                    return;
                }
                
                photos.forEach(function(photo) {
                    var folderTag = photo.folder_path ? '<span class=\"photo-folder\">📂 ' + photo.folder_path + '</span>' : '';
                    
                    var photoHtml = '<div class=\"photo-item\" data-id=\"' + photo.id + '\">' +
                        '<button class=\"delete-photo\" data-id=\"' + photo.id + '\">✕</button>' +
                        '<img src=\"' + photo.image_url + '\">' +
                        '<div class=\"photo-info\">' +
                        folderTag +
                        '<span class=\"photo-id\">#' + photo.photo_id + '</span>' +
                        '<span class=\"photo-date\">' + new Date(photo.upload_date).toLocaleDateString() + '</span>' +
                        '</div></div>';
                    grid.append(photoHtml);
                });
            }
            
            $(document).on('click', '.delete-photo', function() {
                if (!confirm('❌ ¿Eliminar foto?')) return;
                
                var dbId = $(this).data('id');
                var photoItem = $(this).closest('.photo-item');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'delete_gallery_photo',
                        nonce: '" . $nonce . "',
                        db_id: dbId
                    },
                    success: function(response) {
                        if (response.success) {
                            photoItem.fadeOut(300, function() {
                                $(this).remove();
                            });
                            loadFolders();
                        }
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $admin_js);
    }
    
    public function frontend_scripts() {
        $css = '.galeria-whatsapp-frontend { padding: 20px 0; }
        .galeria-search-section { margin-bottom: 30px; text-align: center; }
        .galeria-search-section h3 { margin-bottom: 15px; }
        .galeria-search-box { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .galeria-search-box input { padding: 12px 20px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; min-width: 250px; }
        .galeria-search-box button { padding: 12px 30px; font-size: 16px; background: #2271b1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .galeria-search-box button:hover { background: #135e96; }
        .galeria-folder-filter { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .galeria-folder-btn { padding: 10px 20px; background: #f0f0f1; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.3s; }
        .galeria-folder-btn:hover { background: #e0e0e1; }
        .galeria-folder-btn.active { background: #2271b1; color: white; border-color: #2271b1; }
        .galeria-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; }
        .galeria-item { position: relative; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s, box-shadow 0.3s; }
        .galeria-item:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }
        .galeria-item.highlight { animation: highlight-pulse 1s ease-in-out; }
        @keyframes highlight-pulse { 0%, 100% { box-shadow: 0 4px 6px rgba(0,0,0,0.1); } 50% { box-shadow: 0 0 20px 5px rgba(34, 113, 177, 0.6); } }
        .galeria-image-wrapper { position: relative; overflow: hidden; }
        .galeria-image-wrapper img { width: 100%; height: 300px; object-fit: cover; display: block; }
        .galeria-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: space-between; padding: 15px; opacity: 0; transition: opacity 0.3s; }
        .galeria-item:hover .galeria-overlay { opacity: 1; }
        .galeria-id { color: white; font-size: 24px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        .galeria-whatsapp-btn { display: inline-flex; align-items: center; gap: 8px; background: #25D366; color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: bold; transition: background 0.3s; align-self: flex-start; }
        .galeria-whatsapp-btn:hover { background: #128C7E; color: white; }
        .no-photos { text-align: center; padding: 40px; color: #666; font-size: 18px; }
        .no-results { text-align: center; padding: 40px; color: #dc3232; font-size: 18px; }
        @media (max-width: 768px) { .galeria-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; } .galeria-image-wrapper img { height: 200px; } }';
        
        wp_register_style('galeria-frontend', false);
        wp_enqueue_style('galeria-frontend');
        wp_add_inline_style('galeria-frontend', $css);
        
        wp_enqueue_script('jquery');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap galeria-admin">
            <h1>📸 Galería de Fotos con WhatsApp</h1>
            <p>Organiza tus fotos en carpetas anidadas. Cada foto recibe un ID único automáticamente.</p>
            
            <div class="top-controls">
                <div class="upload-section">
                    <h3>Subir Fotos</h3>
                    <button id="upload-photos-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-upload"></span> Subir Fotos
                    </button>
                </div>
                
                <div class="folder-section">
                    <h3>Gestionar Carpetas</h3>
                    <div class="folder-controls">
                        <input type="text" id="new-folder-name" placeholder="Nombre de la carpeta" />
                        <button id="create-folder-btn" class="button">Crear Carpeta</button>
                    </div>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        💡 Tip: Selecciona una carpeta y haz click en <strong>+</strong> para crear subcarpeta
                    </p>
                    <div class="folders-list">
                        <p style="padding: 10px; text-align: center; color: #666;">Cargando...</p>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <h2>Fotos</h2>
            <div id="photos-grid" class="photos-grid">
                <p style="padding: 20px; text-align: center; color: #666;">Cargando fotos...</p>
            </div>
            
            <hr>
            
            <div class="shortcode-info">
                <h3>📋 Cómo mostrar la galería en tu sitio</h3>
                <p>Copia y pega este shortcode en cualquier página o entrada:</p>
                <code class="shortcode-box">[galeria_whatsapp]</code>
            </div>
        </div>
        <?php
    }
    
    public function create_folder() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        
        if (empty($folder_name)) {
            wp_send_json_error('Nombre inválido');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'galeria_folders';
        
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'name' => $folder_name,
                'parent_id' => $parent_id
            ),
            array('%s', '%d')
        );
        
        if ($inserted) {
            wp_send_json_success(array(
                'folder_id' => $wpdb->insert_id,
                'parent_id' => $parent_id
            ));
        } else {
            wp_send_json_error('Error al crear carpeta');
        }
    }
    
    private function get_folder_path($folder_id) {
        if ($folder_id == 0) {
            return '';
        }
        
        global $wpdb;
        $table_folders = $wpdb->prefix . 'galeria_folders';
        
        $path = array();
        $current_id = $folder_id;
        $max_depth = 10;
        $depth = 0;
        
        while ($current_id > 0 && $depth < $max_depth) {
            $folder = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, parent_id FROM $table_folders WHERE id = %d",
                $current_id
            ));
            
            if (!$folder) break;
            
            array_unshift($path, $folder->name);
            $current_id = $folder->parent_id;
            $depth++;
        }
        
        return implode(' › ', $path);
    }
    
    private function get_all_subfolder_ids($folder_id) {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'galeria_folders';
        
        $ids = array($folder_id);
        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table_folders WHERE parent_id = %d",
            $folder_id
        ));
        
        foreach ($children as $child_id) {
            $ids = array_merge($ids, $this->get_all_subfolder_ids($child_id));
        }
        
        return $ids;
    }
    
    public function get_folders() {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'galeria_folders';
        $table_photos = $wpdb->prefix . 'galeria_whatsapp';
        
        // Obtener todas las carpetas con conteo de fotos (incluyendo subcarpetas)
        $folders = $wpdb->get_results("
            SELECT f.id, f.name, f.parent_id, f.created_date
            FROM $table_folders f 
            ORDER BY f.parent_id ASC, f.name ASC
        ", ARRAY_A);
        
        // Calcular el conteo de fotos incluyendo subcarpetas
        foreach ($folders as &$folder) {
            $subfolder_ids = $this->get_all_subfolder_ids($folder['id']);
            $ids_string = implode(',', array_map('intval', $subfolder_ids));
            
            $count = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM $table_photos 
                WHERE folder_id IN ($ids_string)
            ");
            
            $folder['photo_count'] = $count ? intval($count) : 0;
        }
        
        wp_send_json_success($folders ? $folders : array());
    }
    
    public function delete_folder() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        if ($folder_id <= 0) {
            wp_send_json_error('ID de carpeta inválido');
            return;
        }
        
        global $wpdb;
        $table_folders = $wpdb->prefix . 'galeria_folders';
        $table_photos = $wpdb->prefix . 'galeria_whatsapp';
        
        $all_folder_ids = $this->get_all_subfolder_ids($folder_id);
        $ids_string = implode(',', array_map('intval', $all_folder_ids));
        
        // Mover fotos a raíz
        $wpdb->query("UPDATE $table_photos SET folder_id = 0 WHERE folder_id IN ($ids_string)");
        
        // Eliminar carpetas
        $deleted = $wpdb->query("DELETE FROM $table_folders WHERE id IN ($ids_string)");
        
        if ($deleted !== false) {
            wp_send_json_success(array('deleted_count' => $deleted));
        } else {
            wp_send_json_error('Error al eliminar carpeta');
        }
    }
    
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
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'galeria_whatsapp';
        
        $today = date('Ymd');
        
        $last_id = $wpdb->get_var($wpdb->prepare(
            "SELECT photo_id FROM $table_name WHERE photo_id LIKE %s ORDER BY photo_id DESC LIMIT 1",
            $today . '-%'
        ));
        
        if ($last_id) {
            $parts = explode('-', $last_id);
            $day_number = intval($parts[1]) + 1;
        } else {
            $day_number = 1;
        }
        
        $new_id = $today . '-' . str_pad($day_number, 3, '0', STR_PAD_LEFT);
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            wp_send_json_error('No se pudo obtener la URL de la imagen');
            return;
        }
        
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'photo_id' => $new_id,
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'folder_id' => $folder_id
            ),
            array('%s', '%d', '%s', '%d')
        );
        
        if ($inserted) {
            wp_send_json_success(array(
                'photo_id' => $new_id,
                'image_url' => $image_url,
                'db_id' => $wpdb->insert_id,
                'folder_id' => $folder_id
            ));
        } else {
            wp_send_json_error('Error al guardar en la base de datos');
        }
    }
    
    public function get_photos() {
        global $wpdb;
        $table_photos = $wpdb->prefix . 'galeria_whatsapp';
        $table_folders = $wpdb->prefix . 'galeria_folders';
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
        $include_subfolders = isset($_POST['include_subfolders']) ? (bool)$_POST['include_subfolders'] : false;
        
        // Si es carpeta raíz (0 o null), mostrar todas las fotos
        if ($folder_id === null || $folder_id === 0) {
            $photos = $wpdb->get_results("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM $table_photos p 
                LEFT JOIN $table_folders f ON p.folder_id = f.id 
                ORDER BY p.photo_id DESC
            ", ARRAY_A);
        } 
        // Si debe incluir subcarpetas
        elseif ($include_subfolders) {
            $subfolder_ids = $this->get_all_subfolder_ids($folder_id);
            $ids_string = implode(',', array_map('intval', $subfolder_ids));
            
            $photos = $wpdb->get_results("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM $table_photos p 
                LEFT JOIN $table_folders f ON p.folder_id = f.id 
                WHERE p.folder_id IN ($ids_string)
                ORDER BY p.photo_id DESC
            ", ARRAY_A);
        }
        // Solo carpeta específica
        else {
            $photos = $wpdb->get_results($wpdb->prepare("
                SELECT p.id, p.photo_id, p.attachment_id, p.image_url, p.folder_id, p.upload_date, f.name as folder_name 
                FROM $table_photos p 
                LEFT JOIN $table_folders f ON p.folder_id = f.id 
                WHERE p.folder_id = %d 
                ORDER BY p.photo_id DESC
            ", $folder_id), ARRAY_A);
        }
        
        // Agregar path completo de carpeta
        foreach ($photos as &$photo) {
            if ($photo['folder_id'] > 0) {
                $photo['folder_path'] = $this->get_folder_path($photo['folder_id']);
            } else {
                $photo['folder_path'] = '';
            }
        }
        
        wp_send_json_success($photos ? $photos : array());
    }
    
    public function delete_photo() {
        check_ajax_referer('galeria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        $db_id = isset($_POST['db_id']) ? intval($_POST['db_id']) : 0;
        
        if ($db_id <= 0) {
            wp_send_json_error('ID inválido');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'galeria_whatsapp';
        
        $deleted = $wpdb->delete($table_name, array('id' => $db_id), array('%d'));
        
        if ($deleted) {
            wp_send_json_success(array('message' => 'Foto eliminada'));
        } else {
            wp_send_json_error('Error al eliminar foto');
        }
    }
    
    public function gallery_shortcode($atts) {
        $ajax_url = admin_url('admin-ajax.php');
        
        ob_start();
        ?>
        <div class="galeria-whatsapp-frontend">
            <!-- Buscador -->
            <div class="galeria-search-section">
                <h3>🔍 Buscar foto por número</h3>
                <div class="galeria-search-box">
                    <input type="text" id="galeria-search-input" placeholder="Ejemplo: 20251104-001">
                    <button id="galeria-search-btn">Buscar</button>
                </div>
            </div>
            
            <!-- Filtro por carpetas -->
            <div class="galeria-folder-filter" id="galeria-folder-filter">
                <p style="text-align: center; color: #666;">Cargando carpetas...</p>
            </div>
            
            <!-- Grid de fotos -->
            <div class="galeria-grid" id="galeria-grid">
                <p style="text-align: center; padding: 40px; color: #666;">Cargando fotos...</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('🖼️ Galería WhatsApp Frontend v2.6 - FIXED');
            
            var currentFolder = 0;
            var currentSubfolders = [];
            var allFolders = [];
            
            loadFolders();
            loadPhotos();
            
            function loadFolders() {
                $.ajax({
                    url: '<?php echo $ajax_url; ?>',
                    type: 'POST',
                    data: {
                        action: 'get_gallery_folders'
                    },
                    success: function(response) {
                        console.log('📂 Carpetas cargadas:', response);
                        if (response.success && response.data) {
                            allFolders = response.data;
                            displayFolders(response.data);
                        } else {
                            $('#galeria-folder-filter').html('');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error cargando carpetas:', error);
                        $('#galeria-folder-filter').html('');
                    }
                });
            }
            
            function loadPhotos(folderId) {
                var requestData = {
                    action: 'get_gallery_photos'
                };
                
                // Si hay carpeta específica, incluir subcarpetas
                if (folderId && folderId > 0) {
                    requestData.folder_id = folderId;
                    requestData.include_subfolders = true;
                }
                
                $.ajax({
                    url: '<?php echo $ajax_url; ?>',
                    type: 'POST',
                    data: requestData,
                    success: function(response) {
                        console.log('📷 Fotos cargadas:', response);
                        if (response.success && response.data) {
                            displayPhotos(response.data);
                        } else {
                            $('#galeria-grid').html('<p class="no-photos">📷 No hay fotos disponibles.</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error cargando fotos:', error);
                        $('#galeria-grid').html('<p class="no-photos">❌ Error al cargar fotos.</p>');
                    }
                });
            }
            
            function displayFolders(folders) {
                var filterDiv = $('#galeria-folder-filter');
                filterDiv.empty();
                
                var allBtn = $('<button class="galeria-folder-btn active" data-folder="0">📁 Todas</button>');
                filterDiv.append(allBtn);
                
                // Filtrar carpetas que tienen fotos
                var foldersWithPhotos = folders.filter(function(folder) {
                    return folder.photo_count > 0;
                });
                
                console.log('📂 Carpetas con fotos:', foldersWithPhotos.length);
                
                foldersWithPhotos.forEach(function(folder) {
                    var subfolder_ids = getSubfolderIds(folder.id, folders);
                    var folderPath = getFolderPath(folder.id, folders);
                    var displayName = folderPath || folder.name;
                    
                    var btn = $('<button class="galeria-folder-btn" data-folder="' + folder.id + '" data-subfolders=\'' + JSON.stringify(subfolder_ids) + '\'>' +
                        '📂 ' + displayName + ' (' + folder.photo_count + ')' +
                        '</button>');
                    filterDiv.append(btn);
                });
            }
            
            function getSubfolderIds(folderId, allFolders) {
                var ids = [folderId];
                var children = allFolders.filter(f => parseInt(f.parent_id) === folderId);
                
                children.forEach(function(child) {
                    ids = ids.concat(getSubfolderIds(child.id, allFolders));
                });
                
                return ids;
            }
            
            function getFolderPath(folderId, allFolders) {
                var path = [];
                var currentId = folderId;
                var maxDepth = 10;
                var depth = 0;
                
                while (currentId > 0 && depth < maxDepth) {
                    var folder = allFolders.find(f => parseInt(f.id) === parseInt(currentId));
                    if (!folder) break;
                    
                    path.unshift(folder.name);
                    currentId = folder.parent_id;
                    depth++;
                }
                
                return path.join(' › ');
            }
            
            function displayPhotos(photos) {
                var grid = $('#galeria-grid');
                grid.empty();
                
                if (!photos || photos.length === 0) {
                    grid.html('<p class="no-photos">📷 No hay fotos disponibles.</p>');
                    return;
                }
                
                photos.forEach(function(photo) {
                    var whatsappUrl = 'https://wa.me/<?php echo $this->whatsapp_number; ?>?text=' + 
                        encodeURIComponent('<?php echo $this->whatsapp_message; ?>'.replace('%s', photo.photo_id));
                    
                    var photoHtml = '<div class="galeria-item" data-photo-id="' + photo.photo_id + '" data-folder="' + (photo.folder_id || 0) + '">' +
                        '<div class="galeria-image-wrapper">' +
                        '<img src="' + photo.image_url + '" alt="Foto ' + photo.photo_id + '">' +
                        '<div class="galeria-overlay">' +
                        '<span class="galeria-id">#' + photo.photo_id + '</span>' +
                        '<a href="' + whatsappUrl + '" target="_blank" class="galeria-whatsapp-btn">' +
                        '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">' +
                        '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>' +
                        '</svg> Consultar</a>' +
                        '</div></div></div>';
                    
                    grid.append(photoHtml);
                });
            }
            
            $(document).on('click', '.galeria-folder-btn', function() {
                $('.galeria-folder-btn').removeClass('active');
                $(this).addClass('active');
                
                currentFolder = parseInt($(this).data('folder'));
                var subfoldersData = $(this).data('subfolders');
                currentSubfolders = Array.isArray(subfoldersData) ? subfoldersData : [currentFolder];
                
                console.log('📂 Filtrar carpeta:', currentFolder, 'Subcarpetas:', currentSubfolders);
                
                // Recargar fotos según la carpeta seleccionada
                loadPhotos(currentFolder);
            });
            
            $('#galeria-search-btn').click(searchPhoto);
            $('#galeria-search-input').keypress(function(e) {
                if (e.which === 13) searchPhoto();
            });
            
            function searchPhoto() {
                var searchId = $('#galeria-search-input').val().trim();
                if (!searchId) {
                    alert('⚠️ Ingresa un número');
                    return;
                }
                
                $('.galeria-item').removeClass('highlight');
                $('.no-results').remove();
                
                var found = false;
                $('.galeria-item').each(function() {
                    var photoId = $(this).data('photo-id');
                    if (photoId == searchId || String(photoId).includes(searchId)) {
                        found = true;
                        $(this).show().addClass('highlight');
                        $('html, body').animate({
                            scrollTop: $(this).offset().top - 100
                        }, 500);
                        return false;
                    }
                });
                
                if (!found) {
                    $('#galeria-grid').before('<p class="no-results">❌ No se encontró #' + searchId + '</p>');
                }
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

new Galeria_WhatsApp();