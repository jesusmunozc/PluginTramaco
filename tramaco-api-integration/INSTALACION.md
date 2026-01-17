# Guía de Instalación - Plugin Tramaco API Integration

## Resumen

Este plugin permite integrar las APIs de TRAMACOEXPRESS en tu sitio WordPress para:

- Generar guías de envío
- Consultar tracking de paquetes
- Cotizar envíos
- Descargar PDFs de guías

## Requisitos

- WordPress 5.0 o superior
- PHP 7.4 o superior
- Credenciales de TRAMACOEXPRESS

## Instalación

### Paso 1: Subir el plugin

1. Comprime la carpeta `tramaco-api-integration` en un archivo ZIP
2. Ve al panel de WordPress: **Plugins > Añadir nuevo > Subir plugin**
3. Selecciona el archivo ZIP y haz clic en **Instalar ahora**
4. Activa el plugin

**Alternativa (FTP):**

1. Sube la carpeta `tramaco-api-integration` a `/wp-content/plugins/`
2. Activa el plugin desde el menú de Plugins

### Paso 2: Configurar credenciales

1. Ve a **Tramaco API** en el menú de WordPress
2. Ingresa las credenciales:

| Campo        | Valor (Pruebas) |
| ------------ | --------------- |
| Ambiente     | QA (Pruebas)    |
| Login (RUC)  | 1793191845001   |
| Password     | MAS.39inter.PIN |
| ID Contrato  | 6394            |
| ID Localidad | 21580           |
| ID Producto  | 36              |

3. Haz clic en **Guardar Configuración**
4. Prueba la conexión con el botón **Probar Autenticación**

## Uso de Shortcodes

### Tracking de Envíos

Agrega el siguiente shortcode en cualquier página:

```
[tramaco_tracking]
```

Esto mostrará un formulario donde los clientes pueden ingresar su número de guía y ver el estado de su envío.

### Cotización de Envíos

```
[tramaco_cotizacion]
```

Permite a los visitantes calcular el costo de envío seleccionando:

- Provincia de destino
- Cantón de destino
- Parroquia de destino
- Peso del paquete

### Generar Guía (Solo usuarios logueados)

```
[tramaco_generar_guia]
```

Formulario completo para crear guías de envío. Solo visible para usuarios autenticados.

## Panel de Administración

### Generar Guía

Ve a **Tramaco API > Generar Guía** para crear guías desde el panel de administración.

### Consultar Tracking

Ve a **Tramaco API > Tracking** para consultar el estado de cualquier guía.

## Endpoints de la API

El plugin se conecta a los siguientes servicios:

| Servicio        | URL                                                                                             |
| --------------- | ----------------------------------------------------------------------------------------------- |
| Autenticación   | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/usuario/autenticar            |
| Generar Guía    | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarGuia            |
| Generar PDF     | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/generarPdf             |
| Tracking        | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/consultarTracking      |
| Calcular Precio | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/guiaTk/calcularPrecio         |
| Ubicaciones     | https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources/ubicacionGeografica/consultar |

## Guías de Prueba

Para probar el tracking, puedes usar estas guías:

- 031002005633799
- 031002005633800

## Personalización de Estilos

Puedes sobrescribir los estilos del plugin agregando CSS personalizado en tu tema:

```css
/* Cambiar color principal */
.tramaco-tracking-form .btn-primary {
  background: #tu-color;
}

/* Personalizar formularios */
.tramaco-tracking-form {
  max-width: 800px;
  background: #f5f5f5;
}
```

## Solución de Problemas

### Error de autenticación

- Verifica que las credenciales sean correctas
- Comprueba que el servidor permita conexiones SSL

### No se cargan las ubicaciones

- El plugin cachea las ubicaciones por 24 horas
- Puedes limpiar el caché con: `delete_transient('tramaco_ubicaciones');`

### Error al generar guía

- Verifica que todos los campos obligatorios estén completos
- Revisa que el ID de parroquia sea válido

## Soporte

Para soporte técnico con la API, contacta a TRAMACOEXPRESS.
Para problemas con el plugin, revisa los logs de WordPress.

## Estructura del Plugin

```
tramaco-api-integration/
├── tramaco-api-integration.php    # Archivo principal
├── readme.txt                      # Información del plugin
├── INSTALACION.md                  # Esta guía
└── assets/
    ├── css/
    │   ├── tramaco-styles.css     # Estilos frontend
    │   └── tramaco-admin.css      # Estilos admin
    └── js/
        ├── tramaco-scripts.js     # Scripts frontend
        └── tramaco-admin.js       # Scripts admin
```

## Credenciales WordPress (según el correo)

Para acceder al panel de WordPress:

- **Usuario:** prueba.star.brands.api
- **Contraseña:** ISwoaA8B

## Notas Adicionales

- El plugin está configurado para el ambiente de pruebas (QA)
- Para producción, deberás cambiar las URLs base de la API
- El token de autenticación se renueva automáticamente cada 30 minutos
