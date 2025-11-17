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
        // Limpiar búsqueda activa al cargar nuevas fotos
        if (isSearchActive) {
            clearSearch();
        }
        
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
        
        // Limpiar búsqueda activa al mostrar nuevas fotos
        if (isSearchActive) {
            clearSearch();
        }
        
        if (!photos || photos.length === 0) {
            grid.html('<p class="no-photos">📷 No hay fotos disponibles.</p>');
            return;
        }
        
        photos.forEach(function(photo) {
            var whatsappUrl = 'https://wa.me/' + galeriaPublic.whatsappNumber + '?text=' + 
                encodeURIComponent(galeriaPublic.whatsappMessage.replace('%s', photo.photo_id));
            var folderLabel = photo.folder_name ? photo.folder_name : 'Sin carpeta';
            var formattedDate = '';
            if (photo.upload_date) {
                var dateObj = new Date(photo.upload_date);
                if (!isNaN(dateObj)) {
                    formattedDate = dateObj.toLocaleDateString(undefined, {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                }
            }
            
            var photoHtml = '<div class="galeria-item" data-photo-id="' + photo.photo_id + '" data-folder="' + (photo.folder_id || 0) + '">' +
                '<div class="galeria-image-wrapper">' +
                '<img src="' + photo.image_url + '" alt="Foto ' + photo.photo_id + '">' +
                '<div class="galeria-overlay">' +
                '<div class="galeria-id-row">' +
                '<span class="galeria-id">' + photo.photo_id + '</span>' +
                '<button class="galeria-copy-btn" data-photo-id="' + photo.photo_id + '">Copiar ID</button>' +
                '</div>' +
                '<a href="' + whatsappUrl + '" target="_blank" class="galeria-whatsapp-btn">' +
                '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">' +
                '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>' +
                '</svg> Consultar</a>' +
                '</div></div>' +
                '<div class="galeria-meta">' +
                '<div class="galeria-meta-left">' +
                '<span class="galeria-folder-tag">📁 ' + folderLabel + '</span>' +
                (formattedDate ? '<span class="galeria-date">' + formattedDate + '</span>' : '') +
                '</div>' +
                '</div>';
            
            grid.append(photoHtml);
        });
        
        // Copiar ID
        $(document).off('click', '.galeria-copy-btn').on('click', '.galeria-copy-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var photoId = btn.data('photo-id');
            
            function showCopied() {
                var originalText = btn.text();
                btn.text('Copiado ✅').addClass('copied');
                setTimeout(function() {
                    btn.text('Copiar ID').removeClass('copied');
                }, 1500);
            }
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(photoId).then(showCopied).catch(function() {
                    fallbackCopy(photoId, btn, showCopied);
                });
            } else {
                fallbackCopy(photoId, btn, showCopied);
            }
        });
    }
    
    function fallbackCopy(text, btn, callback) {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
        if (callback) callback();
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
    var isSearchActive = false;
    var allPhotos = [];
    
    $('#galeria-search-btn').click(searchPhoto);
    $('#galeria-search-input').keypress(function(e) {
        if (e.which === 13) searchPhoto();
    });
    
    // Limpiar búsqueda cuando el input esté vacío
    $('#galeria-search-input').on('input', function() {
        if ($(this).val().trim() === '' && isSearchActive) {
            clearSearch();
        }
    });
    
    function searchPhoto() {
        var searchId = $('#galeria-search-input').val().trim();
        if (!searchId) {
            alert('⚠️ Ingresa un número');
            return;
        }
        
        // Guardar todas las fotos si es la primera búsqueda
        if (!isSearchActive) {
            allPhotos = $('.galeria-item').map(function() {
                return {
                    element: $(this),
                    photoId: $(this).data('photo-id')
                };
            }).get();
        }
        
        isSearchActive = true;
        $('.galeria-item').removeClass('highlight');
        $('.no-results').remove();
        $('.clear-search-btn').remove();
        
        var foundPhotos = [];
        var searchTerm = String(searchId).toLowerCase().trim();
        
        // Primero buscar coincidencias exactas
        allPhotos.forEach(function(photo) {
            var photoId = String(photo.photoId).toLowerCase();
            
            // Coincidencia exacta (case-insensitive)
            if (photoId === searchTerm) {
                foundPhotos.push({
                    element: photo.element,
                    photoId: photo.photoId,
                    matchType: 'exact'
                });
            }
        });
        
        // Si no hay coincidencias exactas, buscar coincidencias parciales
        if (foundPhotos.length === 0) {
            allPhotos.forEach(function(photo) {
                var photoId = String(photo.photoId).toLowerCase();
                
                // Coincidencia parcial (solo si el término de búsqueda tiene al menos 3 caracteres)
                if (searchTerm.length >= 3 && photoId.includes(searchTerm)) {
                    foundPhotos.push({
                        element: photo.element,
                        photoId: photo.photoId,
                        matchType: 'partial'
                    });
                }
            });
        }
        
        // Ocultar todas las fotos primero
        allPhotos.forEach(function(photo) {
            photo.element.hide().removeClass('highlight');
        });
        
        // Mostrar solo las fotos encontradas
        if (foundPhotos.length > 0) {
            foundPhotos.forEach(function(found) {
                found.element.show().addClass('highlight');
            });
            
            // Agregar botón para limpiar búsqueda
            var clearBtn = $('<button class="clear-search-btn" style="display: block; margin: 20px auto; padding: 12px 30px; background: #2271b1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px;">↩️ Mostrar todas las fotos</button>');
            clearBtn.click(function() {
                clearSearch();
            });
            $('#galeria-grid').after(clearBtn);
            
            // Scroll a la primera foto encontrada
            if (foundPhotos[0] && foundPhotos[0].element.length) {
                $('html, body').animate({
                    scrollTop: foundPhotos[0].element.offset().top - 100
                }, 500);
            }
        } else {
            // Mostrar todas las fotos si no se encontró nada
            allPhotos.forEach(function(photo) {
                photo.element.show();
            });
            $('#galeria-grid').before('<p class="no-results">❌ No se encontró #' + searchId + '</p>');
            isSearchActive = false;
        }
    }
    
    function clearSearch() {
        isSearchActive = false;
        $('#galeria-search-input').val('');
        $('.galeria-item').removeClass('highlight').show();
        $('.no-results').remove();
        $('.clear-search-btn').remove();
    }
});