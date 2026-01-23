<?php
/**
 * Método de envío Tramaco para WooCommerce
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Método de envío personalizado que usa la API de Tramaco
 */
class Tramaco_Shipping_Method extends WC_Shipping_Method {
    
    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id = 'tramaco_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Envío Tramaco', 'tramaco-api');
        $this->method_description = __('Envío a través de TRAMACOEXPRESS con cálculo automático de tarifas.', 'tramaco-api');
        
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal'
        );
        
        $this->init();
    }
    
    /**
     * Inicializar configuraciones
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', __('Envío Tramaco', 'tramaco-api'));
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->fee_type = $this->get_option('fee_type', 'none');
        $this->fee = $this->get_option('fee', 0);
        $this->free_shipping_min = $this->get_option('free_shipping_min', 0);
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Campos de configuración
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Título', 'tramaco-api'),
                'type' => 'text',
                'description' => __('Título que el cliente verá en el checkout.', 'tramaco-api'),
                'default' => __('Envío Tramaco', 'tramaco-api'),
                'desc_tip' => true
            ),
            'fee_type' => array(
                'title' => __('Tipo de tarifa adicional', 'tramaco-api'),
                'type' => 'select',
                'description' => __('Agrega una tarifa adicional al costo calculado por Tramaco.', 'tramaco-api'),
                'default' => 'none',
                'options' => array(
                    'none' => __('Sin tarifa adicional', 'tramaco-api'),
                    'fixed' => __('Tarifa fija', 'tramaco-api'),
                    'percent' => __('Porcentaje del subtotal', 'tramaco-api')
                ),
                'desc_tip' => true
            ),
            'fee' => array(
                'title' => __('Tarifa adicional', 'tramaco-api'),
                'type' => 'number',
                'description' => __('Valor de la tarifa adicional ($ o %).', 'tramaco-api'),
                'default' => 0,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'free_shipping_min' => array(
                'title' => __('Envío gratis desde', 'tramaco-api'),
                'type' => 'number',
                'description' => __('Monto mínimo del carrito para envío gratis. Dejar en 0 para desactivar.', 'tramaco-api'),
                'default' => 0,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'default_weight' => array(
                'title' => __('Peso por defecto (kg)', 'tramaco-api'),
                'type' => 'number',
                'description' => __('Peso a usar cuando los productos no tienen peso configurado.', 'tramaco-api'),
                'default' => 1,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0.1'
                ),
                'desc_tip' => true
            ),
            'fallback_cost' => array(
                'title' => __('Costo de respaldo', 'tramaco-api'),
                'type' => 'number',
                'description' => __('Costo a mostrar si falla el cálculo con la API.', 'tramaco-api'),
                'default' => 5,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                ),
                'desc_tip' => true
            )
        );
    }
    
    /**
     * Calcular costo de envío
     */
    public function calculate_shipping($package = array()) {
        // Log inicial
        $this->log_debug('==========================================');
        $this->log_debug('INICIO calculate_shipping()');
        
        // Verificar si hay envío gratis
        $cart_total = WC()->cart->get_subtotal();
        $free_min = floatval($this->get_option('free_shipping_min', 0));
        
        $this->log_debug('Cart total: $' . $cart_total . ', Free shipping min: $' . $free_min);
        
        if ($free_min > 0 && $cart_total >= $free_min) {
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $this->title . ' - ' . __('¡Gratis!', 'tramaco-api'),
                'cost' => 0,
                'package' => $package,
                'meta_data' => array(
                    'tramaco_free_shipping' => 'yes'
                )
            );
            
            $this->log_debug('Envío gratis aplicado');
            $this->add_rate($rate);
            return;
        }
        
        // Obtener parroquia de destino
        $parroquia = $this->get_destination_parroquia($package);
        $this->log_debug('Parroquia obtenida: ' . ($parroquia ? $parroquia : 'NULL'));
        
        if (!$parroquia) {
            // Si no hay parroquia, mostrar costo por defecto
            $this->log_debug('⚠️ No hay parroquia - usando fallback');
            $this->add_fallback_rate($package);
            return;
        }
        
        // Calcular peso total
        $peso = $this->calculate_package_weight($package);
        $this->log_debug('Peso calculado: ' . $peso . ' kg');
        
        // Obtener costo de Tramaco
        $this->log_debug('Llamando a API de Tramaco...');
        $wc_integration = Tramaco_WooCommerce_Integration::get_instance();
        $result = $wc_integration->calculate_shipping_cost($parroquia, $peso);
        
        $this->log_debug('Resultado API - Success: ' . ($result['success'] ? 'SÍ' : 'NO'));
        $this->log_debug('Resultado API - Total: $' . (isset($result['total']) ? $result['total'] : 0));
        
        if (!$result['success']) {
            $this->log_debug('Resultado API - Error: ' . (isset($result['message']) ? $result['message'] : 'Sin mensaje'));
        }
        
        if ($result['success'] && $result['total'] > 0) {
            $cost = $result['total'];
            
            // Aplicar tarifa adicional
            $cost = $this->apply_additional_fee($cost, $cart_total);
            
            $this->log_debug('✅ Costo final calculado: $' . $cost);
            
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $cost,
                'package' => $package,
                'meta_data' => array(
                    'tramaco_parroquia' => $parroquia,
                    'tramaco_peso' => $peso,
                    'tramaco_original_cost' => $result['total']
                )
            );
            
            $this->add_rate($rate);
        } else {
            // Fallback si falla el cálculo
            $this->log_debug('⚠️ API falló - usando fallback');
            $this->add_fallback_rate($package);
        }
        
        $this->log_debug('FIN calculate_shipping()');
        $this->log_debug('==========================================');
    }
    
    /**
     * Obtener parroquia de destino
     */
    private function get_destination_parroquia($package) {
        $parroquia = null;
        
        $this->log_debug('--- Buscando parroquia ---');
        
        // 1. Intentar desde POST data (cuando se envía el formulario)
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            $parroquia = isset($post_data['shipping_tramaco_parroquia']) ? intval($post_data['shipping_tramaco_parroquia']) : null;
            
            if ($parroquia) {
                $this->log_debug('✓ Encontrada en post_data[shipping_tramaco_parroquia]: ' . $parroquia);
            }
            
            if (!$parroquia) {
                $parroquia = isset($post_data['billing_tramaco_parroquia']) ? intval($post_data['billing_tramaco_parroquia']) : null;
                if ($parroquia) {
                    $this->log_debug('✓ Encontrada en post_data[billing_tramaco_parroquia]: ' . $parroquia);
                }
            }
        }
        
        // 2. Intentar desde POST directo
        if (!$parroquia && isset($_POST['shipping_tramaco_parroquia'])) {
            $parroquia = intval($_POST['shipping_tramaco_parroquia']);
            $this->log_debug('✓ Encontrada en POST[shipping_tramaco_parroquia]: ' . $parroquia);
        }
        
        if (!$parroquia && isset($_POST['billing_tramaco_parroquia'])) {
            $parroquia = intval($_POST['billing_tramaco_parroquia']);
            $this->log_debug('✓ Encontrada en POST[billing_tramaco_parroquia]: ' . $parroquia);
        }
        
        // 3. Intentar desde la sesión de WooCommerce
        if (!$parroquia && WC()->session) {
            $session_parroquia = WC()->session->get('shipping_tramaco_parroquia');
            if ($session_parroquia) {
                $parroquia = intval($session_parroquia);
                $this->log_debug('✓ Encontrada en sesión WC: ' . $parroquia);
            } else {
                $this->log_debug('✗ No encontrada en sesión WC');
            }
        } else if (!WC()->session) {
            $this->log_debug('⚠️ Sesión WC no disponible');
        }
        
        // 4. Intentar desde el paquete de destino
        if (!$parroquia && isset($package['destination']['tramaco_parroquia'])) {
            $parroquia = intval($package['destination']['tramaco_parroquia']);
            $this->log_debug('✓ Encontrada en package[destination]: ' . $parroquia);
        }
        
        if (!$parroquia) {
            $this->log_debug('✗ Parroquia no encontrada en ninguna fuente');
        }
        
        return $parroquia ? intval($parroquia) : null;
    }
    
    /**
     * Calcular peso del paquete
     */
    private function calculate_package_weight($package) {
        $peso = 0;
        $default_weight = floatval($this->get_option('default_weight', 1));
        
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $product_weight = $product->get_weight();
            
            if ($product_weight) {
                // Convertir a kg si es necesario
                $peso += wc_get_weight(floatval($product_weight), 'kg') * $item['quantity'];
            } else {
                $peso += $default_weight * $item['quantity'];
            }
        }
        
        // Peso mínimo de 1kg
        return max(1, $peso);
    }
    
    /**
     * Aplicar tarifa adicional
     */
    private function apply_additional_fee($cost, $cart_total) {
        $fee_type = $this->get_option('fee_type', 'none');
        $fee = floatval($this->get_option('fee', 0));
        
        if ($fee_type === 'fixed' && $fee > 0) {
            $cost += $fee;
        } elseif ($fee_type === 'percent' && $fee > 0) {
            $cost += ($cart_total * $fee / 100);
        }
        
        return round($cost, 2);
    }
    
    /**
     * Agregar tarifa de respaldo
     */
    private function add_fallback_rate($package) {
        $fallback_cost = floatval($this->get_option('fallback_cost', 5));
        
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title . ' ' . __('(Estimado)', 'tramaco-api'),
            'cost' => $fallback_cost,
            'package' => $package,
            'meta_data' => array(
                'tramaco_fallback' => 'yes'
            )
        );
        
        $this->add_rate($rate);
    }
    
    /**
     * Es este método disponible?
     */
    public function is_available($package) {
        $is_available = $this->enabled === 'yes';
        
        // Verificar si el país es Ecuador
        if ($is_available && !empty($package['destination']['country'])) {
            $is_available = $package['destination']['country'] === 'EC';
        }
        
        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
    }
    
    /**
     * Log de debug para diagnóstico
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tramaco Shipping] ' . $message);
        }
    }
}
