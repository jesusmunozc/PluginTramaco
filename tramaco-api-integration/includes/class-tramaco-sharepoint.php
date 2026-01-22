<?php
/**
 * Integración de Tramaco con SharePoint
 * 
 * Maneja el envío de datos de guías a un Excel en SharePoint
 * usando Microsoft Graph API
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de SharePoint
 */
class Tramaco_SharePoint_Handler {
    
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $site_id;
    private $drive_id;
    private $file_path;
    private $access_token;
    
    public function __construct() {
        $this->client_id = get_option('tramaco_sharepoint_client_id', '');
        $this->client_secret = get_option('tramaco_sharepoint_client_secret', '');
        $this->tenant_id = get_option('tramaco_sharepoint_tenant_id', '');
        $this->site_id = get_option('tramaco_sharepoint_site_id', '');
        $this->drive_id = get_option('tramaco_sharepoint_drive_id', '');
        $this->file_path = get_option('tramaco_sharepoint_file_path', '/Guias/guias-tramaco.xlsx');
    }
    
    /**
     * Verificar si SharePoint está configurado
     */
    public function is_configured() {
        return !empty($this->client_id) 
            && !empty($this->client_secret) 
            && !empty($this->tenant_id)
            && !empty($this->site_id);
    }
    
    /**
     * Obtener token de acceso de Microsoft Graph
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // Intentar obtener de cache
        $cached_token = get_transient('tramaco_sharepoint_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return $this->access_token;
        }
        
        $url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($url, array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Tramaco SharePoint: Error getting token - ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 60 : 3540;
            set_transient('tramaco_sharepoint_token', $this->access_token, $expires_in);
            return $this->access_token;
        }
        
        error_log('Tramaco SharePoint: Invalid token response - ' . print_r($body, true));
        return false;
    }
    
    /**
     * Agregar datos de guía al Excel en SharePoint
     */
    public function add_guia_to_excel($order, $guia_result) {
        if (!$this->is_configured()) {
            error_log('Tramaco SharePoint: Not configured');
            return array('success' => false, 'message' => 'SharePoint no configurado');
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return array('success' => false, 'message' => 'Error de autenticación con SharePoint');
        }
        
        // Preparar datos para el Excel
        $row_data = $this->prepare_excel_row($order, $guia_result);
        
        // Agregar fila al Excel
        $result = $this->append_row_to_excel($row_data);
        
        if ($result['success']) {
            // Guardar referencia en el pedido
            $order->update_meta_data('_tramaco_sharepoint_synced', 'yes');
            $order->update_meta_data('_tramaco_sharepoint_sync_date', current_time('mysql'));
            $order->save();
        }
        
        return $result;
    }
    
    /**
     * Preparar datos para fila del Excel
     */
    private function prepare_excel_row($order, $guia_result) {
        $guia_numero = $guia_result['guia'];
        $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
        
        // Obtener datos del destinatario
        $destinatario_nombre = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        if (empty(trim($destinatario_nombre))) {
            $destinatario_nombre = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }
        
        $direccion = $order->get_shipping_address_1();
        if (empty($direccion)) {
            $direccion = $order->get_billing_address_1();
        }
        
        $ciudad = $order->get_shipping_city() ?: $order->get_billing_city();
        $telefono = $order->get_billing_phone();
        
        // Calcular totales de productos
        $productos = array();
        $cantidad_total = 0;
        foreach ($order->get_items() as $item) {
            $productos[] = $item->get_name();
            $cantidad_total += $item->get_quantity();
        }
        
        return array(
            // Fecha y hora
            date('Y-m-d'),
            date('H:i:s'),
            // Datos del pedido
            $order->get_order_number(),
            $order->get_status(),
            $order->get_total(),
            // Datos de la guía
            $guia_numero,
            $order->get_meta('_tramaco_guia_fecha'),
            // Datos del destinatario
            $destinatario_nombre,
            $telefono,
            $order->get_billing_email(),
            $direccion,
            $ciudad,
            $order->get_meta('_shipping_tramaco_parroquia') ?: $order->get_meta('_billing_tramaco_parroquia'),
            // Datos del envío
            implode(', ', $productos),
            $cantidad_total,
            $order->get_shipping_total(),
            // Enlaces
            $pdf_url ?: '',
            admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            // Tracking URL
            'https://www.tramaco.com.ec/rastreo/?guia=' . $guia_numero
        );
    }
    
    /**
     * Agregar fila al Excel en SharePoint
     */
    private function append_row_to_excel($row_data) {
        $token = $this->get_access_token();
        
        // Construir URL de la API de Graph
        // Primero necesitamos obtener el ID del archivo
        $file_info = $this->get_or_create_excel_file();
        
        if (!$file_info['success']) {
            return $file_info;
        }
        
        $workbook_id = $file_info['item_id'];
        
        // URL para agregar fila a la tabla
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/items/{$workbook_id}/workbook/worksheets/Guias/tables/TablaGuias/rows";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'values' => array($row_data)
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Tramaco SharePoint: Error adding row - ' . $response->get_error_message());
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array('success' => true, 'message' => 'Datos enviados a SharePoint');
        }
        
        // Si la tabla no existe, intentar crearla
        if ($status_code == 404) {
            $create_result = $this->create_excel_table($workbook_id);
            if ($create_result['success']) {
                // Reintentar agregar la fila
                return $this->append_row_to_excel($row_data);
            }
            return $create_result;
        }
        
        error_log('Tramaco SharePoint: Error response - ' . print_r($body, true));
        return array(
            'success' => false, 
            'message' => isset($body['error']['message']) ? $body['error']['message'] : 'Error desconocido'
        );
    }
    
    /**
     * Obtener o crear el archivo Excel
     */
    private function get_or_create_excel_file() {
        $token = $this->get_access_token();
        
        // Intentar obtener el archivo existente
        $encoded_path = rawurlencode(ltrim($this->file_path, '/'));
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/root:/{$encoded_path}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($status_code == 200 && isset($body['id'])) {
                return array('success' => true, 'item_id' => $body['id']);
            }
        }
        
        // Si no existe, crear el archivo
        return $this->create_excel_file();
    }
    
    /**
     * Crear archivo Excel inicial
     */
    private function create_excel_file() {
        $token = $this->get_access_token();
        
        // Crear archivo vacío
        $encoded_path = rawurlencode(ltrim($this->file_path, '/'));
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/root:/{$encoded_path}:/content";
        
        // Contenido inicial del Excel (archivo vacío básico)
        // En producción, podrías subir una plantilla predefinida
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
            'body' => $this->get_excel_template(),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id'])) {
            // Crear la hoja y tabla
            $this->setup_excel_structure($body['id']);
            return array('success' => true, 'item_id' => $body['id']);
        }
        
        return array('success' => false, 'message' => 'No se pudo crear el archivo Excel');
    }
    
    /**
     * Obtener plantilla de Excel básica
     */
    private function get_excel_template() {
        // En un caso real, esto sería un archivo .xlsx válido
        // Por ahora, usaremos la API de Graph para crear la estructura
        $template_path = TRAMACO_API_PLUGIN_DIR . 'templates/guias-template.xlsx';
        
        if (file_exists($template_path)) {
            return file_get_contents($template_path);
        }
        
        // Retornar archivo Excel mínimo válido (44 bytes es el mínimo para un xlsx vacío)
        return '';
    }
    
    /**
     * Configurar estructura del Excel
     */
    private function setup_excel_structure($workbook_id) {
        $token = $this->get_access_token();
        
        // Crear/renombrar hoja
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/items/{$workbook_id}/workbook/worksheets";
        
        // Agregar hoja "Guias"
        wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('name' => 'Guias')),
            'timeout' => 30
        ));
        
        // Agregar encabezados
        $headers_url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/items/{$workbook_id}/workbook/worksheets/Guias/range(address='A1:S1')";
        
        $headers = array(
            'Fecha', 'Hora', 'Pedido', 'Estado', 'Total',
            'Guía', 'Fecha Guía', 'Destinatario', 'Teléfono', 'Email',
            'Dirección', 'Ciudad', 'Parroquia', 'Productos', 'Cantidad',
            'Costo Envío', 'PDF Guía', 'Link Pedido', 'Tracking'
        );
        
        wp_remote_request($headers_url, array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'values' => array($headers)
            )),
            'timeout' => 30
        ));
        
        // Crear tabla
        $this->create_excel_table($workbook_id);
    }
    
    /**
     * Crear tabla en Excel
     */
    private function create_excel_table($workbook_id) {
        $token = $this->get_access_token();
        
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/items/{$workbook_id}/workbook/worksheets/Guias/tables/add";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'address' => 'A1:S1',
                'hasHeaders' => true
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['name'])) {
            // Renombrar la tabla
            $rename_url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}/drive/items/{$workbook_id}/workbook/worksheets/Guias/tables/{$body['name']}";
            
            wp_remote_request($rename_url, array(
                'method' => 'PATCH',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array('name' => 'TablaGuias')),
                'timeout' => 30
            ));
            
            return array('success' => true);
        }
        
        return array('success' => false, 'message' => 'No se pudo crear la tabla');
    }
    
    /**
     * Sincronizar pedidos pendientes
     */
    public function sync_pending_orders() {
        if (!$this->is_configured()) {
            return;
        }
        
        // Obtener pedidos con guía pero sin sincronizar a SharePoint
        $orders = wc_get_orders(array(
            'limit' => 50,
            'meta_query' => array(
                array(
                    'key' => '_tramaco_guia_numero',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_tramaco_sharepoint_synced',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($orders as $order) {
            $guia_numero = $order->get_meta('_tramaco_guia_numero');
            $guia_result = array(
                'guia' => $guia_numero,
                'data' => json_decode($order->get_meta('_tramaco_guia_data'), true)
            );
            
            $this->add_guia_to_excel($order, $guia_result);
        }
    }
}

/**
 * Cron para sincronización de SharePoint
 */
add_action('tramaco_sharepoint_sync', function() {
    $handler = new Tramaco_SharePoint_Handler();
    $handler->sync_pending_orders();
});

// Programar cron si no existe
if (!wp_next_scheduled('tramaco_sharepoint_sync')) {
    wp_schedule_event(time(), 'hourly', 'tramaco_sharepoint_sync');
}
