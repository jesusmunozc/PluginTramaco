<?php
/**
 * Helper para obtener IDs de SharePoint
 * 
 * Este script ayuda a obtener:
 * - Tenant ID
 * - Site ID
 * - Drive ID
 * - Lista de archivos
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Helper para SharePoint
 */
class Tramaco_SharePoint_Helper {
    
    private $client_id;
    private $client_secret;
    private $tenant_id;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_helper_page'), 25);
        add_action('wp_ajax_tramaco_get_sharepoint_ids', array($this, 'ajax_get_sharepoint_ids'));
        add_action('wp_ajax_tramaco_test_sharepoint', array($this, 'ajax_test_sharepoint'));
        add_action('wp_ajax_tramaco_sync_pending_orders', array($this, 'ajax_sync_pending_orders'));
    }
    
    /**
     * Agregar página helper
     */
    public function add_helper_page() {
        add_submenu_page(
            'tramaco-api',
            __('Herramientas SharePoint', 'tramaco-api'),
            __('📋 Herramientas', 'tramaco-api'),
            'manage_options',
            'tramaco-sharepoint-helper',
            array($this, 'render_helper_page')
        );
    }
    
    /**
     * Renderizar página helper
     */
    public function render_helper_page() {
        ?>
        <div class="wrap">
            <h1>🔧 <?php _e('Herramientas de SharePoint', 'tramaco-api'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('¿Qué hace esta herramienta?', 'tramaco-api'); ?></strong></p>
                <p><?php _e('Te ayuda a obtener automáticamente los IDs necesarios para configurar SharePoint (Tenant ID, Site ID, Drive ID).', 'tramaco-api'); ?></p>
            </div>
            
            <!-- Paso 1: Ingresar credenciales -->
            <div class="tramaco-helper-section">
                <h2>📝 <?php _e('Paso 1: Ingresa tus credenciales', 'tramaco-api'); ?></h2>
                <p><?php _e('Necesitamos las credenciales de tu aplicación de Azure AD para consultar los datos.', 'tramaco-api'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Client ID', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" id="helper_client_id" class="regular-text" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_client_id', '')); ?>"
                                   placeholder="3527bd79-b2e2-45d6-b683-5ca052f0792b">
                            <p class="description"><?php _e('ID de la aplicación (Application ID)', 'tramaco-api'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Client Secret', 'tramaco-api'); ?></th>
                        <td>
                            <input type="password" id="helper_client_secret" class="regular-text"
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_client_secret', '')); ?>"
                                   placeholder="8pj8Q~...">
                            <p class="description"><?php _e('Secreto de la aplicación (Client Secret Value)', 'tramaco-api'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Tenant ID', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" id="helper_tenant_id" class="regular-text"
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_tenant_id', '')); ?>"
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            <p class="description">
                                <?php _e('ID del tenant (Directory ID). Si no lo sabes, déjalo vacío e intentaremos detectarlo.', 'tramaco-api'); ?>
                                <br>
                                <strong><?php _e('Consejo:', 'tramaco-api'); ?></strong> 
                                <?php _e('Encuéntralo en Azure Portal → Azure Active Directory → Properties → Tenant ID', 'tramaco-api'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" id="btn_get_ids" class="button button-primary button-large">
                        🔍 <?php _e('Obtener IDs de SharePoint', 'tramaco-api'); ?>
                    </button>
                    <span class="spinner" id="helper_spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            </div>
            
            <!-- Resultados -->
            <div id="helper_results" style="display:none; margin-top: 30px;">
                <h2>✅ <?php _e('Resultados', 'tramaco-api'); ?></h2>
                <div id="helper_results_content"></div>
            </div>
            
            <!-- Paso 2: Test de conexión -->
            <div class="tramaco-helper-section" style="margin-top: 40px;">
                <h2>🧪 <?php _e('Paso 2: Probar Conexión', 'tramaco-api'); ?></h2>
                <p><?php _e('Una vez configurado todo, prueba la conexión a SharePoint.', 'tramaco-api'); ?></p>
                
                <p>
                    <button type="button" id="btn_test_connection" class="button button-secondary">
                        🔌 <?php _e('Probar Conexión a SharePoint', 'tramaco-api'); ?>
                    </button>
                    <span class="spinner" id="test_spinner" style="float: none; margin: 0 10px;"></span>
                </p>
                
                <div id="test_results" style="display:none; margin-top: 20px;"></div>
            </div>
            
            <!-- Paso 3: Sincronizar guías pendientes -->
            <div class="tramaco-helper-section" style="margin-top: 40px;">
                <h2>🔄 <?php _e('Paso 3: Sincronizar Guías Anteriores', 'tramaco-api'); ?></h2>
                <p><?php _e('Si ya tienes pedidos con guías generadas, puedes sincronizarlas ahora a SharePoint.', 'tramaco-api'); ?></p>
                
                <?php
                // Contar pedidos pendientes de sincronizar
                $pending_orders = wc_get_orders(array(
                    'limit' => -1,
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
                
                $pending_count = count($pending_orders);
                ?>
                
                <?php if ($pending_count > 0): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php printf(__('Hay %d pedidos con guías pendientes de sincronizar.', 'tramaco-api'), $pending_count); ?></strong>
                        </p>
                    </div>
                    
                    <p>
                        <button type="button" id="btn_sync_pending" class="button button-secondary">
                            📤 <?php printf(__('Sincronizar %d guías pendientes', 'tramaco-api'), $pending_count); ?>
                        </button>
                        <span class="spinner" id="sync_spinner" style="float: none; margin: 0 10px;"></span>
                    </p>
                    
                    <div id="sync_results" style="display:none; margin-top: 20px;"></div>
                <?php else: ?>
                    <div class="notice notice-success inline">
                        <p>✅ <?php _e('No hay guías pendientes de sincronizar.', 'tramaco-api'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .tramaco-helper-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-bottom: 20px;
            }
            
            .tramaco-result-box {
                background: #f0f0f1;
                border-left: 4px solid #2271b1;
                padding: 15px;
                margin: 10px 0;
                font-family: monospace;
            }
            
            .tramaco-result-box code {
                background: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                display: inline-block;
                margin: 2px 0;
            }
            
            .tramaco-copy-btn {
                margin-left: 10px;
                font-size: 11px;
                padding: 2px 8px;
                cursor: pointer;
            }
            
            .tramaco-error {
                background: #fcf0f1;
                border-left: 4px solid #d63638;
                padding: 15px;
                margin: 10px 0;
            }
            
            .tramaco-success {
                background: #f0f6fc;
                border-left: 4px solid #00a32a;
                padding: 15px;
                margin: 10px 0;
            }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Obtener IDs de SharePoint
            $('#btn_get_ids').on('click', function() {
                var $btn = $(this);
                var clientId = $('#helper_client_id').val();
                var clientSecret = $('#helper_client_secret').val();
                var tenantId = $('#helper_tenant_id').val();
                
                if (!clientId || !clientSecret) {
                    alert('<?php _e('Por favor ingresa Client ID y Client Secret', 'tramaco-api'); ?>');
                    return;
                }
                
                $btn.prop('disabled', true);
                $('#helper_spinner').addClass('is-active');
                $('#helper_results').hide();
                
                $.post(ajaxurl, {
                    action: 'tramaco_get_sharepoint_ids',
                    client_id: clientId,
                    client_secret: clientSecret,
                    tenant_id: tenantId,
                    nonce: '<?php echo wp_create_nonce('tramaco_sharepoint_helper'); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    $('#helper_spinner').removeClass('is-active');
                    
                    if (response.success) {
                        displayResults(response.data);
                    } else {
                        displayError(response.data.message);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $('#helper_spinner').removeClass('is-active');
                    displayError('<?php _e('Error de conexión', 'tramaco-api'); ?>');
                });
            });
            
            // Probar conexión
            $('#btn_test_connection').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                $('#test_spinner').addClass('is-active');
                $('#test_results').hide();
                
                $.post(ajaxurl, {
                    action: 'tramaco_test_sharepoint',
                    nonce: '<?php echo wp_create_nonce('tramaco_sharepoint_helper'); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    $('#test_spinner').removeClass('is-active');
                    $('#test_results').show().html(response.success ? 
                        '<div class="tramaco-success">' + response.data.message + '</div>' :
                        '<div class="tramaco-error">' + response.data.message + '</div>'
                    );
                });
            });
            
            // Sincronizar guías pendientes
            $('#btn_sync_pending').on('click', function() {
                if (!confirm('<?php _e('¿Sincronizar todas las guías pendientes a SharePoint?', 'tramaco-api'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true);
                $('#sync_spinner').addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'tramaco_sync_pending_orders',
                    nonce: '<?php echo wp_create_nonce('tramaco_sharepoint_helper'); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    $('#sync_spinner').removeClass('is-active');
                    $('#sync_results').show().html(response.success ? 
                        '<div class="tramaco-success">' + response.data.message + '</div>' :
                        '<div class="tramaco-error">' + response.data.message + '</div>'
                    );
                });
            });
            
            function displayResults(data) {
                var html = '<div class="tramaco-result-box">';
                
                if (data.tenant_id) {
                    html += '<p><strong>Tenant ID:</strong><br>';
                    html += '<code>' + data.tenant_id + '</code>';
                    html += '<button class="button tramaco-copy-btn" data-copy="' + data.tenant_id + '">📋 Copiar</button></p>';
                }
                
                if (data.sites && data.sites.length > 0) {
                    html += '<p><strong>Sites encontrados:</strong></p>';
                    data.sites.forEach(function(site) {
                        html += '<div style="margin: 10px 0; padding: 10px; background: #fff; border: 1px solid #ddd;">';
                        html += '<strong>' + site.name + '</strong><br>';
                        html += '<small>' + site.webUrl + '</small><br>';
                        html += '<code>' + site.id + '</code>';
                        html += '<button class="button tramaco-copy-btn" data-copy="' + site.id + '">📋 Copiar Site ID</button>';
                        html += '</div>';
                    });
                }
                
                if (data.drives && data.drives.length > 0) {
                    html += '<p><strong>Drives encontrados:</strong></p>';
                    data.drives.forEach(function(drive) {
                        html += '<div style="margin: 10px 0; padding: 10px; background: #fff; border: 1px solid #ddd;">';
                        html += '<strong>' + drive.name + '</strong> (' + drive.driveType + ')<br>';
                        html += '<code>' + drive.id + '</code>';
                        html += '<button class="button tramaco-copy-btn" data-copy="' + drive.id + '">📋 Copiar Drive ID</button>';
                        html += '</div>';
                    });
                }
                
                html += '<hr><p><strong>📝 Próximo paso:</strong> Copia estos valores y pégalos en WooCommerce → Ajustes → Tramaco → SharePoint</p>';
                html += '</div>';
                
                $('#helper_results_content').html(html);
                $('#helper_results').show();
                
                // Funcionalidad copiar
                $('.tramaco-copy-btn').on('click', function() {
                    var text = $(this).data('copy');
                    navigator.clipboard.writeText(text).then(function() {
                        alert('✅ Copiado al portapapeles');
                    });
                });
            }
            
            function displayError(message) {
                var html = '<div class="tramaco-error">' +
                    '<strong>❌ Error:</strong> ' + message +
                    '</div>';
                $('#helper_results_content').html(html);
                $('#helper_results').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Obtener IDs de SharePoint
     */
    public function ajax_get_sharepoint_ids() {
        check_ajax_referer('tramaco_sharepoint_helper', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'tramaco-api')));
            return;
        }
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = sanitize_text_field($_POST['client_secret']);
        $tenant_id = sanitize_text_field($_POST['tenant_id']);
        
        // Si no hay tenant_id, intentar obtenerlo
        if (empty($tenant_id)) {
            $tenant_id = 'common'; // Usar common para auto-detectar
        }
        
        // Obtener token
        $token_result = $this->get_token($client_id, $client_secret, $tenant_id);
        
        if (!$token_result['success']) {
            wp_send_json_error(array('message' => $token_result['message']));
            return;
        }
        
        $token = $token_result['token'];
        
        // Obtener información del usuario (para detectar tenant)
        $me_result = $this->get_me_info($token);
        
        // Obtener sites
        $sites_result = $this->get_sites($token);
        
        // Obtener drive del usuario
        $drive_result = $this->get_my_drive($token);
        
        wp_send_json_success(array(
            'tenant_id' => $me_result['tenant_id'] ?? $tenant_id,
            'sites' => $sites_result['sites'] ?? array(),
            'drives' => $drive_result['drives'] ?? array()
        ));
    }
    
    /**
     * Obtener token de acceso
     */
    private function get_token($client_id, $client_secret, $tenant_id) {
        $url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            return array('success' => true, 'token' => $body['access_token']);
        }
        
        $error_msg = isset($body['error_description']) ? $body['error_description'] : 'Error desconocido';
        return array('success' => false, 'message' => $error_msg);
    }
    
    /**
     * Obtener información del usuario
     */
    private function get_me_info($token) {
        $url = "https://graph.microsoft.com/v1.0/me";
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['id'])) {
                // Extraer tenant del userPrincipalName
                if (isset($body['userPrincipalName'])) {
                    $parts = explode('@', $body['userPrincipalName']);
                    if (count($parts) > 1) {
                        return array('tenant_id' => $parts[1]);
                    }
                }
            }
        }
        
        return array();
    }
    
    /**
     * Obtener sites
     */
    private function get_sites($token) {
        // Primero intentar obtener el root site
        $url = "https://graph.microsoft.com/v1.0/sites/root";
        
        $sites = array();
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['id'])) {
                $sites[] = array(
                    'id' => $body['id'],
                    'name' => $body['displayName'] ?? 'Root Site',
                    'webUrl' => $body['webUrl'] ?? ''
                );
            }
        }
        
        // Buscar otros sites
        $search_url = "https://graph.microsoft.com/v1.0/sites?search=*";
        $response = wp_remote_get($search_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['value'])) {
                foreach ($body['value'] as $site) {
                    $sites[] = array(
                        'id' => $site['id'],
                        'name' => $site['displayName'] ?? $site['name'],
                        'webUrl' => $site['webUrl'] ?? ''
                    );
                }
            }
        }
        
        return array('sites' => $sites);
    }
    
    /**
     * Obtener drive del usuario
     */
    private function get_my_drive($token) {
        $url = "https://graph.microsoft.com/v1.0/me/drive";
        
        $drives = array();
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['id'])) {
                $drives[] = array(
                    'id' => $body['id'],
                    'name' => $body['name'] ?? 'OneDrive',
                    'driveType' => $body['driveType'] ?? 'personal'
                );
            }
        }
        
        return array('drives' => $drives);
    }
    
    /**
     * AJAX: Test de conexión
     */
    public function ajax_test_sharepoint() {
        check_ajax_referer('tramaco_sharepoint_helper', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'tramaco-api')));
            return;
        }
        
        $handler = new Tramaco_SharePoint_Handler();
        
        if (!$handler->is_configured()) {
            wp_send_json_error(array('message' => __('SharePoint no está configurado completamente', 'tramaco-api')));
            return;
        }
        
        // Intentar obtener token
        $reflection = new ReflectionClass($handler);
        $method = $reflection->getMethod('get_access_token');
        $method->setAccessible(true);
        $token = $method->invoke($handler);
        
        if (!$token) {
            wp_send_json_error(array('message' => __('No se pudo obtener el token de acceso', 'tramaco-api')));
            return;
        }
        
        wp_send_json_success(array('message' => __('✅ Conexión exitosa con SharePoint', 'tramaco-api')));
    }
    
    /**
     * AJAX: Sincronizar pedidos pendientes
     */
    public function ajax_sync_pending_orders() {
        check_ajax_referer('tramaco_sharepoint_helper', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'tramaco-api')));
            return;
        }
        
        $handler = new Tramaco_SharePoint_Handler();
        
        if (!$handler->is_configured()) {
            wp_send_json_error(array('message' => __('SharePoint no está configurado', 'tramaco-api')));
            return;
        }
        
        // Sincronizar
        $handler->sync_pending_orders();
        
        // Contar cuántos quedaron pendientes
        $remaining = wc_get_orders(array(
            'limit' => -1,
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
        
        if (count($remaining) > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Se sincronizaron algunos pedidos, pero %d siguen pendientes. Verifica los logs.', 'tramaco-api'),
                    count($remaining)
                )
            ));
        } else {
            wp_send_json_success(array('message' => __('✅ Todas las guías fueron sincronizadas exitosamente', 'tramaco-api')));
        }
    }
}

// Inicializar
new Tramaco_SharePoint_Helper();
