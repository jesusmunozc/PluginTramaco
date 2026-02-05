<?php
/**
 * Plugin Name: Tramaco API Integration
 * Plugin URI: https://starbrand.com
 * Description: Integración con la API de TRAMACOEXPRESS para generación de guías, tracking y consultas
 * Version: 1.0.0
 * Author: Star Brand
 * Author URI: https://starbrand.com
 * License: GPL v2 or later
 * Text Domain: tramaco-api
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('TRAMACO_API_VERSION', '1.1.0');
define('TRAMACO_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRAMACO_API_PLUGIN_URL', plugin_dir_url(__FILE__));

// Cargar autoloader de Composer
require_once TRAMACO_API_PLUGIN_DIR . 'vendor/autoload.php';

// Cargar módulos adicionales
require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-woocommerce.php';
require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-sharepoint.php';
require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-sharepoint-helper.php';
require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-wc-settings.php';
require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-wc-emails.php';

// URLs de la API (Ambiente QA)
define('TRAMACO_API_BASE_URL', 'https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources');
define('TRAMACO_API_AUTH_URL', TRAMACO_API_BASE_URL . '/usuario/autenticar');
define('TRAMACO_API_GENERAR_GUIA_URL', TRAMACO_API_BASE_URL . '/guiaTk/generarGuia');
define('TRAMACO_API_GENERAR_PDF_URL', TRAMACO_API_BASE_URL . '/guiaTk/generarPdf');
define('TRAMACO_API_GENERAR_ETIQUETA_URL', TRAMACO_API_BASE_URL . '/guiaTk/generarEtiquetaGuia10x10Pdf');
define('TRAMACO_API_TRACKING_URL', TRAMACO_API_BASE_URL . '/guiaTk/consultarTracking');
define('TRAMACO_API_CALCULAR_PRECIO_URL', TRAMACO_API_BASE_URL . '/guiaTk/calcularPrecio');
define('TRAMACO_API_LOCALIDAD_CONTRATO_URL', TRAMACO_API_BASE_URL . '/consultaTk/consultarLocalidadContrato');
define('TRAMACO_API_UBICACION_URL', TRAMACO_API_BASE_URL . '/ubicacionGeografica/consultar');

/**
 * Clase principal del plugin
 */
class Tramaco_API_Integration {
    
    private static $instance = null;
    private $token = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Inicializar el plugin
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Registrar shortcodes
        add_shortcode('tramaco_tracking', array($this, 'tracking_shortcode'));
        add_shortcode('tramaco_cotizacion', array($this, 'cotizacion_shortcode'));
        add_shortcode('tramaco_generar_guia', array($this, 'generar_guia_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_tramaco_auth', array($this, 'ajax_authenticate'));
        add_action('wp_ajax_nopriv_tramaco_auth', array($this, 'ajax_authenticate'));
        add_action('wp_ajax_tramaco_tracking', array($this, 'ajax_tracking'));
        add_action('wp_ajax_nopriv_tramaco_tracking', array($this, 'ajax_tracking'));
        add_action('wp_ajax_tramaco_cotizacion', array($this, 'ajax_cotizacion'));
        add_action('wp_ajax_nopriv_tramaco_cotizacion', array($this, 'ajax_cotizacion'));
        add_action('wp_ajax_tramaco_generar_guia', array($this, 'ajax_generar_guia'));
        add_action('wp_ajax_tramaco_generar_pdf', array($this, 'ajax_generar_pdf'));
        add_action('wp_ajax_tramaco_generar_etiqueta', array($this, 'ajax_generar_etiqueta'));
        add_action('wp_ajax_tramaco_ubicaciones', array($this, 'ajax_get_ubicaciones'));
        add_action('wp_ajax_nopriv_tramaco_ubicaciones', array($this, 'ajax_get_ubicaciones'));
        add_action('wp_ajax_tramaco_localidades', array($this, 'ajax_get_localidades'));
        add_action('wp_ajax_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        add_action('wp_ajax_nopriv_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        add_action('wp_ajax_tramaco_clear_cache', array($this, 'ajax_clear_cache'));
        
        // AJAX handlers para SharePoint
        add_action('wp_ajax_tramaco_sharepoint_test', array($this, 'ajax_sharepoint_test'));
        add_action('wp_ajax_tramaco_sharepoint_sync', array($this, 'ajax_sharepoint_sync'));
    }
    
    public function init() {
        // Cargar traducciones
        load_plugin_textdomain('tramaco-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Tramaco API', 'tramaco-api'),
            __('Tramaco API', 'tramaco-api'),
            'manage_options',
            'tramaco-api',
            array($this, 'admin_page'),
            'dashicons-airplane',
            30
        );
        
        add_submenu_page(
            'tramaco-api',
            __('Configuración', 'tramaco-api'),
            __('Configuración', 'tramaco-api'),
            'manage_options',
            'tramaco-api',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'tramaco-api',
            __('Generar Guía', 'tramaco-api'),
            __('Generar Guía', 'tramaco-api'),
            'manage_options',
            'tramaco-api-generar-guia',
            array($this, 'admin_generar_guia_page')
        );
        
        add_submenu_page(
            'tramaco-api',
            __('Tracking', 'tramaco-api'),
            __('Tracking', 'tramaco-api'),
            'manage_options',
            'tramaco-api-tracking',
            array($this, 'admin_tracking_page')
        );
        
        add_submenu_page(
            'tramaco-api',
            __('Configuración SharePoint', 'tramaco-api'),
            __('SharePoint', 'tramaco-api'),
            'manage_options',
            'tramaco-api-sharepoint',
            array($this, 'admin_sharepoint_page')
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        // Configuración de API Tramaco
        register_setting('tramaco_api_settings', 'tramaco_api_login');
        register_setting('tramaco_api_settings', 'tramaco_api_password');
        register_setting('tramaco_api_settings', 'tramaco_api_contrato');
        register_setting('tramaco_api_settings', 'tramaco_api_localidad');
        register_setting('tramaco_api_settings', 'tramaco_api_producto');
        register_setting('tramaco_api_settings', 'tramaco_api_environment');
        
        // Configuración de SharePoint
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_client_id');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_client_secret');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_tenant_id');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_site_id');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_drive_id');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_file_path');
        register_setting('tramaco_sharepoint_settings', 'tramaco_sharepoint_enabled');
    }
    
    /**
     * Cargar scripts y estilos del frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'tramaco-api-styles',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-styles.css',
            array(),
            TRAMACO_API_VERSION
        );
        
        wp_enqueue_style(
            'tramaco-api-tracking-pro',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-tracking-pro.css',
            array('tramaco-api-styles'),
            TRAMACO_API_VERSION
        );
        
        wp_enqueue_style(
            'tramaco-api-theme-professional',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-theme-professional.css',
            array('tramaco-api-tracking-pro'),
            TRAMACO_API_VERSION
        );
        
        // CSS para select personalizado
        wp_enqueue_style(
            'tramaco-api-select-custom',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-select-custom.css',
            array('tramaco-api-theme-professional'),
            TRAMACO_API_VERSION
        );
        
        // JS para select personalizado (cargar primero)
        wp_enqueue_script(
            'tramaco-api-select-custom',
            TRAMACO_API_PLUGIN_URL . 'assets/js/tramaco-select-custom.js',
            array('jquery'),
            TRAMACO_API_VERSION,
            true
        );
        
        wp_enqueue_script(
            'tramaco-api-scripts',
            TRAMACO_API_PLUGIN_URL . 'assets/js/tramaco-scripts.js',
            array('jquery', 'tramaco-api-select-custom'),
            TRAMACO_API_VERSION,
            true
        );
        
        wp_localize_script('tramaco-api-scripts', 'tramacoApi', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tramaco_api_nonce')
        ));
    }
    
    /**
     * Cargar scripts y estilos del admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'tramaco-api') === false) {
            return;
        }
        
        wp_enqueue_style(
            'tramaco-api-admin-styles',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-admin.css',
            array(),
            TRAMACO_API_VERSION
        );
        
        wp_enqueue_script(
            'tramaco-api-admin-scripts',
            TRAMACO_API_PLUGIN_URL . 'assets/js/tramaco-admin.js',
            array('jquery'),
            TRAMACO_API_VERSION,
            true
        );
        
        wp_localize_script('tramaco-api-admin-scripts', 'tramacoAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tramaco_api_nonce')
        ));
    }
    
    /**
     * Página principal de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración de Tramaco API', 'tramaco-api'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('tramaco_api_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_environment"><?php _e('Ambiente', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <select name="tramaco_api_environment" id="tramaco_api_environment">
                                <option value="qa" <?php selected(get_option('tramaco_api_environment'), 'qa'); ?>>QA (Pruebas)</option>
                                <option value="production" <?php selected(get_option('tramaco_api_environment'), 'production'); ?>>Producción</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_login"><?php _e('Login (RUC)', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="tramaco_api_login" id="tramaco_api_login" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_login', '1793191845001')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_password"><?php _e('Password', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="tramaco_api_password" id="tramaco_api_password" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_password')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_contrato"><?php _e('ID Contrato', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="tramaco_api_contrato" id="tramaco_api_contrato" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_contrato', '6394')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_localidad"><?php _e('ID Localidad', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="tramaco_api_localidad" id="tramaco_api_localidad" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_localidad', '21580')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tramaco_api_producto"><?php _e('ID Producto', 'tramaco-api'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="tramaco_api_producto" id="tramaco_api_producto" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_producto', '36')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Guardar Configuración', 'tramaco-api')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Probar Conexión', 'tramaco-api'); ?></h2>
            <button type="button" id="test-connection" class="button button-secondary">
                <?php _e('Probar Autenticación', 'tramaco-api'); ?>
            </button>
            <div id="connection-result" style="margin-top: 10px;"></div>
            
            <hr>
            
            <h2><?php _e('Mantenimiento', 'tramaco-api'); ?></h2>
            <p><?php _e('Si tienes problemas con los selectores de ubicación o la cotización, puedes limpiar el caché:', 'tramaco-api'); ?></p>
            <button type="button" id="clear-cache" class="button button-secondary">
                🗑️ <?php _e('Limpiar Caché de Ubicaciones', 'tramaco-api'); ?>
            </button>
            <div id="cache-result" style="margin-top: 10px;"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#clear-cache').on('click', function() {
                    var $btn = $(this);
                    var $result = $('#cache-result');
                    
                    $btn.prop('disabled', true).text('Limpiando...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'tramaco_clear_cache' },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                            } else {
                                $result.html('<div class="notice notice-error"><p>❌ Error al limpiar caché</p></div>');
                            }
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error"><p>❌ Error de conexión</p></div>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('🗑️ <?php _e('Limpiar Caché de Ubicaciones', 'tramaco-api'); ?>');
                        }
                    });
                });
            });
            </script>
            
            <hr>
            
            <h2><?php _e('Shortcodes Disponibles', 'tramaco-api'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[tramaco_tracking]</code></td>
                        <td>Formulario de tracking de guías</td>
                    </tr>
                    <tr>
                        <td><code>[tramaco_cotizacion]</code></td>
                        <td>Formulario de cotización de envíos</td>
                    </tr>
                    <tr>
                        <td><code>[tramaco_generar_guia]</code></td>
                        <td>Formulario para generar guías (requiere login)</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Página de generación de guías en admin
     */
    public function admin_generar_guia_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Generar Nueva Guía', 'tramaco-api'); ?></h1>
            
            <form id="form-generar-guia" class="tramaco-form">
                <h3><?php _e('Datos del Destinatario', 'tramaco-api'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="dest_nombres"><?php _e('Nombres', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_nombres" name="dest_nombres" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_apellidos"><?php _e('Apellidos', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_apellidos" name="dest_apellidos" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_ci_ruc"><?php _e('Cédula/RUC', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_ci_ruc" name="dest_ci_ruc" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_tipo_iden"><?php _e('Tipo Identificación', 'tramaco-api'); ?></label></th>
                        <td>
                            <select id="dest_tipo_iden" name="dest_tipo_iden">
                                <option value="05">Cédula</option>
                                <option value="04">RUC</option>
                                <option value="06">Pasaporte</option>
                                <option value="07">Consumidor Final</option>
                                <option value="08">Identificación Exterior</option>
                                <option value="09">Placa</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dest_telefono"><?php _e('Teléfono', 'tramaco-api'); ?></label></th>
                        <td><input type="tel" id="dest_telefono" name="dest_telefono" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_email"><?php _e('Email', 'tramaco-api'); ?></label></th>
                        <td><input type="email" id="dest_email" name="dest_email" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_provincia"><?php _e('Provincia', 'tramaco-api'); ?></label></th>
                        <td><select id="dest_provincia" name="dest_provincia" class="regular-text"></select></td>
                    </tr>
                    <tr>
                        <th><label for="dest_canton"><?php _e('Cantón', 'tramaco-api'); ?></label></th>
                        <td><select id="dest_canton" name="dest_canton" class="regular-text"></select></td>
                    </tr>
                    <tr>
                        <th><label for="dest_parroquia"><?php _e('Parroquia', 'tramaco-api'); ?></label></th>
                        <td><select id="dest_parroquia" name="dest_parroquia" class="regular-text" required></select></td>
                    </tr>
                    <tr>
                        <th><label for="dest_calle_primaria"><?php _e('Calle Principal', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_calle_primaria" name="dest_calle_primaria" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_calle_secundaria"><?php _e('Calle Secundaria', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_calle_secundaria" name="dest_calle_secundaria" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_numero"><?php _e('Número', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_numero" name="dest_numero" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_referencia"><?php _e('Referencia', 'tramaco-api'); ?></label></th>
                        <td><textarea id="dest_referencia" name="dest_referencia" class="regular-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="dest_codigo_postal"><?php _e('Código Postal', 'tramaco-api'); ?></label></th>
                        <td><input type="text" id="dest_codigo_postal" name="dest_codigo_postal" class="regular-text" /></td>
                    </tr>
                </table>
                
                <h3><?php _e('Datos de la Carga', 'tramaco-api'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="carga_descripcion"><?php _e('Descripción', 'tramaco-api'); ?></label></th>
                        <td><textarea id="carga_descripcion" name="carga_descripcion" class="regular-text" required></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="carga_peso"><?php _e('Peso (kg)', 'tramaco-api'); ?></label></th>
                        <td><input type="number" step="0.01" id="carga_peso" name="carga_peso" class="small-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="carga_cajas"><?php _e('Cantidad de Cajas', 'tramaco-api'); ?></label></th>
                        <td><input type="number" id="carga_cajas" name="carga_cajas" class="small-text" value="1" /></td>
                    </tr>
                    <tr>
                        <th><label for="carga_bultos"><?php _e('Cantidad de Bultos', 'tramaco-api'); ?></label></th>
                        <td><input type="number" id="carga_bultos" name="carga_bultos" class="small-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="carga_valor_cobro"><?php _e('Valor a Cobrar', 'tramaco-api'); ?></label></th>
                        <td><input type="number" step="0.01" id="carga_valor_cobro" name="carga_valor_cobro" class="small-text" value="0" /></td>
                    </tr>
                    <tr>
                        <th><label for="carga_valor_asegurado"><?php _e('Valor Asegurado', 'tramaco-api'); ?></label></th>
                        <td><input type="number" step="0.01" id="carga_valor_asegurado" name="carga_valor_asegurado" class="small-text" value="0" /></td>
                    </tr>
                    <tr>
                        <th><label for="carga_observacion"><?php _e('Observación', 'tramaco-api'); ?></label></th>
                        <td><textarea id="carga_observacion" name="carga_observacion" class="regular-text"></textarea></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Generar Guía', 'tramaco-api'); ?></button>
                </p>
            </form>
            
            <div id="guia-result" style="margin-top: 20px;"></div>
        </div>
        <?php
    }
    
    /**
     * Página de tracking en admin
     */
    public function admin_tracking_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Consultar Tracking', 'tramaco-api'); ?></h1>
            
            <form id="form-tracking-admin" class="tramaco-form">
                <table class="form-table">
                    <tr>
                        <th><label for="numero_guia"><?php _e('Número de Guía', 'tramaco-api'); ?></label></th>
                        <td>
                            <input type="text" id="numero_guia" name="numero_guia" class="regular-text" 
                                   placeholder="Ej: 031002005633799" required />
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Consultar', 'tramaco-api'); ?></button>
                </p>
            </form>
            
            <div id="tracking-result" style="margin-top: 20px;"></div>
        </div>
        <?php
    }
    
    /**
     * Autenticación con la API
     */
    public function authenticate() {
        $login = get_option('tramaco_api_login', '1793191845001');
        $password = get_option('tramaco_api_password', 'MAS.39inter.PIN');
        
        $response = wp_remote_post(TRAMACO_API_AUTH_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'login' => $login,
                'password' => $password
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Manejar diferentes estructuras de respuesta
        $codigo = null;
        $mensaje = '';
        $token = null;
        
        // Estructura nueva: cuerpoRespuesta.codigo
        if (isset($body['cuerpoRespuesta']['codigo'])) {
            $codigo = $body['cuerpoRespuesta']['codigo'];
            $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : '';
            if (isset($body['salidaAutenticarUsuarioJWTWs']['token'])) {
                $token = $body['salidaAutenticarUsuarioJWTWs']['token'];
            }
        }
        // Estructura antigua: codigo directo
        elseif (isset($body['codigo'])) {
            $codigo = $body['codigo'];
            $mensaje = isset($body['mensaje']) ? $body['mensaje'] : '';
            if (isset($body['salidaAutenticarWs']['token'])) {
                $token = $body['salidaAutenticarWs']['token'];
            }
        }
        
        if (($codigo == '1' || $codigo == 1) && $token) {
            $this->token = $token;
            
            // Guardar token en transient (120 minutos para JWT)
            set_transient('tramaco_api_token', $this->token, 120 * MINUTE_IN_SECONDS);
            
            return array(
                'success' => true,
                'token' => $this->token,
                'message' => $mensaje
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($body['excepcion']) ? $body['excepcion'] : 'Error de autenticación: ' . $mensaje
        );
    }
    
    /**
     * Obtener token válido
     */
    public function get_token() {
        $token = get_transient('tramaco_api_token');
        
        if (!$token) {
            $auth = $this->authenticate();
            if ($auth['success']) {
                $token = $auth['token'];
            }
        }
        
        return $token;
    }
    
    /**
     * AJAX: Autenticar
     */
    public function ajax_authenticate() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $result = $this->authenticate();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Consultar tracking
     */
    public function ajax_tracking() {
        $guia = sanitize_text_field($_POST['guia']);
        $verificacion_tipo = sanitize_text_field($_POST['verificacion_tipo'] ?? '');
        $verificacion_valor = sanitize_text_field($_POST['verificacion_valor'] ?? '');
        
        if (empty($guia)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Número de guía requerido'
            ));
        }
        
        if (empty($verificacion_valor)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Dato de verificación requerido'
            ));
        }
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación con el servicio'
            ));
        }
        
        $response = wp_remote_post(TRAMACO_API_TRACKING_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode(array(
                'guia' => $guia
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Manejar diferentes estructuras de respuesta
        $codigo = null;
        if (isset($body['cuerpoRespuesta']['codigo'])) {
            $codigo = $body['cuerpoRespuesta']['codigo'];
        } elseif (isset($body['codigo'])) {
            $codigo = $body['codigo'];
        }
        
        $success = ($codigo == '1' || $codigo == 1);
        
        if (!$success) {
            wp_send_json(array(
                'success' => false,
                'message' => 'No se encontró información para esta guía'
            ));
        }
        
        // Verificar datos del destinatario o remitente para seguridad
        $verificacion_exitosa = false;
        $persona_verificar = null;
        $tipo_persona = '';
        
        // Determinar si verificamos destinatario o remitente
        if (strpos($verificacion_tipo, 'destinatario') !== false && isset($body['destinatario'])) {
            $persona_verificar = $body['destinatario'];
            $tipo_persona = 'destinatario';
        } elseif (strpos($verificacion_tipo, 'remitente') !== false && isset($body['remitente'])) {
            $persona_verificar = $body['remitente'];
            $tipo_persona = 'remitente';
        }
        
        if ($persona_verificar) {
            // Verificar por teléfono
            if (strpos($verificacion_tipo, 'telefono') !== false) {
                $telefono_registrado = preg_replace('/[^0-9]/', '', $persona_verificar['telefono'] ?? '');
                $telefono_ingresado = preg_replace('/[^0-9]/', '', $verificacion_valor);
                
                // Permitir comparación con últimos 10 dígitos o completo
                if (strlen($telefono_registrado) >= 10 && strlen($telefono_ingresado) >= 10) {
                    $verificacion_exitosa = (substr($telefono_registrado, -10) === substr($telefono_ingresado, -10));
                } elseif ($telefono_registrado === $telefono_ingresado) {
                    $verificacion_exitosa = true;
                }
            }
            // Verificar por documento
            elseif (strpos($verificacion_tipo, 'documento') !== false) {
                $documento_registrado = preg_replace('/[^0-9]/', '', $persona_verificar['ciRuc'] ?? '');
                $documento_ingresado = preg_replace('/[^0-9]/', '', $verificacion_valor);
                
                $verificacion_exitosa = ($documento_registrado === $documento_ingresado);
            }
        }
        
        if (!$verificacion_exitosa) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Los datos de verificación no coinciden con la información registrada del ' . $tipo_persona . '. Por favor verifica que hayas ingresado correctamente el ' . 
                            (strpos($verificacion_tipo, 'telefono') !== false ? 'teléfono' : 'documento') . '.',
                'error_type' => 'verification_failed'
            ));
        }
        
        wp_send_json(array(
            'success' => true,
            'data' => $body,
            'message' => 'Guía encontrada y verificada correctamente'
        ));
    }
    
    /**
     * AJAX: Calcular cotización
     */
    public function ajax_cotizacion() {
        $peso = floatval($_POST['peso']);
        $bultos = intval($_POST['bultos'] ?? 1);
        $parroquia_destino = intval($_POST['parroquia_destino']);
        
        if (!$peso || !$parroquia_destino) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Datos incompletos. Por favor ingrese el peso y la parroquia de destino.'
            ));
        }
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación. Por favor intente nuevamente.'
            ));
        }
        
        $contrato = get_option('tramaco_api_contrato', '6394');
        $localidad = get_option('tramaco_api_localidad', '21580');
        $producto = get_option('tramaco_api_producto', '36');
        
        // Estructura según documentación: EntradaCalcularPrecioGuiaWs
        $data = array(
            'codParroquiaRemit' => '316',  // Parroquia origen (Quito por defecto)
            'lstCargaDestino' => array(
                array(
                    'carga' => array(
                        'alto' => '',
                        'ancho' => '',
                        'bultos' => strval($bultos),
                        'cajas' => '',
                        'cantidadDoc' => '0',
                        'largo' => '',
                        'peso' => strval($peso),
                        'producto' => $producto,
                        'adjuntos' => 'false',
                        'contrato' => $contrato,
                        'valorAsegurado' => '',
                        'localidad' => $localidad,
                        'localidadDestino' => ''
                    ),
                    'codParroquiaDest' => strval($parroquia_destino),
                    'id' => '1'
                )
            ),
            'lstServicio' => array()
        );
        
        $response = wp_remote_post(TRAMACO_API_CALCULAR_PRECIO_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de conexión: ' . $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Manejar ambas estructuras de respuesta
        $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : (isset($body['codigo']) ? $body['codigo'] : null);
        
        if ($codigo == 1) {
            // Obtener lstGuias de la estructura correcta
            $lstGuias = null;
            if (isset($body['salidaCalcularPrecioGuiaWs']['lstGuias'])) {
                $lstGuias = $body['salidaCalcularPrecioGuiaWs']['lstGuias'];
            } elseif (isset($body['cuerpoRespuesta']['lstGuias'])) {
                $lstGuias = $body['cuerpoRespuesta']['lstGuias'];
            } elseif (isset($body['lstGuias'])) {
                $lstGuias = $body['lstGuias'];
            }
            
            wp_send_json(array(
                'success' => true,
                'data' => array(
                    'lstGuias' => $lstGuias,
                    'codigo' => $codigo
                )
            ));
        } else {
            $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : (isset($body['mensaje']) ? $body['mensaje'] : 'No se pudo calcular la cotización');
            wp_send_json(array(
                'success' => false,
                'message' => $mensaje,
                'data' => $body
            ));
        }
    }
    
    /**
     * AJAX: Generar guía
     */
    public function ajax_generar_guia() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Sin permisos'
            ));
        }
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación'
            ));
        }
        
        $contrato = get_option('tramaco_api_contrato', '6394');
        $localidad = get_option('tramaco_api_localidad', '21580');
        $producto = get_option('tramaco_api_producto', '36');
        
        // Construir el JSON de la guía
        $data = array(
            'lstCargaDestino' => array(
                array(
                    'id' => 1,
                    'datoAdicional' => array(
                        'motivo' => '',
                        'citacion' => '',
                        'boleta' => ''
                    ),
                    'destinatario' => array(
                        'codigoPostal' => sanitize_text_field($_POST['dest_codigo_postal'] ?? ''),
                        'nombres' => sanitize_text_field($_POST['dest_nombres']),
                        'codigoParroquia' => intval($_POST['dest_parroquia']),
                        'email' => sanitize_email($_POST['dest_email'] ?? ' '),
                        'apellidos' => sanitize_text_field($_POST['dest_apellidos']),
                        'callePrimaria' => sanitize_text_field($_POST['dest_calle_primaria']),
                        'telefono' => sanitize_text_field($_POST['dest_telefono']),
                        'calleSecundaria' => sanitize_text_field($_POST['dest_calle_secundaria'] ?? ''),
                        'tipoIden' => sanitize_text_field($_POST['dest_tipo_iden']),
                        'referencia' => sanitize_text_field($_POST['dest_referencia'] ?? ''),
                        'ciRuc' => sanitize_text_field($_POST['dest_ci_ruc']),
                        'numero' => sanitize_text_field($_POST['dest_numero'] ?? ' ')
                    ),
                    'carga' => array(
                        'localidad' => intval($localidad),
                        'adjuntos' => '',
                        'referenciaTercero' => '',
                        'largo' => 0,
                        'descripcion' => sanitize_textarea_field($_POST['carga_descripcion']),
                        'valorCobro' => floatval($_POST['carga_valor_cobro'] ?? 0),
                        'valorAsegurado' => floatval($_POST['carga_valor_asegurado'] ?? 0),
                        'contrato' => intval($contrato),
                        'peso' => floatval($_POST['carga_peso']),
                        'observacion' => sanitize_textarea_field($_POST['carga_observacion'] ?? ''),
                        'producto' => $producto,
                        'ancho' => '',
                        'bultos' => sanitize_text_field($_POST['carga_bultos'] ?? ''),
                        'cajas' => sanitize_text_field($_POST['carga_cajas'] ?? '1'),
                        'cantidadDoc' => '',
                        'alto' => ''
                    )
                )
            ),
            'remitente' => array(
                'codigoPostal' => '',
                'nombres' => 'GUIA PRUEBA',
                'codigoParroquia' => 316,
                'email' => ' ',
                'apellidos' => 'STAR BRAND',
                'callePrimaria' => 'GUIA PRUEBA',
                'telefono' => '09812345677',
                'calleSecundaria' => '',
                'tipoIden' => '06',
                'referencia' => 'REFERENCIA REMITENTE',
                'ciRuc' => '1793191845001',
                'numero' => '000000047'
            )
        );
        
        $response = wp_remote_post(TRAMACO_API_GENERAR_GUIA_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        wp_send_json(array(
            'success' => isset($body['codigo']) && $body['codigo'] == 1,
            'data' => $body
        ));
    }
    
    /**
     * AJAX: Generar PDF de guía
     */
    public function ajax_generar_pdf() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $guias = isset($_POST['guias']) ? $_POST['guias'] : array();
        
        if (empty($guias)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Número de guía requerido'
            ));
        }
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación'
            ));
        }
        
        $response = wp_remote_post(TRAMACO_API_GENERAR_PDF_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode(array(
                'guias' => (array) $guias
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        wp_send_json(array(
            'success' => isset($body['codigo']) && $body['codigo'] == 1,
            'data' => $body
        ));
    }
    
    /**
     * AJAX: Generar Etiqueta 10x10 PDF de guías
     */
    public function ajax_generar_etiqueta() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $guias = isset($_POST['guias']) ? $_POST['guias'] : array();
        
        if (empty($guias)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Número de guía requerido'
            ));
        }
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación'
            ));
        }
        
        $usuario = get_option('tramaco_api_usuario', '8651');
        
        // Formatear guías para el endpoint de etiquetas
        $guias_formateadas = array();
        foreach ((array) $guias as $guia) {
            $guias_formateadas[] = array('numeroGuia' => $guia);
        }
        
        $response = wp_remote_post(TRAMACO_API_GENERAR_ETIQUETA_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode(array(
                'usuario' => intval($usuario),
                'guias' => $guias_formateadas,
                'generaEtiqueta' => true,
                'generaGuia' => false
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        wp_send_json(array(
            'success' => isset($body['codigo']) && $body['codigo'] == 1,
            'data' => $body
        ));
    }
    
    /**
     * Generar etiqueta 10x10 de forma pública (para WooCommerce)
     */
    public function generar_etiqueta_publica($guias, $usuario = null) {
        $token = $this->get_token();
        
        if (!$token) {
            return array('success' => false, 'message' => 'Error de autenticación');
        }
        
        $usuario = $usuario ?? get_option('tramaco_api_usuario', '8651');
        
        // Formatear guías para el endpoint de etiquetas
        $guias_formateadas = array();
        foreach ((array) $guias as $guia) {
            $guias_formateadas[] = array('numeroGuia' => $guia);
        }
        
        $response = wp_remote_post(TRAMACO_API_GENERAR_ETIQUETA_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode(array(
                'usuario' => intval($usuario),
                'guias' => $guias_formateadas,
                'generaEtiqueta' => true,
                'generaGuia' => false
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'success' => isset($body['codigo']) && $body['codigo'] == 1,
            'data' => $body
        );
    }
    
    /**
     * AJAX: Limpiar cache de ubicaciones y token
     */
    public function ajax_clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
            return;
        }
        
        delete_transient('tramaco_api_token');
        delete_transient('tramaco_ubicaciones');
        
        wp_send_json_success(array('message' => 'Cache limpiado correctamente'));
    }
    
    /**
     * AJAX: Obtener ubicaciones geográficas
     */
    public function ajax_get_ubicaciones() {
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación'
            ));
        }
        
        // Cache de ubicaciones
        $ubicaciones = get_transient('tramaco_ubicaciones');
        
        if (!$ubicaciones) {
            $response = wp_remote_get(TRAMACO_API_UBICACION_URL, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $token
                ),
                'timeout' => 60,
                'sslverify' => false
            ));
            
            if (is_wp_error($response)) {
                wp_send_json(array(
                    'success' => false,
                    'message' => $response->get_error_message()
                ));
            }
            
            $ubicaciones = json_decode(wp_remote_retrieve_body($response), true);
            
            // Guardar en cache por 24 horas
            set_transient('tramaco_ubicaciones', $ubicaciones, DAY_IN_SECONDS);
        }
        
        wp_send_json(array(
            'success' => true,
            'data' => $ubicaciones
        ));
    }
    
    /**
     * AJAX: Guardar parroquia seleccionada en sesión de WooCommerce
     */
    public function ajax_save_checkout_parroquia() {
        $parroquia = intval($_POST['parroquia'] ?? 0);
        
        if ($parroquia && class_exists('WC_Session_Handler')) {
            if (WC()->session) {
                WC()->session->set('shipping_tramaco_parroquia', $parroquia);
            }
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Obtener localidades del contrato
     */
    public function ajax_get_localidades() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $token = $this->get_token();
        
        if (!$token) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error de autenticación'
            ));
        }
        
        $response = wp_remote_get(TRAMACO_API_LOCALIDAD_CONTRATO_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response->get_error_message()
            ));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        wp_send_json(array(
            'success' => isset($body['codigo']) && $body['codigo'] == 1,
            'data' => $body
        ));
    }
    
    /**
     * Shortcode: Tracking
     */
    public function tracking_shortcode($atts) {
        ob_start();
        ?>
        <div class="tramaco-tracking-wrapper">
            <div class="tramaco-tracking-header">
                <h2 class="tracking-title">
                    <span class="tracking-icon">📦</span>
                    <?php _e('Rastrear tu Envío', 'tramaco-api'); ?>
                </h2>
                <p class="tracking-subtitle"><?php _e('Ingresa tu número de guía y verifica tu identidad para consultar el estado de tu envío', 'tramaco-api'); ?></p>
            </div>
            
            <div class="tramaco-tracking-form">
                <form id="tramaco-tracking-form">
                    <div class="form-row">
                        <div class="form-group form-group-large">
                            <label for="tracking-guia">
                                <span class="label-icon">🔢</span>
                                <?php _e('Número de Guía', 'tramaco-api'); ?>
                            </label>
                            <input type="text" 
                                   id="tracking-guia" 
                                   name="guia" 
                                   placeholder="Ej: 031002005633799" 
                                   class="form-control-lg"
                                   required />
                            <small class="form-text"><?php _e('Ingresa el número de guía proporcionado', 'tramaco-api'); ?></small>
                        </div>
                    </div>
                    
                    <div class="form-row form-row-verification">
                        <div class="form-group">
                            <label for="tracking-verificacion">
                                <span class="label-icon">🔐</span>
                                <?php _e('Tipo de Verificación', 'tramaco-api'); ?>
                            </label>
                            <select id="tracking-verificacion-tipo" name="verificacion_tipo" class="form-control form-control-select" required>
                                <option value="telefono-destinatario"><?php _e('📱 Teléfono del Destinatario', 'tramaco-api'); ?></option>
                                <option value="documento-destinatario"><?php _e('🆔 Cédula/RUC del Destinatario', 'tramaco-api'); ?></option>
                                <option value="telefono-remitente"><?php _e('📱 Teléfono del Remitente', 'tramaco-api'); ?></option>
                                <option value="documento-remitente"><?php _e('🆔 Cédula/RUC del Remitente', 'tramaco-api'); ?></option>
                            </select>
                            <small class="form-text"><?php _e('Selecciona cómo deseas verificar tu identidad', 'tramaco-api'); ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tracking-verificacion-valor" class="verificacion-label">
                                <span class="label-icon-dynamic">📱</span>
                                <span class="label-text-dynamic"><?php _e('Teléfono del Destinatario', 'tramaco-api'); ?></span>
                            </label>
                            <input type="text" 
                                   id="tracking-verificacion-valor" 
                                   name="verificacion_valor" 
                                   placeholder="Ej: 0987654321" 
                                   class="form-control"
                                   maxlength="13"
                                   required />
                            <small class="form-text verificacion-help"><?php _e('Ingresa el dato tal como fue registrado', 'tramaco-api'); ?></small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <span class="btn-icon">🔍</span>
                        <?php _e('Consultar Seguimiento', 'tramaco-api'); ?>
                    </button>
                </form>
            </div>
            
            <div id="tracking-result" class="tramaco-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Cotización
     */
    public function cotizacion_shortcode($atts) {
        ob_start();
        ?>
        <div class="tramaco-cotizacion-wrapper">
            <div class="tramaco-header">
                <h2 class="tramaco-title">
                    <span class="tramaco-title-icon">💰</span>
                    <?php _e('Cotizar Envío', 'tramaco-api'); ?>
                </h2>
                <p class="tramaco-subtitle"><?php _e('Calcula el costo de tu envío de manera rápida y sencilla', 'tramaco-api'); ?></p>
            </div>
            
            <div class="tramaco-card">
                <form id="tramaco-cotizacion-form">
                    <div class="tramaco-section">
                        <h3 class="tramaco-section-title">
                            <span class="section-icon">📍</span>
                            <?php _e('Destino del Envío', 'tramaco-api'); ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cotizacion-provincia" class="form-label">
                                    <span class="label-icon">🗺️</span>
                                    <?php _e('Provincia', 'tramaco-api'); ?>
                                </label>
                                <select id="cotizacion-provincia" name="provincia" class="form-select" required>
                                    <option value="">Seleccione una provincia...</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cotizacion-canton" class="form-label">
                                    <span class="label-icon">🏘️</span>
                                    <?php _e('Cantón', 'tramaco-api'); ?>
                                </label>
                                <select id="cotizacion-canton" name="canton" class="form-select" required>
                                    <option value="">Seleccione provincia primero...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cotizacion-parroquia" class="form-label">
                                <span class="label-icon">📌</span>
                                <?php _e('Parroquia', 'tramaco-api'); ?>
                            </label>
                            <select id="cotizacion-parroquia" name="parroquia" class="form-select" required>
                                <option value="">Seleccione cantón primero...</option>
                            </select>
                            <small class="form-text"><?php _e('Selecciona la parroquia de destino exacta', 'tramaco-api'); ?></small>
                        </div>
                    </div>
                    
                    <div class="tramaco-section">
                        <h3 class="tramaco-section-title">
                            <span class="section-icon">📦</span>
                            <?php _e('Detalles del Paquete', 'tramaco-api'); ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cotizacion-peso" class="form-label">
                                    <span class="label-icon">⚖️</span>
                                    <?php _e('Peso (kg)', 'tramaco-api'); ?>
                                </label>
                                <input type="number" 
                                       id="cotizacion-peso" 
                                       name="peso" 
                                       class="form-control" 
                                       step="0.01" 
                                       min="0.1" 
                                       placeholder="Ej: 2.5"
                                       required />
                                <small class="form-text"><?php _e('Peso real del paquete en kilogramos', 'tramaco-api'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="cotizacion-bultos" class="form-label">
                                    <span class="label-icon">📦</span>
                                    <?php _e('Cantidad de Bultos', 'tramaco-api'); ?>
                                </label>
                                <input type="number" 
                                       id="cotizacion-bultos" 
                                       name="bultos" 
                                       class="form-control" 
                                       min="1" 
                                       value="1"
                                       placeholder="1"
                                       required />
                                <small class="form-text"><?php _e('Número de paquetes a enviar', 'tramaco-api'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <span class="btn-icon">💰</span>
                        <?php _e('Calcular Precio', 'tramaco-api'); ?>
                    </button>
                </form>
            </div>
            
            <div id="cotizacion-result" class="tramaco-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Generar Guía (solo usuarios logueados)
     */
    public function generar_guia_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="tramaco-card"><p class="tramaco-login-required">' . __('Debe iniciar sesión para generar guías.', 'tramaco-api') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="tramaco-generar-guia-wrapper">
            <div class="tramaco-header">
                <h2 class="tramaco-title">
                    <span class="tramaco-title-icon">📦</span>
                    <?php _e('Generar Guía de Envío', 'tramaco-api'); ?>
                </h2>
                <p class="tramaco-subtitle"><?php _e('Complete la información para crear una nueva guía de envío', 'tramaco-api'); ?></p>
            </div>
            
            <div class="tramaco-card">
                <form id="tramaco-generar-guia-form">
                    <!-- Sección Destinatario -->
                    <div class="tramaco-section">
                        <h3 class="tramaco-section-title">
                            <span class="section-icon">👤</span>
                            <?php _e('Datos del Destinatario', 'tramaco-api'); ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-nombres" class="form-label">
                                    <span class="label-icon">✍️</span>
                                    <?php _e('Nombres', 'tramaco-api'); ?>
                                </label>
                                <input type="text" id="fg-nombres" name="dest_nombres" class="form-control" required />
                            </div>
                            <div class="form-group">
                                <label for="fg-apellidos" class="form-label">
                                    <span class="label-icon">✍️</span>
                                    <?php _e('Apellidos', 'tramaco-api'); ?>
                                </label>
                                <input type="text" id="fg-apellidos" name="dest_apellidos" class="form-control" required />
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-ci" class="form-label">
                                    <span class="label-icon">🆔</span>
                                    <?php _e('Cédula/RUC', 'tramaco-api'); ?>
                                </label>
                                <input type="text" id="fg-ci" name="dest_ci_ruc" class="form-control" required />
                            </div>
                            <div class="form-group">
                                <label for="fg-tipo-id" class="form-label">
                                    <span class="label-icon">📋</span>
                                    <?php _e('Tipo ID', 'tramaco-api'); ?>
                                </label>
                                <select id="fg-tipo-id" name="dest_tipo_iden" class="form-select">
                                    <option value="05">Cédula</option>
                                    <option value="04">RUC</option>
                                    <option value="06">Pasaporte</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-telefono" class="form-label">
                                    <span class="label-icon">📱</span>
                                    <?php _e('Teléfono', 'tramaco-api'); ?>
                                </label>
                                <input type="tel" id="fg-telefono" name="dest_telefono" class="form-control" placeholder="0987654321" required />
                            </div>
                            <div class="form-group">
                                <label for="fg-email" class="form-label">
                                    <span class="label-icon">📧</span>
                                    <?php _e('Email', 'tramaco-api'); ?>
                                </label>
                                <input type="email" id="fg-email" name="dest_email" class="form-control" placeholder="correo@ejemplo.com" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección Dirección -->
                    <div class="tramaco-section">
                        <h3 class="tramaco-section-title">
                            <span class="section-icon">📍</span>
                            <?php _e('Dirección de Destino', 'tramaco-api'); ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-provincia" class="form-label">
                                    <span class="label-icon">🗺️</span>
                                    <?php _e('Provincia', 'tramaco-api'); ?>
                                </label>
                                <select id="fg-provincia" name="provincia" class="form-select" required>
                                    <option value="">Seleccione una provincia...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="fg-canton" class="form-label">
                                    <span class="label-icon">🏘️</span>
                                    <?php _e('Cantón', 'tramaco-api'); ?>
                                </label>
                                <select id="fg-canton" name="canton" class="form-select" required>
                                    <option value="">Seleccione provincia primero...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="fg-parroquia" class="form-label">
                                <span class="label-icon">📌</span>
                                <?php _e('Parroquia', 'tramaco-api'); ?>
                            </label>
                            <select id="fg-parroquia" name="parroquia" class="form-select" required>
                                <option value="">Seleccione cantón primero...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fg-direccion" class="form-label">
                                <span class="label-icon">🏠</span>
                                <?php _e('Dirección Completa', 'tramaco-api'); ?>
                            </label>
                            <textarea id="fg-direccion" name="dest_direccion" class="form-control" rows="2" required></textarea>
                            <small class="form-text"><?php _e('Incluye calle principal, número, referencias', 'tramaco-api'); ?></small>
                        </div>
                    </div>
                    
                    <!-- Sección Carga -->
                    <div class="tramaco-section">
                        <h3 class="tramaco-section-title">
                            <span class="section-icon">📦</span>
                            <?php _e('Detalles de la Carga', 'tramaco-api'); ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-peso" class="form-label">
                                    <span class="label-icon">⚖️</span>
                                    <?php _e('Peso (kg)', 'tramaco-api'); ?>
                                </label>
                                <input type="number" id="fg-peso" name="peso" class="form-control" step="0.01" min="0.1" placeholder="Ej: 2.5" required />
                            </div>
                            <div class="form-group">
                                <label for="fg-bultos" class="form-label">
                                    <span class="label-icon">📦</span>
                                    <?php _e('Bultos', 'tramaco-api'); ?>
                                </label>
                                <input type="number" id="fg-bultos" name="bultos" class="form-control" min="1" value="1" required />
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="fg-contenido" class="form-label">
                                <span class="label-icon">📝</span>
                                <?php _e('Contenido', 'tramaco-api'); ?>
                            </label>
                            <textarea id="fg-contenido" name="contenido" class="form-control" rows="2" required></textarea>
                            <small class="form-text"><?php _e('Describe el contenido del paquete', 'tramaco-api'); ?></small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fg-valor" class="form-label">
                                    <span class="label-icon">💵</span>
                                    <?php _e('Valor Declarado', 'tramaco-api'); ?>
                                </label>
                                <input type="number" id="fg-valor" name="valor_declarado" class="form-control" step="0.01" min="0" placeholder="0.00" />
                                <small class="form-text"><?php _e('Valor del contenido para seguro', 'tramaco-api'); ?></small>
                            </div>
                            <div class="form-group">
                                <label for="fg-observaciones" class="form-label">
                                    <span class="label-icon">💬</span>
                                    <?php _e('Observaciones', 'tramaco-api'); ?>
                                </label>
                                <input type="text" id="fg-observaciones" name="observaciones" class="form-control" placeholder="Información adicional" />
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <span class="btn-icon">📦</span>
                        <?php _e('Generar Guía de Envío', 'tramaco-api'); ?>
                    </button>
                </form>
            </div>
            
            <div id="generar-guia-result" class="tramaco-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Página de configuración de SharePoint
     */
    public function admin_sharepoint_page() {
        $handler = new Tramaco_SharePoint_Handler();
        $is_configured = $handler->is_configured();
        $config_status = $handler->get_config_status();
        
        // Guardar configuración si se envió el formulario
        if (isset($_POST['tramaco_sharepoint_save']) && check_admin_referer('tramaco_sharepoint_settings')) {
            update_option('tramaco_sharepoint_enabled', isset($_POST['tramaco_sharepoint_enabled']) ? '1' : '0');
            update_option('tramaco_sharepoint_client_id', sanitize_text_field($_POST['tramaco_sharepoint_client_id']));
            update_option('tramaco_sharepoint_client_secret', sanitize_text_field($_POST['tramaco_sharepoint_client_secret']));
            update_option('tramaco_sharepoint_tenant_id', sanitize_text_field($_POST['tramaco_sharepoint_tenant_id']));
            update_option('tramaco_sharepoint_site_id', sanitize_text_field($_POST['tramaco_sharepoint_site_id']));
            update_option('tramaco_sharepoint_drive_id', sanitize_text_field($_POST['tramaco_sharepoint_drive_id']));
            update_option('tramaco_sharepoint_file_path', sanitize_text_field($_POST['tramaco_sharepoint_file_path']));
            
            echo '<div class="notice notice-success"><p>Configuración guardada correctamente</p></div>';
            
            // Recargar handler con nueva configuración
            $handler = new Tramaco_SharePoint_Handler();
            $is_configured = $handler->is_configured();
            $config_status = $handler->get_config_status();
        }
        
        $enabled = get_option('tramaco_sharepoint_enabled', '0');
        $client_id = get_option('tramaco_sharepoint_client_id', '');
        $client_secret = get_option('tramaco_sharepoint_client_secret', '');
        $tenant_id = get_option('tramaco_sharepoint_tenant_id', '');
        $site_id = get_option('tramaco_sharepoint_site_id', '');
        $drive_id = get_option('tramaco_sharepoint_drive_id', '');
        $file_path = get_option('tramaco_sharepoint_file_path', '/Data Guías/Registro-Guias-Tramaco.xlsx');
        ?>
        <div class="wrap">
            <h1>📊 Configuración SharePoint</h1>
            <p>Configura la integración con Microsoft SharePoint para enviar automáticamente los datos de las guías a un archivo Excel.</p>
            
            <?php if (!$is_configured): ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ Estado:</strong> <?php echo esc_html($config_status); ?></p>
                    <?php if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')): ?>
                        <p><strong>📦 PhpSpreadsheet no instalado.</strong> Ejecuta en la carpeta del plugin:</p>
                        <pre style="background: #f0f0f0; padding: 10px; border-radius: 4px;">composer install</pre>
                        <p>O sigue las instrucciones en <a href="<?php echo esc_url(TRAMACO_API_PLUGIN_URL . 'SHAREPOINT-SETUP.md'); ?>" target="_blank">SHAREPOINT-SETUP.md</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ Estado:</strong> Configurado correctamente</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('tramaco_sharepoint_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_enabled">Habilitar SharePoint</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="tramaco_sharepoint_enabled" 
                                       name="tramaco_sharepoint_enabled" 
                                       value="1" 
                                       <?php checked($enabled, '1'); ?> />
                                Enviar datos de guías a SharePoint automáticamente
                            </label>
                            <p class="description">Cuando está habilitado, los datos de cada guía se envían automáticamente al Excel en SharePoint.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>🔐 Credenciales de Azure AD</h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tramaco_sharepoint_client_id" 
                                   name="tramaco_sharepoint_client_id" 
                                   value="<?php echo esc_attr($client_id); ?>" 
                                   class="regular-text" 
                                   placeholder="00000000-0000-0000-0000-000000000000" />
                            <p class="description">Application (client) ID desde Azure AD</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="tramaco_sharepoint_client_secret" 
                                   name="tramaco_sharepoint_client_secret" 
                                   value="<?php echo esc_attr($client_secret); ?>" 
                                   class="regular-text" />
                            <p class="description">Client Secret (Value) desde Azure AD</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_tenant_id">Tenant ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tramaco_sharepoint_tenant_id" 
                                   name="tramaco_sharepoint_tenant_id" 
                                   value="<?php echo esc_attr($tenant_id); ?>" 
                                   class="regular-text" 
                                   placeholder="00000000-0000-0000-0000-000000000000" />
                            <p class="description">Directory (tenant) ID desde Azure AD</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>📁 Configuración del Archivo</h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_site_id">Site ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tramaco_sharepoint_site_id" 
                                   name="tramaco_sharepoint_site_id" 
                                   value="<?php echo esc_attr($site_id); ?>" 
                                   class="large-text" 
                                   placeholder="domain.sharepoint.com,00000000-0000-0000-0000-000000000000,00000000-0000-0000-0000-000000000000" />
                            <p class="description">ID del sitio de SharePoint (formato: domain.sharepoint.com,siteId,webId)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_drive_id">Drive ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tramaco_sharepoint_drive_id" 
                                   name="tramaco_sharepoint_drive_id" 
                                   value="<?php echo esc_attr($drive_id); ?>" 
                                   class="large-text" 
                                   placeholder="b!xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" />
                            <p class="description">ID del drive donde está el archivo Excel</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tramaco_sharepoint_file_path">Ruta del Archivo</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tramaco_sharepoint_file_path" 
                                   name="tramaco_sharepoint_file_path" 
                                   value="<?php echo esc_attr($file_path); ?>" 
                                   class="large-text" 
                                   placeholder="/Data Guías/Registro-Guias-Tramaco.xlsx" />
                            <p class="description">Ruta completa del archivo Excel en SharePoint (se creará si no existe)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="tramaco_sharepoint_save" class="button button-primary">💾 Guardar Configuración</button>
                    <button type="button" id="tramaco-sharepoint-test" class="button button-secondary" <?php echo !$is_configured ? 'disabled' : ''; ?>>
                        🔍 Probar Conexión
                    </button>
                    <button type="button" id="tramaco-sharepoint-sync" class="button button-secondary" <?php echo !$is_configured || $enabled !== '1' ? 'disabled' : ''; ?>>
                        🔄 Sincronizar Pedidos Pendientes
                    </button>
                </p>
            </form>
            
            <div id="tramaco-sharepoint-result" style="margin-top: 20px;"></div>
            
            <hr style="margin: 40px 0;" />
            
            <h2>📚 Documentación</h2>
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h3>Permisos necesarios en Azure AD:</h3>
                <ul>
                    <li><code>Sites.ReadWrite.All</code> (Application)</li>
                    <li><code>Files.ReadWrite.All</code> (Application)</li>
                </ul>
                <p>Estos permisos deben tener <strong>Admin Consent</strong> otorgado.</p>
                
                <h3>Formato del Excel:</h3>
                <p>El archivo se creará automáticamente con estos campos:</p>
                <ul style="column-count: 3; column-gap: 20px;">
                    <li>Fecha</li>
                    <li>Hora</li>
                    <li>Pedido</li>
                    <li>Estado</li>
                    <li>Total</li>
                    <li>Guía</li>
                    <li>Fecha Guía</li>
                    <li>Destinatario</li>
                    <li>Teléfono</li>
                    <li>Email</li>
                    <li>Dirección</li>
                    <li>Ciudad</li>
                    <li>Parroquia</li>
                    <li>Productos</li>
                    <li>Cantidad</li>
                    <li>Costo Envío</li>
                    <li>PDF Guía</li>
                    <li>Link Pedido</li>
                    <li>Tracking</li>
                </ul>
                
                <p><strong>Más información:</strong> <a href="<?php echo esc_url(TRAMACO_API_PLUGIN_URL . 'SHAREPOINT-SETUP.md'); ?>" target="_blank">SHAREPOINT-SETUP.md</a></p>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#tramaco-sharepoint-test').on('click', function() {
                    const btn = $(this);
                    const result = $('#tramaco-sharepoint-result');
                    
                    btn.prop('disabled', true).text('🔄 Probando...');
                    result.html('<div class="notice notice-info"><p>Probando conexión...</p></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tramaco_sharepoint_test',
                            nonce: '<?php echo wp_create_nonce("tramaco_sharepoint_test"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                result.html(
                                    '<div class="notice notice-success"><p><strong>✅ ' + response.data.message + '</strong></p>' +
                                    (response.data.site_name ? '<p>Sitio: ' + response.data.site_name + '</p>' : '') +
                                    (response.data.site_url ? '<p>URL: <a href="' + response.data.site_url + '" target="_blank">' + response.data.site_url + '</a></p>' : '') +
                                    '</div>'
                                );
                            } else {
                                result.html('<div class="notice notice-error"><p><strong>❌ Error:</strong> ' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            result.html('<div class="notice notice-error"><p><strong>❌ Error de conexión</strong></p></div>');
                        },
                        complete: function() {
                            btn.prop('disabled', false).text('🔍 Probar Conexión');
                        }
                    });
                });
                
                $('#tramaco-sharepoint-sync').on('click', function() {
                    if (!confirm('¿Deseas sincronizar todos los pedidos pendientes con SharePoint?')) {
                        return;
                    }
                    
                    const btn = $(this);
                    const result = $('#tramaco-sharepoint-result');
                    
                    btn.prop('disabled', true).text('🔄 Sincronizando...');
                    result.html('<div class="notice notice-info"><p>Sincronizando pedidos... esto puede tomar varios minutos.</p></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tramaco_sharepoint_sync',
                            nonce: '<?php echo wp_create_nonce("tramaco_sharepoint_sync"); ?>'
                        },
                        timeout: 300000, // 5 minutos
                        success: function(response) {
                            if (response.success) {
                                result.html('<div class="notice notice-success"><p><strong>✅ ' + response.data.message + '</strong></p></div>');
                            } else {
                                result.html('<div class="notice notice-error"><p><strong>❌ Error:</strong> ' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            result.html('<div class="notice notice-error"><p><strong>❌ Error durante la sincronización</strong></p></div>');
                        },
                        complete: function() {
                            btn.prop('disabled', false).text('🔄 Sincronizar Pedidos Pendientes');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX: Probar conexión con SharePoint
     */
    public function ajax_sharepoint_test() {
        check_ajax_referer('tramaco_sharepoint_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
            return;
        }
        
        $handler = new Tramaco_SharePoint_Handler();
        $result = $handler->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sincronizar pedidos pendientes con SharePoint
     */
    public function ajax_sharepoint_sync() {
        check_ajax_referer('tramaco_sharepoint_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
            return;
        }
        
        $handler = new Tramaco_SharePoint_Handler();
        $handler->sync_pending_orders();
        
        wp_send_json_success(array('message' => 'Sincronización completada. Revisa los logs para más detalles.'));
    }
}

// Inicializar el plugin
function tramaco_api_init() {
    return Tramaco_API_Integration::get_instance();
}
add_action('plugins_loaded', 'tramaco_api_init');

// Activación del plugin
register_activation_hook(__FILE__, function() {
    // Crear tablas o configuraciones iniciales si es necesario
    add_option('tramaco_api_login', '1793191845001');
    add_option('tramaco_api_password', 'MAS.39inter.PIN');
    add_option('tramaco_api_contrato', '6394');
    add_option('tramaco_api_localidad', '21580');
    add_option('tramaco_api_producto', '36');
    add_option('tramaco_api_environment', 'qa');
});

// Desactivación del plugin
register_deactivation_hook(__FILE__, function() {
    delete_transient('tramaco_api_token');
    delete_transient('tramaco_ubicaciones');
});
