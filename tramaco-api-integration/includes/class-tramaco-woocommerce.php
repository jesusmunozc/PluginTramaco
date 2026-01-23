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
        
        // AJAX para actualizar costos de envío
        add_action('wp_ajax_tramaco_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        add_action('wp_ajax_nopriv_tramaco_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        
        // AJAX para guardar parroquia en sesión
        add_action('wp_ajax_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        add_action('wp_ajax_nopriv_tramaco_save_checkout_parroquia', array($this, 'ajax_save_checkout_parroquia'));
        
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
        
        // Agregar campos de parroquia después de la ciudad
        $fields['shipping']['shipping_tramaco_provincia'] = array(
            'type' => 'select',
            'label' => __('Provincia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 45,
            'options' => $this->get_provincias_options($ubicaciones)
        );
        
        $fields['shipping']['shipping_tramaco_canton'] = array(
            'type' => 'select',
            'label' => __('Cantón', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 46,
            'options' => array('' => __('Seleccione provincia primero...', 'tramaco-api'))
        );
        
        $fields['shipping']['shipping_tramaco_parroquia'] = array(
            'type' => 'select',
            'label' => __('Parroquia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 47,
            'options' => array('' => __('Seleccione cantón primero...', 'tramaco-api'))
        );
        
        // Campos para billing también
        $fields['billing']['billing_tramaco_provincia'] = array(
            'type' => 'select',
            'label' => __('Provincia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 45,
            'options' => $this->get_provincias_options($ubicaciones)
        );
        
        $fields['billing']['billing_tramaco_canton'] = array(
            'type' => 'select',
            'label' => __('Cantón', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 46,
            'options' => array('' => __('Seleccione provincia primero...', 'tramaco-api'))
        );
        
        $fields['billing']['billing_tramaco_parroquia'] = array(
            'type' => 'select',
            'label' => __('Parroquia', 'tramaco-api'),
            'required' => true,
            'class' => array('form-row-wide', 'tramaco-ubicacion'),
            'priority' => 47,
            'options' => array('' => __('Seleccione cantón primero...', 'tramaco-api'))
        );
        
        return $fields;
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
                    $ubicaciones = json_decode(wp_remote_retrieve_body($response), true);
                    set_transient('tramaco_ubicaciones', $ubicaciones, DAY_IN_SECONDS);
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
        
        // Guardar datos de ubicación de shipping
        if (!empty($_POST['shipping_tramaco_provincia'])) {
            $order->update_meta_data('_shipping_tramaco_provincia', sanitize_text_field($_POST['shipping_tramaco_provincia']));
        }
        if (!empty($_POST['shipping_tramaco_canton'])) {
            $order->update_meta_data('_shipping_tramaco_canton', sanitize_text_field($_POST['shipping_tramaco_canton']));
        }
        if (!empty($_POST['shipping_tramaco_parroquia'])) {
            $order->update_meta_data('_shipping_tramaco_parroquia', sanitize_text_field($_POST['shipping_tramaco_parroquia']));
        }
        
        // Guardar datos de ubicación de billing
        if (!empty($_POST['billing_tramaco_provincia'])) {
            $order->update_meta_data('_billing_tramaco_provincia', sanitize_text_field($_POST['billing_tramaco_provincia']));
        }
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
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : (isset($body['codigo']) ? $body['codigo'] : null);
        
        if ($codigo == 1) {
            $lstGuias = null;
            if (isset($body['salidaCalcularPrecioGuiaWs']['lstGuias'])) {
                $lstGuias = $body['salidaCalcularPrecioGuiaWs']['lstGuias'];
            } elseif (isset($body['cuerpoRespuesta']['lstGuias'])) {
                $lstGuias = $body['cuerpoRespuesta']['lstGuias'];
            }
            
            $total = 0;
            if ($lstGuias && !empty($lstGuias[0])) {
                $guia = $lstGuias[0];
                $total = floatval(isset($guia['subTotal']) ? $guia['subTotal'] : 0) + floatval(isset($guia['iva']) ? $guia['iva'] : 0);
            }
            
            return array(
                'success' => true,
                'total' => $total,
                'data' => $lstGuias
            );
        }
        
        return array(
            'success' => false,
            'message' => 'No se pudo calcular el envío'
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
     * Enqueue scripts para checkout
     */
    public function enqueue_checkout_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        wp_enqueue_script(
            'tramaco-checkout',
            TRAMACO_API_PLUGIN_URL . 'assets/js/tramaco-checkout.js',
            array('jquery', 'wc-checkout'),
            TRAMACO_API_VERSION,
            true
        );
        
        wp_localize_script('tramaco-checkout', 'tramacoCheckout', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tramaco_api_nonce'),
            'ubicaciones' => $this->get_ubicaciones_cached(),
            'i18n' => array(
                'selectProvincia' => __('Seleccione una provincia...', 'tramaco-api'),
                'selectCanton' => __('Seleccione un cantón...', 'tramaco-api'),
                'selectParroquia' => __('Seleccione una parroquia...', 'tramaco-api'),
                'loading' => __('Cargando...', 'tramaco-api')
            )
        ));
        
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
