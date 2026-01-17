# Guía de Implementación - Generación de Guías Tramaco

## 📋 Descripción General

Esta guía documenta el proceso completo para implementar la generación automática de guías de envío con Tramaco API cuando un cliente realiza una compra en un sitio e-commerce.

---

## 🔄 Flujo Completo del Proceso

### 1. Cliente en Checkout (Formulario de Envío)

El cliente completa el formulario de envío durante el proceso de compra. Los datos requeridos son:

- **Datos Personales:**
  - Nombres
  - Apellidos
  - Cédula/RUC
  - Teléfono
  - Email

- **Dirección de Envío:**
  - Provincia
  - Cantón
  - Parroquia (código)
  - Calle principal
  - Calle secundaria
  - Número de casa
  - Referencia

### 2. Confirmación de Pago

- El pago se procesa en la pasarela de pagos
- **Importante:** La guía solo se genera después de que el pago sea **confirmado y debitado**
- Esto evita generar guías para pagos rechazados o cancelados

### 3. Generación de Guía (Backend)

#### A) Autenticación con Tramaco

**Endpoint:**
```
POST https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/usuario/autenticar
```

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "login": "1793191845001",
    "password": "MAS.39inter.PIN"
}
```

**Response:**
```json
{
    "cuerpoRespuesta": {
        "codigo": 1,
        "mensaje": "EXITO"
    },
    "salidaAutenticarUsuarioJWTWs": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
    }
}
```

**Nota:** Guardar el token JWT para usarlo en todas las peticiones siguientes.

---

#### B) Generar la Guía

**Endpoint:**
```
POST https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarGuia
```

**Headers:**
```
Content-Type: application/json
Authorization: [TOKEN_JWT]
```

**Request Body:**
```json
{
    "lstCargaDestino": [
        {
            "id": 1,
            "datoAdicional": {
                "motivo": "",
                "citacion": "",
                "boleta": ""
            },
            "destinatario": {
                "nombres": "JUAN",
                "apellidos": "PEREZ GARCIA",
                "codigoParroquia": 316,
                "telefono": "0984652402",
                "email": "cliente@ejemplo.com",
                "callePrimaria": "AV PRINCIPAL",
                "calleSecundaria": "CALLE SECUNDARIA",
                "numero": "123",
                "referencia": "CERCA DEL PARQUE",
                "ciRuc": "0912345678",
                "tipoIden": "05",
                "codigoPostal": ""
            },
            "carga": {
                "peso": "2.5",
                "bultos": "1",
                "cajas": "1",
                "descripcion": "Productos de compra online",
                "producto": "36",
                "contrato": "6394",
                "localidad": "21580",
                "valorAsegurado": 0,
                "valorCobro": 0,
                "adjuntos": "",
                "alto": "",
                "ancho": "",
                "largo": "",
                "cantidadDoc": "",
                "observacion": "",
                "referenciaTercero": ""
            }
        }
    ],
    "remitente": {
        "nombres": "TU EMPRESA",
        "apellidos": "SA",
        "codigoParroquia": 316,
        "telefono": "0981234567",
        "email": "info@tuempresa.com",
        "callePrimaria": "TU DIRECCION COMERCIAL",
        "calleSecundaria": "",
        "numero": "123",
        "referencia": "REFERENCIA DEL LOCAL",
        "ciRuc": "1793191845001",
        "tipoIden": "04",
        "codigoPostal": ""
    }
}
```

**Response Exitosa:**
```json
{
    "cuerpoRespuesta": {
        "codigo": 1,
        "mensaje": "EXITO"
    },
    "salidaGenerarGuiaWs": {
        "lstGuias": [
            {
                "id": 1,
                "guia": "031002005633799"
            }
        ]
    }
}
```

**Códigos de Respuesta:**
- `codigo: 1` = EXITO
- `codigo: 2` = ERROR
- `codigo: 3` = EXCEPCION

---

#### C) Generar PDF de la Guía

**Endpoint:**
```
POST https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarPdf
```

**Headers:**
```
Content-Type: application/json
Authorization: [TOKEN_JWT]
```

**Request Body:**
```json
{
    "guias": ["031002005633799"]
}
```

**Response:**
- Retorna un `InputStream` con el archivo PDF
- Este PDF debe ser descargado y guardado para adjuntarlo al correo del proveedor

---

### 4. Envío de Correos Electrónicos

#### Email al Cliente

**Asunto:** "Confirmación de Compra #[NUMERO_ORDEN]"

**Contenido:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h2>¡Gracias por tu compra!</h2>
    
    <p>Tu pedido ha sido confirmado y está siendo preparado para envío.</p>
    
    <div style="background-color: #f0f0f0; padding: 15px; margin: 20px 0;">
        <h3 style="margin-top: 0;">📦 Número de Guía: 031002005633799</h3>
        <p>
            <a href="https://tu-sitio.com/tracking?guia=031002005633799" 
               style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Rastrear mi Pedido
            </a>
        </p>
    </div>
    
    <h3>Detalles del Pedido:</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Producto</th>
            <th style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">Cantidad</th>
            <th style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">Precio</th>
        </tr>
        <!-- Productos aquí -->
    </table>
    
    <h3>Dirección de Envío:</h3>
    <p>
        [Nombre Cliente]<br>
        [Dirección Completa]<br>
        [Ciudad, Provincia]<br>
        Tel: [Teléfono]
    </p>
    
    <p style="margin-top: 30px; color: #666;">
        Recibirás otro email cuando tu pedido sea despachado.<br>
        Tiempo estimado de entrega: 2-5 días hábiles.
    </p>
</body>
</html>
```

---

#### Email al Proveedor/Logística

**Asunto:** "Nueva Orden para Despacho #[NUMERO_ORDEN]"

**Contenido:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h2>🚚 Nueva Orden Recibida</h2>
    
    <div style="background-color: #fff3cd; padding: 15px; margin: 20px 0;">
        <strong>Orden:</strong> #12345<br>
        <strong>Fecha:</strong> 15/01/2026 14:30<br>
        <strong>Guía Tramaco:</strong> 031002005633799<br>
        <strong>Estado:</strong> PENDIENTE DE PREPARACIÓN
    </div>
    
    <h3>Datos del Cliente:</h3>
    <table style="border-collapse: collapse; width: 100%;">
        <tr>
            <td style="padding: 5px;"><strong>Nombre:</strong></td>
            <td style="padding: 5px;">Juan Pérez García</td>
        </tr>
        <tr>
            <td style="padding: 5px;"><strong>Teléfono:</strong></td>
            <td style="padding: 5px;">0984652402</td>
        </tr>
        <tr>
            <td style="padding: 5px;"><strong>Email:</strong></td>
            <td style="padding: 5px;">cliente@ejemplo.com</td>
        </tr>
        <tr>
            <td style="padding: 5px;"><strong>Dirección:</strong></td>
            <td style="padding: 5px;">
                Av Principal y Calle Secundaria, #123<br>
                Cerca del parque<br>
                Quito, Pichincha
            </td>
        </tr>
    </table>
    
    <h3>Productos a Enviar:</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr style="background-color: #f8f9fa;">
            <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">SKU</th>
            <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Producto</th>
            <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">Cantidad</th>
        </tr>
        <!-- Lista de productos aquí -->
    </table>
    
    <h3>Instrucciones de Empaque:</h3>
    <ul>
        <li>Verificar stock de todos los productos</li>
        <li>Empacar con cuidado y protección adecuada</li>
        <li><strong>IMPORTANTE:</strong> Adjuntar e imprimir la guía Tramaco (PDF adjunto)</li>
        <li>Coordinar el retiro con Tramaco</li>
    </ul>
    
    <p style="margin-top: 30px; padding: 15px; background-color: #d4edda; border-left: 4px solid #28a745;">
        <strong>📄 PDF de guía adjunto</strong><br>
        Imprimir y pegar en el paquete antes de entregarlo a Tramaco.
    </p>
</body>
</html>
```

**Adjunto:** `guia-031002005633799.pdf`

---

## 💻 Implementación en Código

### Estructura para WordPress/WooCommerce

#### 1. Hook Principal - Trigger después del pago

```php
<?php
/**
 * Generar guía Tramaco después de confirmar el pago
 */
add_action('woocommerce_payment_complete', 'generar_guia_tramaco', 10, 1);

function generar_guia_tramaco($order_id) {
    $order = wc_get_order($order_id);
    
    // Verificar que no se haya generado una guía previamente
    if (get_post_meta($order_id, '_tramaco_guia', true)) {
        error_log("La orden $order_id ya tiene una guía generada");
        return;
    }
    
    // 1. Autenticar con Tramaco
    $token = tramaco_autenticar();
    
    if (!$token) {
        error_log("Error: No se pudo autenticar con Tramaco para orden $order_id");
        $order->add_order_note('⚠️ Error al autenticar con Tramaco API');
        return;
    }
    
    // 2. Preparar datos de la guía
    $datos_guia = preparar_datos_guia($order);
    
    // 3. Generar la guía
    $resultado = tramaco_generar_guia($token, $datos_guia);
    
    if ($resultado['success']) {
        $numero_guia = $resultado['guia'];
        
        // 4. Guardar número de guía en el pedido
        update_post_meta($order_id, '_tramaco_guia', $numero_guia);
        $order->add_order_note('✅ Guía Tramaco generada: ' . $numero_guia);
        
        // 5. Generar PDF de la guía
        $pdf_content = tramaco_generar_pdf($token, $numero_guia);
        
        // 6. Enviar correos
        enviar_correo_cliente($order, $numero_guia);
        enviar_correo_proveedor($order, $numero_guia, $pdf_content);
        
        error_log("✓ Guía generada exitosamente: $numero_guia para orden $order_id");
    } else {
        error_log("✗ Error generando guía para orden $order_id: " . $resultado['mensaje']);
        $order->add_order_note('⚠️ Error al generar guía Tramaco: ' . $resultado['mensaje']);
    }
}
```

---

#### 2. Función de Autenticación

```php
<?php
/**
 * Autenticar con la API de Tramaco
 * @return string|false Token JWT o false si falla
 */
function tramaco_autenticar() {
    $login = get_option('tramaco_api_login', '1793191845001');
    $password = get_option('tramaco_api_password', 'MAS.39inter.PIN');
    
    $response = wp_remote_post('https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/usuario/autenticar', array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'login' => $login,
            'password' => $password
        )),
        'timeout' => 30,
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        error_log('Error de autenticación Tramaco: ' . $response->get_error_message());
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Intentar obtener token de diferentes estructuras posibles
    if (isset($body['salidaAutenticarUsuarioJWTWs']['token'])) {
        return $body['salidaAutenticarUsuarioJWTWs']['token'];
    } elseif (isset($body['cuerpoRespuesta']['salidaAutenticarUsuarioJWTWs']['token'])) {
        return $body['cuerpoRespuesta']['salidaAutenticarUsuarioJWTWs']['token'];
    } elseif (isset($body['token'])) {
        return $body['token'];
    }
    
    return false;
}
```

---

#### 3. Preparar Datos de la Guía

```php
<?php
/**
 * Preparar datos de la guía desde el pedido
 * @param WC_Order $order
 * @return array
 */
function preparar_datos_guia($order) {
    $order_id = $order->get_id();
    
    return array(
        'lstCargaDestino' => array(
            array(
                'id' => $order_id,
                'datoAdicional' => array(
                    'motivo' => '',
                    'citacion' => '',
                    'boleta' => ''
                ),
                'destinatario' => array(
                    'nombres' => $order->get_shipping_first_name(),
                    'apellidos' => $order->get_shipping_last_name(),
                    'codigoParroquia' => intval(get_post_meta($order_id, '_shipping_parroquia_code', true)),
                    'telefono' => $order->get_billing_phone(),
                    'email' => $order->get_billing_email() ?: ' ',
                    'callePrimaria' => $order->get_shipping_address_1(),
                    'calleSecundaria' => $order->get_shipping_address_2() ?: ' ',
                    'numero' => get_post_meta($order_id, '_shipping_numero', true) ?: 'S/N',
                    'referencia' => get_post_meta($order_id, '_shipping_referencia', true) ?: ' ',
                    'ciRuc' => get_post_meta($order_id, '_billing_cedula', true),
                    'tipoIden' => '05', // 05 = Cédula
                    'codigoPostal' => ''
                ),
                'carga' => array(
                    'peso' => calcular_peso_total($order),
                    'bultos' => '1',
                    'cajas' => '1',
                    'descripcion' => obtener_descripcion_productos($order),
                    'producto' => get_option('tramaco_api_producto', '36'),
                    'contrato' => get_option('tramaco_api_contrato', '6394'),
                    'localidad' => get_option('tramaco_api_localidad', '21580'),
                    'valorAsegurado' => 0,
                    'valorCobro' => 0,
                    'adjuntos' => '',
                    'alto' => '',
                    'ancho' => '',
                    'largo' => '',
                    'cantidadDoc' => '',
                    'observacion' => '',
                    'referenciaTercero' => ''
                )
            )
        ),
        'remitente' => obtener_datos_remitente()
    );
}

/**
 * Calcular peso total del pedido
 * @param WC_Order $order
 * @return string
 */
function calcular_peso_total($order) {
    $peso_total = 0;
    
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $peso = $product->get_weight();
        $cantidad = $item->get_quantity();
        
        if ($peso) {
            $peso_total += floatval($peso) * $cantidad;
        }
    }
    
    // Peso mínimo de 0.5 kg si no tiene peso definido
    return $peso_total > 0 ? strval($peso_total) : '0.5';
}

/**
 * Obtener descripción de productos para la guía
 * @param WC_Order $order
 * @return string
 */
function obtener_descripcion_productos($order) {
    $productos = array();
    
    foreach ($order->get_items() as $item) {
        $productos[] = $item->get_name() . ' x' . $item->get_quantity();
    }
    
    $descripcion = implode(', ', $productos);
    
    // Limitar a 200 caracteres
    if (strlen($descripcion) > 200) {
        $descripcion = substr($descripcion, 0, 197) . '...';
    }
    
    return $descripcion;
}

/**
 * Obtener datos del remitente (tu empresa)
 * @return array
 */
function obtener_datos_remitente() {
    return array(
        'nombres' => get_option('tramaco_remitente_nombres', 'TU EMPRESA'),
        'apellidos' => get_option('tramaco_remitente_apellidos', 'SA'),
        'codigoParroquia' => intval(get_option('tramaco_remitente_parroquia', '316')),
        'telefono' => get_option('tramaco_remitente_telefono', '0981234567'),
        'email' => get_option('tramaco_remitente_email', 'info@tuempresa.com'),
        'callePrimaria' => get_option('tramaco_remitente_calle', 'TU DIRECCION'),
        'calleSecundaria' => '',
        'numero' => get_option('tramaco_remitente_numero', '123'),
        'referencia' => get_option('tramaco_remitente_referencia', ''),
        'ciRuc' => get_option('tramaco_api_login', '1793191845001'),
        'tipoIden' => '04', // 04 = RUC
        'codigoPostal' => ''
    );
}
```

---

#### 4. Generar Guía

```php
<?php
/**
 * Generar guía en Tramaco
 * @param string $token
 * @param array $datos_guia
 * @return array ['success' => bool, 'guia' => string, 'mensaje' => string]
 */
function tramaco_generar_guia($token, $datos_guia) {
    $response = wp_remote_post('https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarGuia', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => $token
        ),
        'body' => json_encode($datos_guia),
        'timeout' => 30,
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'guia' => null,
            'mensaje' => $response->get_error_message()
        );
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Verificar respuesta exitosa
    $codigo = isset($body['cuerpoRespuesta']['codigo']) ? $body['cuerpoRespuesta']['codigo'] : null;
    
    if ($codigo == 1 && isset($body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'])) {
        return array(
            'success' => true,
            'guia' => $body['salidaGenerarGuiaWs']['lstGuias'][0]['guia'],
            'mensaje' => 'Guía generada exitosamente'
        );
    } else {
        $mensaje = isset($body['cuerpoRespuesta']['mensaje']) ? $body['cuerpoRespuesta']['mensaje'] : 'Error desconocido';
        return array(
            'success' => false,
            'guia' => null,
            'mensaje' => $mensaje
        );
    }
}
```

---

#### 5. Generar PDF

```php
<?php
/**
 * Generar PDF de la guía
 * @param string $token
 * @param string $numero_guia
 * @return string|false Contenido del PDF o false si falla
 */
function tramaco_generar_pdf($token, $numero_guia) {
    $response = wp_remote_post('https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarPdf', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => $token
        ),
        'body' => json_encode(array(
            'guias' => array($numero_guia)
        )),
        'timeout' => 30,
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        error_log('Error generando PDF: ' . $response->get_error_message());
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['cuerpoRespuesta']['codigo']) && $body['cuerpoRespuesta']['codigo'] == 1) {
        // El PDF viene en base64 en el campo inStrPfd
        if (isset($body['salidaGenerarPdfWs']['inStrPfd'])) {
            return base64_decode($body['salidaGenerarPdfWs']['inStrPfd']);
        }
    }
    
    return false;
}
```

---

#### 6. Enviar Email al Cliente

```php
<?php
/**
 * Enviar email de confirmación al cliente
 * @param WC_Order $order
 * @param string $numero_guia
 */
function enviar_correo_cliente($order, $numero_guia) {
    $to = $order->get_billing_email();
    $subject = 'Confirmación de Compra #' . $order->get_order_number();
    
    $tracking_url = home_url('/tracking?guia=' . $numero_guia);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .guia-box { background-color: #fff; border: 2px solid #007bff; padding: 15px; margin: 20px 0; text-align: center; }
            .button { display: inline-block; background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .productos { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .productos th, .productos td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .productos th { background-color: #f0f0f0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>¡Gracias por tu compra!</h1>
            </div>
            
            <div class="content">
                <p>Hola <strong><?php echo esc_html($order->get_billing_first_name()); ?></strong>,</p>
                
                <p>Tu pedido <strong>#<?php echo $order->get_order_number(); ?></strong> ha sido confirmado y está siendo preparado para envío.</p>
                
                <div class="guia-box">
                    <h2 style="margin-top: 0;">📦 Número de Guía</h2>
                    <h1 style="color: #007bff; margin: 10px 0;"><?php echo esc_html($numero_guia); ?></h1>
                    <a href="<?php echo esc_url($tracking_url); ?>" class="button">Rastrear mi Pedido</a>
                </div>
                
                <h3>Detalles del Pedido:</h3>
                <table class="productos">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: right;">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->get_name()); ?></td>
                            <td style="text-align: center;"><?php echo $item->get_quantity(); ?></td>
                            <td style="text-align: right;"><?php echo $order->get_formatted_line_subtotal($item); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align: right;"><strong>Total:</strong></td>
                            <td style="text-align: right;"><strong><?php echo $order->get_formatted_order_total(); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <h3>Dirección de Envío:</h3>
                <p>
                    <strong><?php echo esc_html($order->get_formatted_shipping_full_name()); ?></strong><br>
                    <?php echo esc_html($order->get_shipping_address_1()); ?><br>
                    <?php if ($order->get_shipping_address_2()): ?>
                        <?php echo esc_html($order->get_shipping_address_2()); ?><br>
                    <?php endif; ?>
                    <?php echo esc_html($order->get_shipping_city()); ?>, <?php echo esc_html($order->get_shipping_state()); ?><br>
                    Tel: <?php echo esc_html($order->get_billing_phone()); ?>
                </p>
                
                <p style="margin-top: 30px; padding: 15px; background-color: #d4edda; border-left: 4px solid #28a745;">
                    <strong>📅 Tiempo estimado de entrega:</strong> 2-5 días hábiles<br>
                    Recibirás una notificación cuando tu pedido sea despachado.
                </p>
            </div>
            
            <div class="footer">
                <p>Si tienes alguna pregunta, contáctanos a <?php echo get_option('admin_email'); ?></p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $message = ob_get_clean();
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    wp_mail($to, $subject, $message, $headers);
}
```

---

#### 7. Enviar Email al Proveedor

```php
<?php
/**
 * Enviar email al proveedor/logística con PDF adjunto
 * @param WC_Order $order
 * @param string $numero_guia
 * @param string $pdf_content Contenido binario del PDF
 */
function enviar_correo_proveedor($order, $numero_guia, $pdf_content) {
    $to = get_option('tramaco_email_logistica', get_option('admin_email'));
    $subject = 'Nueva Orden para Despacho #' . $order->get_order_number();
    
    // Guardar PDF temporalmente
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/guias-tramaco';
    
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $pdf_file = $pdf_dir . '/guia-' . $numero_guia . '.pdf';
    file_put_contents($pdf_file, $pdf_content);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 700px; margin: 0 auto; padding: 20px; }
            .alert { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
            .info-table td:first-child { font-weight: bold; width: 150px; }
            .productos { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .productos th, .productos td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            .productos th { background-color: #f8f9fa; }
            .importante { background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚚 Nueva Orden para Despacho</h1>
            
            <div class="alert">
                <strong>Orden:</strong> #<?php echo $order->get_order_number(); ?><br>
                <strong>Fecha:</strong> <?php echo $order->get_date_created()->format('d/m/Y H:i'); ?><br>
                <strong>Guía Tramaco:</strong> <span style="font-size: 18px; color: #007bff;"><?php echo esc_html($numero_guia); ?></span><br>
                <strong>Estado:</strong> PENDIENTE DE PREPARACIÓN
            </div>
            
            <h2>Datos del Cliente:</h2>
            <table class="info-table">
                <tr>
                    <td>Nombre:</td>
                    <td><?php echo esc_html($order->get_formatted_shipping_full_name()); ?></td>
                </tr>
                <tr>
                    <td>Teléfono:</td>
                    <td><?php echo esc_html($order->get_billing_phone()); ?></td>
                </tr>
                <tr>
                    <td>Email:</td>
                    <td><?php echo esc_html($order->get_billing_email()); ?></td>
                </tr>
                <tr>
                    <td>Cédula/RUC:</td>
                    <td><?php echo esc_html(get_post_meta($order->get_id(), '_billing_cedula', true)); ?></td>
                </tr>
                <tr>
                    <td>Dirección:</td>
                    <td>
                        <?php echo esc_html($order->get_shipping_address_1()); ?><br>
                        <?php if ($order->get_shipping_address_2()): ?>
                            <?php echo esc_html($order->get_shipping_address_2()); ?><br>
                        <?php endif; ?>
                        <?php 
                        $referencia = get_post_meta($order->get_id(), '_shipping_referencia', true);
                        if ($referencia) {
                            echo '<strong>Ref:</strong> ' . esc_html($referencia) . '<br>';
                        }
                        ?>
                        <?php echo esc_html($order->get_shipping_city()); ?>, <?php echo esc_html($order->get_shipping_state()); ?>
                    </td>
                </tr>
            </table>
            
            <h2>Productos a Enviar:</h2>
            <table class="productos">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th style="text-align: center;">Cantidad</th>
                        <th>Ubicación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item): 
                        $product = $item->get_product();
                    ?>
                    <tr>
                        <td><?php echo esc_html($product->get_sku()); ?></td>
                        <td><?php echo esc_html($item->get_name()); ?></td>
                        <td style="text-align: center;"><strong><?php echo $item->get_quantity(); ?></strong></td>
                        <td><?php echo esc_html(get_post_meta($product->get_id(), '_warehouse_location', true)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Instrucciones de Empaque:</h2>
            <ol>
                <li>✓ Verificar stock de todos los productos listados</li>
                <li>✓ Revisar estado y calidad de los productos</li>
                <li>✓ Empacar con material de protección adecuado</li>
                <li>✓ <strong>IMPORTANTE:</strong> Imprimir la guía Tramaco adjunta en este email</li>
                <li>✓ Pegar la guía impresa en el exterior del paquete</li>
                <li>✓ Incluir factura dentro del paquete</li>
                <li>✓ Coordinar retiro con Tramaco</li>
            </ol>
            
            <div class="importante">
                <h3 style="margin-top: 0;">📄 Guía de Envío</h3>
                <p><strong>Archivo adjunto:</strong> guia-<?php echo esc_html($numero_guia); ?>.pdf</p>
                <p>Por favor, <strong>imprimir este PDF</strong> y pegarlo en el paquete antes de entregarlo a Tramaco.</p>
            </div>
            
            <p style="margin-top: 30px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
                <strong>Nota:</strong> Una vez despachado el pedido, actualizar el estado en el sistema a "Enviado" 
                e ingresar la información de tracking.
            </p>
        </div>
    </body>
    </html>
    <?php
    $message = ob_get_clean();
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Sistema de Pedidos <' . get_option('admin_email') . '>'
    );
    
    $sent = wp_mail($to, $subject, $message, $headers, array($pdf_file));
    
    // Opcional: Eliminar archivo temporal después de enviar
    // unlink($pdf_file);
    
    if (!$sent) {
        error_log("Error al enviar email al proveedor para orden " . $order->get_id());
    }
}
```

---

## 📊 Datos de Configuración

### Credenciales de Prueba (QA)

```php
// Configuración en WordPress Options o archivo de configuración
define('TRAMACO_API_LOGIN', '1793191845001');
define('TRAMACO_API_PASSWORD', 'MAS.39inter.PIN');
define('TRAMACO_API_CONTRATO', '6394');
define('TRAMACO_API_LOCALIDAD', '21580');
define('TRAMACO_API_PRODUCTO', '36');
```

### Endpoints de la API

| Servicio | URL |
|----------|-----|
| Autenticación | `https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/usuario/autenticar` |
| Generar Guía | `https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarGuia` |
| Generar PDF | `https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarPdf` |
| Tracking | `https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/consultarTracking` |

---

## 🔑 Campos Importantes

### Tipos de Identificación (tipoIden)

| Código | Descripción |
|--------|-------------|
| `05` | Cédula de Ciudadanía |
| `04` | RUC (Registro Único de Contribuyentes) |
| `06` | Pasaporte |
| `07` | Venta a Consumidor Final |
| `08` | Identificación del Exterior |
| `09` | Placa |

### Campos Obligatorios

**Destinatario (Cliente):**
- ✅ nombres
- ✅ apellidos
- ✅ telefono
- ✅ ciRuc (Cédula/RUC)
- ✅ codigoParroquia (ID de parroquia)
- ✅ callePrimaria
- ✅ calleSecundaria (puede ser espacio vacío)
- ✅ numero
- ✅ referencia (puede ser espacio vacío)
- ✅ tipoIden

**Carga:**
- ✅ peso (en kilogramos)
- ✅ descripcion
- ✅ producto (ID del producto del contrato)
- ✅ contrato (ID del contrato)
- ✅ localidad (ID de localidad origen)

**Remitente (Tu Empresa):**
- ✅ Todos los campos similares al destinatario

---

## 🧪 Pruebas

### Guías de Prueba para Testing

Puedes usar estos números de guía para probar el tracking:
- `031002005633799`
- `031002005633800`

### Código de Parroquia de Prueba

- `316` = BOLIVAR (GUAYAQUIL)

### Flujo de Prueba Completo

1. **Crear orden de prueba** en WooCommerce
2. **Marcar como pagada** manualmente
3. **Verificar logs** del servidor (`error_log`)
4. **Revisar bandeja de entrada** del cliente y proveedor
5. **Validar PDF** adjunto en email del proveedor
6. **Probar tracking** con el número de guía generado

---

## ⚠️ Consideraciones Importantes

### Seguridad

- ✅ **Validar pago antes de generar guía** (evitar guías para pagos rechazados)
- ✅ **No generar guías duplicadas** (verificar si ya existe)
- ✅ **Logs de auditoría** para cada generación
- ✅ **Credenciales en variables de entorno** (no hardcodear)

### Manejo de Errores

```php
try {
    // Intentar generar guía
    $resultado = generar_guia_tramaco($order_id);
    
    if (!$resultado['success']) {
        // Registrar error
        error_log("Error Tramaco: " . $resultado['mensaje']);
        
        // Notificar administrador
        wp_mail(
            get_option('admin_email'),
            'Error en generación de guía',
            "No se pudo generar la guía para la orden #$order_id"
        );
        
        // Agregar nota al pedido
        $order->add_order_note('⚠️ Error generando guía. Revisar manualmente.');
    }
} catch (Exception $e) {
    error_log("Excepción Tramaco: " . $e->getMessage());
}
```

### Performance

- ⚡ **Caché del token** JWT (válido por varias horas)
- ⚡ **Queue/Cola de trabajos** para procesar en background
- ⚡ **Timeout adecuado** (30 segundos recomendado)
- ⚡ **Reintentos automáticos** en caso de error temporal

### Campos del Formulario de Checkout

Asegurarse de recopilar estos campos adicionales:

```php
// Agregar campos personalizados al checkout
add_action('woocommerce_after_checkout_billing_form', 'agregar_campo_cedula');

function agregar_campo_cedula($checkout) {
    echo '<div class="custom-checkout-field">';
    
    woocommerce_form_field('billing_cedula', array(
        'type' => 'text',
        'class' => array('form-row-wide'),
        'label' => 'Cédula de Identidad',
        'placeholder' => 'Ingrese su cédula',
        'required' => true,
    ), $checkout->get_value('billing_cedula'));
    
    echo '</div>';
}

// Guardar el campo
add_action('woocommerce_checkout_update_order_meta', 'guardar_campo_cedula');

function guardar_campo_cedula($order_id) {
    if (!empty($_POST['billing_cedula'])) {
        update_post_meta($order_id, '_billing_cedula', sanitize_text_field($_POST['billing_cedula']));
    }
}
```

---

## 📝 Resumen del Flujo

```
┌─────────────────────┐
│  Cliente Checkout   │
│  (Llena Formulario) │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Procesa Pago       │
│  (Pasarela)         │
└──────────┬──────────┘
           │
           ▼ Pago Confirmado
┌─────────────────────┐
│  1. Autenticar      │
│     Tramaco API     │
└──────────┬──────────┘
           │
           ▼ Token JWT
┌─────────────────────┐
│  2. Generar Guía    │
│     POST /generarGuia│
└──────────┬──────────┘
           │
           ▼ Número de Guía
┌─────────────────────┐
│  3. Generar PDF     │
│     POST /generarPdf│
└──────────┬──────────┘
           │
           ▼ PDF Binary
┌─────────────────────┐
│  4. Enviar Emails   │
├─────────────────────┤
│  → Cliente          │
│    (Confirmación +  │
│     Nº Guía)        │
│                     │
│  → Proveedor        │
│    (PDF Adjunto +   │
│     Detalles)       │
└─────────────────────┘
```

---

## 🎯 Checklist de Implementación

- [ ] Configurar credenciales de Tramaco en WordPress
- [ ] Agregar campo "Cédula" al checkout
- [ ] Agregar campo "Código de Parroquia" (dropdown con provincias/cantones/parroquias)
- [ ] Agregar campo "Referencia" para dirección
- [ ] Implementar función de autenticación
- [ ] Implementar función de generación de guía
- [ ] Implementar función de generación de PDF
- [ ] Configurar hook `woocommerce_payment_complete`
- [ ] Crear template de email para cliente
- [ ] Crear template de email para proveedor
- [ ] Implementar manejo de errores y logs
- [ ] Crear página de tracking público
- [ ] Configurar datos del remitente (empresa)
- [ ] Probar con credenciales de QA
- [ ] Validar PDFs generados
- [ ] Verificar recepción de emails
- [ ] Documentar proceso para el equipo de logística

---

## 📞 Soporte

Para más información sobre la API de Tramaco:
- **Documentación:** MA.TI.2025.001-Manual-Técnico-Servicios-Rest-TOKEN-TI-QA_2025_SSL.txt
- **Ambiente de Pruebas:** https://wsqa.tramaco.com.ec/

---

**Última actualización:** Enero 2026
