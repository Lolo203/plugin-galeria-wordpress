<?php
/**
 * Template del shortcode de galería - Botones fuera del overlay
 *
 * @package Galeria_WhatsApp
 * @version 3.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="galeria-whatsapp-frontend">
    <!-- Encabezado -->
    <div class="galeria-header">
        <div class="galeria-header-text">
            <h2>📸 Galería de Fotos</h2>
            <p>Busca y consulta nuestras fotos disponibles</p>
        </div>
        
        <!-- Buscador -->
        <div class="galeria-search-box">
            <input type="text" id="galeria-search-input" placeholder="Buscar por ID o nombre...">
            <button id="galeria-search-btn">🔍 Buscar</button>
        </div>
    </div>
    
    <!-- Filtro por carpetas -->
    <div class="galeria-folder-filter" id="galeria-folder-filter">
        <p style="text-align: center; color: #666;">Cargando carpetas...</p>
    </div>
    
    <!-- Grid de fotos -->
    <div class="galeria-grid" id="galeria-grid">
        <p style="text-align: center; padding: 40px; color: #666;">⏳ Cargando fotos...</p>
    </div>
</div>