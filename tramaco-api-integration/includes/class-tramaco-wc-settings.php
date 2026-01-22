<?php
/**
 * Configuración de WooCommerce para Tramaco
 * 
 * Agrega página de configuración en WooCommerce para el plugin
 * 
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase de configuración
 */
class Tramaco_WC_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Agregar tab de configuración en WooCommerce
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_tramaco', array($this, 'settings_tab_content'));
        add_action('woocommerce_update_options_tramaco', array($this, 'save_settings'));
        
        // Agregar sección en la página de admin del plugin
        add_action('admin_menu', array($this, 'add_wc_submenu'), 20);
    }
    
    /**
     * Agregar tab en configuración de WooCommerce
     */
    public function add_settings_tab($tabs) {
        $tabs['tramaco'] = __('Tramaco', 'tramaco-api');
        return $tabs;
    }
    
    /**
     * Contenido del tab
     */
    public function settings_tab_content() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * Guardar configuración
     */
    public function save_settings() {
        woocommerce_update_options($this->get_settings());
    }
    
    /**
     * Obtener campos de configuración
     */
    private function get_settings() {
        return array(
            // Sección: Generación de Guías
            array(
                'title' => __('Generación Automática de Guías', 'tramaco-api'),
                'type' => 'title',
                'desc' => __('Configura cuándo se generan automáticamente las guías de envío.', 'tramaco-api'),
                'id' => 'tramaco_wc_guia_section'
            ),
            
            array(
                'title' => __('Generar guía automáticamente', 'tramaco-api'),
                'id' => 'tramaco_wc_auto_generate',
                'type' => 'select',
                'default' => 'payment_complete',
                'options' => array(
                    'payment_complete' => __('Cuando el pago se completa', 'tramaco-api'),
                    'processing' => __('Cuando el pedido pasa a "Procesando"', 'tramaco-api'),
                    'completed' => __('Cuando el pedido se marca como "Completado"', 'tramaco-api'),
                    'manual' => __('Solo manualmente', 'tramaco-api')
                ),
                'desc' => __('Define en qué momento se genera automáticamente la guía de envío.', 'tramaco-api')
            ),
            
            array(
                'type' => 'sectionend',
                'id' => 'tramaco_wc_guia_section'
            ),
            
            // Sección: Datos del Remitente
            array(
                'title' => __('Datos del Remitente', 'tramaco-api'),
                'type' => 'title',
                'desc' => __('Información del remitente que aparecerá en las guías.', 'tramaco-api'),
                'id' => 'tramaco_remitente_section'
            ),
            
            array(
                'title' => __('Nombre/Empresa', 'tramaco-api'),
                'id' => 'tramaco_remitente_nombre',
                'type' => 'text',
                'default' => get_bloginfo('name'),
                'desc' => __('Nombre o razón social del remitente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Apellido/Departamento', 'tramaco-api'),
                'id' => 'tramaco_remitente_apellido',
                'type' => 'text',
                'default' => '',
                'desc' => __('Opcional. Apellido o departamento.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Teléfono', 'tramaco-api'),
                'id' => 'tramaco_remitente_telefono',
                'type' => 'text',
                'default' => '',
                'desc' => __('Teléfono de contacto del remitente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Email', 'tramaco-api'),
                'id' => 'tramaco_remitente_email',
                'type' => 'email',
                'default' => get_option('admin_email'),
                'desc' => __('Email del remitente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Dirección', 'tramaco-api'),
                'id' => 'tramaco_remitente_direccion',
                'type' => 'text',
                'default' => '',
                'desc' => __('Dirección completa del remitente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Parroquia de Origen', 'tramaco-api'),
                'id' => 'tramaco_api_parroquia_origen',
                'type' => 'text',
                'default' => '316',
                'desc' => __('Código de parroquia donde se origina el envío. (316 = Quito Centro)', 'tramaco-api')
            ),
            
            array(
                'type' => 'sectionend',
                'id' => 'tramaco_remitente_section'
            ),
            
            // Sección: SharePoint
            array(
                'title' => __('Integración con SharePoint', 'tramaco-api'),
                'type' => 'title',
                'desc' => __('Configura la integración con Microsoft SharePoint para exportar datos de guías a Excel.', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_section'
            ),
            
            array(
                'title' => __('Habilitar SharePoint', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_enabled',
                'type' => 'checkbox',
                'default' => 'no',
                'desc' => __('Enviar datos de guías a SharePoint automáticamente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Tenant ID', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_tenant_id',
                'type' => 'text',
                'default' => '',
                'desc' => __('ID del tenant de Azure AD.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Client ID', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_client_id',
                'type' => 'text',
                'default' => '',
                'desc' => __('ID de la aplicación registrada en Azure AD.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Client Secret', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_client_secret',
                'type' => 'password',
                'default' => '',
                'desc' => __('Secreto de la aplicación.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Site ID', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_site_id',
                'type' => 'text',
                'default' => '',
                'desc' => __('ID del sitio de SharePoint. Formato: hostname,site-id,web-id', 'tramaco-api')
            ),
            
            array(
                'title' => __('Drive ID', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_drive_id',
                'type' => 'text',
                'default' => '',
                'desc' => __('ID del drive de SharePoint (opcional).', 'tramaco-api')
            ),
            
            array(
                'title' => __('Ruta del archivo Excel', 'tramaco-api'),
                'id' => 'tramaco_sharepoint_file_path',
                'type' => 'text',
                'default' => '/Guias/guias-tramaco.xlsx',
                'desc' => __('Ruta del archivo Excel dentro del SharePoint.', 'tramaco-api')
            ),
            
            array(
                'type' => 'sectionend',
                'id' => 'tramaco_sharepoint_section'
            ),
            
            // Sección: Notificaciones
            array(
                'title' => __('Notificaciones', 'tramaco-api'),
                'type' => 'title',
                'desc' => __('Configura las notificaciones relacionadas con el envío.', 'tramaco-api'),
                'id' => 'tramaco_notifications_section'
            ),
            
            array(
                'title' => __('Notificar al cliente', 'tramaco-api'),
                'id' => 'tramaco_notify_customer',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc' => __('Enviar email al cliente cuando se genera la guía.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Incluir PDF en email', 'tramaco-api'),
                'id' => 'tramaco_attach_pdf_email',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc' => __('Adjuntar el PDF de la guía en el email al cliente.', 'tramaco-api')
            ),
            
            array(
                'title' => __('Notificar al admin', 'tramaco-api'),
                'id' => 'tramaco_notify_admin',
                'type' => 'checkbox',
                'default' => 'no',
                'desc' => __('Enviar email al administrador cuando se genera una guía.', 'tramaco-api')
            ),
            
            array(
                'type' => 'sectionend',
                'id' => 'tramaco_notifications_section'
            )
        );
    }
    
    /**
     * Agregar submenú para configuración WooCommerce
     */
    public function add_wc_submenu() {
        add_submenu_page(
            'tramaco-api',
            __('Configuración WooCommerce', 'tramaco-api'),
            __('WooCommerce', 'tramaco-api'),
            'manage_options',
            'tramaco-api-woocommerce',
            array($this, 'wc_settings_page')
        );
    }
    
    /**
     * Página de configuración WooCommerce
     */
    public function wc_settings_page() {
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>' . __('WooCommerce no está instalado o activado.', 'tramaco-api') . '</p></div>';
            return;
        }
        
        // Procesar guardado
        if (isset($_POST['save_tramaco_wc_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'tramaco_wc_settings')) {
            $this->save_wc_settings();
            echo '<div class="notice notice-success"><p>' . __('Configuración guardada.', 'tramaco-api') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración de Tramaco para WooCommerce', 'tramaco-api'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('tramaco_wc_settings'); ?>
                
                <h2><?php _e('Generación de Guías', 'tramaco-api'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Generar guía automáticamente', 'tramaco-api'); ?></th>
                        <td>
                            <select name="tramaco_wc_auto_generate">
                                <?php
                                $current = get_option('tramaco_wc_auto_generate', 'payment_complete');
                                $options = array(
                                    'payment_complete' => __('Cuando el pago se completa', 'tramaco-api'),
                                    'processing' => __('Cuando el pedido pasa a "Procesando"', 'tramaco-api'),
                                    'completed' => __('Cuando el pedido se marca como "Completado"', 'tramaco-api'),
                                    'manual' => __('Solo manualmente', 'tramaco-api')
                                );
                                foreach ($options as $value => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($value),
                                        selected($current, $value, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Datos del Remitente', 'tramaco-api'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Nombre/Empresa', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_remitente_nombre" 
                                   value="<?php echo esc_attr(get_option('tramaco_remitente_nombre', get_bloginfo('name'))); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Teléfono', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_remitente_telefono" 
                                   value="<?php echo esc_attr(get_option('tramaco_remitente_telefono', '')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email', 'tramaco-api'); ?></th>
                        <td>
                            <input type="email" name="tramaco_remitente_email" 
                                   value="<?php echo esc_attr(get_option('tramaco_remitente_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Dirección', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_remitente_direccion" 
                                   value="<?php echo esc_attr(get_option('tramaco_remitente_direccion', '')); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Código Parroquia Origen', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_api_parroquia_origen" 
                                   value="<?php echo esc_attr(get_option('tramaco_api_parroquia_origen', '316')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Código de la parroquia donde se origina el envío.', 'tramaco-api'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Integración con SharePoint', 'tramaco-api'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Habilitar SharePoint', 'tramaco-api'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tramaco_sharepoint_enabled" value="1" 
                                       <?php checked(get_option('tramaco_sharepoint_enabled'), '1'); ?> />
                                <?php _e('Enviar datos de guías a SharePoint automáticamente', 'tramaco-api'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Tenant ID', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_sharepoint_tenant_id" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_tenant_id', '')); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Client ID', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_sharepoint_client_id" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_client_id', '')); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Client Secret', 'tramaco-api'); ?></th>
                        <td>
                            <input type="password" name="tramaco_sharepoint_client_secret" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_client_secret', '')); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Site ID', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_sharepoint_site_id" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_site_id', '')); ?>" 
                                   class="large-text" />
                            <p class="description"><?php _e('Formato: contoso.sharepoint.com,site-guid,web-guid', 'tramaco-api'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Ruta del archivo Excel', 'tramaco-api'); ?></th>
                        <td>
                            <input type="text" name="tramaco_sharepoint_file_path" 
                                   value="<?php echo esc_attr(get_option('tramaco_sharepoint_file_path', '/Guias/guias-tramaco.xlsx')); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Notificaciones', 'tramaco-api'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Notificar al cliente', 'tramaco-api'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tramaco_notify_customer" value="1" 
                                       <?php checked(get_option('tramaco_notify_customer', '1'), '1'); ?> />
                                <?php _e('Enviar email al cliente cuando se genera la guía', 'tramaco-api'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Adjuntar PDF', 'tramaco-api'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tramaco_attach_pdf_email" value="1" 
                                       <?php checked(get_option('tramaco_attach_pdf_email', '1'), '1'); ?> />
                                <?php _e('Adjuntar el PDF de la guía en el email al cliente', 'tramaco-api'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_tramaco_wc_settings" class="button-primary" 
                           value="<?php _e('Guardar Configuración', 'tramaco-api'); ?>" />
                </p>
            </form>
            
            <hr>
            
            <h2><?php _e('Instrucciones de SharePoint', 'tramaco-api'); ?></h2>
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h3><?php _e('Configurar Azure AD para SharePoint', 'tramaco-api'); ?></h3>
                <ol>
                    <li><?php _e('Ve a <a href="https://portal.azure.com" target="_blank">Azure Portal</a>', 'tramaco-api'); ?></li>
                    <li><?php _e('Navega a "Azure Active Directory" > "App registrations"', 'tramaco-api'); ?></li>
                    <li><?php _e('Crea una nueva aplicación', 'tramaco-api'); ?></li>
                    <li><?php _e('Anota el "Application (client) ID" y "Directory (tenant) ID"', 'tramaco-api'); ?></li>
                    <li><?php _e('Ve a "Certificates & secrets" y crea un nuevo secreto', 'tramaco-api'); ?></li>
                    <li><?php _e('Ve a "API permissions" y agrega:', 'tramaco-api'); ?>
                        <ul>
                            <li>Microsoft Graph > Sites.ReadWrite.All</li>
                            <li>Microsoft Graph > Files.ReadWrite.All</li>
                        </ul>
                    </li>
                    <li><?php _e('Concede permiso de administrador para los permisos agregados', 'tramaco-api'); ?></li>
                </ol>
                
                <h3><?php _e('Obtener Site ID', 'tramaco-api'); ?></h3>
                <p><?php _e('Usa esta URL en el navegador (reemplaza los valores):', 'tramaco-api'); ?></p>
                <code>https://graph.microsoft.com/v1.0/sites/{hostname}:/sites/{site-name}</code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Guardar configuración WooCommerce
     */
    private function save_wc_settings() {
        $options = array(
            'tramaco_wc_auto_generate',
            'tramaco_remitente_nombre',
            'tramaco_remitente_telefono',
            'tramaco_remitente_email',
            'tramaco_remitente_direccion',
            'tramaco_api_parroquia_origen',
            'tramaco_sharepoint_tenant_id',
            'tramaco_sharepoint_client_id',
            'tramaco_sharepoint_client_secret',
            'tramaco_sharepoint_site_id',
            'tramaco_sharepoint_file_path'
        );
        
        foreach ($options as $option) {
            if (isset($_POST[$option])) {
                update_option($option, sanitize_text_field($_POST[$option]));
            }
        }
        
        // Checkboxes
        update_option('tramaco_sharepoint_enabled', isset($_POST['tramaco_sharepoint_enabled']) ? '1' : '');
        update_option('tramaco_notify_customer', isset($_POST['tramaco_notify_customer']) ? '1' : '');
        update_option('tramaco_attach_pdf_email', isset($_POST['tramaco_attach_pdf_email']) ? '1' : '');
    }
}

// Inicializar
add_action('plugins_loaded', function() {
    Tramaco_WC_Settings::get_instance();
}, 25);
