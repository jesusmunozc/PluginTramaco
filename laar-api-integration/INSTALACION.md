# Guía de Instalación - Laar Courier API Integration

## 📋 Requisitos Previos

- WordPress 5.0 o superior
- PHP 7.4 o superior
- Extensión PHP cURL habilitada
- Credenciales de API de Laar Courier

## 🚀 Instalación

### Método 1: Subir vía FTP/SFTP

1. Descarga la carpeta `laar-api-integration`
2. Conéctate a tu servidor vía FTP/SFTP
3. Sube la carpeta a `/wp-content/plugins/`
4. Ve al panel de WordPress > Plugins
5. Busca "Laar Courier API Integration" y actívalo

### Método 2: Subir como ZIP

1. Comprime la carpeta `laar-api-integration` en un archivo ZIP
2. En WordPress, ve a **Plugins > Añadir nuevo > Subir plugin**
3. Selecciona el archivo ZIP
4. Haz clic en "Instalar ahora"
5. Activa el plugin

## ⚙️ Configuración

### Paso 1: Acceder a la Configuración

1. Ve al menú lateral de WordPress
2. Haz clic en **Laar Courier**
3. Ingresa tus credenciales:
   - **Usuario:** `prueba.star.brands.api`
   - **Contraseña:** `ISwoaA8B`

### Paso 2: Probar Conexión

1. Haz clic en el botón **"Probar Autenticación"**
2. Deberías ver un mensaje de éxito con la información de tu cuenta:
   - Código de usuario
   - Código de sucursal
   - RUC asociado
3. También se cargarán los productos disponibles

## 📝 Uso de Shortcodes

### Tracking de Envíos

```html
[laar_tracking]
```

Muestra un formulario para que los clientes consulten el estado de sus envíos.

### Cotizador

```html
[laar_cotizacion]
```

Permite a los usuarios calcular el costo de envío seleccionando origen, destino, peso y tipo de servicio.

### Generar Guía (Solo usuarios logueados)

```html
[laar_generar_guia]
```

Formulario completo para generar guías de envío. Solo disponible para usuarios con sesión iniciada.

## 🎨 Personalización de Estilos

Los estilos se pueden personalizar editando:

- `assets/css/laar-styles.css` - Estilos del frontend
- `assets/css/laar-admin.css` - Estilos del panel admin

### Variables CSS Personalizables

```css
:root {
  --laar-primary: #ff6b00; /* Color principal (naranja) */
  --laar-secondary: #1a237e; /* Color secundario (azul) */
  --laar-success: #4caf50; /* Color de éxito */
  --laar-error: #f44336; /* Color de error */
}
```

## 🔧 Funciones de Desarrollo

### Obtener Token de API

```php
$plugin = Laar_API_Integration::get_instance();
$token = $plugin->get_token();
```

### Autenticación Manual

```php
$plugin = Laar_API_Integration::get_instance();
$result = $plugin->authenticate();
if ($result['success']) {
    $userInfo = $result['data'];
}
```

## 📡 Endpoints de API Disponibles

| Endpoint                        | Método | Descripción        |
| ------------------------------- | ------ | ------------------ |
| `/authenticate`                 | POST   | Autenticación      |
| `/guias/{sucursal}`             | POST   | Generar guía       |
| `/clientes/{guia}/tracking`     | GET    | Consultar tracking |
| `/guias/pdfs/{guia}/{sucursal}` | GET    | Obtener PDF        |
| `/ciudades`                     | GET    | Listar ciudades    |
| `/productos`                    | GET    | Listar productos   |
| `/cotizadores/tarifanormal`     | POST   | Calcular tarifa    |

## 🛠️ Solución de Problemas

### Error de Autenticación

1. Verifica que las credenciales sean correctas
2. Comprueba que tu servidor tenga acceso a `https://api.laarcourier.com:9727`
3. Asegúrate de que cURL esté habilitado en PHP

### Las ciudades no cargan

1. Verifica la conexión a internet del servidor
2. Revisa los logs de PHP para errores
3. El cache de ciudades dura 24 horas

### SSL/Certificados

Si tienes problemas con certificados SSL, contacta a tu proveedor de hosting o verifica la configuración de cURL.

## 📞 Soporte

Para soporte técnico o consultas sobre las APIs de Laar Courier:

- **API Laar Courier:** https://api.laarcourier.com
- **Documentación:** Consulta el manual técnico proporcionado

---

**Desarrollado para Star Brand** | Versión 1.0.0
