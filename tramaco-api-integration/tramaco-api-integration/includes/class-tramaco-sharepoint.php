<?php
/**
 * Integración de Tramaco con SharePoint
 * 
 * Maneja el envío de datos de guías a un Excel en SharePoint
 * usando Microsoft Graph API y PhpSpreadsheet
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

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
    private $use_phpspreadsheet;
    
    public function __construct() {
        $this->client_id = get_option('tramaco_sharepoint_client_id', '');
        $this->client_secret = get_option('tramaco_sharepoint_client_secret', '');
        $this->tenant_id = get_option('tramaco_sharepoint_tenant_id', '');
        $this->site_id = get_option('tramaco_sharepoint_site_id', '');
        $this->drive_id = get_option('tramaco_sharepoint_drive_id', '');
        $this->file_path = get_option('tramaco_sharepoint_file_path', '/Data Guías/Registro-Guias-Tramaco.xlsx');
        
        // Verificar si PhpSpreadsheet está disponible
        $this->use_phpspreadsheet = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
        
        if (!$this->use_phpspreadsheet) {
            error_log('Tramaco SharePoint: PhpSpreadsheet no está instalado. Instala con: composer require phpoffice/phpspreadsheet');
        }
    }
    
    /**
     * Verificar si SharePoint está configurado
     */
    public function is_configured() {
        return !empty($this->client_id) 
            && !empty($this->client_secret) 
            && !empty($this->tenant_id)
            && !empty($this->site_id)
            && !empty($this->drive_id)
            && $this->use_phpspreadsheet;
    }
    
    /**
     * Obtener mensaje de estado de configuración
     */
    public function get_config_status() {
        $status = array();
        
        if (empty($this->client_id)) $status[] = 'Client ID faltante';
        if (empty($this->client_secret)) $status[] = 'Client Secret faltante';
        if (empty($this->tenant_id)) $status[] = 'Tenant ID faltante';
        if (empty($this->site_id)) $status[] = 'Site ID faltante';
        if (empty($this->drive_id)) $status[] = 'Drive ID faltante';
        if (!$this->use_phpspreadsheet) $status[] = 'PhpSpreadsheet no instalado';
        
        return empty($status) ? 'Configurado correctamente' : implode(', ', $status);
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
     * Replica la funcionalidad del script test-sharepoint.js
     */
    public function add_guia_to_excel($order, $guia_result) {
        if (!$this->is_configured()) {
            $status = $this->get_config_status();
            error_log("Tramaco SharePoint: No configurado - $status");
            return array('success' => false, 'message' => "SharePoint no configurado: $status");
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return array('success' => false, 'message' => 'Error de autenticación con SharePoint');
        }
        
        // Preparar datos para el Excel
        $row_data = $this->prepare_excel_row($order, $guia_result);
        
        // Agregar fila al Excel usando el método de descarga/modificación/subida
        $result = $this->add_row_to_excel_file($token, $row_data);
        
        if ($result['success']) {
            // Guardar referencia en el pedido
            $order->update_meta_data('_tramaco_sharepoint_synced', 'yes');
            $order->update_meta_data('_tramaco_sharepoint_sync_date', current_time('mysql'));
            $order->save();
            
            error_log("Tramaco SharePoint: Guía {$guia_result['guia']} agregada al Excel");
        }
        
        return $result;
    }
    
    /**
     * Descargar archivo Excel desde SharePoint
     */
    private function download_excel_file($token) {
        $encoded_path = rawurlencode(ltrim($this->file_path, '/'));
        $url = "https://graph.microsoft.com/v1.0/drives/{$this->drive_id}/root:/{$encoded_path}:/content";
        
        error_log("Tramaco SharePoint: Descargando Excel desde $url");
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Tramaco SharePoint: Error descargando - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('Tramaco SharePoint: Excel descargado - ' . strlen($body) . ' bytes');
            return $body;
        }
        
        if ($status_code === 404) {
            error_log('Tramaco SharePoint: Archivo no existe, se creará');
            return null; // Archivo no existe, se creará
        }
        
        $error_body = wp_remote_retrieve_body($response);
        error_log("Tramaco SharePoint: Error $status_code - $error_body");
        return false;
    }
    
    /**
     * Subir archivo Excel a SharePoint
     */
    private function upload_excel_file($token, $file_content) {
        $encoded_path = rawurlencode(ltrim($this->file_path, '/'));
        $url = "https://graph.microsoft.com/v1.0/drives/{$this->drive_id}/root:/{$encoded_path}:/content";
        
        error_log('Tramaco SharePoint: Subiendo Excel - ' . strlen($file_content) . ' bytes');
        
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
            'body' => $file_content,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Tramaco SharePoint: Error subiendo - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            error_log('Tramaco SharePoint: Excel subido exitosamente');
            return true;
        }
        
        $error_body = wp_remote_retrieve_body($response);
        error_log("Tramaco SharePoint: Error subiendo $status_code - $error_body");
        return false;
    }
    
    /**
     * Agregar fila al Excel (descarga, modifica, sube)
     * Replica el comportamiento de test-sharepoint.js
     */
    private function add_row_to_excel_file($token, $row_data) {
        try {
            // 1. Descargar archivo Excel
            $file_content = $this->download_excel_file($token);
            
            // Si no existe, crear uno nuevo
            if ($file_content === null) {
                $result = $this->create_excel_with_headers();
                if (!$result['success']) {
                    return $result;
                }
                $file_content = $result['content'];
            }
            
            if ($file_content === false) {
                return array('success' => false, 'message' => 'Error al descargar el archivo Excel');
            }
            
            // 2. Cargar con PhpSpreadsheet
            $temp_file = wp_tempnam();
            file_put_contents($temp_file, $file_content);
            
            $spreadsheet = IOFactory::load($temp_file);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // 3. Agregar nueva fila
            $next_row = $worksheet->getHighestRow() + 1;
            $column = 'A';
            
            foreach ($row_data as $value) {
                $worksheet->setCellValue($column . $next_row, $value);
                $column++;
            }
            
            error_log("Tramaco SharePoint: Fila agregada en posición $next_row");
            
            // 4. Guardar a buffer
            $writer = new Xlsx($spreadsheet);
            $output_file = wp_tempnam();
            $writer->save($output_file);
            
            $new_content = file_get_contents($output_file);
            
            // Limpiar archivos temporales
            @unlink($temp_file);
            @unlink($output_file);
            
            // 5. Subir a SharePoint
            if ($this->upload_excel_file($token, $new_content)) {
                return array('success' => true, 'message' => 'Datos enviados a SharePoint correctamente');
            }
            
            return array('success' => false, 'message' => 'Error al subir el archivo modificado');
            
        } catch (Exception $e) {
            error_log('Tramaco SharePoint: Exception - ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear Excel con encabezados
     */
    private function create_excel_with_headers() {
        try {
            $spreadsheet = new Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Hoja1');
            
            // Encabezados según el script de Node.js
            $headers = array(
                'Fecha', 'Hora', 'Pedido', 'Estado', 'Total',
                'Guía', 'Fecha Guía', 'Destinatario', 'Teléfono', 'Email',
                'Dirección', 'Ciudad', 'Parroquia', 'Productos', 'Cantidad',
                'Costo Envío', 'PDF Guía', 'Link Pedido', 'Tracking'
            );
            
            // Agregar encabezados en la primera fila
            $column = 'A';
            foreach ($headers as $header) {
                $worksheet->setCellValue($column . '1', $header);
                
                // Estilo para encabezados
                $worksheet->getStyle($column . '1')->getFont()->setBold(true);
                $worksheet->getStyle($column . '1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
                
                // Ajustar ancho de columna
                $worksheet->getColumnDimension($column)->setWidth(15);
                
                $column++;
            }
            
            // Guardar a buffer
            $writer = new Xlsx($spreadsheet);
            $temp_file = wp_tempnam();
            $writer->save($temp_file);
            
            $content = file_get_contents($temp_file);
            @unlink($temp_file);
            
            error_log('Tramaco SharePoint: Excel creado con encabezados');
            
            return array('success' => true, 'content' => $content);
            
        } catch (Exception $e) {
            error_log('Tramaco SharePoint: Error creando Excel - ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error creando Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * Preparar datos para fila del Excel
     * Formato idéntico al script test-sharepoint.js
     */
    private function prepare_excel_row($order, $guia_result) {
        $guia_numero = isset($guia_result['guia']) ? $guia_result['guia'] : '';
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
        $parroquia = $order->get_meta('_shipping_tramaco_parroquia') ?: $order->get_meta('_billing_tramaco_parroquia');
        
        // Calcular totales de productos
        $productos = array();
        $cantidad_total = 0;
        foreach ($order->get_items() as $item) {
            $cantidad = $item->get_quantity();
            $productos[] = $item->get_name() . ' x' . $cantidad;
            $cantidad_total += $cantidad;
        }
        
        // Formatear fecha y hora según formato de Ecuador
        $now = current_time('timestamp');
        $fecha = date('Y-m-d', $now);
        $hora = date('H:i:s', $now);
        
        return array(
            // Fecha y hora
            $fecha,
            $hora,
            // Datos del pedido
            $order->get_order_number(),
            $order->get_status(),
            $order->get_total(),
            // Datos de la guía
            $guia_numero,
            $order->get_meta('_tramaco_guia_fecha') ?: $fecha,
            // Datos del destinatario
            $destinatario_nombre,
            $telefono,
            $order->get_billing_email(),
            $direccion,
            $ciudad,
            $parroquia,
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
     * Sincronizar pedidos pendientes
     */
    public function sync_pending_orders() {
        if (!$this->is_configured()) {
            error_log('Tramaco SharePoint: No se puede sincronizar - ' . $this->get_config_status());
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
        
        $synced = 0;
        $errors = 0;
        
        foreach ($orders as $order) {
            $guia_numero = $order->get_meta('_tramaco_guia_numero');
            $guia_result = array(
                'guia' => $guia_numero,
                'data' => json_decode($order->get_meta('_tramaco_guia_data'), true)
            );
            
            $result = $this->add_guia_to_excel($order, $guia_result);
            
            if ($result['success']) {
                $synced++;
            } else {
                $errors++;
                error_log("Tramaco SharePoint: Error sincronizando pedido #{$order->get_id()}: {$result['message']}");
            }
            
            // Pausa para no saturar la API
            sleep(1);
        }
        
        error_log("Tramaco SharePoint: Sincronización completada - $synced exitosos, $errors errores");
    }
    
    /**
     * Verificar acceso al sitio de SharePoint (para diagnóstico)
     */
    public function test_connection() {
        $token = $this->get_access_token();
        
        if (!$token) {
            return array('success' => false, 'message' => 'No se pudo obtener token de acceso');
        }
        
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->site_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code === 200) {
            return array(
                'success' => true, 
                'message' => 'Conexión exitosa',
                'site_name' => isset($body['displayName']) ? $body['displayName'] : 'N/A',
                'site_url' => isset($body['webUrl']) ? $body['webUrl'] : 'N/A'
            );
        }
        
        return array(
            'success' => false, 
            'message' => "Error $status_code: " . (isset($body['error']['message']) ? $body['error']['message'] : 'Error desconocido')
        );
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
