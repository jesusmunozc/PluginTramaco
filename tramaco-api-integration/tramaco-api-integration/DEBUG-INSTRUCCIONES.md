# 🔍 Instrucciones de Debug - Tramaco Shipping

## 🚨 Si el costo de envío no se calcula

Sigue estos pasos para diagnosticar el problema:

---

## 1️⃣ Activar modo DEBUG en WordPress

Edita `wp-config.php` y agrega/modifica estas líneas:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Esto creará logs en: `wp-content/debug.log`

---

## 2️⃣ Abrir la Consola del Navegador

1. **Abre tu sitio en Chrome/Firefox**
2. **Presiona F12** (o clic derecho → Inspeccionar)
3. **Ve a la pestaña "Consola"**
4. **Mantén la consola abierta mientras pruebas**

---

## 3️⃣ Probar el Checkout

1. **Agrega un producto al carrito**
2. **Ve al Checkout**
3. **Abre la consola (F12)**
4. **Selecciona:**
   - Provincia: Pichincha
   - Cantón: Quito
   - Parroquia: (cualquiera)

---

## 4️⃣ Verificar en la Consola

**✅ Deberías ver:**

```
Tramaco: Parroquia guardada en sesión: 101
Tramaco: Actualizando checkout...
```

**❌ Si ves errores:**

```javascript
Error: tramacoCheckout is not defined
// ☝️ El script no se cargó correctamente

POST admin-ajax.php 400 (Bad Request)
// ☝️ Error en la llamada AJAX
```

---

## 5️⃣ Revisar el Log de WordPress

Abre `wp-content/debug.log` y busca:

### ✅ Si funciona correctamente:

```
[21-Jan-2026 10:30:15] [Tramaco] Parroquia guardada en sesión: 101
[21-Jan-2026 10:30:16] [Tramaco Shipping] ==========================================
[21-Jan-2026 10:30:16] [Tramaco Shipping] INICIO calculate_shipping()
[21-Jan-2026 10:30:16] [Tramaco Shipping] Cart total: $13.50, Free shipping min: $0
[21-Jan-2026 10:30:16] [Tramaco Shipping] --- Buscando parroquia ---
[21-Jan-2026 10:30:16] [Tramaco Shipping] ✓ Encontrada en sesión WC: 101
[21-Jan-2026 10:30:16] [Tramaco Shipping] Parroquia obtenida: 101
[21-Jan-2026 10:30:16] [Tramaco Shipping] Peso calculado: 1 kg
[21-Jan-2026 10:30:16] [Tramaco Shipping] Llamando a API de Tramaco...
[21-Jan-2026 10:30:17] [Tramaco Shipping] Resultado API - Success: SÍ
[21-Jan-2026 10:30:17] [Tramaco Shipping] Resultado API - Total: $5.25
[21-Jan-2026 10:30:17] [Tramaco Shipping] ✅ Costo final calculado: $5.25
[21-Jan-2026 10:30:17] [Tramaco Shipping] FIN calculate_shipping()
```

### ❌ Si NO funciona (usando fallback):

```
[21-Jan-2026 10:30:16] [Tramaco Shipping] ==========================================
[21-Jan-2026 10:30:16] [Tramaco Shipping] INICIO calculate_shipping()
[21-Jan-2026 10:30:16] [Tramaco Shipping] Cart total: $13.50, Free shipping min: $0
[21-Jan-2026 10:30:16] [Tramaco Shipping] --- Buscando parroquia ---
[21-Jan-2026 10:30:16] [Tramaco Shipping] ✗ No encontrada en sesión WC
[21-Jan-2026 10:30:16] [Tramaco Shipping] ✗ Parroquia no encontrada en ninguna fuente
[21-Jan-2026 10:30:16] [Tramaco Shipping] Parroquia obtenida: NULL
[21-Jan-2026 10:30:16] [Tramaco Shipping] ⚠️ No hay parroquia - usando fallback
```

---

## 6️⃣ Problemas Comunes y Soluciones

### ❌ Problema: "tramacoCheckout is not defined"

**Causa:** Los scripts no se están cargando.

**Solución:**
1. Ve a WordPress → Plugins
2. Desactiva y reactiva el plugin "Tramaco API Integration"
3. Limpia el caché de WordPress (si usas algún plugin de caché)
4. Limpia el caché del navegador (Ctrl + Shift + Del)

---

### ❌ Problema: "Parroquia no encontrada en ninguna fuente"

**Causa:** La sesión de WooCommerce no está guardando el valor.

**Solución:**

1. **Verifica que el AJAX responda correctamente:**
   - Abre Consola (F12) → Network
   - Selecciona una parroquia
   - Busca la llamada a `admin-ajax.php`
   - Verifica que la respuesta sea:
     ```json
     {"success":true,"data":{"parroquia":101,"message":"Parroquia guardada correctamente"}}
     ```

2. **Si la respuesta es un error:**
   ```json
   {"success":false,"data":{"message":"Sesión de WooCommerce no disponible"}}
   ```
   - Ve a WooCommerce → Ajustes → Avanzado
   - Asegúrate que "Habilitar sesiones" esté activado

3. **Limpia las sesiones:**
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE '_wc_session_%';
   ```

---

### ❌ Problema: "Resultado API - Success: NO"

**Causa:** La API de Tramaco está fallando.

**Solución:**

1. Ve a WooCommerce → Ajustes → Tramaco API
2. Verifica las credenciales:
   - Login
   - Password
   - Usuario
   - Contrato
   - Localidad
   - Producto

3. Prueba el botón "Probar Conexión"

---

### ❌ Problema: Los campos de ubicación no aparecen

**Causa:** El hook de WooCommerce no se está ejecutando.

**Solución:**

1. Verifica que WooCommerce esté actualizado (mínimo 8.0)
2. Desactiva otros plugins de envío por conflictos
3. Cambia temporalmente a un tema por defecto (Twenty Twenty-Four)

---

## 7️⃣ Comando SQL para Debug Manual

Si necesitas verificar si la sesión está guardando:

```sql
-- Ver todas las sesiones activas
SELECT * FROM wp_options 
WHERE option_name LIKE '_wc_session_%' 
LIMIT 10;

-- Buscar una sesión específica (reemplaza XXX con tu session key)
SELECT option_value FROM wp_options 
WHERE option_name = '_wc_session_XXX';
```

---

## 8️⃣ Test de Funcionalidad Completa

### Test 1: Verificar que los scripts se cargan

```javascript
// En la consola del checkout, ejecuta:
console.log(typeof tramacoCheckout);
// Debe mostrar: "object"
```

### Test 2: Verificar ubicaciones cargadas

```javascript
// En la consola del checkout, ejecuta:
console.log(tramacoCheckout.ubicaciones);
// Debe mostrar: {lstProvincia: Array(24), ...}
```

### Test 3: Probar guardar manualmente

```javascript
// En la consola del checkout, ejecuta:
jQuery.ajax({
  url: tramacoCheckout.ajaxUrl,
  type: "POST",
  data: {
    action: "tramaco_save_checkout_parroquia",
    parroquia: 101,
    nonce: tramacoCheckout.nonce,
  },
  success: function(r) { console.log('✅ Success:', r); },
  error: function(e) { console.log('❌ Error:', e); }
});
```

---

## 9️⃣ Contactar Soporte

Si después de seguir todos los pasos el problema persiste, envía:

1. **El archivo `debug.log` completo**
2. **Captura de pantalla de la Consola del navegador (F12)**
3. **Captura de pantalla del checkout mostrando el error**
4. **Versión de WordPress y WooCommerce**
5. **Lista de plugins activos**

---

## 🔟 Desactivar Debug

Cuando termines de diagnosticar, edita `wp-config.php`:

```php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
```

Y **elimina el archivo** `wp-content/debug.log` (puede contener información sensible).

---

**Última actualización:** 22 de enero de 2026
