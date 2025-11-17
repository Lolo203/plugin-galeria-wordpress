<?php
/**
 * Template del shortcode de galería
 *
 * @package Galeria_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="galeria-whatsapp-frontend">
    <!-- Encabezado compacto con título y buscador -->
    <div class="galeria-header">
        <div class="galeria-header-text">
            <h2>📸 Galería de Fotos</h2>
            <p>Ingresa el número para encontrar una foto específica</p>
        </div>
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