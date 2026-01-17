=== Laar Courier API Integration ===
Contributors: starbrand
Tags: courier, shipping, tracking, laar, ecuador
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integración completa con la API de Laar Courier para generación de guías, tracking y cotizaciones.

== Description ==

Este plugin permite integrar los servicios de Laar Courier en tu sitio WordPress:

* **Generación de guías de envío** - Crea guías directamente desde WordPress
* **Tracking de envíos** - Consulta el estado de tus envíos en tiempo real
* **Cotizador** - Calcula el costo de envío antes de generar una guía
* **Panel de administración** - Gestiona todo desde el panel de WordPress

= Shortcodes Disponibles =

* `[laar_tracking]` - Formulario de tracking de guías
* `[laar_cotizacion]` - Formulario de cotización de envíos
* `[laar_generar_guia]` - Formulario para generar guías (requiere login)

= Características =

* Autenticación segura con JWT
* Cache de ciudades para mejor rendimiento
* Interfaz responsive y moderna
* Soporte para COD (Cobro en destino)
* Múltiples tipos de servicio: DELIVERY, DOCUMENTO, CARGA, VALIJA

== Installation ==

1. Sube la carpeta `laar-api-integration` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Ve a **Laar Courier** en el menú lateral
4. Configura tus credenciales de API
5. ¡Listo! Ya puedes usar los shortcodes

== Frequently Asked Questions ==

= ¿Necesito credenciales especiales? =

Sí, necesitas credenciales de API proporcionadas por Laar Courier.

= ¿Funciona con WooCommerce? =

Este plugin puede usarse de forma independiente. Para integración con WooCommerce, próximamente habrá una extensión.

= ¿Cómo obtengo el PDF de una guía? =

Después de generar una guía, aparecerá un botón para descargar el PDF.

== Screenshots ==

1. Panel de configuración
2. Formulario de tracking
3. Cotizador de envíos
4. Generación de guías

== Changelog ==

= 1.0.0 =
* Versión inicial
* Autenticación con API de Laar Courier
* Generación de guías
* Tracking de envíos
* Cotizador
* Panel de administración

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin.
