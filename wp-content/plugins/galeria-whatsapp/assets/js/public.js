/**
 * JavaScript del Frontend
 * @package Galeria_WhatsApp
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var currentFolder = 0;
    var currentSubfolders = [];
    var allFolders = [];
    
    console.log('🖼️ Galería WhatsApp Frontend v' + galeriaPublic.version);
    
    // Inicializar
    loadFolders();
    loadPhotos();
    
    /**
     * Cargar carpetas
     */
    function loadFolders() {
        $.ajax({
            url: galeriaPublic.ajaxUrl,
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
    
    /**
     * Cargar fotos
     */
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
            url: galeriaPublic.ajaxUrl,
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
    
    /**
     * Mostrar carpetas
     */
    function displayFolders(folders) {
        var filterDiv = $('#galeria-folder-filter');
        filterDiv.empty();
        
        // Botón "Todas"
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
    
    /**
     * Obtener IDs de subcarpetas (recursivo)
     */
    function getSubfolderIds(folderId, allFolders) {
        var ids = [folderId];
        var children = allFolders.filter(f => parseInt(f.parent_id) === folderId);
        
        children.forEach(function(child) {
            ids = ids.concat(getSubfolderIds(child.id, allFolders));
        });
        
        return ids;
    }
    
    /**
     * Obtener path completo de carpeta
     */
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
    
    /**
     * Mostrar fotos
     */
    function displayPhotos(photos) {
        var grid = $('#galeria-grid');
        grid.empty();
        
        if (!photos || photos.length === 0) {
            grid.html('<p class="no-photos">📷 No hay fotos disponibles.</p>');
            return;
        }
        
        photos.forEach(function(photo) {
            var whatsappUrl = 'https://wa.me/' + galeriaPublic.whatsappNumber + '?text=' + 
                encodeURIComponent(galeriaPublic.whatsappMessage.replace('%s', photo.photo_id));
            
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
    
    /**
     * Click en filtro de carpeta
     */
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
    
    /**
     * Buscar foto
     */
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