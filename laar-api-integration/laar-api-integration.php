<?php
/**
 * Plugin Name: Laar Courier API Integration
 * Plugin URI: https://starbrand.com
 * Description: Integración con la API de Laar Courier para generación de guías, tracking y consultas
 * Version: 1.0.0
 * Author: Star Brand
 * Author URI: https://starbrand.com
 * License: GPL v2 or later
 * Text Domain: laar-api
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('LAAR_API_VERSION', '1.0.0');
define('LAAR_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LAAR_API_PLUGIN_URL', plugin_dir_url(__FILE__));

// URL base de la API
define('LAAR_API_BASE_URL', 'https://api.laarcourier.com:9727');

/**
 * Clase principal del plugin
 */
class Laar_API_Integration {
    
    private static $instance = null;
    private $token = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Shortcodes
        add_shortcode('laar_tracking', array($this, 'tracking_shortcode'));
        add_shortcode('laar_cotizacion', array($this, 'cotizacion_shortcode'));
        add_shortcode('laar_generar_guia', array($this, 'generar_guia_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_laar_auth', array($this, 'ajax_authenticate'));
        add_action('wp_ajax_nopriv_laar_auth', array($this, 'ajax_authenticate'));
        add_action('wp_ajax_laar_tracking', array($this, 'ajax_tracking'));
        add_action('wp_ajax_nopriv_laar_tracking', array($this, 'ajax_tracking'));
        add_action('wp_ajax_laar_cotizacion', array($this, 'ajax_cotizacion'));
        add_action('wp_ajax_nopriv_laar_cotizacion', array($this, 'ajax_cotizacion'));
        add_action('wp_ajax_laar_generar_guia', array($this, 'ajax_generar_guia'));
        add_action('wp_ajax_laar_ciudades', array($this, 'ajax_get_ciudades'));
        add_action('wp_ajax_nopriv_laar_ciudades', array($this, 'ajax_get_ciudades'));
        add_action('wp_ajax_laar_productos', array($this, 'ajax_get_productos'));
        add_action('wp_ajax_laar_sucursales', array($this, 'ajax_get_sucursales'));
        add_action('wp_ajax_laar_pdf', array($this, 'ajax_get_pdf'));
    }
    
    public function init() {
        load_plugin_textdomain('laar-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Laar Courier', 'laar-api'),
            __('Laar Courier', 'laar-api'),
            'manage_options',
            'laar-api',
            array($this, 'admin_page'),
            'dashicons-airplane',
            30
        );
        
        add_submenu_page(
            'laar-api',
            __('Configuración', 'laar-api'),
            __('Configuración', 'laar-api'),
            'manage_options',
            'laar-api',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'laar-api',
            __('Generar Guía', 'laar-api'),
            __('Generar Guía', 'laar-api'),
            'manage_options',
            'laar-api-generar-guia',
            array($this, 'admin_generar_guia_page')
        );
        
        add_submenu_page(
            'laar-api',
            __('Tracking', 'laar-api'),
            __('Tracking', 'laar-api'),
            'manage_options',
            'laar-api-tracking',
            array($this, 'admin_tracking_page')
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('laar_api_settings', 'laar_api_username');
        register_setting('laar_api_settings', 'laar_api_password');
    }
    
    /**
     * Cargar scripts frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_style('laar-api-styles', LAAR_API_PLUGIN_URL . 'assets/css/laar-styles.css', array(), LAAR_API_VERSION);
        wp_enqueue_script('laar-api-scripts', LAAR_API_PLUGIN_URL . 'assets/js/laar-scripts.js', array('jquery'), LAAR_API_VERSION, true);
        wp_localize_script('laar-api-scripts', 'laarApi', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laar_api_nonce')
        ));
    }
    
    /**
     * Cargar scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'laar-api') === false) return;
        
        wp_enqueue_style('laar-api-admin-styles', LAAR_API_PLUGIN_URL . 'assets/css/laar-admin.css', array(), LAAR_API_VERSION);
        wp_enqueue_script('laar-api-admin-scripts', LAAR_API_PLUGIN_URL . 'assets/js/laar-admin.js', array('jquery'), LAAR_API_VERSION, true);
        wp_localize_script('laar-api-admin-scripts', 'laarAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laar_api_nonce')
        ));
    }
    
    /**
     * Página de configuración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración de Laar Courier API', 'laar-api'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('laar_api_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="laar_api_username"><?php _e('Usuario', 'laar-api'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="laar_api_username" id="laar_api_username" 
                                   value="<?php echo esc_attr(get_option('laar_api_username', 'prueba.star.brands.api')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="laar_api_password"><?php _e('Contraseña', 'laar-api'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="laar_api_password" id="laar_api_password" 
                                   value="<?php echo esc_attr(get_option('laar_api_password', 'ISwoaA8B')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Guardar Configuración', 'laar-api')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Probar Conexión', 'laar-api'); ?></h2>
            <button type="button" id="test-connection" class="button button-secondary">
                <?php _e('Probar Autenticación', 'laar-api'); ?>
            </button>
            <div id="connection-result" style="margin-top: 10px;"></div>
            
            <hr>
            
            <h2><?php _e('Información de la Cuenta', 'laar-api'); ?></h2>
            <div id="account-info"></div>
            
            <hr>
            
            <h2><?php _e('Shortcodes Disponibles', 'laar-api'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[laar_tracking]</code></td>
                        <td>Formulario de tracking de guías</td>
                    </tr>
                    <tr>
                        <td><code>[laar_cotizacion]</code></td>
                        <td>Formulario de cotización de envíos</td>
                    </tr>
                    <tr>
                        <td><code>[laar_generar_guia]</code></td>
                        <td>Formulario para generar guías (requiere login)</td>
                    </tr>
                </tbody>
            </table>
            
            <hr>
            
            <h2><?php _e('Productos Disponibles', 'laar-api'); ?></h2>
            <div id="productos-list">
                <p><em>Haz clic en "Probar Autenticación" para cargar los productos.</em></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de generación de guías
     */
    public function admin_generar_guia_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Generar Nueva Guía', 'laar-api'); ?></h1>
            
            <form id="form-generar-guia" class="laar-form">
                <h3><?php _e('Datos del Destino', 'laar-api'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="dest_nombre"><?php _e('Nombre Completo', 'laar-api'); ?></label></th>
                        <td><input type="text" id="dest_nombre" name="dest_nombre" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_cedula"><?php _e('Cédula/RUC', 'laar-api'); ?></label></th>
                        <td><input type="text" id="dest_cedula" name="dest_cedula" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_ciudad"><?php _e('Ciudad Destino', 'laar-api'); ?></label></th>
                        <td><select id="dest_ciudad" name="dest_ciudad" class="regular-text" required></select></td>
                    </tr>
                    <tr>
                        <th><label for="dest_direccion"><?php _e('Dirección', 'laar-api'); ?></label></th>
                        <td><input type="text" id="dest_direccion" name="dest_direccion" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_referencia"><?php _e('Referencia', 'laar-api'); ?></label></th>
                        <td><input type="text" id="dest_referencia" name="dest_referencia" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_numero_casa"><?php _e('Número Casa', 'laar-api'); ?></label></th>
                        <td><input type="text" id="dest_numero_casa" name="dest_numero_casa" class="small-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_telefono"><?php _e('Teléfono', 'laar-api'); ?></label></th>
                        <td><input type="tel" id="dest_telefono" name="dest_telefono" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_celular"><?php _e('Celular', 'laar-api'); ?></label></th>
                        <td><input type="tel" id="dest_celular" name="dest_celular" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="dest_correo"><?php _e('Correo', 'laar-api'); ?></label></th>
                        <td><input type="email" id="dest_correo" name="dest_correo" class="regular-text" /></td>
                    </tr>
                </table>
                
                <h3><?php _e('Datos del Envío', 'laar-api'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="tipo_servicio"><?php _e('Tipo de Servicio', 'laar-api'); ?></label></th>
                        <td>
                            <select id="tipo_servicio" name="tipo_servicio">
                                <option value="2012020020091">DELIVERY</option>
                                <option value="201202002001002">DOCUMENTO</option>
                                <option value="201202002002013">CARGA</option>
                                <option value="201202002004001">VALIJA</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="piezas"><?php _e('Número de Piezas', 'laar-api'); ?></label></th>
                        <td><input type="number" id="piezas" name="piezas" class="small-text" value="1" min="1" required /></td>
                    </tr>
                    <tr>
                        <th><label for="peso"><?php _e('Peso (kg)', 'laar-api'); ?></label></th>
                        <td><input type="number" id="peso" name="peso" class="small-text" step="0.01" required /></td>
                    </tr>
                    <tr>
                        <th><label for="valor_declarado"><?php _e('Valor Declarado ($)', 'laar-api'); ?></label></th>
                        <td><input type="number" id="valor_declarado" name="valor_declarado" class="small-text" step="0.01" value="0" /></td>
                    </tr>
                    <tr>
                        <th><label for="contiene"><?php _e('Contenido', 'laar-api'); ?></label></th>
                        <td><textarea id="contiene" name="contiene" class="regular-text" required></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="tamanio"><?php _e('Tamaño', 'laar-api'); ?></label></th>
                        <td>
                            <select id="tamanio" name="tamanio">
                                <option value="PEQUEÑO">Pequeño</option>
                                <option value="MEDIANO">Mediano</option>
                                <option value="GRANDE">Grande</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cod"><?php _e('Cobro en Destino (COD)', 'laar-api'); ?></label></th>
                        <td>
                            <input type="checkbox" id="cod" name="cod" value="1" />
                            <label for="cod"><?php _e('Sí, cobrar en destino', 'laar-api'); ?></label>
                        </td>
                    </tr>
                    <tr id="row-costo-flete" style="display:none;">
                        <th><label for="costo_flete"><?php _e('Costo Flete ($)', 'laar-api'); ?></label></th>
                        <td><input type="number" id="costo_flete" name="costo_flete" class="small-text" step="0.01" value="0" /></td>
                    </tr>
                    <tr id="row-costo-producto" style="display:none;">
                        <th><label for="costo_producto"><?php _e('Costo Producto ($)', 'laar-api'); ?></label></th>
                        <td><input type="number" id="costo_producto" name="costo_producto" class="small-text" step="0.01" value="0" /></td>
                    </tr>
                    <tr>
                        <th><label for="comentario"><?php _e('Comentario', 'laar-api'); ?></label></th>
                        <td><textarea id="comentario" name="comentario" class="regular-text"></textarea></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Generar Guía', 'laar-api'); ?></button>
                </p>
            </form>
            
            <div id="guia-result" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#cod').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#row-costo-flete, #row-costo-producto').show();
                } else {
                    $('#row-costo-flete, #row-costo-producto').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Página de tracking
     */
    public function admin_tracking_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Consultar Tracking', 'laar-api'); ?></h1>
            
            <form id="form-tracking-admin" class="laar-form">
                <table class="form-table">
                    <tr>
                        <th><label for="numero_guia"><?php _e('Número de Guía', 'laar-api'); ?></label></th>
                        <td>
                            <input type="text" id="numero_guia" name="numero_guia" class="regular-text" 
                                   placeholder="Ingrese el número de guía" required />
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Consultar', 'laar-api'); ?></button>
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
        $username = get_option('laar_api_username', 'prueba.star.brands.api');
        $password = get_option('laar_api_password', 'ISwoaA8B');
        
        $response = wp_remote_post(LAAR_API_BASE_URL . '/authenticate', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'username' => $username,
                'password' => $password
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['token'])) {
            $this->token = $body['token'];
            set_transient('laar_api_token', $this->token, 2 * HOUR_IN_SECONDS);
            set_transient('laar_api_user_info', $body, 2 * HOUR_IN_SECONDS);
            
            return array(
                'success' => true,
                'token' => $this->token,
                'data' => $body
            );
        }
        
        return array('success' => false, 'message' => 'Error de autenticación');
    }
    
    /**
     * Obtener token válido
     */
    public function get_token() {
        $token = get_transient('laar_api_token');
        if (!$token) {
            $auth = $this->authenticate();
            if ($auth['success']) {
                $token = $auth['token'];
            }
        }
        return $token;
    }
    
    /**
     * Hacer petición a la API
     */
    private function api_request($endpoint, $method = 'GET', $body = null) {
        $token = $this->get_token();
        if (!$token) {
            return array('success' => false, 'message' => 'Error de autenticación');
        }
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30,
            'sslverify' => false
        );
        
        if ($body) {
            $args['body'] = json_encode($body);
        }
        
        if ($method === 'POST') {
            $response = wp_remote_post(LAAR_API_BASE_URL . $endpoint, $args);
        } else {
            $response = wp_remote_get(LAAR_API_BASE_URL . $endpoint, $args);
        }
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        return array(
            'success' => true,
            'data' => json_decode(wp_remote_retrieve_body($response), true)
        );
    }
    
    /**
     * AJAX: Autenticar
     */
    public function ajax_authenticate() {
        check_ajax_referer('laar_api_nonce', 'nonce');
        $result = $this->authenticate();
        wp_send_json($result);
    }
    
    /**
     * AJAX: Tracking
     */
    public function ajax_tracking() {
        $guia = sanitize_text_field($_POST['guia']);
        if (empty($guia)) {
            wp_send_json(array('success' => false, 'message' => 'Número de guía requerido'));
        }
        
        $result = $this->api_request('/clientes/' . $guia . '/tracking');
        wp_send_json($result);
    }
    
    /**
     * AJAX: Cotización
     */
    public function ajax_cotizacion() {
        $data = array(
            'codigoServicio' => floatval($_POST['servicio']),
            'codigoCiudadOrigen' => floatval($_POST['ciudad_origen']),
            'codigoCiudadDestino' => floatval($_POST['ciudad_destino']),
            'piezas' => floatval($_POST['piezas']),
            'peso' => floatval($_POST['peso'])
        );
        
        $result = $this->api_request('/cotizadores/tarifanormal', 'POST', $data);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Generar guía
     */
    public function ajax_generar_guia() {
        check_ajax_referer('laar_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json(array('success' => false, 'message' => 'Sin permisos'));
        }
        
        $userInfo = get_transient('laar_api_user_info');
        $codigoSucursal = $userInfo['codigoSucursal'] ?? 82301;
        
        $data = array(
            'destino' => array(
                'identificacionD' => sanitize_text_field($_POST['dest_cedula']),
                'ciudadD' => sanitize_text_field($_POST['dest_ciudad']),
                'nombreD' => sanitize_text_field($_POST['dest_nombre']),
                'direccion' => sanitize_text_field($_POST['dest_direccion']),
                'referencia' => sanitize_text_field($_POST['dest_referencia'] ?? ''),
                'numeroCasa' => sanitize_text_field($_POST['dest_numero_casa'] ?? ''),
                'postal' => '',
                'telefono' => sanitize_text_field($_POST['dest_telefono']),
                'celular' => sanitize_text_field($_POST['dest_celular'] ?? ''),
                'correo' => sanitize_email($_POST['dest_correo'] ?? '')
            ),
            'numeroGuia' => '',
            'tipoServicio' => sanitize_text_field($_POST['tipo_servicio']),
            'noPiezas' => intval($_POST['piezas']),
            'peso' => floatval($_POST['peso']),
            'valorDeclarado' => floatval($_POST['valor_declarado'] ?? 0),
            'contiene' => sanitize_textarea_field($_POST['contiene']),
            'tamanio' => sanitize_text_field($_POST['tamanio'] ?? 'MEDIANO'),
            'cod' => isset($_POST['cod']) && $_POST['cod'] == '1',
            'costoflete' => floatval($_POST['costo_flete'] ?? 0),
            'costoproducto' => floatval($_POST['costo_producto'] ?? 0),
            'tipocobro' => 0,
            'comentario' => sanitize_textarea_field($_POST['comentario'] ?? ''),
            'agendar' => false,
            'fechaPedido' => date('d/m/Y')
        );
        
        $result = $this->api_request('/guias/' . $codigoSucursal, 'POST', $data);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Obtener ciudades
     */
    public function ajax_get_ciudades() {
        $ciudades = get_transient('laar_ciudades');
        if (!$ciudades) {
            $result = $this->api_request('/ciudades');
            if ($result['success']) {
                $ciudades = $result['data'];
                set_transient('laar_ciudades', $ciudades, DAY_IN_SECONDS);
            }
        }
        wp_send_json(array('success' => true, 'data' => $ciudades));
    }
    
    /**
     * AJAX: Obtener productos
     */
    public function ajax_get_productos() {
        check_ajax_referer('laar_api_nonce', 'nonce');
        $result = $this->api_request('/productos');
        wp_send_json($result);
    }
    
    /**
     * AJAX: Obtener sucursales
     */
    public function ajax_get_sucursales() {
        check_ajax_referer('laar_api_nonce', 'nonce');
        $result = $this->api_request('/clientes/sucursales');
        wp_send_json($result);
    }
    
    /**
     * AJAX: Obtener PDF de guía
     */
    public function ajax_get_pdf() {
        check_ajax_referer('laar_api_nonce', 'nonce');
        $guia = sanitize_text_field($_POST['guia']);
        $userInfo = get_transient('laar_api_user_info');
        $sucursal = $userInfo['codigoSucursal'] ?? 82301;
        
        $result = $this->api_request('/guias/pdfs/' . $guia . '/' . $sucursal);
        wp_send_json($result);
    }
    
    /**
     * Shortcode: Tracking
     */
    public function tracking_shortcode($atts) {
        ob_start();
        ?>
        <div class="laar-tracking-form">
            <h3><?php _e('Rastrear tu Envío', 'laar-api'); ?></h3>
            <form id="laar-tracking-form">
                <div class="form-group">
                    <label for="tracking-guia"><?php _e('Número de Guía', 'laar-api'); ?></label>
                    <input type="text" id="tracking-guia" name="guia" placeholder="Ingrese su número de guía" required />
                </div>
                <button type="submit" class="btn btn-primary"><?php _e('Consultar', 'laar-api'); ?></button>
            </form>
            <div id="tracking-result" class="laar-result"></div>
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
        <div class="laar-cotizacion-form">
            <h3><?php _e('Cotizar Envío', 'laar-api'); ?></h3>
            <form id="laar-cotizacion-form">
                <div class="form-group">
                    <label for="cot-ciudad-origen"><?php _e('Ciudad Origen', 'laar-api'); ?></label>
                    <select id="cot-ciudad-origen" name="ciudad_origen" required></select>
                </div>
                <div class="form-group">
                    <label for="cot-ciudad-destino"><?php _e('Ciudad Destino', 'laar-api'); ?></label>
                    <select id="cot-ciudad-destino" name="ciudad_destino" required></select>
                </div>
                <div class="form-group">
                    <label for="cot-servicio"><?php _e('Tipo de Servicio', 'laar-api'); ?></label>
                    <select id="cot-servicio" name="servicio">
                        <option value="2012020020091">DELIVERY</option>
                        <option value="201202002001002">DOCUMENTO</option>
                        <option value="201202002002013">CARGA</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cot-piezas"><?php _e('Piezas', 'laar-api'); ?></label>
                    <input type="number" id="cot-piezas" name="piezas" value="1" min="1" required />
                </div>
                <div class="form-group">
                    <label for="cot-peso"><?php _e('Peso (kg)', 'laar-api'); ?></label>
                    <input type="number" id="cot-peso" name="peso" step="0.01" min="0.1" required />
                </div>
                <button type="submit" class="btn btn-primary"><?php _e('Cotizar', 'laar-api'); ?></button>
            </form>
            <div id="cotizacion-result" class="laar-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Generar Guía
     */
    public function generar_guia_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="laar-login-required">' . __('Debe iniciar sesión para generar guías.', 'laar-api') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="laar-generar-guia-form">
            <h3><?php _e('Generar Nueva Guía', 'laar-api'); ?></h3>
            <form id="laar-generar-guia-form">
                <!-- Destino -->
                <fieldset>
                    <legend><?php _e('Datos del Destinatario', 'laar-api'); ?></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php _e('Nombre Completo', 'laar-api'); ?></label>
                            <input type="text" name="dest_nombre" required />
                        </div>
                        <div class="form-group">
                            <label><?php _e('Cédula/RUC', 'laar-api'); ?></label>
                            <input type="text" name="dest_cedula" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Ciudad Destino', 'laar-api'); ?></label>
                        <select name="dest_ciudad" id="fg-ciudad" required></select>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Dirección', 'laar-api'); ?></label>
                        <input type="text" name="dest_direccion" required />
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php _e('Teléfono', 'laar-api'); ?></label>
                            <input type="tel" name="dest_telefono" required />
                        </div>
                        <div class="form-group">
                            <label><?php _e('Correo', 'laar-api'); ?></label>
                            <input type="email" name="dest_correo" />
                        </div>
                    </div>
                </fieldset>
                
                <!-- Envío -->
                <fieldset>
                    <legend><?php _e('Datos del Envío', 'laar-api'); ?></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php _e('Servicio', 'laar-api'); ?></label>
                            <select name="tipo_servicio">
                                <option value="2012020020091">DELIVERY</option>
                                <option value="201202002001002">DOCUMENTO</option>
                                <option value="201202002002013">CARGA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php _e('Piezas', 'laar-api'); ?></label>
                            <input type="number" name="piezas" value="1" min="1" />
                        </div>
                        <div class="form-group">
                            <label><?php _e('Peso (kg)', 'laar-api'); ?></label>
                            <input type="number" name="peso" step="0.01" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Contenido', 'laar-api'); ?></label>
                        <textarea name="contiene" required></textarea>
                    </div>
                </fieldset>
                
                <button type="submit" class="btn btn-primary"><?php _e('Generar Guía', 'laar-api'); ?></button>
            </form>
            <div id="generar-guia-result" class="laar-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializar
function laar_api_init() {
    return Laar_API_Integration::get_instance();
}
add_action('plugins_loaded', 'laar_api_init');

// Activación
register_activation_hook(__FILE__, function() {
    add_option('laar_api_username', 'prueba.star.brands.api');
    add_option('laar_api_password', 'ISwoaA8B');
});

// Desactivación
register_deactivation_hook(__FILE__, function() {
    delete_transient('laar_api_token');
    delete_transient('laar_api_user_info');
    delete_transient('laar_ciudades');
});
