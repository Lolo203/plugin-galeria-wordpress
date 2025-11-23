/**
 * JavaScript del Frontend - Botones siempre visibles
 * @package Galeria_WhatsApp
 * @version 2.5.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var currentFolder = 0;
    var currentSubfolders = [];
    var allFolders = [];
    var isSearchActive = false;
    var allPhotos = [];
    
    var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
    
    console.log('🖼️ Galería WhatsApp Frontend v' + galeriaPublic.version, 'Touch device:', isTouchDevice);
    
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
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log('📂 Carpetas cargadas:', response);
                if (response && response.success && response.data) {
                    allFolders = response.data;
                    displayFolders(response.data);
                } else {
                    $('#galeria-folder-filter').html('');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error cargando carpetas:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    responseText: xhr.responseText.substring(0, 200)
                });
                $('#galeria-folder-filter').html('');
            }
        });
    }
    
    /**
     * Cargar fotos - CORREGIDO: Ahora hace AJAX real
     */
    function loadPhotos(folderId) {
        // Limpiar búsqueda activa al cargar nuevas fotos
        if (isSearchActive) {
            clearSearch();
        }
        
        // Mostrar indicador de carga
        $('#galeria-grid').html('<div class="loading-spinner">⏳ Cargando fotos...</div>');
        
        var requestData = {
            action: 'get_gallery_photos'
        };
        
        // Si hay carpeta específica, incluir subcarpetas
        if (folderId && folderId > 0) {
            console.log('📂 Solicitando fotos de carpeta:', folderId);
            requestData.folder_id = folderId;
            requestData.include_subfolders = true;
        } else {
            console.log('📂 Solicitando todas las fotos');
        }
        
        $.ajax({
            url: galeriaPublic.ajaxUrl,
            type: 'POST',
            data: requestData,
            timeout: 20000,
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log('📷 Fotos recibidas:', response);
                if (response && response.success && response.data) {
                    // Asegurar que data es un array
                    var photosData = Array.isArray(response.data) ? response.data : (response.data.photos || []);
                    console.log('✅ Mostrando', photosData.length, 'fotos');
                    displayPhotos(photosData);
                } else {
                    var errorMsg = response && response.data ? response.data : 'No se recibieron datos válidos';
                    console.error('❌ Error en la respuesta:', errorMsg);
                    $('#galeria-grid').html('<p class="error-msg">❌ Error: ' + errorMsg + '</p>');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error al cargar las fotos. ';
                if (status === 'timeout') {
                    errorMsg += 'La solicitud ha tardado demasiado tiempo.';
                } else if (status === 'error') {
                    errorMsg += 'Error de conexión: ' + error;
                } else if (status === 'parsererror') {
                    errorMsg += 'Error al procesar la respuesta del servidor.';
                }
                
                console.error('❌ Error en la petición AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                $('#galeria-grid').html(
                    '<div class="error-container">' +
                    '   <p class="error-msg">' + errorMsg + '</p>' +
                    '   <button class="retry-btn">🔄 Reintentar</button>' +
                    '</div>'
                );
                
                $('.retry-btn').on('click', function() {
                    loadPhotos(folderId);
                });
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
     * Mostrar fotos en la galería
     */
    function displayPhotos(photos) {
        var grid = $('#galeria-grid');
        grid.empty();
        
        // Limpiar búsqueda activa
        if (isSearchActive) {
            clearSearch();
        }
        
        if (!photos || photos.length === 0) {
            grid.html('<p class="no-photos">📷 No hay fotos disponibles en esta carpeta.</p>');
            return;
        }
        
        console.log('📷 Mostrando', photos.length, 'fotos');
        
        // Guardar todas las fotos para búsqueda
        allPhotos = photos;
        
        photos.forEach(function(photo) {
            var whatsappUrl = 'https://wa.me/' + galeriaPublic.whatsappNumber + '?text=' + 
                encodeURIComponent(galeriaPublic.whatsappMessage.replace('%s', photo.photo_id));
            
            var folderLabel = photo.folder_path || photo.folder_name || 'Sin carpeta';
            var formattedDate = '';
            
            if (photo.upload_date) {
                var dateObj = new Date(photo.upload_date);
                if (!isNaN(dateObj)) {
                    formattedDate = dateObj.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                }
            }
            
            // HTML de la tarjeta - Botones fuera del overlay
            var photoHtml = 
                '<div class="galeria-item" data-photo-id="' + photo.photo_id + '" data-folder="' + (photo.folder_id || 0) + '">' +
                    // Imagen con overlay (solo ID)
                    '<div class="galeria-image-wrapper">' +
                        '<img src="' + photo.image_url + '" alt="Foto ' + photo.photo_id + '" loading="lazy" />' +
                        '<div class="galeria-overlay">' +
                            '<span class="galeria-id">#' + photo.photo_id + '</span>' +
                        '</div>' +
                    '</div>' +
                    // Info de carpeta y fecha
                    '<div class="galeria-meta">' +
                        '<div class="galeria-meta-left">' +
                            '<span class="galeria-folder-tag">📁 ' + folderLabel + '</span>' +
                            (formattedDate ? '<span class="galeria-date">📅 ' + formattedDate + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    // Botones siempre visibles
                    '<div class="galeria-buttons-row">' +
                        '<button class="galeria-copy-btn" data-photo-id="' + photo.photo_id + '" type="button">' +
                            '📋 Copiar ID' +
                        '</button>' +
                        '<a href="' + whatsappUrl + '" target="_blank" rel="noopener noreferrer" class="galeria-whatsapp-btn">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">' +
                                '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>' +
                            '</svg>' +
                            ' Consultar' +
                        '</a>' +
                    '</div>' +
                '</div>';
            
            grid.append(photoHtml);
        });
        
        // Inicializar botones de copiar
        initCopyButtons();
        
        // Inicializar manejo de toques en móviles para mostrar/ocultar ID
        initMobileTouchHandlers();
    }
    
    /**
     * Inicializar botones de copiar ID
     */
    function initCopyButtons() {
        $(document).off('click', '.galeria-copy-btn').on('click', '.galeria-copy-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var photoId = $(this).data('photo-id');
            var button = $(this);
            var originalText = button.html();
            
            console.log('📋 Copiando ID:', photoId);
            
            // Copiar al portapapeles
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(photoId).then(function() {
                    button.html('✓ Copiado').addClass('copied');
                    setTimeout(function() {
                        button.html(originalText).removeClass('copied');
                    }, 2000);
                }).catch(function(err) {
                    console.error('Error al copiar:', err);
                    fallbackCopy(photoId, button, originalText);
                });
            } else {
                fallbackCopy(photoId, button, originalText);
            }
        });
    }
    
    /**
     * Fallback para copiar al portapapeles (navegadores antiguos)
     */
    function fallbackCopy(text, button, originalText) {
        var tempInput = $('<input>').val(text).css({
            position: 'absolute',
            left: '-9999px'
        });
        $('body').append(tempInput);
        tempInput.select();
        
        try {
            document.execCommand('copy');
            button.html('✓ Copiado').addClass('copied');
            setTimeout(function() {
                button.html(originalText).removeClass('copied');
            }, 2000);
        } catch (err) {
            console.error('Error al copiar:', err);
            alert('No se pudo copiar. ID: ' + text);
        }
        
        tempInput.remove();
    }
    
    /**
     * Inicializar manejo de toques en móviles para mostrar/ocultar ID
     */
    function initMobileTouchHandlers() {
        // Solo aplicar en dispositivos táctiles
        if (!isTouchDevice) {
            return;
        }
        
        // Función para toggle el overlay
        function toggleOverlay($item) {
            if ($item.hasClass('touched')) {
                $item.removeClass('touched');
            } else {
                // Ocultar otros items que estén tocados
                $('.galeria-item').removeClass('touched');
                // Mostrar este item
                $item.addClass('touched');
            }
        }
        
        // Usar solo 'click' que funciona bien en móviles modernos y no necesita preventDefault
        // El evento 'click' se dispara después de touchstart+touchend en móviles
        $(document).off('click', '.galeria-image-wrapper').on('click', '.galeria-image-wrapper', function(e) {
            e.stopPropagation();
            
            var $item = $(this).closest('.galeria-item');
            toggleOverlay($item);
        });
        
        // Para touchstart, usar addEventListener nativo con { passive: false } solo si necesitamos prevenir scroll
        // Pero en este caso, no necesitamos prevenir nada, solo queremos detectar el toque
        // Así que usamos una solución más simple: solo usar click que ya funciona bien
        
        // Ocultar overlay al tocar fuera de la imagen
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.galeria-image-wrapper').length && 
                !$(e.target).closest('.galeria-overlay').length) {
                $('.galeria-item').removeClass('touched');
            }
        });
    }
    
    /**
     * Click en filtro de carpeta - CORREGIDO: Ahora hace loadPhotos real
     */
    $(document).on('click', '.galeria-folder-btn', function() {
        $('.galeria-folder-btn').removeClass('active');
        $(this).addClass('active');
        
        currentFolder = parseInt($(this).data('folder'));
        var subfoldersData = $(this).data('subfolders');
        currentSubfolders = Array.isArray(subfoldersData) ? subfoldersData : [currentFolder];
        
        console.log('📂 Filtrar carpeta:', currentFolder, 'Subcarpetas:', currentSubfolders);
        
        // CORREGIDO: Hacer AJAX real en lugar de filtrar local
        loadPhotos(currentFolder);
    });
    
    /**
     * Búsqueda de fotos
     */
    $('#galeria-search-btn').on('click', searchPhoto);
    
    $('#galeria-search-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            searchPhoto();
        }
    });
    
    $('#galeria-search-input').on('input', function() {
        if ($(this).val().trim() === '' && isSearchActive) {
            clearSearch();
        }
    });
    
    /**
     * Buscar foto por ID
     */
    function searchPhoto() {
        var searchId = $('#galeria-search-input').val().trim();
        
        if (!searchId) {
            alert('⚠️ Por favor ingresa un número de foto');
            return;
        }
        
        console.log('🔍 Buscando:', searchId);
        
        isSearchActive = true;
        $('.galeria-item').removeClass('highlight');
        $('.no-results').remove();
        $('.clear-search-btn').remove();
        
        var foundPhotos = [];
        var searchTerm = String(searchId).toLowerCase().trim();
        
        // Buscar coincidencias exactas primero
        $('.galeria-item').each(function() {
            var photoId = String($(this).data('photo-id')).toLowerCase();
            if (photoId === searchTerm) {
                foundPhotos.push($(this));
            }
        });
        
        // Si no hay exactas, buscar parciales (mínimo 3 caracteres)
        if (foundPhotos.length === 0 && searchTerm.length >= 3) {
            $('.galeria-item').each(function() {
                var photoId = String($(this).data('photo-id')).toLowerCase();
                if (photoId.includes(searchTerm)) {
                    foundPhotos.push($(this));
                }
            });
        }
        
        // Ocultar todas las fotos
        $('.galeria-item').hide();
        
        // Mostrar solo las encontradas
        if (foundPhotos.length > 0) {
            console.log('✅ Encontradas', foundPhotos.length, 'fotos');
            
            foundPhotos.forEach(function(item) {
                item.show().addClass('highlight');
            });
            
            // Botón para limpiar búsqueda
            var clearBtn = $('<button class="clear-search-btn">↩️ Mostrar todas las fotos</button>');
            clearBtn.on('click', clearSearch);
            $('#galeria-grid').after(clearBtn);
            
            // Scroll a la primera foto encontrada
            if (foundPhotos[0]) {
                $('html, body').animate({
                    scrollTop: foundPhotos[0].offset().top - 100
                }, 500);
            }
        } else {
            console.log('❌ No se encontraron fotos');
            $('.galeria-item').show();
            $('#galeria-grid').before('<p class="no-results">❌ No se encontró la foto con ID: <strong>' + searchId + '</strong></p>');
            isSearchActive = false;
        }
    }
    
    /**
     * Limpiar búsqueda
     */
    function clearSearch() {
        console.log('🧹 Limpiando búsqueda');
        isSearchActive = false;
        $('#galeria-search-input').val('');
        $('.galeria-item').removeClass('highlight').show();
        $('.no-results').remove();
        $('.clear-search-btn').remove();
    }
});