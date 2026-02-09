<?php
/**
 * Integración de Tramaco con SharePoint
 * 
 * Maneja el envío de datos de guías a un Excel en SharePoint
 * usando Microsoft Graph API y PhpSpreadsheet
 * 
 * FLUJO DE MANEJO DE PDF:
 * =====================
 * 1. Generación: La API de Tramaco retorna el PDF en base64
 * 2. Decodificación: Se convierte de base64 a binario
 * 3. Almacenamiento: Se guarda en /wp-content/uploads/tramaco-guias/{año}/{mes}/
 * 4. URL Pública: Se genera una URL accesible desde cualquier lugar
 *    Ejemplo: https://tu-sitio.com/wp-content/uploads/tramaco-guias/2026/02/guia-031002005633823-order-12345.pdf
 * 5. Metadatos WooCommerce:
 *    - _tramaco_guia_pdf_url: URL pública del PDF
 *    - _tramaco_guia_pdf_path: Ruta física del archivo
 * 6. SharePoint Excel: La URL se envía en la columna "PDF Guía"
 * 7. Visualización: En Excel de SharePoint, el link es clickeable y abre el PDF
 * 
 * VENTAJAS:
 * - El PDF está en tu servidor (control total)
 * - URL pública accesible desde SharePoint
 * - No necesitas subir el PDF a SharePoint
 * - Los archivos están organizados por año/mes
 * - Fácil respaldo y gestión
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
     * Obtener URL del PDF de la guía para un pedido
     * Verifica si existe y retorna la URL pública
     * 
     * @param WC_Order $order El pedido de WooCommerce
     * @return string URL del PDF o mensaje si no existe
     */
    private function get_order_pdf_url($order) {
        $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
        $pdf_path = $order->get_meta('_tramaco_guia_pdf_path');
        
        if (empty($pdf_url)) {
            error_log("Tramaco SharePoint: ⚠️ No hay URL de PDF para pedido #{$order->get_id()}");
            return 'Sin PDF';
        }
        
        // Verificar si el archivo físicamente existe
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            error_log("Tramaco SharePoint: ✅ PDF verificado y accesible: $pdf_url");
            return $pdf_url;
        } else {
            error_log("Tramaco SharePoint: ⚠️ URL de PDF existe pero archivo no encontrado en disco para pedido #{$order->get_id()}");
            // Retornar la URL de todas formas, puede que esté en otro servidor o CDN
            return $pdf_url;
        }
    }
    
    /**
     * Preparar datos para fila del Excel
     * Formato idéntico al script test-sharepoint.js
     */
    private function prepare_excel_row($order, $guia_result) {
        $guia_numero = isset($guia_result['guia']) ? $guia_result['guia'] : '';
        $pdf_url = $this->get_order_pdf_url($order);
        
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
        
        // URL del tracking de Tramaco
        $tracking_url = 'https://www.tramaco.com.ec/rastreo.html?guia=' . $guia_numero;
        
        // URL del pedido en WooCommerce admin
        $order_admin_url = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        
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
            // Enlaces - URL pública del PDF
            $pdf_url ?: 'Sin PDF',
            $order_admin_url,
            // Tracking URL
            $tracking_url
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
    
    /**
     * Crear pedido de prueba, generar guía y enviar a SharePoint
     */
    public function create_test_order_and_sync() {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => 'SharePoint no está configurado correctamente');
        }
        
        try {
            // 1. Crear pedido de prueba
            $order = wc_create_order();
            
            // Configurar dirección de facturación
            $order->set_billing_first_name('Cliente');
            $order->set_billing_last_name('Prueba SharePoint');
            $order->set_billing_email('prueba@tramaco-test.com');
            $order->set_billing_phone('0999999999');
            $order->set_billing_address_1('Av. Principal 123');
            $order->set_billing_city('Quito');
            $order->set_billing_postcode('170501');
            $order->set_billing_country('EC');
            
            // Configurar dirección de envío
            $order->set_shipping_first_name('Cliente');
            $order->set_shipping_last_name('Prueba SharePoint');
            $order->set_shipping_address_1('Av. Principal 123');
            $order->set_shipping_city('Quito');
            $order->set_shipping_postcode('170501');
            $order->set_shipping_country('EC');
            
            // Añadir metadatos de ubicación (parroquia de Quito - Centro Histórico por ejemplo)
            // Debes ajustar estos valores según tu configuración
            $order->update_meta_data('_shipping_tramaco_parroquia', '21580'); // Localidad de ejemplo
            
            // Añadir un producto de prueba
            $product = wc_get_product(false);
            if (!$product) {
                // Si no hay productos, crear uno temporal
                $product_data = array(
                    'name' => 'Producto de Prueba - Tramaco SharePoint',
                    'regular_price' => '25.00',
                    'virtual' => false,
                    'weight' => '1'
                );
                $product = new WC_Product_Simple();
                $product->set_props($product_data);
                $product->save();
            }
            
            // Añadir producto al pedido
            $order->add_product($product, 1);
            
            // Calcular totales
            $order->calculate_totals();
            
            // Marcar como procesando (esto debería disparar la generación de guía)
            $order->set_status('processing', 'Pedido de prueba creado desde SharePoint');
            $order->save();
            
            $order_id = $order->get_id();
            
            error_log("Tramaco SharePoint: Pedido de prueba #{$order_id} creado");
            
            // 2. Generar guía de Tramaco con datos de prueba específicos
            $tramaco_api = Tramaco_API_Integration::get_instance();
            
            error_log("Tramaco SharePoint: Obteniendo token de autenticación...");
            $token = $tramaco_api->get_token();
            
            if (!$token) {
                error_log("Tramaco SharePoint: ERROR - No se pudo obtener token de autenticación");
                return array(
                    'success' => false, 
                    'message' => 'No se pudo obtener token de Tramaco API',
                    'order_id' => $order_id,
                    'order_url' => admin_url('post.php?post=' . $order_id . '&action=edit')
                );
            }
            
            error_log("Tramaco SharePoint: Token obtenido exitosamente - " . substr($token, 0, 50) . "...");
            
            // Datos de prueba específicos de Tramaco
            $guia_data = array(
                "lstCargaDestino" => array(
                    array(
                        "id" => 1,
                        "datoAdicional" => array(
                            "motivo" => "",
                            "citacion" => "",
                            "boleta" => ""
                        ),
                        "destinatario" => array(
                            "codigoPostal" => "050101",
                            "nombres" => " ",
                            "codigoParroquia" => 290,
                            "email" => " ",
                            "apellidos" => "PAZMI LOOR WILLIAM BAN ",
                            "callePrimaria" => "NORTE DE QUITO",
                            "telefono" => "0984652402",
                            "calleSecundaria" => "",
                            "tipoIden" => "05",
                            "referencia" => "",
                            "ciRuc" => "1302311475",
                            "numero" => " "
                        ),
                        "carga" => array(
                            "localidad" => 21580,
                            "adjuntos" => "",
                            "referenciaTercero" => "",
                            "largo" => 0,
                            "descripcion" => "CAMISETA 1, PERFUME 1, PAR DE ZAPATOS 3, VITAMINAS 2",
                            "valorCobro" => 0,
                            "valorAsegurado" => 0.0,
                            "contrato" => 6394,
                            "peso" => 3.63,
                            "observacion" => "",
                            "producto" => "36",
                            "ancho" => "",
                            "bultos" => "",
                            "cajas" => "1",
                            "cantidadDoc" => "",
                            "alto" => ""
                        )
                    )
                ),
                "remitente" => array(
                    "codigoPostal" => "",
                    "nombres" => "GUIA PRUEBA",
                    "codigoParroquia" => 316,
                    "email" => " ",
                    "apellidos" => "STAR BRAND",
                    "callePrimaria" => "GUIA PRUEBA",
                    "telefono" => "09812345677",
                    "calleSecundaria" => "",
                    "tipoIden" => "06",
                    "referencia" => "REFERENCIA REMITENTE",
                    "ciRuc" => "1793191845001",
                    "numero" => "000000047"
                )
            );
            
            error_log("Tramaco SharePoint: Generando guía con datos de prueba");
            error_log("Tramaco SharePoint: POST a " . TRAMACO_API_GENERAR_GUIA_URL);
            error_log("Tramaco SharePoint: Datos enviados: " . json_encode($guia_data, JSON_PRETTY_PRINT));
            
            // Llamar a la API de Tramaco
            $response = wp_remote_post(TRAMACO_API_GENERAR_GUIA_URL, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $token
                ),
                'body' => json_encode($guia_data),
                'timeout' => 30,
                'sslverify' => false
            ));
            
            if (is_wp_error($response)) {
                error_log("Tramaco SharePoint: ERROR de conexión con API - " . $response->get_error_message());
                return array(
                    'success' => false, 
                    'message' => 'Error al conectar con Tramaco API: ' . $response->get_error_message(),
                    'order_id' => $order_id,
                    'order_url' => admin_url('post.php?post=' . $order_id . '&action=edit'),
                    'error_type' => 'connection_error'
                );
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            error_log("Tramaco SharePoint: Respuesta recibida - HTTP Status: $status_code");
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            error_log("Tramaco SharePoint: Respuesta completa de la API: " . json_encode($body, JSON_PRETTY_PRINT));
            
            // Extraer número de guía - La estructura correcta es:
            // body.cuerpoRespuesta.codigo = "1" (éxito)
            // body.salidaGenerarGuiaWs.lstGuias[0].guia = "031002005633823"
            $guia_numero = null;
            $codigo = null;
            
            // Extraer código de respuesta
            if (isset($body['cuerpoRespuesta']['codigo'])) {
                $codigo = $body['cuerpoRespuesta']['codigo'];
            } elseif (isset($body['codigo'])) {
                $codigo = $body['codigo'];
            }
            
            // Extraer número de guía de la estructura correcta
            if (isset($body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'])) {
                $guia_numero = $body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'];
            } elseif (isset($body['salidaGenerarGuiaWs'][0]['guia'])) {
                // Estructura alternativa
                $guia_numero = $body['salidaGenerarGuiaWs'][0]['guia'];
            } elseif (isset($body['cuerpoRespuesta']['salidaGenerarGuiaWs']['lstGuias'][0]['guia'])) {
                // Otra variante posible
                $guia_numero = $body['cuerpoRespuesta']['salidaGenerarGuiaWs']['lstGuias'][0]['guia'];
            }
            
            error_log("Tramaco SharePoint: Código extraído: $codigo, Guía extraída: " . ($guia_numero ?: 'NO ENCONTRADA'));
            
            if (($codigo == '1' || $codigo == 1) && $guia_numero) {
                // Guardar datos en el pedido
                $order->update_meta_data('_tramaco_guia_numero', $guia_numero);
                $order->update_meta_data('_tramaco_guia_data', json_encode($body));
                $order->update_meta_data('_tramaco_guia_fecha', current_time('mysql'));
                
                // Generar PDF
                error_log("Tramaco SharePoint: Generando PDF para guía $guia_numero...");
                error_log("Tramaco SharePoint: POST a " . TRAMACO_API_GENERAR_PDF_URL);
                
                $pdf_data = array('guias' => array($guia_numero));
                error_log("Tramaco SharePoint: Body enviado: " . json_encode($pdf_data));
                
                $pdf_response = wp_remote_post(TRAMACO_API_GENERAR_PDF_URL, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => $token
                    ),
                    'body' => json_encode($pdf_data),
                    'timeout' => 30,
                    'sslverify' => false
                ));
                
                if (is_wp_error($pdf_response)) {
                    error_log("Tramaco SharePoint: ❌ ERROR al generar PDF - " . $pdf_response->get_error_message());
                } else {
                    $pdf_status = wp_remote_retrieve_response_code($pdf_response);
                    error_log("Tramaco SharePoint: Respuesta PDF - HTTP Status: $pdf_status");
                    
                    $pdf_body_raw = wp_remote_retrieve_body($pdf_response);
                    error_log("Tramaco SharePoint: Respuesta PDF RAW (primeros 100 chars): " . substr($pdf_body_raw, 0, 100));
                    
                    // Verificar si es PDF directo o JSON
                    $pdf_content = null;
                    
                    if (substr($pdf_body_raw, 0, 4) === '%PDF') {
                        // Es un PDF directo
                        error_log("Tramaco SharePoint: ✅ PDF directo detectado (no JSON)");
                        $pdf_content = $pdf_body_raw;
                    } else {
                        // Intentar parsear como JSON
                        error_log("Tramaco SharePoint: Intentando parsear como JSON...");
                        $pdf_body = json_decode($pdf_body_raw, true);
                        
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            error_log("Tramaco SharePoint: ❌ ERROR al decodificar JSON: " . json_last_error_msg());
                        } elseif (is_array($pdf_body)) {
                            error_log("Tramaco SharePoint: Respuesta JSON parseada exitosamente");
                            
                            // Verificar código de respuesta
                            $pdf_codigo = null;
                            if (isset($pdf_body['cuerpoRespuesta']['codigo'])) {
                                $pdf_codigo = $pdf_body['cuerpoRespuesta']['codigo'];
                            } elseif (isset($pdf_body['codigo'])) {
                                $pdf_codigo = $pdf_body['codigo'];
                            }
                            
                            error_log("Tramaco SharePoint: Código de respuesta: " . ($pdf_codigo ?: 'NO ENCONTRADO'));
                            
                            // Intentar extraer el PDF en base64 de diferentes estructuras posibles
                            $pdf_base64 = null;
                            if (isset($pdf_body['salidaGenerarPdfWs']['inStrPfd'])) {
                                $pdf_base64 = $pdf_body['salidaGenerarPdfWs']['inStrPfd'];
                                error_log("Tramaco SharePoint: ✅ PDF base64 encontrado en salidaGenerarPdfWs.inStrPfd");
                            } elseif (isset($pdf_body['salidaGenerarPdfWs']['pdf'])) {
                                $pdf_base64 = $pdf_body['salidaGenerarPdfWs']['pdf'];
                                error_log("Tramaco SharePoint: ✅ PDF base64 encontrado en salidaGenerarPdfWs.pdf");
                            } elseif (isset($pdf_body['inStrPfd'])) {
                                $pdf_base64 = $pdf_body['inStrPfd'];
                                error_log("Tramaco SharePoint: ✅ PDF base64 encontrado en inStrPfd");
                            } elseif (isset($pdf_body['pdf'])) {
                                $pdf_base64 = $pdf_body['pdf'];
                                error_log("Tramaco SharePoint: ✅ PDF base64 encontrado en pdf");
                            } else {
                                error_log("Tramaco SharePoint: ⚠️ PDF no encontrado en ninguna ubicación conocida de JSON");
                                if (is_array($pdf_body)) {
                                    error_log("Tramaco SharePoint: Estructura de respuesta: " . json_encode(array_keys($pdf_body)));
                                }
                            }
                            
                            if ($pdf_base64) {
                                error_log("Tramaco SharePoint: Decodificando base64 - Tamaño: " . strlen($pdf_base64) . " caracteres");
                                $pdf_content = base64_decode($pdf_base64);
                            }
                        }
                    }
                    
                    // Si tenemos contenido PDF (directo o decodificado), guardarlo
                    if ($pdf_content) {
                        $pdf_size = strlen($pdf_content);
                        error_log("Tramaco SharePoint: 📦 PDF disponible - Tamaño: " . ($pdf_size / 1024) . " KB");
                        
                        // Verificar que sea un PDF válido (debe empezar con %PDF)
                        if (substr($pdf_content, 0, 4) === '%PDF') {
                            error_log("Tramaco SharePoint: ✅ PDF válido detectado (header correcto)");
                        } else {
                            $header = substr($pdf_content, 0, 20);
                            error_log("Tramaco SharePoint: ⚠️ No es un PDF válido. Header: " . bin2hex($header));
                        }
                        
                        // Crear directorio si no existe
                        $upload_dir = wp_upload_dir();
                        $tramaco_dir = $upload_dir['basedir'] . '/tramaco-guias/' . date('Y') . '/' . date('m');
                        
                        error_log("Tramaco SharePoint: Directorio destino: $tramaco_dir");
                        
                        if (!file_exists($tramaco_dir)) {
                            $mkdir_result = wp_mkdir_p($tramaco_dir);
                            if ($mkdir_result) {
                                error_log("Tramaco SharePoint: ✅ Directorio creado exitosamente");
                            } else {
                                error_log("Tramaco SharePoint: ❌ ERROR al crear directorio");
                            }
                        } else {
                            error_log("Tramaco SharePoint: ℹ️ Directorio ya existe");
                        }
                        
                        // Guardar archivo
                        $filename = 'guia-' . $guia_numero . '-order-' . $order_id . '.pdf';
                        $filepath = $tramaco_dir . '/' . $filename;
                        
                        error_log("Tramaco SharePoint: Guardando PDF en: $filepath");
                        
                        $bytes_written = file_put_contents($filepath, $pdf_content);
                        
                        if ($bytes_written !== false) {
                            error_log("Tramaco SharePoint: ✅ Archivo escrito - $bytes_written bytes");
                        } else {
                            error_log("Tramaco SharePoint: ❌ ERROR al escribir archivo");
                        }
                        
                        // Verificar que el archivo se guardó correctamente
                        if (file_exists($filepath)) {
                            $file_size_kb = filesize($filepath) / 1024;
                            error_log("Tramaco SharePoint: ✅ PDF verificado en disco - Tamaño: {$file_size_kb} KB");
                            
                            // Verificar permisos
                            $perms = substr(sprintf('%o', fileperms($filepath)), -4);
                            error_log("Tramaco SharePoint: Permisos del archivo: $perms");
                            
                            // URL del archivo
                            $pdf_url = $upload_dir['baseurl'] . '/tramaco-guias/' . date('Y') . '/' . date('m') . '/' . $filename;
                            
                            $order->update_meta_data('_tramaco_guia_pdf_url', $pdf_url);
                            $order->update_meta_data('_tramaco_guia_pdf_path', $filepath);
                            
                            error_log("Tramaco SharePoint: ✅ PDF guardado y accesible en: $pdf_url");
                        } else {
                            error_log("Tramaco SharePoint: ❌ ERROR - El archivo NO existe después de escribirlo: $filepath");
                            
                            // Debug adicional
                            if (is_writable(dirname($filepath))) {
                                error_log("Tramaco SharePoint: ℹ️ El directorio SÍ es escribible");
                            } else {
                                error_log("Tramaco SharePoint: ❌ El directorio NO es escribible");
                            }
                        }
                    } else {
                        error_log("Tramaco SharePoint: ⚠️ No se obtuvo contenido PDF (ni directo ni base64)");
                    }
                }
                
                $order->save();
                $order->add_order_note('Guía Tramaco de prueba generada: ' . $guia_numero);
                
                error_log("Tramaco SharePoint: Guía {$guia_numero} generada para pedido #{$order_id}");
            } else {
                // Extraer mensaje de error
                $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : 
                           (isset($body['mensaje']) ? $body['mensaje'] : 'Sin mensaje de respuesta');
                
                // Crear mensaje de error detallado
                $error_detalle = "Código: " . ($codigo ?: 'NO ENCONTRADO') . " | Mensaje: " . $mensaje;
                
                if (!$guia_numero) {
                    $error_detalle .= " | ⚠️ Número de guía no encontrado en la respuesta";
                }
                
                error_log("Tramaco SharePoint: ERROR al generar guía - $error_detalle - Respuesta completa: " . json_encode($body));
                
                return array(
                    'success' => false, 
                    'message' => 'Error al generar guía: ' . $error_detalle,
                    'order_id' => $order_id,
                    'order_url' => admin_url('post.php?post=' . $order_id . '&action=edit'),
                    'response_codigo' => $codigo,
                    'response_mensaje' => $mensaje,
                    'guia_encontrada' => $guia_numero ? 'Sí' : 'No',
                    'debug_completo' => $body
                );
            }
            
            // Recargar orden para obtener metadatos actualizados
            $order = wc_get_order($order_id);
            $guia_numero = $order->get_meta('_tramaco_guia_numero');
            $pdf_url = $order->get_meta('_tramaco_guia_pdf_url');
            $pdf_path = $order->get_meta('_tramaco_guia_pdf_path');
            
            if (!$guia_numero) {
                return array(
                    'success' => false, 
                    'message' => 'Pedido creado pero no se obtuvo el número de guía',
                    'order_id' => $order_id,
                    'order_url' => admin_url('post.php?post=' . $order_id . '&action=edit')
                );
            }
            
            // Verificar estado del PDF
            $pdf_status = '';
            if ($pdf_url && $pdf_path && file_exists($pdf_path)) {
                $pdf_status = '✅ PDF disponible';
                error_log("Tramaco SharePoint: ✅ PDF confirmado - URL: $pdf_url");
            } elseif ($pdf_url) {
                $pdf_status = '⚠️ PDF URL guardada pero archivo no encontrado';
                error_log("Tramaco SharePoint: ⚠️ PDF URL existe pero archivo no encontrado: $pdf_path");
            } else {
                $pdf_status = '❌ PDF no generado';
                error_log("Tramaco SharePoint: ❌ PDF no fue generado o guardado");
            }
            
            // 3. Enviar a SharePoint
            $guia_result = array(
                'guia' => $guia_numero,
                'data' => json_decode($order->get_meta('_tramaco_guia_data'), true)
            );
            
            $sharepoint_result = $this->add_guia_to_excel($order, $guia_result);
            
            // 4. Preparar respuesta
            $response = array(
                'success' => true,
                'message' => '✅ Pedido de prueba creado exitosamente',
                'order_id' => $order_id,
                'order_url' => admin_url('post.php?post=' . $order_id . '&action=edit'),
                'guia' => $guia_numero,
                'guia_message' => '📦 Guía generada: ' . $guia_numero,
                'pdf_url' => $pdf_url ?: null,
                'pdf_path' => $pdf_path ?: null,
                'pdf_status' => $pdf_status,
                'pdf_message' => $pdf_status,
                'sharepoint_result' => $sharepoint_result['success'] ? '✅ Enviado a SharePoint' : '❌ Error SharePoint: ' . $sharepoint_result['message'],
                'tracking_url' => 'https://www.tramaco.com.ec/rastreo.html?guia=' . $guia_numero
            );
            
            error_log("Tramaco SharePoint: ✅ Pedido de prueba completado - Guía: $guia_numero - PDF: $pdf_status - Respuesta: " . json_encode($response));
            
            return $response;
            
        } catch (Exception $e) {
            error_log('Tramaco SharePoint: Error en pedido de prueba - ' . $e->getMessage());
            return array(
                'success' => false, 
                'message' => 'Error al crear pedido de prueba: ' . $e->getMessage()
            );
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
