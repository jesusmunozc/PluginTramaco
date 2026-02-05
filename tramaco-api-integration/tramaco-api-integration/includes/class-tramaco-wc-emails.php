<?php
/**
 * Emails de Tramaco para WooCommerce
 * 
 * Maneja las notificaciones por email relacionadas con las guías
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de emails
 */
class Tramaco_WC_Emails {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook cuando se genera una guía
        add_action('tramaco_guia_generated', array($this, 'send_guia_email'), 10, 2);
        
        // Agregar info de guía a emails de WooCommerce
        add_action('woocommerce_email_order_meta', array($this, 'add_guia_to_emails'), 20, 3);
        
        // Agregar tracking link al email de completado
        add_action('woocommerce_email_after_order_table', array($this, 'add_tracking_to_email'), 10, 4);
    }
    
    /**
     * Enviar email cuando se genera una guía
     */
    public function send_guia_email($order_id, $guia_result) {
        if (!get_option('tramaco_notify_customer', '1')) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $guia_numero = $guia_result['guia'];
        $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
        $pdf_path = $order->get_meta('_tramaco_guia_pdf_path');
        
        // Datos para el email
        $to = $order->get_billing_email();
        $subject = sprintf(
            __('[%s] Tu guía de envío ha sido generada - Pedido #%s', 'tramaco-api'),
            get_bloginfo('name'),
            $order->get_order_number()
        );
        
        // Contenido del email
        $message = $this->get_email_content($order, $guia_numero, $pdf_url);
        
        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Adjuntos
        $attachments = array();
        if (get_option('tramaco_attach_pdf_email', '1') && $pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
        
        // Enviar email
        wp_mail($to, $subject, $message, $headers, $attachments);
        
        // Notificar al admin si está configurado
        if (get_option('tramaco_notify_admin', '')) {
            $admin_email = get_option('admin_email');
            $admin_subject = sprintf(
                __('[%s] Nueva guía generada - Pedido #%s', 'tramaco-api'),
                get_bloginfo('name'),
                $order->get_order_number()
            );
            
            wp_mail($admin_email, $admin_subject, $message, $headers);
        }
    }
    
    /**
     * Obtener contenido del email
     */
    private function get_email_content($order, $guia_numero, $pdf_url) {
        $tracking_url = 'https://www.tramaco.com.ec/rastreo/?guia=' . $guia_numero;
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #f6f6f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f6f6f6;">
                <tr>
                    <td align="center" style="padding: 40px 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); padding: 30px;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">📦 ¡Tu envío está en camino!</h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                                        Hola <strong><?php echo esc_html($order->get_billing_first_name()); ?></strong>,
                                    </p>
                                    
                                    <p style="color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 30px;">
                                        ¡Buenas noticias! Tu pedido <strong>#<?php echo $order->get_order_number(); ?></strong> ha sido despachado y ya está en camino.
                                    </p>
                                    
                                    <!-- Guía Info Box -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-radius: 8px; margin-bottom: 30px;">
                                        <tr>
                                            <td style="padding: 25px;">
                                                <p style="color: #666; font-size: 13px; margin: 0 0 5px; text-transform: uppercase; letter-spacing: 1px;">Número de Guía</p>
                                                <p style="color: #0073aa; font-size: 24px; font-weight: bold; margin: 0; font-family: monospace;">
                                                    <?php echo esc_html($guia_numero); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Tracking Button -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center" style="padding-bottom: 30px;">
                                                <a href="<?php echo esc_url($tracking_url); ?>" 
                                                   style="display: inline-block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 30px; font-weight: bold; font-size: 16px;">
                                                    🔍 Rastrear mi Envío
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <?php if ($pdf_url): ?>
                                    <!-- PDF Link -->
                                    <p style="color: #666; font-size: 14px; text-align: center; margin: 0 0 30px;">
                                        <a href="<?php echo esc_url($pdf_url); ?>" style="color: #0073aa;">
                                            📄 Descargar guía en PDF
                                        </a>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <!-- Order Details -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px solid #eee; padding-top: 20px;">
                                        <tr>
                                            <td style="padding-top: 20px;">
                                                <h3 style="color: #333; font-size: 16px; margin: 0 0 15px;">Detalles de envío:</h3>
                                                <p style="color: #666; font-size: 14px; line-height: 1.8; margin: 0;">
                                                    <strong>Destinatario:</strong> <?php echo esc_html($order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name()); ?><br>
                                                    <strong>Dirección:</strong> <?php echo esc_html($order->get_shipping_address_1() ?: $order->get_billing_address_1()); ?><br>
                                                    <strong>Ciudad:</strong> <?php echo esc_html($order->get_shipping_city() ?: $order->get_billing_city()); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center;">
                                    <p style="color: #999; font-size: 13px; margin: 0;">
                                        Enviado con 💜 por <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
                                    </p>
                                    <p style="color: #bbb; font-size: 11px; margin: 10px 0 0;">
                                        Si tienes alguna pregunta, contáctanos a <?php echo esc_html(get_option('admin_email')); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Agregar información de guía a los emails de WooCommerce
     */
    public function add_guia_to_emails($order, $sent_to_admin, $plain_text) {
        $guia_numero = $order->get_meta('_tramaco_guia_numero');
        
        if (!$guia_numero) {
            return;
        }
        
        $tracking_url = 'https://www.tramaco.com.ec/rastreo/?guia=' . $guia_numero;
        
        if ($plain_text) {
            echo "\n\n";
            echo "==========\n";
            echo __('INFORMACIÓN DE ENVÍO', 'tramaco-api') . "\n";
            echo "==========\n";
            echo __('Número de Guía:', 'tramaco-api') . ' ' . $guia_numero . "\n";
            echo __('Rastrear:', 'tramaco-api') . ' ' . $tracking_url . "\n";
        } else {
            ?>
            <div style="margin: 20px 0; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">
                <h3 style="margin: 0 0 10px; color: #0073aa; font-size: 16px;">📦 <?php _e('Información de Envío', 'tramaco-api'); ?></h3>
                <p style="margin: 0; font-size: 14px;">
                    <strong><?php _e('Número de Guía:', 'tramaco-api'); ?></strong> 
                    <code style="background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($guia_numero); ?></code>
                </p>
                <p style="margin: 10px 0 0;">
                    <a href="<?php echo esc_url($tracking_url); ?>" 
                       style="display: inline-block; background: #0073aa; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 13px;">
                        🔍 <?php _e('Rastrear Envío', 'tramaco-api'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Agregar tracking después de la tabla de pedido
     */
    public function add_tracking_to_email($order, $sent_to_admin, $plain_text, $email) {
        // Solo para ciertos tipos de email
        $allowed_emails = array('customer_completed_order', 'customer_processing_order', 'customer_on_hold_order');
        
        if (!in_array($email->id, $allowed_emails)) {
            return;
        }
        
        $guia_numero = $order->get_meta('_tramaco_guia_numero');
        
        if (!$guia_numero) {
            return;
        }
        
        $tracking_url = 'https://www.tramaco.com.ec/rastreo/?guia=' . $guia_numero;
        $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
        
        if (!$plain_text) {
            ?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 30px;">
                <tr>
                    <td align="center">
                        <a href="<?php echo esc_url($tracking_url); ?>" 
                           style="display: inline-block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 25px; font-weight: bold; font-size: 14px; margin-right: 10px;">
                            🔍 <?php _e('Rastrear mi Envío', 'tramaco-api'); ?>
                        </a>
                        <?php if ($pdf_url): ?>
                        <a href="<?php echo esc_url($pdf_url); ?>" 
                           style="display: inline-block; background: #6c757d; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 25px; font-weight: bold; font-size: 14px;">
                            📄 <?php _e('Ver Guía PDF', 'tramaco-api'); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php
        }
    }
}

// Inicializar
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Tramaco_WC_Emails::get_instance();
    }
}, 30);
