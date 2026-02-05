<?php
/**
 * Integración de Tramaco con WooCommerce
 * 
 * Maneja:
 * - Método de envío personalizado con cálculo de precios
 * - Generación automática de guías al confirmar pago
 * - Almacenamiento del PDF de guía en el pedido
 * - Integración con SharePoint
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal de integración WooCommerce
 */
class Tramaco_WooCommerce_Integration {
    
    private static $instance = null;
    private $tramaco_api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Verificar que WooCommerce esté activo
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $this->tramaco_api = Tramaco_API_Integration::get_instance();
        
        // Hooks de WooCommerce
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Campos personalizados en checkout
        add_filter('woocommerce_checkout_fields', array($this, 'custom_checkout_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_fields'));
        
        // Establecer Ecuador como país por defecto
        add_filter('default_checkout_billing_country', array($this, 'set_default_country'));
        add_filter('default_checkout_shipping_country', array($this, 'set_default_country'));
        
        // AJAX para actualizar costos de envío
        add_action('wp_ajax_tramaco_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        add_action('wp_ajax_nopriv_tramaco_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        
        // AJAX para guardar parroquia en sesión
        add_action('wp_ajax_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        add_action('wp_ajax_nopriv_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        
        // AJAX para obtener cantones por provincia
        add_action('wp_ajax_tramaco_get_cantones', array($this, 'ajax_get_cantones'));
        add_action('wp_ajax_nopriv_tramaco_get_cantones', array($this, 'ajax_get_cantones'));
        
        // AJAX para obtener parroquias por cantón
        add_action('wp_ajax_tramaco_get_parroquias', array($this, 'ajax_get_parroquias'));
        add_action('wp_ajax_nopriv_tramaco_get_parroquias', array($this, 'ajax_get_parroquias'));
        
        // ========== CHECKOUT EN 2 PASOS - CARRITO ==========
        // Mostrar selector de ubicación en la página del carrito (múltiples hooks para compatibilidad)
        add_action('woocommerce_before_cart_totals', array($this, 'render_cart_location_selector'));
        add_action('woocommerce_cart_totals_before_shipping', array($this, 'render_cart_location_selector'));
        add_action('woocommerce_before_cart_collaterals', array($this, 'render_cart_location_selector_once'));
        
        // Soporte para WooCommerce Blocks - Inyectar en el footer de la página del carrito
        add_action('wp_footer', array($this, 'inject_cart_location_for_blocks'));
        
        // Soporte para WooCommerce Blocks - Shortcode para insertar en cualquier lugar
        add_shortcode('tramaco_location_selector', array($this, 'location_selector_shortcode'));
        
        // Validar que se haya seleccionado ubicación antes de ir al checkout
        add_action('woocommerce_check_cart_items', array($this, 'validate_cart_location'));
        
        // AJAX para calcular precio de envío desde el carrito
        add_action('wp_ajax_tramaco_cart_calculate_shipping', array($this, 'ajax_cart_calculate_shipping'));
        add_action('wp_ajax_nopriv_tramaco_cart_calculate_shipping', array($this, 'ajax_cart_calculate_shipping'));
        
        // AJAX para guardar ubicación completa en sesión (provincia, cantón, parroquia)
        add_action('wp_ajax_tramaco_save_cart_location', array($this, 'ajax_save_cart_location'));
        add_action('wp_ajax_nopriv_tramaco_save_cart_location', array($this, 'ajax_save_cart_location'));
        
        // Pre-llenar campos del checkout con ubicación del carrito
        add_filter('woocommerce_checkout_get_value', array($this, 'prefill_checkout_fields'), 10, 2);
        
        // Hooks cuando se completa el pago
        add_action('woocommerce_payment_complete', array($this, 'on_payment_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'on_order_processing'));
        add_action('woocommerce_order_status_completed', array($this, 'on_order_completed'));
        
        // Meta box en admin para ver guía
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // Scripts para checkout
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        
        // Columna en lista de pedidos
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_column'), 10, 2);
        
        // HPOS compatibility (WooCommerce 8.0+)
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_column'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_order_column_hpos'), 10, 2);
        
        // Acción manual para generar guía
        add_action('wp_ajax_tramaco_generate_order_guia', array($this, 'ajax_generate_order_guia'));
        
        // Acción para descargar PDF
        add_action('wp_ajax_tramaco_download_guia_pdf', array($this, 'ajax_download_guia_pdf'));
    }
    
    /**
     * Inicializar método de envío
     */
    public function init_shipping_method() {
        require_once TRAMACO_API_PLUGIN_DIR . 'includes/class-tramaco-shipping-method.php';
    }
    
    /**
     * Agregar método de envío a WooCommerce
     */
    public function add_shipping_method($methods) {
        $methods['tramaco_shipping'] = 'Tramaco_Shipping_Method';
        return $methods;
    }
    
    /**
     * Campos personalizados en checkout para ubicación Tramaco
     */
    public function custom_checkout_fields($fields) {
        // Obtener ubicaciones de Tramaco
        $ubicaciones = $this->get_ubicaciones_cached();
        
        // ====== SHIPPING FIELDS ======
        
        // 1) Reutilizar shipping_state como PROVINCIA (campo estándar de WooCommerce)
        if (isset($fields['shipping']['shipping_state'])) {
            $fields['shipping']['shipping_state']['type'] = 'select';
            $fields['shipping']['shipping_state']['label'] = __('Provincia', 'tramaco-api');
            $fields['shipping']['shipping_state']['required'] = true;
            $fields['shipping']['shipping_state']['class'] = array('form-row-first', 'tramaco-field', 'tramaco-provincia');
            $fields['shipping']['shipping_state']['priority'] = 60;
            $fields['shipping']['shipping_state']['options'] = $this->get_provincias_options($ubicaciones);
        }
        
        // 2) Cantón
        $fields['shipping']['shipping_tramaco_canton'] = array(
            'type' => 'select',
            'label' => __('Cantón', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-last', 'tramaco-field', 'tramaco-canton'),
            'options' => array('' => __('Selecciona cantón', 'tramaco-api')),
            'priority' => 61,
        );
        
        // 3) Parroquia
        $fields['shipping']['shipping_tramaco_parroquia'] = array(
            'type' => 'select',
            'label' => __('Parroquia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-field', 'tramaco-parroquia'),
            'options' => array('' => __('Selecciona parroquia', 'tramaco-api')),
            'priority' => 62,
        );
        
        // ====== BILLING FIELDS ======
        
        // 1) Reutilizar billing_state como PROVINCIA
        if (isset($fields['billing']['billing_state'])) {
            $fields['billing']['billing_state']['type'] = 'select';
            $fields['billing']['billing_state']['label'] = __('Provincia', 'tramaco-api');
            $fields['billing']['billing_state']['required'] = true;
            $fields['billing']['billing_state']['class'] = array('form-row-first', 'tramaco-field', 'tramaco-provincia');
            $fields['billing']['billing_state']['priority'] = 60;
            $fields['billing']['billing_state']['options'] = $this->get_provincias_options($ubicaciones);
        }
        
        // 2) Cantón
        $fields['billing']['billing_tramaco_canton'] = array(
            'type' => 'select',
            'label' => __('Cantón', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-last', 'tramaco-field', 'tramaco-canton'),
            'options' => array('' => __('Selecciona cantón', 'tramaco-api')),
            'priority' => 61,
        );
        
        // 3) Parroquia
        $fields['billing']['billing_tramaco_parroquia'] = array(
            'type' => 'select',
            'label' => __('Parroquia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-field', 'tramaco-parroquia'),
            'options' => array('' => __('Selecciona parroquia', 'tramaco-api')),
            'priority' => 62,
        );
        
        return $fields;
    }
    
    /**
     * Establecer Ecuador como país por defecto
     */
    public function set_default_country($country) {
        return 'EC';
    }
    
    /**
     * Obtener opciones de provincias
     */
    private function get_provincias_options($ubicaciones) {
        $options = array('' => __('Seleccione una provincia...', 'tramaco-api'));
        
        if (!empty($ubicaciones) && isset($ubicaciones['lstProvincia'])) {
            foreach ($ubicaciones['lstProvincia'] as $provincia) {
                $options[$provincia['codigo']] = $provincia['nombre'];
            }
        }
        
        return $options;
    }
    
    /**
     * Obtener ubicaciones en cache
     */
    private function get_ubicaciones_cached() {
        $ubicaciones = get_transient('tramaco_ubicaciones');
        
        if (!$ubicaciones) {
            $token = $this->tramaco_api->get_token();
            if ($token) {
                $response = wp_remote_get(TRAMACO_API_UBICACION_URL, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => $token
                    ),
                    'timeout' => 60,
                    'sslverify' => false
                ));
                
                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    
                    // Adaptar estructura de la API a la esperada por el frontend
                    if (isset($body['provincias'])) {
                        $ubicaciones = array(
                            'lstProvincia' => array()
                        );
                        
                        foreach ($body['provincias'] as $provincia) {
                            $cantones = array();
                            
                            if (isset($provincia['cantones'])) {
                                foreach ($provincia['cantones'] as $canton) {
                                    $parroquias = array();
                                    
                                    if (isset($canton['parroquias'])) {
                                        foreach ($canton['parroquias'] as $parroquia) {
                                            $parroquias[] = array(
                                                'codigo' => $parroquia['id'],
                                                'nombre' => $parroquia['nombre']
                                            );
                                        }
                                    }
                                    
                                    $cantones[] = array(
                                        'codigo' => $canton['id'],
                                        'nombre' => $canton['nombre'],
                                        'lstParroquia' => $parroquias
                                    );
                                }
                            }
                            
                            $ubicaciones['lstProvincia'][] = array(
                                'codigo' => $provincia['id'],
                                'nombre' => $provincia['nombre'],
                                'lstCanton' => $cantones
                            );
                        }
                        
                        set_transient('tramaco_ubicaciones', $ubicaciones, DAY_IN_SECONDS);
                    }
                }
            }
        }
        
        return $ubicaciones;
    }
    
    /**
     * Guardar campos personalizados del checkout
     */
    public function save_custom_checkout_fields($order_id) {
        $order = wc_get_order($order_id);
        
        // Guardar provincia de shipping_state (campo estándar de WooCommerce ya lo guarda, pero lo registramos también)
        if (!empty($_POST['shipping_state'])) {
            // WooCommerce ya guarda esto, pero lo duplicamos para nuestro uso
            $order->update_meta_data('_shipping_tramaco_provincia', sanitize_text_field($_POST['shipping_state']));
        }
        
        // Guardar cantón y parroquia de shipping
        if (!empty($_POST['shipping_tramaco_canton'])) {
            $order->update_meta_data('_shipping_tramaco_canton', sanitize_text_field($_POST['shipping_tramaco_canton']));
        }
        if (!empty($_POST['shipping_tramaco_parroquia'])) {
            $order->update_meta_data('_shipping_tramaco_parroquia', sanitize_text_field($_POST['shipping_tramaco_parroquia']));
        }
        
        // Guardar provincia de billing_state
        if (!empty($_POST['billing_state'])) {
            $order->update_meta_data('_billing_tramaco_provincia', sanitize_text_field($_POST['billing_state']));
        }
        
        // Guardar cantón y parroquia de billing
        if (!empty($_POST['billing_tramaco_canton'])) {
            $order->update_meta_data('_billing_tramaco_canton', sanitize_text_field($_POST['billing_tramaco_canton']));
        }
        if (!empty($_POST['billing_tramaco_parroquia'])) {
            $order->update_meta_data('_billing_tramaco_parroquia', sanitize_text_field($_POST['billing_tramaco_parroquia']));
        }
        
        $order->save();
    }
    
    /**
     * AJAX: Calcular costo de envío
     */
    public function ajax_calculate_shipping() {
        $parroquia = isset($_POST['parroquia']) ? intval($_POST['parroquia']) : 0;
        $peso = isset($_POST['peso']) ? floatval($_POST['peso']) : 1;
        
        if (!$parroquia) {
            wp_send_json_error(array('message' => 'Parroquia requerida'));
        }
        
        $result = $this->calculate_shipping_cost($parroquia, $peso);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Obtener cantones por provincia
     */
    public function ajax_get_cantones() {
        $provincia_id = isset($_POST['provincia']) ? intval($_POST['provincia']) : 0;
        
        if (!$provincia_id) {
            wp_send_json_error(array('message' => 'Provincia requerida'));
            return;
        }
        
        $ubicaciones = $this->get_ubicaciones_cached();
        
        if (!$ubicaciones || !isset($ubicaciones['lstProvincia'])) {
            wp_send_json_error(array('message' => 'No se pudieron cargar las ubicaciones'));
            return;
        }
        
        // Buscar provincia
        $cantones = array();
        foreach ($ubicaciones['lstProvincia'] as $provincia) {
            if ($provincia['codigo'] == $provincia_id && isset($provincia['lstCanton'])) {
                $cantones = $provincia['lstCanton'];
                break;
            }
        }
        
        wp_send_json_success(array('cantones' => $cantones));
    }
    
    /**
     * AJAX: Obtener parroquias por cantón
     */
    public function ajax_get_parroquias() {
        $canton_id = isset($_POST['canton']) ? intval($_POST['canton']) : 0;
        
        if (!$canton_id) {
            wp_send_json_error(array('message' => 'Cantón requerido'));
            return;
        }
        
        $ubicaciones = $this->get_ubicaciones_cached();
        
        if (!$ubicaciones || !isset($ubicaciones['lstProvincia'])) {
            wp_send_json_error(array('message' => 'No se pudieron cargar las ubicaciones'));
            return;
        }
        
        // Buscar cantón en todas las provincias
        $parroquias = array();
        foreach ($ubicaciones['lstProvincia'] as $provincia) {
            if (isset($provincia['lstCanton'])) {
                foreach ($provincia['lstCanton'] as $canton) {
                    if ($canton['codigo'] == $canton_id && isset($canton['lstParroquia'])) {
                        $parroquias = $canton['lstParroquia'];
                        break 2;
                    }
                }
            }
        }
        
        wp_send_json_success(array('parroquias' => $parroquias));
    }
    
    /**
     * AJAX: Guardar parroquia en sesión de WooCommerce
     */
    public function ajax_save_checkout_parroquia() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $parroquia = isset($_POST['parroquia']) ? intval($_POST['parroquia']) : 0;
        
        if (!$parroquia) {
            wp_send_json_error(array('message' => 'Parroquia inválida'));
            return;
        }
        
        if (!WC()->session) {
            wp_send_json_error(array('message' => 'Sesión de WooCommerce no disponible'));
            return;
        }
        
        // Guardar en sesión
        WC()->session->set('shipping_tramaco_parroquia', $parroquia);
        
        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco] Parroquia guardada en sesión: ' . $parroquia);
        }
        
        wp_send_json_success(array(
            'parroquia' => $parroquia,
            'message' => 'Parroquia guardada correctamente'
        ));
    }
    
    // ============================================================
    // CHECKOUT EN 2 PASOS - FUNCIONES PARA EL CARRITO
    // ============================================================
    
    private $cart_selector_rendered = false;
    
    /**
     * Renderizar selector solo una vez (evitar duplicados)
     */
    public function render_cart_location_selector_once() {
        if ($this->cart_selector_rendered) {
            return;
        }
        $this->render_cart_location_selector();
    }
    
    /**
     * Inyectar selector de ubicación para WooCommerce Blocks (carrito moderno)
     * Se inyecta via JavaScript en el footer
     */
    public function inject_cart_location_for_blocks() {
        // Solo en la página del carrito
        if (!is_cart()) {
            return;
        }
        
        // Verificar si hay productos en el carrito
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }
        
        // Obtener ubicaciones y datos
        $ubicaciones = $this->get_ubicaciones_cached();
        $saved_provincia = WC()->session ? WC()->session->get('tramaco_cart_provincia', '') : '';
        $saved_canton = WC()->session ? WC()->session->get('tramaco_cart_canton', '') : '';
        $saved_parroquia = WC()->session ? WC()->session->get('tramaco_cart_parroquia', '') : '';
        $saved_shipping_cost = WC()->session ? WC()->session->get('tramaco_calculated_shipping', null) : null;
        
        // Generar opciones de provincias
        $provincias_options = '<option value="">' . esc_html__('Seleccione una provincia...', 'tramaco-api') . '</option>';
        if (!empty($ubicaciones) && isset($ubicaciones['lstProvincia'])) {
            foreach ($ubicaciones['lstProvincia'] as $provincia) {
                $selected = ($saved_provincia == $provincia['codigo']) ? ' selected' : '';
                $provincias_options .= '<option value="' . esc_attr($provincia['codigo']) . '"' . $selected . '>' . esc_html($provincia['nombre']) . '</option>';
            }
        }
        
        ?>
        <script type="text/javascript">
            (function() {
                console.log('Tramaco Blocks: Script de inyección cargando...');
                
                // HTML del selector
                var selectorHTML = '<div class="tramaco-cart-location-selector" id="tramaco-cart-location">' +
                    '<h3>📍 <?php echo esc_js(__('Calcular costo de envío', 'tramaco-api')); ?></h3>' +
                    '<p class="description"><?php echo esc_js(__('Selecciona tu ubicación para calcular el costo de envío antes de proceder al pago.', 'tramaco-api')); ?></p>' +
                    '<div class="tramaco-cart-location-fields">' +
                        '<div class="tramaco-cart-field">' +
                            '<label for="tramaco_cart_provincia"><?php echo esc_js(__('Provincia', 'tramaco-api')); ?> <abbr class="required" title="<?php echo esc_js(__('requerido', 'tramaco-api')); ?>">*</abbr></label>' +
                            '<select id="tramaco_cart_provincia" name="tramaco_cart_provincia" class="tramaco-select" required>' +
                                '<?php echo $provincias_options; ?>' +
                            '</select>' +
                        '</div>' +
                        '<div class="tramaco-cart-field">' +
                            '<label for="tramaco_cart_canton"><?php echo esc_js(__('Cantón', 'tramaco-api')); ?> <abbr class="required" title="<?php echo esc_js(__('requerido', 'tramaco-api')); ?>">*</abbr></label>' +
                            '<select id="tramaco_cart_canton" name="tramaco_cart_canton" class="tramaco-select" required disabled>' +
                                '<option value=""><?php echo esc_js(__('Primero seleccione provincia', 'tramaco-api')); ?></option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="tramaco-cart-field">' +
                            '<label for="tramaco_cart_parroquia"><?php echo esc_js(__('Parroquia', 'tramaco-api')); ?> <abbr class="required" title="<?php echo esc_js(__('requerido', 'tramaco-api')); ?>">*</abbr></label>' +
                            '<select id="tramaco_cart_parroquia" name="tramaco_cart_parroquia" class="tramaco-select" required disabled>' +
                                '<option value=""><?php echo esc_js(__('Primero seleccione cantón', 'tramaco-api')); ?></option>' +
                            '</select>' +
                        '</div>' +
                    '</div>' +
                    '<div class="tramaco-cart-shipping-result" id="tramaco-shipping-result" style="display:none;">' +
                        '<div class="tramaco-shipping-calculated">' +
                            '<span class="tramaco-shipping-icon">🚚</span>' +
                            '<span class="tramaco-shipping-label"><?php echo esc_js(__('Costo de envío Tramaco:', 'tramaco-api')); ?></span>' +
                            '<span class="tramaco-shipping-price" id="tramaco-shipping-price"></span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="tramaco-cart-calculating" id="tramaco-calculating" style="display:none;">' +
                        '<span class="spinner"></span><?php echo esc_js(__('Calculando costo de envío...', 'tramaco-api')); ?>' +
                    '</div>' +
                    '<div class="tramaco-cart-error" id="tramaco-cart-error" style="display:none;"></div>' +
                    '<div class="tramaco-cart-warning" id="tramaco-cart-warning">' +
                        '<span class="warning-icon">⚠️</span>' +
                        '<?php echo esc_js(__('Debes seleccionar tu ubicación para calcular el envío antes de continuar al checkout.', 'tramaco-api')); ?>' +
                    '</div>' +
                '</div>';
                
                // Función para insertar el selector
                function insertTramacoSelector() {
                    // Si ya existe, no duplicar
                    if (document.getElementById('tramaco-cart-location')) {
                        console.log('Tramaco Blocks: Selector ya existe');
                        return true;
                    }
                    
                    // Buscar dónde insertar (WooCommerce Blocks)
                    var targets = [
                        '.wp-block-woocommerce-cart-order-summary-block',
                        '.wc-block-cart__sidebar',
                        '.wc-block-components-sidebar',
                        '.wc-block-cart-items',
                        '.wp-block-woocommerce-cart',
                        '.wc-block-cart',
                        '.woocommerce-cart-form',
                        '.cart-collaterals',
                        '.woocommerce'
                    ];
                    
                    var container = null;
                    for (var i = 0; i < targets.length; i++) {
                        container = document.querySelector(targets[i]);
                        if (container) {
                            console.log('Tramaco Blocks: Contenedor encontrado:', targets[i]);
                            break;
                        }
                    }
                    
                    if (container) {
                        var div = document.createElement('div');
                        div.innerHTML = selectorHTML;
                        container.parentNode.insertBefore(div.firstElementChild, container);
                        console.log('Tramaco Blocks: Selector insertado correctamente');
                        
                        // Disparar evento para que el JS lo inicialice
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document).trigger('tramaco_cart_injected');
                        }
                        return true;
                    }
                    
                    console.log('Tramaco Blocks: No se encontró contenedor para insertar');
                    return false;
                }
                
                // Intentar insertar cuando el DOM esté listo
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(insertTramacoSelector, 500);
                    });
                } else {
                    setTimeout(insertTramacoSelector, 500);
                }
                
                // También intentar después de un tiempo (para React/Blocks que cargan después)
                setTimeout(insertTramacoSelector, 1000);
                setTimeout(insertTramacoSelector, 2000);
                setTimeout(insertTramacoSelector, 3000);
            })();
        </script>
        <?php
    }
    
    /**
     * Shortcode para insertar el selector de ubicación manualmente
     * Uso: [tramaco_location_selector]
     */
    public function location_selector_shortcode($atts) {
        ob_start();
        $this->render_cart_location_selector();
        return ob_get_clean();
    }
    
    /**
     * Renderizar el selector de ubicación en la página del carrito
     */
    public function render_cart_location_selector() {
        // Evitar renderizar más de una vez
        if ($this->cart_selector_rendered) {
            return;
        }
        $this->cart_selector_rendered = true;
        // Solo mostrar si hay productos en el carrito
        if (WC()->cart->is_empty()) {
            return;
        }
        
        // Obtener ubicaciones
        $ubicaciones = $this->get_ubicaciones_cached();
        
        // Obtener valores guardados en sesión
        $saved_provincia = WC()->session ? WC()->session->get('tramaco_cart_provincia', '') : '';
        $saved_canton = WC()->session ? WC()->session->get('tramaco_cart_canton', '') : '';
        $saved_parroquia = WC()->session ? WC()->session->get('tramaco_cart_parroquia', '') : '';
        $saved_shipping_cost = WC()->session ? WC()->session->get('tramaco_calculated_shipping', null) : null;
        
        ?>
        <div class="tramaco-cart-location-selector" id="tramaco-cart-location">
            <h3><?php _e('📍 Calcular costo de envío', 'tramaco-api'); ?></h3>
            <p class="description"><?php _e('Selecciona tu ubicación para calcular el costo de envío antes de proceder al pago.', 'tramaco-api'); ?></p>
            
            <div class="tramaco-cart-location-fields">
                <!-- Provincia -->
                <div class="tramaco-cart-field">
                    <label for="tramaco_cart_provincia"><?php _e('Provincia', 'tramaco-api'); ?> <abbr class="required" title="<?php _e('requerido', 'tramaco-api'); ?>">*</abbr></label>
                    <select id="tramaco_cart_provincia" name="tramaco_cart_provincia" class="tramaco-select" required>
                        <option value=""><?php _e('Seleccione una provincia...', 'tramaco-api'); ?></option>
                        <?php if (!empty($ubicaciones) && isset($ubicaciones['lstProvincia'])): ?>
                            <?php foreach ($ubicaciones['lstProvincia'] as $provincia): ?>
                                <option value="<?php echo esc_attr($provincia['codigo']); ?>" <?php selected($saved_provincia, $provincia['codigo']); ?>>
                                    <?php echo esc_html($provincia['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Cantón -->
                <div class="tramaco-cart-field">
                    <label for="tramaco_cart_canton"><?php _e('Cantón', 'tramaco-api'); ?> <abbr class="required" title="<?php _e('requerido', 'tramaco-api'); ?>">*</abbr></label>
                    <select id="tramaco_cart_canton" name="tramaco_cart_canton" class="tramaco-select" required disabled>
                        <option value=""><?php _e('Primero seleccione provincia', 'tramaco-api'); ?></option>
                    </select>
                </div>
                
                <!-- Parroquia -->
                <div class="tramaco-cart-field">
                    <label for="tramaco_cart_parroquia"><?php _e('Parroquia', 'tramaco-api'); ?> <abbr class="required" title="<?php _e('requerido', 'tramaco-api'); ?>">*</abbr></label>
                    <select id="tramaco_cart_parroquia" name="tramaco_cart_parroquia" class="tramaco-select" required disabled>
                        <option value=""><?php _e('Primero seleccione cantón', 'tramaco-api'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Resultado del cálculo -->
            <div class="tramaco-cart-shipping-result" id="tramaco-shipping-result" style="<?php echo $saved_shipping_cost ? '' : 'display:none;'; ?>">
                <?php if ($saved_shipping_cost): ?>
                    <div class="tramaco-shipping-calculated">
                        <span class="tramaco-shipping-icon">🚚</span>
                        <span class="tramaco-shipping-label"><?php _e('Costo de envío Tramaco:', 'tramaco-api'); ?></span>
                        <span class="tramaco-shipping-price" id="tramaco-shipping-price">
                            <?php echo wc_price($saved_shipping_cost); ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="tramaco-shipping-calculated">
                        <span class="tramaco-shipping-icon">🚚</span>
                        <span class="tramaco-shipping-label"><?php _e('Costo de envío Tramaco:', 'tramaco-api'); ?></span>
                        <span class="tramaco-shipping-price" id="tramaco-shipping-price"></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Mensaje de cálculo en proceso -->
            <div class="tramaco-cart-calculating" id="tramaco-calculating" style="display:none;">
                <span class="spinner"></span>
                <?php _e('Calculando costo de envío...', 'tramaco-api'); ?>
            </div>
            
            <!-- Mensaje de error -->
            <div class="tramaco-cart-error" id="tramaco-cart-error" style="display:none;"></div>
            
            <!-- Mensaje de ubicación confirmada (cuando ya se calculó) -->
            <div class="tramaco-location-confirmed" id="tramaco-location-confirmed" <?php echo ($saved_parroquia && $saved_shipping_cost) ? '' : 'style="display:none;"'; ?>>
                <span class="check-icon">✅</span>
                <div class="confirmed-text">
                    <strong><?php _e('¡Envío aplicado correctamente!', 'tramaco-api'); ?></strong>
                    <span><?php _e('Puedes proceder al pago o modificar tu ubicación si lo necesitas.', 'tramaco-api'); ?></span>
                </div>
            </div>
            
            <!-- Mensaje de advertencia si no hay ubicación -->
            <div class="tramaco-cart-warning" id="tramaco-cart-warning" <?php echo ($saved_parroquia && $saved_shipping_cost) ? 'style="display:none;"' : ''; ?>>
                <span class="warning-icon">⚠️</span>
                <?php _e('Debes seleccionar tu ubicación para calcular el envío antes de continuar al checkout.', 'tramaco-api'); ?>
            </div>
        </div>
        
        <script type="text/javascript">
            // Pasar datos de ubicaciones al JavaScript
            var tramacoCartData = {
                ubicaciones: <?php echo json_encode($ubicaciones); ?>,
                savedProvincia: '<?php echo esc_js($saved_provincia); ?>',
                savedCanton: '<?php echo esc_js($saved_canton); ?>',
                savedParroquia: '<?php echo esc_js($saved_parroquia); ?>',
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('tramaco_api_nonce'); ?>',
                i18n: {
                    selectProvincia: '<?php _e('Seleccione una provincia...', 'tramaco-api'); ?>',
                    selectCanton: '<?php _e('Seleccione un cantón...', 'tramaco-api'); ?>',
                    selectParroquia: '<?php _e('Seleccione una parroquia...', 'tramaco-api'); ?>',
                    calculating: '<?php _e('Calculando...', 'tramaco-api'); ?>',
                    error: '<?php _e('Error al calcular el envío', 'tramaco-api'); ?>',
                    firstSelectProvince: '<?php _e('Primero seleccione provincia', 'tramaco-api'); ?>',
                    firstSelectCanton: '<?php _e('Primero seleccione cantón', 'tramaco-api'); ?>'
                }
            };
        </script>
        <?php
    }
    
    /**
     * Validar que se haya seleccionado ubicación antes de ir al checkout
     */
    public function validate_cart_location() {
        // Solo validar en checkout, no en carrito
        if (!is_checkout()) {
            return;
        }
        
        // Verificar si hay parroquia guardada en sesión
        if (!WC()->session) {
            return;
        }
        
        $parroquia = WC()->session->get('tramaco_cart_parroquia', '');
        $parroquia_checkout = WC()->session->get('shipping_tramaco_parroquia', '');
        
        // Si no hay parroquia ni en carrito ni en checkout, mostrar error
        if (empty($parroquia) && empty($parroquia_checkout)) {
            wc_add_notice(
                __('Por favor, selecciona tu ubicación (provincia, cantón y parroquia) en la página del carrito para calcular el costo de envío antes de continuar.', 'tramaco-api'),
                'error'
            );
        }
    }
    
    /**
     * AJAX: Calcular precio de envío desde el carrito
     */
    public function ajax_cart_calculate_shipping() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $parroquia = isset($_POST['parroquia']) ? intval($_POST['parroquia']) : 0;
        
        if (!$parroquia) {
            wp_send_json_error(array('message' => __('Parroquia requerida', 'tramaco-api')));
            return;
        }
        
        // Calcular peso total del carrito
        $peso_total = 0;
        $default_weight = 1; // kg por defecto
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $product_weight = $product->get_weight();
            
            if ($product_weight) {
                $peso_total += wc_get_weight(floatval($product_weight), 'kg') * $cart_item['quantity'];
            } else {
                $peso_total += $default_weight * $cart_item['quantity'];
            }
        }
        
        // Peso mínimo 1kg
        $peso_total = max(1, $peso_total);
        
        // Calcular costo de envío
        $result = $this->calculate_shipping_cost($parroquia, $peso_total);
        
        if ($result['success'] && $result['total'] > 0) {
            // Guardar en sesión para usar en checkout
            WC()->session->set('tramaco_calculated_shipping', $result['total']);
            WC()->session->set('shipping_tramaco_parroquia', $parroquia);
            
            wp_send_json_success(array(
                'total' => $result['total'],
                'total_formatted' => wc_price($result['total']),
                'peso' => $peso_total,
                'message' => __('Costo calculado correctamente', 'tramaco-api')
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($result['message']) ? $result['message'] : __('No se pudo calcular el envío', 'tramaco-api')
            ));
        }
    }
    
    /**
     * AJAX: Guardar ubicación completa en sesión (provincia, cantón, parroquia)
     */
    public function ajax_save_cart_location() {
        check_ajax_referer('tramaco_api_nonce', 'nonce');
        
        $provincia = isset($_POST['provincia']) ? sanitize_text_field($_POST['provincia']) : '';
        $canton = isset($_POST['canton']) ? sanitize_text_field($_POST['canton']) : '';
        $parroquia = isset($_POST['parroquia']) ? sanitize_text_field($_POST['parroquia']) : '';
        
        if (!WC()->session) {
            wp_send_json_error(array('message' => __('Sesión no disponible', 'tramaco-api')));
            return;
        }
        
        // Guardar en sesión
        WC()->session->set('tramaco_cart_provincia', $provincia);
        WC()->session->set('tramaco_cart_canton', $canton);
        WC()->session->set('tramaco_cart_parroquia', $parroquia);
        
        // También guardar para el método de envío
        if ($parroquia) {
            WC()->session->set('shipping_tramaco_parroquia', $parroquia);
        }
        
        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco Cart] Ubicación guardada - Provincia: ' . $provincia . ', Cantón: ' . $canton . ', Parroquia: ' . $parroquia);
        }
        
        wp_send_json_success(array(
            'message' => __('Ubicación guardada correctamente', 'tramaco-api'),
            'provincia' => $provincia,
            'canton' => $canton,
            'parroquia' => $parroquia
        ));
    }
    
    /**
     * Pre-llenar campos del checkout con ubicación guardada en carrito
     */
    public function prefill_checkout_fields($value, $input) {
        if (!WC()->session) {
            return $value;
        }
        
        // Pre-llenar campos de shipping
        switch ($input) {
            case 'shipping_state':
            case 'billing_state':
                $provincia = WC()->session->get('tramaco_cart_provincia', '');
                return $provincia ? $provincia : $value;
                
            case 'shipping_tramaco_canton':
            case 'billing_tramaco_canton':
                $canton = WC()->session->get('tramaco_cart_canton', '');
                return $canton ? $canton : $value;
                
            case 'shipping_tramaco_parroquia':
            case 'billing_tramaco_parroquia':
                $parroquia = WC()->session->get('tramaco_cart_parroquia', '');
                return $parroquia ? $parroquia : $value;
        }
        
        return $value;
    }
    
    // ============================================================
    // FIN - CHECKOUT EN 2 PASOS
    // ============================================================
    
    /**
     * Calcular costo de envío usando API Tramaco
     */
    public function calculate_shipping_cost($parroquia_destino, $peso = 1, $bultos = 1) {
        $token = $this->tramaco_api->get_token();
        
        if (!$token) {
            return array(
                'success' => false,
                'message' => 'Error de autenticación'
            );
        }
        
        $contrato = get_option('tramaco_api_contrato', '6394');
        $localidad = get_option('tramaco_api_localidad', '21580');
        $producto = get_option('tramaco_api_producto', '36');
        $parroquia_origen = get_option('tramaco_api_parroquia_origen', '316'); // Quito por defecto
        
        $data = array(
            'codParroquiaRemit' => $parroquia_origen,
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
                    'codParroquiaDest' => strval($parroquia_destino ?: 908),
                    'id' => '1'
                )
            ),
            'lstServicio' => array()
        );
        
        // Log del request para debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco API] Calcular Precio Request: ' . json_encode($data));
        }
        
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
            $error_msg = $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Tramaco API] Error en request: ' . $error_msg);
            }
            return array(
                'success' => false,
                'message' => $error_msg
            );
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        
        // Log de la respuesta completa para debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco API] Calcular Precio Response: ' . $response_body);
        }
        
        // Verificar que se recibió una respuesta válida
        if (!$body || !is_array($body)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Tramaco API] Respuesta inválida o vacía');
            }
            return array(
                'success' => false,
                'message' => 'Respuesta inválida del servidor'
            );
        }
        
        // Obtener código de respuesta (puede venir como string "1" o int 1)
        $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : (isset($body['codigo']) ? $body['codigo'] : null);
        
        // Normalizar código a string para comparación
        $codigo = strval($codigo);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco API] Código respuesta: ' . $codigo);
        }
        
        if ($codigo === '1') {
            // Buscar lstGuias en la respuesta
            $lstGuias = null;
            if (isset($body['salidaCalcularPrecioGuiaWs']['lstGuias'])) {
                $lstGuias = $body['salidaCalcularPrecioGuiaWs']['lstGuias'];
            } elseif (isset($body['cuerpoRespuesta']['lstGuias'])) {
                $lstGuias = $body['cuerpoRespuesta']['lstGuias'];
            }
            
            $total = 0;
            if ($lstGuias && !empty($lstGuias[0])) {
                $guia = $lstGuias[0];
                
                // Usar el campo 'total' o 'totalCalculado' si está disponible
                if (isset($guia['total'])) {
                    $total = floatval($guia['total']);
                } elseif (isset($guia['totalCalculado'])) {
                    $total = floatval($guia['totalCalculado']);
                } else {
                    // Fallback: calcular sumando subTotal + iva
                    $total = floatval(isset($guia['subTotal']) ? $guia['subTotal'] : 0) + floatval(isset($guia['iva']) ? $guia['iva'] : 0);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Tramaco API] ✅ Costo calculado exitosamente: $' . $total);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Tramaco API] ⚠️ lstGuias vacío o no encontrado');
                }
            }
            
            return array(
                'success' => true,
                'total' => $total,
                'data' => $lstGuias
            );
        }
        
        // Si el código no es 1, obtener mensaje de error
        $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : (isset($body['mensaje']) ? $body['mensaje'] : 'No se pudo calcular el envío');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco API] ❌ Error: ' . $mensaje . ' (código: ' . $codigo . ')');
        }
        
        return array(
            'success' => false,
            'message' => $mensaje,
            'codigo' => $codigo
        );
    }
    
    /**
     * Cuando el pago se completa - Generar guía automáticamente
     */
    public function on_payment_complete($order_id) {
        $this->process_order_guia($order_id, 'payment_complete');
    }
    
    /**
     * Cuando la orden pasa a processing
     */
    public function on_order_processing($order_id) {
        $this->process_order_guia($order_id, 'processing');
    }
    
    /**
     * Cuando la orden se completa
     */
    public function on_order_completed($order_id) {
        // Solo procesar si no se generó antes
        $order = wc_get_order($order_id);
        if (!$order->get_meta('_tramaco_guia_numero')) {
            $this->process_order_guia($order_id, 'completed');
        }
    }
    
    /**
     * Procesar generación de guía para un pedido
     */
    public function process_order_guia($order_id, $trigger = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Verificar si ya tiene guía generada
        $guia_existente = $order->get_meta('_tramaco_guia_numero');
        if ($guia_existente) {
            return true; // Ya tiene guía
        }
        
        // Verificar configuración de cuándo generar
        $auto_generate = get_option('tramaco_wc_auto_generate', 'payment_complete');
        if ($trigger && $trigger !== $auto_generate) {
            return false;
        }
        
        // Obtener datos del pedido
        $order_data = $this->prepare_order_data_for_guia($order);
        
        if (!$order_data) {
            $order->add_order_note(__('Error: No se pudieron preparar los datos para la guía Tramaco.', 'tramaco-api'));
            return false;
        }
        
        // Generar guía
        $result = $this->generate_guia($order_data);
        
        if ($result['success']) {
            // Guardar número de guía en el pedido
            $order->update_meta_data('_tramaco_guia_numero', $result['guia']);
            $order->update_meta_data('_tramaco_guia_data', json_encode($result['data']));
            $order->update_meta_data('_tramaco_guia_fecha', current_time('mysql'));
            
            // Generar y guardar PDF
            $pdf_result = $this->generate_and_save_pdf($order, $result['guia']);
            
            if ($pdf_result['success']) {
                $order->update_meta_data('_tramaco_guia_pdf_url', $pdf_result['url']);
                $order->update_meta_data('_tramaco_guia_pdf_path', $pdf_result['path']);
            }
            
            $order->save();
            
            // Agregar nota al pedido
            $order->add_order_note(sprintf(
                __('Guía Tramaco generada exitosamente: %s', 'tramaco-api'),
                $result['guia']
            ));
            
            // Enviar a SharePoint si está configurado
            if (get_option('tramaco_sharepoint_enabled', false)) {
                $this->send_to_sharepoint($order, $result);
            }
            
            // Disparar acción para extensiones
            do_action('tramaco_guia_generated', $order_id, $result);
            
            return true;
        } else {
            $order->add_order_note(sprintf(
                __('Error al generar guía Tramaco: %s', 'tramaco-api'),
                $result['message']
            ));
            return false;
        }
    }
    
    /**
     * Preparar datos del pedido para generar guía
     */
    private function prepare_order_data_for_guia($order) {
        // Obtener parroquia de envío
        $parroquia = $order->get_meta('_shipping_tramaco_parroquia');
        if (!$parroquia) {
            $parroquia = $order->get_meta('_billing_tramaco_parroquia');
        }
        
        if (!$parroquia) {
            return false;
        }
        
        // Calcular peso total
        $peso_total = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_weight()) {
                $peso_total += floatval($product->get_weight()) * $item->get_quantity();
            }
        }
        
        // Peso mínimo de 1kg
        if ($peso_total < 1) {
            $peso_total = 1;
        }
        
        // Descripción de productos
        $productos = array();
        foreach ($order->get_items() as $item) {
            $productos[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        $descripcion = implode(', ', $productos);
        
        // Preparar datos del destinatario
        $nombres = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
        $apellidos = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
        $telefono = $order->get_billing_phone();
        $email = $order->get_billing_email();
        $direccion = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $direccion2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
        
        // Cédula/RUC (puede estar en meta o en campo de empresa)
        $ci_ruc = $order->get_meta('_billing_ci_ruc');
        if (!$ci_ruc) {
            $ci_ruc = $order->get_meta('_billing_cedula');
        }
        if (!$ci_ruc) {
            $ci_ruc = '9999999999'; // Consumidor final
        }
        
        return array(
            'destinatario' => array(
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'ciRuc' => $ci_ruc,
                'tipoIden' => strlen($ci_ruc) == 13 ? '04' : '05', // RUC o Cédula
                'telefono' => $telefono,
                'email' => $email,
                'codigoParroquia' => intval($parroquia),
                'callePrimaria' => $direccion,
                'calleSecundaria' => $direccion2,
                'numero' => '',
                'referencia' => $order->get_customer_note() ?: '',
                'codigoPostal' => $order->get_shipping_postcode() ?: ''
            ),
            'carga' => array(
                'peso' => $peso_total,
                'cajas' => '1',
                'bultos' => '',
                'descripcion' => substr($descripcion, 0, 200),
                'valorCobro' => 0, // Ya pagado online
                'valorAsegurado' => floatval($order->get_total()),
                'observacion' => 'Pedido #' . $order->get_order_number()
            ),
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number()
        );
    }
    
    /**
     * Generar guía en Tramaco
     */
    private function generate_guia($order_data) {
        $token = $this->tramaco_api->get_token();
        
        if (!$token) {
            return array(
                'success' => false,
                'message' => 'Error de autenticación con Tramaco'
            );
        }
        
        $contrato = get_option('tramaco_api_contrato', '6394');
        $localidad = get_option('tramaco_api_localidad', '21580');
        $producto = get_option('tramaco_api_producto', '36');
        
        // Datos del remitente desde configuración
        $remitente_nombre = get_option('tramaco_remitente_nombre', 'TIENDA ONLINE');
        $remitente_apellido = get_option('tramaco_remitente_apellido', '');
        $remitente_ci = get_option('tramaco_api_login', '1793191845001');
        $remitente_telefono = get_option('tramaco_remitente_telefono', '0987654321');
        $remitente_email = get_option('tramaco_remitente_email', '');
        $remitente_direccion = get_option('tramaco_remitente_direccion', 'Dirección del remitente');
        $remitente_parroquia = get_option('tramaco_api_parroquia_origen', '316');
        
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
                        'codigoPostal' => $order_data['destinatario']['codigoPostal'],
                        'nombres' => $order_data['destinatario']['nombres'],
                        'codigoParroquia' => $order_data['destinatario']['codigoParroquia'],
                        'email' => $order_data['destinatario']['email'] ?: ' ',
                        'apellidos' => $order_data['destinatario']['apellidos'],
                        'callePrimaria' => $order_data['destinatario']['callePrimaria'],
                        'telefono' => $order_data['destinatario']['telefono'],
                        'calleSecundaria' => $order_data['destinatario']['calleSecundaria'] ?: '',
                        'tipoIden' => $order_data['destinatario']['tipoIden'],
                        'referencia' => $order_data['destinatario']['referencia'],
                        'ciRuc' => $order_data['destinatario']['ciRuc'],
                        'numero' => $order_data['destinatario']['numero'] ?: ' '
                    ),
                    'carga' => array(
                        'localidad' => intval($localidad),
                        'adjuntos' => '',
                        'referenciaTercero' => 'Pedido #' . $order_data['order_number'],
                        'largo' => 0,
                        'descripcion' => $order_data['carga']['descripcion'],
                        'valorCobro' => floatval($order_data['carga']['valorCobro']),
                        'valorAsegurado' => floatval($order_data['carga']['valorAsegurado']),
                        'contrato' => intval($contrato),
                        'peso' => floatval($order_data['carga']['peso']),
                        'observacion' => $order_data['carga']['observacion'],
                        'producto' => $producto,
                        'ancho' => '',
                        'bultos' => $order_data['carga']['bultos'] ?: '',
                        'cajas' => $order_data['carga']['cajas'] ?: '1',
                        'cantidadDoc' => '',
                        'alto' => ''
                    )
                )
            ),
            'remitente' => array(
                'codigoPostal' => '',
                'nombres' => $remitente_nombre,
                'codigoParroquia' => intval($remitente_parroquia),
                'email' => $remitente_email ?: ' ',
                'apellidos' => $remitente_apellido,
                'callePrimaria' => $remitente_direccion,
                'telefono' => $remitente_telefono,
                'calleSecundaria' => '',
                'tipoIden' => strlen($remitente_ci) == 13 ? '04' : '06',
                'referencia' => '',
                'ciRuc' => $remitente_ci,
                'numero' => ''
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
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Verificar respuesta
        $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : (isset($body['codigo']) ? $body['codigo'] : null);
        
        if ($codigo == 1) {
            // Obtener número de guía
            $guia_numero = null;
            if (isset($body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'])) {
                $guia_numero = $body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'];
            } elseif (isset($body['lstGuias'][0]['guia'])) {
                $guia_numero = $body['lstGuias'][0]['guia'];
            }
            
            return array(
                'success' => true,
                'guia' => $guia_numero,
                'data' => $body
            );
        }
        
        $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : (isset($body['mensaje']) ? $body['mensaje'] : 'Error desconocido');
        
        return array(
            'success' => false,
            'message' => $mensaje,
            'data' => $body
        );
    }
    
    /**
     * Generar y guardar PDF de la guía
     */
    private function generate_and_save_pdf($order, $guia_numero) {
        $token = $this->tramaco_api->get_token();
        
        if (!$token) {
            return array('success' => false, 'message' => 'Error de autenticación');
        }
        
        $response = wp_remote_post(TRAMACO_API_GENERAR_PDF_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode(array(
                'guias' => array($guia_numero)
            )),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : (isset($body['codigo']) ? $body['codigo'] : null);
        
        if ($codigo == 1) {
            // El PDF viene en base64
            $pdf_base64 = null;
            if (isset($body['salidaGenerarPdfWs']['pdf'])) {
                $pdf_base64 = $body['salidaGenerarPdfWs']['pdf'];
            } elseif (isset($body['pdf'])) {
                $pdf_base64 = $body['pdf'];
            }
            
            if ($pdf_base64) {
                // Decodificar y guardar
                $pdf_content = base64_decode($pdf_base64);
                
                // Crear directorio si no existe
                $upload_dir = wp_upload_dir();
                $tramaco_dir = $upload_dir['basedir'] . '/tramaco-guias/' . date('Y') . '/' . date('m');
                
                if (!file_exists($tramaco_dir)) {
                    wp_mkdir_p($tramaco_dir);
                }
                
                // Guardar archivo
                $filename = 'guia-' . $guia_numero . '-order-' . $order->get_id() . '.pdf';
                $filepath = $tramaco_dir . '/' . $filename;
                
                file_put_contents($filepath, $pdf_content);
                
                // URL del archivo
                $file_url = $upload_dir['baseurl'] . '/tramaco-guias/' . date('Y') . '/' . date('m') . '/' . $filename;
                
                return array(
                    'success' => true,
                    'path' => $filepath,
                    'url' => $file_url
                );
            }
        }
        
        return array('success' => false, 'message' => 'No se pudo obtener el PDF');
    }
    
    /**
     * Enviar datos a SharePoint
     */
    private function send_to_sharepoint($order, $guia_result) {
        $sharepoint_handler = new Tramaco_SharePoint_Handler();
        return $sharepoint_handler->add_guia_to_excel($order, $guia_result);
    }
    
    /**
     * Meta box en admin para ver datos de guía
     */
    public function add_order_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        
        add_meta_box(
            'tramaco_guia_metabox',
            __('Guía Tramaco', 'tramaco-api'),
            array($this, 'render_order_meta_box'),
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar meta box
     */
    public function render_order_meta_box($post_or_order) {
        $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : $post_or_order;
        
        if (!$order) {
            return;
        }
        
        $guia_numero = $order->get_meta('_tramaco_guia_numero');
        $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
        $guia_fecha = $order->get_meta('_tramaco_guia_fecha');
        
        ?>
        <div class="tramaco-order-metabox">
            <?php if ($guia_numero): ?>
                <p>
                    <strong><?php _e('Número de Guía:', 'tramaco-api'); ?></strong><br>
                    <code style="font-size: 14px;"><?php echo esc_html($guia_numero); ?></code>
                </p>
                
                <?php if ($guia_fecha): ?>
                <p>
                    <strong><?php _e('Fecha:', 'tramaco-api'); ?></strong><br>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($guia_fecha))); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($pdf_url): ?>
                <p>
                    <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button button-primary">
                        <?php _e('Descargar PDF', 'tramaco-api'); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=tramaco-api-tracking&guia=' . $guia_numero)); ?>" class="button">
                        <?php _e('Ver Tracking', 'tramaco-api'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><?php _e('No se ha generado guía para este pedido.', 'tramaco-api'); ?></p>
                
                <button type="button" class="button button-primary tramaco-generate-guia" data-order-id="<?php echo $order->get_id(); ?>">
                    <?php _e('Generar Guía Ahora', 'tramaco-api'); ?>
                </button>
                
                <script>
                jQuery(document).ready(function($) {
                    $('.tramaco-generate-guia').on('click', function() {
                        var btn = $(this);
                        var orderId = btn.data('order-id');
                        
                        btn.prop('disabled', true).text('<?php _e('Generando...', 'tramaco-api'); ?>');
                        
                        $.post(ajaxurl, {
                            action: 'tramaco_generate_order_guia',
                            order_id: orderId,
                            nonce: '<?php echo wp_create_nonce('tramaco_generate_guia'); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php _e('Error al generar guía', 'tramaco-api'); ?>');
                                btn.prop('disabled', false).text('<?php _e('Generar Guía Ahora', 'tramaco-api'); ?>');
                            }
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Generar guía manualmente para un pedido
     */
    public function ajax_generate_order_guia() {
        check_ajax_referer('tramaco_generate_guia', 'nonce');
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'ID de pedido inválido'));
        }
        
        $result = $this->process_order_guia($order_id, '');
        
        if ($result) {
            $order = wc_get_order($order_id);
            wp_send_json_success(array(
                'message' => 'Guía generada exitosamente',
                'guia' => $order->get_meta('_tramaco_guia_numero')
            ));
        } else {
            wp_send_json_error(array('message' => 'Error al generar la guía'));
        }
    }
    
    /**
     * Columna de guía en lista de pedidos
     */
    public function add_order_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_status') {
                $new_columns['tramaco_guia'] = __('Guía Tramaco', 'tramaco-api');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Renderizar columna de guía (legacy)
     */
    public function render_order_column($column, $post_id) {
        if ($column === 'tramaco_guia') {
            $order = wc_get_order($post_id);
            $this->output_guia_column_content($order);
        }
    }
    
    /**
     * Renderizar columna de guía (HPOS)
     */
    public function render_order_column_hpos($column, $order) {
        if ($column === 'tramaco_guia') {
            $this->output_guia_column_content($order);
        }
    }
    
    /**
     * Output del contenido de la columna
     */
    private function output_guia_column_content($order) {
        if (!$order) {
            echo '—';
            return;
        }
        
        $guia = $order->get_meta('_tramaco_guia_numero');
        
        if ($guia) {
            $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
            echo '<code>' . esc_html($guia) . '</code>';
            if ($pdf_url) {
                echo ' <a href="' . esc_url($pdf_url) . '" target="_blank" title="' . __('Descargar PDF', 'tramaco-api') . '">📄</a>';
            }
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }
    
    /**
     * Enqueue scripts para checkout y carrito
     */
    public function enqueue_checkout_scripts() {
        // Cargar en checkout Y en carrito para el checkout en 2 pasos
        if (!is_checkout() && !is_cart()) {
            return;
        }
        
        // Dependencias base
        $dependencies = array('jquery');
        
        // Agregar dependencias específicas según la página
        if (is_checkout()) {
            $dependencies[] = 'wc-checkout';
        }
        if (is_cart()) {
            $dependencies[] = 'wc-cart';
        }
        
        wp_enqueue_script(
            'tramaco-checkout',
            TRAMACO_API_PLUGIN_URL . 'assets/js/tramaco-checkout.js',
            $dependencies,
            TRAMACO_API_VERSION,
            true
        );
        
        // Datos comunes
        $ubicaciones = $this->get_ubicaciones_cached();
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('tramaco_api_nonce');
        
        // Localizar script para checkout
        wp_localize_script('tramaco-checkout', 'tramacoCheckout', array(
            'ajaxUrl' => $ajax_url,
            'nonce' => $nonce,
            'ubicaciones' => $ubicaciones,
            'isCart' => is_cart(),
            'isCheckout' => is_checkout(),
            'i18n' => array(
                'selectProvincia' => __('Seleccione una provincia...', 'tramaco-api'),
                'selectCanton' => __('Seleccione un cantón...', 'tramaco-api'),
                'selectParroquia' => __('Seleccione una parroquia...', 'tramaco-api'),
                'loading' => __('Cargando...', 'tramaco-api')
            )
        ));
        
        // También pasar datos para el carrito (tramacoCartData)
        if (is_cart()) {
            $saved_provincia = WC()->session ? WC()->session->get('tramaco_cart_provincia', '') : '';
            $saved_canton = WC()->session ? WC()->session->get('tramaco_cart_canton', '') : '';
            $saved_parroquia = WC()->session ? WC()->session->get('tramaco_cart_parroquia', '') : '';
            $saved_shipping_cost = WC()->session ? WC()->session->get('tramaco_calculated_shipping', null) : null;
            
            wp_localize_script('tramaco-checkout', 'tramacoCartData', array(
                'ubicaciones' => $ubicaciones,
                'savedProvincia' => $saved_provincia,
                'savedCanton' => $saved_canton,
                'savedParroquia' => $saved_parroquia,
                'savedShippingCost' => $saved_shipping_cost,
                'ajaxUrl' => $ajax_url,
                'nonce' => $nonce,
                'i18n' => array(
                    'selectProvincia' => __('Seleccione una provincia...', 'tramaco-api'),
                    'selectCanton' => __('Seleccione un cantón...', 'tramaco-api'),
                    'selectParroquia' => __('Seleccione una parroquia...', 'tramaco-api'),
                    'calculating' => __('Calculando...', 'tramaco-api'),
                    'error' => __('Error al calcular el envío', 'tramaco-api'),
                    'firstSelectProvince' => __('Primero seleccione provincia', 'tramaco-api'),
                    'firstSelectCanton' => __('Primero seleccione cantón', 'tramaco-api')
                )
            ));
        }
        
        wp_enqueue_style(
            'tramaco-checkout',
            TRAMACO_API_PLUGIN_URL . 'assets/css/tramaco-checkout.css',
            array(),
            TRAMACO_API_VERSION
        );
    }
}

// Inicializar
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Tramaco_WooCommerce_Integration::get_instance();
    }
}, 20);
