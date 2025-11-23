<?php
/**
 * Plugin Name: Galería con WhatsApp
 * Plugin URI: https://tusitio.com
 * Description: Galería simple para subir fotos con ID único y botón de WhatsApp (con carpetas anidadas)
 * Version: 3.2.1
 * Author: Lorenzo Sayes
 * Text Domain: galeria-whatsapp
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('GALERIA_WHATSAPP_VERSION', '3.2.1');
define('GALERIA_WHATSAPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GALERIA_WHATSAPP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GALERIA_WHATSAPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Configuración de WhatsApp
define('GALERIA_WHATSAPP_NUMBER', '5491153461105');
define('GALERIA_WHATSAPP_MESSAGE', 'Hola, me interesa la foto #%s digital');

/**
 * Autoloader simple para cargar clases
 */
spl_autoload_register(function ($class) {
    // Prefijo del namespace del plugin
    $prefix = 'Galeria_WhatsApp_';
    
    // Verificar si la clase usa nuestro prefijo
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    // Obtener el nombre de la clase sin prefijo
    $class_name = str_replace($prefix, '', $class);
    
    // Directorios donde buscar
    $directories = array(
        'includes',
        'admin',
        'public'
    );
    
    // Intentar cargar desde cada directorio
    foreach ($directories as $dir) {
        $file = GALERIA_WHATSAPP_PLUGIN_DIR . $dir . '/class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Clase principal del plugin
 */
class Galeria_WhatsApp {
    
    private static $instance = null;
    private $database;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        // Cargar clases core
        require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'includes/class-database.php';
        require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'includes/class-folder-manager.php';
        require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'includes/class-photo-manager.php';
        
        // Inicializar database
        $this->database = new Galeria_WhatsApp_Database();
        
        // Cargar admin si estamos en admin
        if (is_admin()) {
            require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'admin/class-admin.php';
            require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'admin/class-admin-ajax.php';
            new Galeria_WhatsApp_Admin();
            new Galeria_WhatsApp_Admin_Ajax();
        }
        
        // Cargar public siempre (para AJAX público)
        require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'public/class-public.php';
        require_once GALERIA_WHATSAPP_PLUGIN_DIR . 'public/class-public-ajax.php';
        new Galeria_WhatsApp_Public();
        new Galeria_WhatsApp_Public_Ajax();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this->database, 'activate'));
        add_action('plugins_loaded', array($this, 'check_database'));
    }
    
    /**
     * Verificar estructura de base de datos
     */
    public function check_database() {
        if (is_admin()) {
            $this->database->check_structure();
        }
    }
}

/**
 * Iniciar el plugin
 * Se ejecuta temprano para asegurar que las acciones AJAX se registren correctamente
 */
function galeria_whatsapp_init() {
    return Galeria_WhatsApp::get_instance();
}

// Ejecutar el plugin inmediatamente para asegurar que las acciones AJAX se registren
// Esto es especialmente importante para peticiones AJAX públicas
galeria_whatsapp_init();