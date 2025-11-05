<?php
/**
 * Vista de la página del admin
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}
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
    
    <div class="photos-header">
        <h2>Fotos</h2>
        <div class="bulk-actions-bar" style="display: none;">
            <label class="select-all-container">
                <input type="checkbox" id="select-all-photos">
                <span>Seleccionar todas</span>
            </label>
            <span class="selected-count">0 fotos seleccionadas</span>
            <button id="delete-selected-btn" class="button button-danger" disabled>
                <span class="dashicons dashicons-trash"></span> Eliminar seleccionadas
            </button>
        </div>
    </div>
    
    <div id="photos-grid" class="photos-grid">
        <p style="padding: 20px; text-align: center; color: #666;">Cargando fotos...</p>
    </div>
    
    <hr>
    
    <div class="shortcode-info">
        <h3>📋 Cómo mostrar la galería en tu sitio</h3>
        <p>Copia y pega este shortcode en cualquier página o entrada:</p>
        <code class="shortcode-box">[galeria_whatsapp]</code>
        
        <div style="margin-top: 20px; padding: 15px; background: white; border-left: 4px solid #00a0d2; border-radius: 4px;">
            <h4 style="margin-top: 0;">ℹ️ Información del Plugin</h4>
            <p style="margin: 5px 0;"><strong>Versión:</strong> <?php echo GALERIA_WHATSAPP_VERSION; ?></p>
            <p style="margin: 5px 0;"><strong>WhatsApp:</strong> <?php echo GALERIA_WHATSAPP_NUMBER; ?></p>
        </div>
    </div>
</div>