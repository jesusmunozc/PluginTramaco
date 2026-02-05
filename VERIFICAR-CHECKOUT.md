# ✅ Verificar Campos Tramaco en Checkout

## 🔍 Pasos para verificar

### 1. Limpiar Caché

```bash
# En WordPress, elimina la caché si usas algún plugin de caché
# O en el navegador: Ctrl + Shift + R (recarga forzada)
```

### 2. Verificar País por Defecto

1. Ve a: **WooCommerce → Settings → General**
2. Verifica que:
   - **Default customer location**: Store location
   - **Selling location(s)**: Sell to specific countries → Ecuador
   - **Default country / region**: Ecuador

### 3. Probar en el Checkout

1. Agrega un producto al carrito
2. Ve al checkout
3. **Deberías ver estos campos** (después del Estado/Provincia):
   - ✅ **Provincia (Tramaco)** - Select con provincias
   - ✅ **Cantón (Tramaco)** - Select (se activa al seleccionar provincia)
   - ✅ **Parroquia (Tramaco)** - Select (se activa al seleccionar cantón)

### 4. Abrir Consola del Navegador

Presiona **F12** y ve a la pestaña **Console**. Deberías ver:

```
Tramaco Checkout: Inicializando...
Tramaco: Ubicaciones cargadas correctamente
Provincias disponibles: [número]
País Billing: EC
País Shipping: EC
Tramaco: Mostrando campos para Ecuador
```

### 5. Probar Selectores en Cascada

1. **Selecciona una Provincia** (ej: Azuay)
   - El selector de Cantón debería llenarse automáticamente
2. **Selecciona un Cantón** (ej: Camilo Ponce Enriquez)
   - El selector de Parroquia debería llenarse automáticamente
3. **Selecciona una Parroquia**
   - El costo de envío debería actualizarse automáticamente
   - Verás "Calculando..." brevemente

### 6. Verificar Cálculo de Envío

En la consola deberías ver:

```
Tramaco: Parroquia guardada en sesión: [ID]
Tramaco: Actualizando checkout...
```

Y en la sección de envío del checkout debería aparecer:

```
🚚 Envío Tramaco - $X.XX
```

## 🐛 Si NO ves los campos

### Solución 1: Verificar que el país es Ecuador

En la consola del navegador ejecuta:

```javascript
jQuery("#billing_country").val();
jQuery("#shipping_country").val();
```

Debe retornar `"EC"`. Si no, cámbialo manualmente:

```javascript
jQuery("#billing_country").val("EC").trigger("change");
jQuery("#shipping_country").val("EC").trigger("change");
```

### Solución 2: Forzar visibilidad

En la consola ejecuta:

```javascript
jQuery(".tramaco-field").show();
```

### Solución 3: Verificar ubicaciones cargadas

En la consola ejecuta:

```javascript
console.log(tramacoCheckout.ubicaciones);
```

Debe mostrar un objeto con `lstProvincia` array.

### Solución 4: Verificar campos en HTML

En la consola ejecuta:

```javascript
console.log(
  "Campos Shipping:",
  jQuery("#shipping_tramaco_provincia").length,
  jQuery("#shipping_tramaco_canton").length,
  jQuery("#shipping_tramaco_parroquia").length,
);

console.log(
  "Campos Billing:",
  jQuery("#billing_tramaco_provincia").length,
  jQuery("#billing_tramaco_canton").length,
  jQuery("#billing_tramaco_parroquia").length,
);
```

Cada uno debe retornar `1`. Si retorna `0`, los campos no se están agregando.

## 📝 Logs de Debug

Si tienes `WP_DEBUG` activo, revisa `wp-content/debug.log` para ver:

```
[Tramaco API] Calcular Precio Request: {...}
[Tramaco API] Calcular Precio Response: {...}
[Tramaco API] Código respuesta: 1
[Tramaco API] ✅ Costo calculado exitosamente: $XX.XX
```

## 🎨 Si los campos se ven mal

Los campos deben tener la clase `tramaco-field`. Verifica en el inspector:

```html
<p
  class="form-row form-row-wide tramaco-ubicacion tramaco-field"
  id="shipping_tramaco_provincia_field"
>
  <label>Provincia (Tramaco)</label>
  <select name="shipping_tramaco_provincia" id="shipping_tramaco_provincia">
    <option value="">Seleccione una provincia...</option>
    <option value="3">AZUAY</option>
    ...
  </select>
</p>
```

## ✨ Comportamiento Esperado

### Flujo Completo:

1. Usuario entra al checkout → País = Ecuador (EC)
2. Se muestran 3 campos adicionales (Provincia, Cantón, Parroquia)
3. Usuario selecciona **Provincia** → Se cargan cantones de esa provincia
4. Usuario selecciona **Cantón** → Se cargan parroquias de ese cantón
5. Usuario selecciona **Parroquia** → Se actualiza costo de envío automáticamente
6. Costo calculado se muestra en la sección de envío

### IDs de Ejemplo para Prueba:

- **Provincia**: 3 (AZUAY)
- **Cantón**: 41 (CAMILO PONCE ENRIQUEZ)
- **Parroquia**: 400 (CAMILO PONCE ENRIQUEZ)

Con estos valores, el sistema debería calcular un costo de envío específico usando la API de Tramaco.

## 🚨 Problemas Comunes

### El costo muestra $5.00 (Estimado)

- Significa que no detectó la parroquia correctamente
- Revisa la consola para ver si hay errores
- Verifica que la parroquia se guardó en sesión

### Los selectores no se llenan

- Verifica que `tramacoCheckout.ubicaciones` tenga datos
- Puede ser que la API de ubicaciones no respondió
- Revisa el token de autenticación

### No se actualiza el costo al cambiar parroquia

- Verifica que el método de envío "Tramaco" esté seleccionado
- Revisa que WooCommerce esté recalculando el envío

## 📞 Soporte Adicional

Si sigues teniendo problemas:

1. Activa `WP_DEBUG` en `wp-config.php`
2. Usa el archivo `test-tramaco-shipping.php` para probar el cálculo directamente
3. Revisa los logs en `wp-content/debug.log`
4. Exporta los mensajes de la consola del navegador
