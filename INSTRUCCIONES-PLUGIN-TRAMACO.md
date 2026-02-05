# 🚚 Tramaco API Integration para WooCommerce

## Guía de Configuración Post-Instalación

---

## 📋 Índice

1. [Requisitos Previos](#requisitos-previos)
2. [Instalación del Plugin](#instalación-del-plugin)
3. [WooCommerce Clásico vs WooCommerce Blocks](#woocommerce-clásico-vs-woocommerce-blocks)
4. [Configuración de Credenciales Tramaco](#configuración-de-credenciales-tramaco)
5. [Configuración del Método de Envío](#configuración-del-método-de-envío)
6. [Configuración de SharePoint (Opcional)](#configuración-de-sharepoint-opcional)
7. [Prueba del Sistema](#prueba-del-sistema)
8. [Solución de Problemas](#solución-de-problemas)

---

## 🔧 Requisitos Previos

Antes de instalar el plugin, asegúrate de tener:

- ✅ WordPress 5.0 o superior
- ✅ WooCommerce 4.0 o superior
- ✅ PHP 7.4 o superior
- ✅ Extensión PHP cURL habilitada
- ✅ Extensión PHP JSON habilitada
- ✅ Certificado SSL (HTTPS) en tu sitio
- ✅ Credenciales de API Tramaco (proporcionadas por Tramaco)

---

## 📦 Instalación del Plugin

### Opción 1: Subir ZIP desde WordPress Admin

1. Ve a **WordPress Admin → Plugins → Añadir nuevo**
2. Clic en **"Subir plugin"**
3. Selecciona el archivo `tramaco-api-integration.zip`
4. Clic en **"Instalar ahora"**
5. Una vez instalado, clic en **"Activar plugin"**

### Opción 2: Subir por FTP

1. Descomprime el archivo ZIP
2. Sube la carpeta `tramaco-api-integration` a `/wp-content/plugins/`
3. Ve a **WordPress Admin → Plugins**
4. Busca "Tramaco API Integration" y actívalo

---

## 🧱 WooCommerce Clásico vs WooCommerce Blocks

### ¿Por qué es importante saber qué versión usas?

El plugin necesita mostrar **selectores de Provincia, Cantón y Parroquia** para calcular el costo de envío con la API de Tramaco. Sin embargo, WooCommerce tiene **dos formas diferentes** de renderizar las páginas de carrito y checkout:

#### 1. WooCommerce Clásico (Shortcodes)

- Usa shortcodes como `[woocommerce_cart]` y `[woocommerce_checkout]`
- Los hooks de PHP tradicionales funcionan correctamente
- El plugin puede inyectar campos directamente usando `woocommerce_before_cart_totals`

#### 2. WooCommerce Blocks (Gutenberg) - A partir de WooCommerce 8.3+

- Usa bloques de Gutenberg como `<!-- wp:woocommerce/cart -->`
- Es una aplicación React que se renderiza en el cliente
- **Los hooks tradicionales de PHP NO funcionan** porque la página se construye con JavaScript
- El plugin debe inyectar el HTML dinámicamente via JavaScript en el footer

### ¿Cómo saber cuál estás usando?

1. Ve a **WordPress Admin → Páginas → Carrito**
2. Edita la página y observa:

| Si ves...                                           | Estás usando...         |
| --------------------------------------------------- | ----------------------- |
| Shortcode `[woocommerce_cart]` en el contenido      | WooCommerce **Clásico** |
| Bloques visuales con "Cart" en el editor de bloques | WooCommerce **Blocks**  |

### Compatibilidad del Plugin

✅ **El plugin soporta ambas versiones automáticamente:**

- **Clásico**: Los selectores se inyectan via hooks PHP tradicionales
- **Blocks**: Los selectores se inyectan via JavaScript en el footer de la página

> 💡 **Nota técnica**: Para WooCommerce Blocks, el plugin usa `wp_footer` para inyectar un script que detecta los contenedores de Blocks (`.wc-block-cart`, `.wp-block-woocommerce-cart`, etc.) e inserta el formulario de ubicación dinámicamente después de que React renderiza la página.

### Checkout en 2 Pasos

Debido a que el checkout de WooCommerce (ya sea clásico o Blocks) es un formulario predefinido que no podemos modificar fácilmente para agregar campos de parroquia, el plugin implementa un **flujo de 2 pasos**:

```
┌─────────────────────────────────────────────────────────────────┐
│  PASO 1: Página del Carrito (/cart/)                            │
│  ────────────────────────────────────────────────────────────   │
│  📍 Calcular costo de envío                                     │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐            │
│  │ Provincia ▼  │ │  Cantón ▼    │ │ Parroquia ▼  │            │
│  └──────────────┘ └──────────────┘ └──────────────┘            │
│                                                                 │
│  🚚 Costo de envío Tramaco: $5.44                              │
│                                                                 │
│  [Proceder al pago]                                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  PASO 2: Página de Checkout (/checkout/)                        │
│  ────────────────────────────────────────────────────────────   │
│  • Campos de ubicación pre-llenados automáticamente             │
│  • Costo de envío ya calculado                                  │
│  • El cliente solo completa datos de pago                       │
└─────────────────────────────────────────────────────────────────┘
```

Este enfoque permite:

- ✅ Mostrar el costo de envío ANTES de ir al checkout
- ✅ No modificar el formulario de checkout de WooCommerce
- ✅ Funcionar tanto con WooCommerce Clásico como con Blocks
- ✅ Pre-llenar los campos en el checkout si el cliente ya seleccionó ubicación

---

## 🔐 Configuración de Credenciales Tramaco

### Paso 1: Acceder a la Configuración

1. Ve a **WordPress Admin → WooCommerce → Ajustes**
2. Clic en la pestaña **"Tramaco API"**

### Paso 2: Ingresar Credenciales API

Completa los siguientes campos con los datos proporcionados por Tramaco:

| Campo                   | Descripción                          | Ejemplo         |
| ----------------------- | ------------------------------------ | --------------- |
| **Login (RUC/Cédula)**  | Tu número de RUC o cédula registrado | `1793191845001` |
| **Contraseña API**      | Contraseña proporcionada por Tramaco | `MiPassword123` |
| **ID Usuario**          | Identificador de usuario Tramaco     | `8651`          |
| **ID Contrato**         | Número de contrato con Tramaco       | `6394`          |
| **ID Localidad Origen** | Código de tu localidad de envío      | `21580`         |
| **ID Producto**         | Tipo de servicio contratado          | `36`            |

### Paso 3: Seleccionar Ambiente

- **Ambiente QA (Pruebas)**: Para probar la integración sin afectar datos reales
- **Ambiente Producción**: Para operación real con guías válidas

> ⚠️ **IMPORTANTE**: Comienza siempre en ambiente QA para verificar que todo funciona correctamente.

### Paso 4: Guardar Cambios

Clic en **"Guardar cambios"** y verifica que aparezca el mensaje de confirmación.

---

## 🚛 Configuración del Método de Envío

### Paso 1: Crear Zona de Envío

1. Ve a **WooCommerce → Ajustes → Envío**
2. Clic en **"Añadir zona de envío"**
3. Nombra la zona (ej: "Ecuador")
4. En "Regiones de zona", selecciona **Ecuador**
5. Guarda la zona

### Paso 2: Añadir Método Tramaco

1. En la zona creada, clic en **"Añadir método de envío"**
2. Selecciona **"Envío Tramaco"**
3. Clic en **"Añadir método de envío"**

### Paso 3: Configurar el Método

Clic en "Editar" junto al método Tramaco y configura:

| Opción                           | Descripción                         | Recomendación   |
| -------------------------------- | ----------------------------------- | --------------- |
| **Título**                       | Nombre que verán los clientes       | "Envío Tramaco" |
| **Habilitar cálculo automático** | Calcula precio según peso y destino | ✅ Activar      |
| **Margen adicional**             | Porcentaje extra sobre el costo     | 0-10%           |
| **Peso por defecto**             | Si producto no tiene peso           | 1 kg            |

---

## 📊 Configuración de SharePoint (Opcional)

Si deseas enviar automáticamente los datos de cada guía a un Excel en SharePoint:

### Paso 1: Crear Aplicación en Azure AD

1. Ve a [Azure Portal](https://portal.azure.com)
2. Navega a **Azure Active Directory → Registros de aplicaciones**
3. Clic en **"Nuevo registro"**
4. Configura:
   - Nombre: "Tramaco WooCommerce Integration"
   - Tipos de cuenta: "Solo esta organización"
   - URI de redirección: (dejar vacío)
5. Clic en **"Registrar"**

### Paso 2: Obtener Credenciales

1. Copia el **ID de aplicación (cliente)**
2. Copia el **ID de directorio (inquilino)**
3. Ve a **Certificados y secretos → Nuevo secreto de cliente**
4. Copia el **Valor del secreto** (solo visible una vez)

### Paso 3: Configurar Permisos

1. Ve a **Permisos de API → Agregar permiso**
2. Selecciona **Microsoft Graph**
3. Selecciona **Permisos de aplicación**
4. Añade estos permisos:
   - `Sites.ReadWrite.All`
   - `Files.ReadWrite.All`
5. Clic en **"Conceder consentimiento de administrador"**

### Paso 4: Preparar Excel en SharePoint

1. Crea un archivo Excel en SharePoint
2. Crea una tabla con estas columnas:
   ```
   Fecha | Hora | Pedido | Estado | Total | Guía | Fecha Guía |
   Destinatario | Teléfono | Email | Dirección | Ciudad | Parroquia |
   Productos | Cantidad | Costo Envío | PDF Guía | Link Pedido | Tracking
   ```
3. Nombra la tabla como "TablaPedidos"

### Paso 5: Configurar en WordPress

1. Ve a **WooCommerce → Ajustes → Tramaco API → SharePoint**
2. Ingresa:
   - Client ID
   - Client Secret
   - Tenant ID
   - Site ID (ID del sitio SharePoint)
   - Drive ID (ID del drive)
   - Item ID (ID del archivo Excel)
   - Nombre de la tabla
3. Guarda los cambios

---

## ✅ Prueba del Sistema

### Prueba 1: Verificar Conexión API

1. Ve a **WooCommerce → Ajustes → Tramaco API**
2. Clic en el botón **"Probar Conexión"**
3. Deberías ver: "✅ Conexión exitosa"

### Prueba 2: Verificar Cálculo de Envío

1. Ve a tu tienda
2. Añade un producto al carrito
3. Ve al carrito y selecciona una dirección de Ecuador
4. Verifica que aparezca el costo de envío Tramaco

### Prueba 3: Prueba de Pedido Completo

1. Crea un pedido de prueba
2. Completa el checkout
3. Verifica en **WooCommerce → Pedidos** que el pedido tenga el número de guía
4. En la página del pedido, verifica:
   - Número de guía Tramaco
   - Botón para descargar PDF
   - Link de tracking

---

## 🔄 Flujo Automático del Plugin

Una vez configurado, el plugin funciona así:

```
Cliente hace pedido → Selecciona Tramaco como envío → Pago completado
                                    ↓
                    Plugin genera guía automáticamente
                                    ↓
        ┌───────────────────────────┼───────────────────────────┐
        ↓                           ↓                           ↓
   Guía guardada              PDF almacenado             Datos enviados
   en el pedido               en WordPress               a SharePoint
        ↓                           ↓                           ↓
   Email enviado              Disponible para            Excel actualizado
   al cliente                 descargar                  automáticamente
```

---

## ❗ Solución de Problemas

### Error: "No se pudo autenticar"

- Verifica que las credenciales sean correctas
- Confirma que el ambiente seleccionado coincide con tus credenciales
- Contacta a Tramaco si las credenciales son nuevas

### Error: "No se pudo generar la guía"

- Verifica que todos los datos del cliente estén completos
- El RUC/Cédula del remitente debe ser válido
- Verifica que el contrato esté activo

### El costo de envío no aparece

- Verifica que la zona de envío incluya Ecuador
- Asegúrate de que el método Tramaco esté habilitado
- Los productos deben tener peso asignado

### Error de SharePoint

- Verifica los permisos de la aplicación Azure AD
- Confirma que el archivo Excel existe y tiene la tabla correcta
- Revisa que los IDs de Site, Drive e Item sean correctos

### El PDF no se genera

- Verifica que el número de guía sea válido
- La guía debe existir en el sistema Tramaco
- En ambiente QA, algunas guías de prueba pueden no generar PDF

---

## 📞 Soporte

### Tramaco

- **Teléfono**: (02) 299-0000
- **Email**: soporte@tramaco.com.ec
- **Web**: https://www.tramaco.com.ec

### Plugin

- Revisa los logs en **WooCommerce → Estado → Logs**
- Busca archivos que empiecen con "tramaco-"

---

## 📝 Notas Importantes

1. **Ambiente de Producción**: Solo cambia a producción cuando hayas probado todo en QA
2. **Backup**: Siempre haz backup antes de actualizar el plugin
3. **SSL**: El plugin requiere HTTPS para funcionar correctamente
4. **Logs**: Habilita los logs en desarrollo para depurar problemas

---

_Última actualización: Enero 2026_
_Versión del plugin: 1.1.0_
