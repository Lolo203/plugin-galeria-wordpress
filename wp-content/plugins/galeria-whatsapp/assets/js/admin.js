/**
 * JavaScript del Admin
 * @package Galeria_WhatsApp
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var mediaUploader;
    var currentFolder = 0;
    var currentFolderName = 'Todas las fotos';
    var currentParentFolder = 0;
    var folderPath = [];
    var selectedPhotos = [];
    
    console.log('📂 Galería WhatsApp Admin v' + galeriaAdmin.version);
    
    // Inicializar
    loadFolders();
    loadPhotos();
    
    /**
     * Actualizar información de carpeta actual
     */
    function updateCurrentFolderInfo() {
        var breadcrumb = '<div class="breadcrumb">';
        
        if (folderPath.length === 0) {
            breadcrumb += '<span class="breadcrumb-item" data-id="0">📁 Raíz</span>';
        } else {
            breadcrumb += '<span class="breadcrumb-item" data-id="0">📁 Raíz</span>';
            folderPath.forEach(function(folder) {
                breadcrumb += '<span class="breadcrumb-separator">›</span>';
                breadcrumb += '<span class="breadcrumb-item" data-id="' + folder.id + '">' + folder.name + '</span>';
            });
        }
        breadcrumb += '</div>';
        
        var infoText = breadcrumb + '📁 Carpeta actual: <strong>' + currentFolderName + '</strong>';
        $('.current-folder-info').remove();
        $('.upload-section').append('<div class="current-folder-info">' + infoText + '</div>');
    }
    
    updateCurrentFolderInfo();
    
    /**
     * Click en breadcrumb
     */
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
        $('.folder-item[data-id="' + currentFolder + '"]').addClass('active');
        updateCurrentFolderInfo();
        loadPhotos();
    });
    
    /**
     * Botón subir fotos
     */
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
            var errorDetails = [];
            
            console.log('📤 Subiendo', totalToUpload, 'foto(s)...');
            
            attachments.forEach(function(attachment) {
                $.ajax({
                    url: galeriaAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'upload_gallery_photo',
                        nonce: galeriaAdmin.nonce,
                        attachment_id: attachment.id,
                        folder_id: currentFolder
                    },
                    success: function(response) {
                        console.log('📷 Respuesta subida:', response);
                        
                        if (response.success && response.data) {
                            totalUploaded++;
                            console.log('✅ Foto subida:', response.data.photo_id);
                        } else {
                            errors++;
                            var errorMsg = response.data || 'Error desconocido';
                            errorDetails.push(attachment.filename + ': ' + errorMsg);
                            console.error('❌ Error:', errorMsg);
                        }
                        
                        // Cuando terminan todas las subidas
                        if (totalUploaded + errors === totalToUpload) {
                            loadPhotos();
                            loadFolders();
                            
                            if (totalUploaded > 0 && errors === 0) {
                                alert('✅ ' + totalUploaded + ' foto(s) subidas correctamente!');
                            } else if (totalUploaded > 0 && errors > 0) {
                                alert('⚠️ ' + totalUploaded + ' foto(s) subidas\n' + errors + ' error(es):\n' + errorDetails.join('\n'));
                            } else {
                                alert('❌ No se pudo subir ninguna foto:\n' + errorDetails.join('\n'));
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        errors++;
                        errorDetails.push(attachment.filename + ': Error AJAX - ' + error);
                        console.error('❌ Error AJAX:', error, xhr);
                        
                        if (totalUploaded + errors === totalToUpload) {
                            loadPhotos();
                            loadFolders();
                            alert('❌ Error al subir fotos:\n' + errorDetails.join('\n'));
                        }
                    }
                });
            });
        });
        
        mediaUploader.open();
    });
    
    /**
     * Crear carpeta
     */
    $('#create-folder-btn').on('click', function() {
        var folderName = $('#new-folder-name').val().trim();
        
        if (!folderName) {
            alert('⚠️ Ingresa un nombre');
            return;
        }
        
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_gallery_folder',
                nonce: galeriaAdmin.nonce,
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
    
    /**
     * Agregar subcarpeta
     */
    $(document).on('click', '.add-subfolder', function(e) {
        e.stopPropagation();
        
        var parentId = $(this).data('id');
        var subfolderName = prompt('Nombre de la subcarpeta:');
        
        if (!subfolderName || !subfolderName.trim()) return;
        
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_gallery_folder',
                nonce: galeriaAdmin.nonce,
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
    
    /**
     * Cargar carpetas
     */
    function loadFolders() {
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_gallery_folders',
                nonce: galeriaAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayFolders(response.data);
                }
            }
        });
    }
    
    /**
     * Mostrar carpetas
     */
    function displayFolders(folders) {
        var list = $('.folders-list');
        list.empty();
        
        // Carpeta "Todas"
        var allHtml = '<div class="folder-item ' + (currentFolder === 0 ? 'active' : '') + '" data-id="0" data-parent="0">' +
            '<span class="folder-name">📁 Todas las fotos</span>' +
            '<div class="folder-actions"><span class="folder-count"></span></div>' +
            '</div>';
        list.append(allHtml);
        
        // Construir árbol recursivo
        function buildTree(parentId, level) {
            var children = folders.filter(f => parseInt(f.parent_id) === parentId);
            
            children.forEach(function(folder) {
                var isActive = currentFolder == folder.id;
                var hasChildren = folders.some(f => parseInt(f.parent_id) === parseInt(folder.id));
                var toggle = hasChildren ? '<span class="folder-toggle">▼</span>' : '<span style="width: 15px; display: inline-block;"></span>';
                
                var folderHtml = '<div class="folder-item level-' + level + ' ' + (isActive ? 'active' : '') + '" ' +
                    'data-id="' + folder.id + '" ' +
                    'data-parent="' + folder.parent_id + '" ' +
                    'data-name="' + folder.name + '">' +
                    '<span class="folder-name">' + toggle + ' 📂 ' + folder.name + '</span>' +
                    '<div class="folder-actions">' +
                    '<span class="folder-count">(' + (folder.photo_count || 0) + ')</span>' +
                    '<span class="add-subfolder" data-id="' + folder.id + '">+</span>' +
                    '<span class="delete-folder" data-id="' + folder.id + '">✕</span>' +
                    '</div></div>';
                
                list.append(folderHtml);
                
                if (hasChildren) {
                    buildTree(parseInt(folder.id), level + 1);
                }
            });
        }
        
        buildTree(0, 1);
    }
    
    /**
     * Toggle carpeta
     */
    $(document).on('click', '.folder-toggle', function(e) {
        e.stopPropagation();
        var folderItem = $(this).closest('.folder-item');
        var folderId = folderItem.data('id');
        var level = parseInt(folderItem.attr('class').match(/level-(\d+)/)[1]);
        
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
    
    /**
     * Seleccionar carpeta
     */
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
    
    /**
     * Construir path de carpeta
     */
    function buildPath(folderId) {
        $('.folder-item[data-id="' + folderId + '"]').each(function() {
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
    
    /**
     * Eliminar carpeta
     */
    $(document).on('click', '.delete-folder', function(e) {
        e.stopPropagation();
        
        if (!confirm('❌ ¿Eliminar carpeta y subcarpetas?')) return;
        
        var folderId = $(this).data('id');
        
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_gallery_folder',
                nonce: galeriaAdmin.nonce,
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
    
    /**
     * Cargar fotos
     */
    function loadPhotos() {
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_gallery_photos',
                nonce: galeriaAdmin.nonce,
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
    
    /**
     * Mostrar fotos
     */
    function displayPhotos(photos) {
        var grid = $('#photos-grid');
        grid.empty();
        
        // Resetear selección
        selectedPhotos = [];
        updateBulkActionsBar();
        
        if (!photos || photos.length === 0) {
            grid.html('<p style="grid-column: 1/-1; text-align: center; padding: 40px;">No hay fotos.</p>');
            return;
        }
        
        photos.forEach(function(photo) {
            var folderTag = photo.folder_path ? '<span class="photo-folder">📂 ' + photo.folder_path + '</span>' : '';
            
            var photoHtml = '<div class="photo-item" data-id="' + photo.id + '">' +
                '<input type="checkbox" class="photo-checkbox" data-id="' + photo.id + '">' +
                '<button class="delete-photo" data-id="' + photo.id + '">✕</button>' +
                '<img src="' + photo.image_url + '">' +
                '<div class="photo-info">' +
                folderTag +
                '<span class="photo-id">#' + photo.photo_id + '</span>' +
                '<span class="photo-date">' + new Date(photo.upload_date).toLocaleDateString() + '</span>' +
                '</div></div>';
            grid.append(photoHtml);
        });
    }
    
    /**
     * Actualizar barra de acciones masivas
     */
    function updateBulkActionsBar() {
        var count = selectedPhotos.length;
        $('#selected-count').text(count);
        
        if (count > 0) {
            $('#bulk-actions-bar').addClass('active');
        } else {
            $('#bulk-actions-bar').removeClass('active');
        }
    }
    
    /**
     * Checkbox de foto individual
     */
    $(document).on('change', '.photo-checkbox', function() {
        var photoId = parseInt($(this).data('id'));
        var photoItem = $(this).closest('.photo-item');
        
        if ($(this).is(':checked')) {
            if (!selectedPhotos.includes(photoId)) {
                selectedPhotos.push(photoId);
                photoItem.addClass('selected');
            }
        } else {
            selectedPhotos = selectedPhotos.filter(id => id !== photoId);
            photoItem.removeClass('selected');
        }
        
        updateBulkActionsBar();
    });
    
    /**
     * Seleccionar todas las fotos
     */
    $('#select-all-btn').on('click', function() {
        $('.photo-checkbox').prop('checked', true).trigger('change');
    });
    
    /**
     * Deseleccionar todas las fotos
     */
    $('#deselect-all-btn').on('click', function() {
        $('.photo-checkbox').prop('checked', false).trigger('change');
    });
    
    /**
     * Eliminar fotos seleccionadas
     */
    $('#delete-selected-btn').on('click', function() {
        if (selectedPhotos.length === 0) {
            alert('⚠️ No hay fotos seleccionadas');
            return;
        }
        
        var count = selectedPhotos.length;
        if (!confirm('❌ ¿Eliminar ' + count + ' foto(s) seleccionadas?')) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Eliminando...');
        
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_multiple_gallery_photos',
                nonce: galeriaAdmin.nonce,
                photo_ids: selectedPhotos
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    loadPhotos();
                    loadFolders();
                    selectedPhotos = [];
                    updateBulkActionsBar();
                } else {
                    alert('❌ Error: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error al eliminar fotos');
            },
            complete: function() {
                btn.prop('disabled', false).text('🗑️ Eliminar seleccionadas');
            }
        });
    });
    
    /**
     * Eliminar foto individual
     */
    $(document).on('click', '.delete-photo', function() {
        if (!confirm('❌ ¿Eliminar foto?')) return;
        
        var dbId = $(this).data('id');
        var photoItem = $(this).closest('.photo-item');
        
        $.ajax({
            url: galeriaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_gallery_photo',
                nonce: galeriaAdmin.nonce,
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