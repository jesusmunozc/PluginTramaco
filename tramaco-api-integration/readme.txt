=== Tramaco API Integration ===
Contributors: starbrand
Tags: tramaco, courier, shipping, tracking, guias
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integración con la API de TRAMACOEXPRESS para generación de guías, tracking y consultas de envíos.

== Description ==

Este plugin permite integrar tu sitio WordPress con los servicios de TRAMACOEXPRESS, facilitando:

* **Generación de Guías**: Crea guías de envío directamente desde tu sitio web
* **Tracking de Envíos**: Permite a tus clientes rastrear sus paquetes
* **Cotizaciones**: Calcula el costo de envío según destino y peso
* **Consulta de Ubicaciones**: Acceso a la base de datos de provincias, cantones y parroquias de Ecuador

== Instalación ==

1. Sube la carpeta `tramaco-api-integration` al directorio `/wp-content/plugins/`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. Ve a **Tramaco API > Configuración** para ingresar tus credenciales
4. Usa los shortcodes en tus páginas

== Configuración ==

1. **Login (RUC)**: Tu número de RUC registrado con TRAMACOEXPRESS
2. **Password**: Tu contraseña de acceso a la API
3. **ID Contrato**: El identificador de tu contrato
4. **ID Localidad**: El identificador de tu localidad de origen
5. **ID Producto**: El identificador del producto/servicio a usar

== Shortcodes ==

= Tracking de Envíos =
`[tramaco_tracking]`
Muestra un formulario para que los clientes rastreen sus envíos.

= Cotización de Envíos =
`[tramaco_cotizacion]`
Muestra un formulario para calcular el costo de envío.

= Generar Guía =
`[tramaco_generar_guia]`
Formulario completo para generar guías (requiere usuario logueado).

== Preguntas Frecuentes ==

= ¿Necesito credenciales especiales? =
Sí, debes tener un contrato activo con TRAMACOEXPRESS y solicitar tus credenciales de API.

= ¿Funciona con WooCommerce? =
Este plugin se puede usar junto con WooCommerce, pero la integración automática con pedidos requiere desarrollo adicional.

= ¿Puedo personalizar los estilos? =
Sí, puedes sobrescribir los estilos CSS en tu tema.

== Changelog ==

= 1.0.0 =
* Versión inicial
* Autenticación con API
* Generación de guías
* Consulta de tracking
* Cotización de envíos
* Consulta de ubicaciones geográficas

== Credenciales de Prueba ==

Para el ambiente de pruebas (QA):
* Login: 1793191845001
* Password: MAS.39inter.PIN
* Contrato: 6394
* Localidad: 21580
* Producto: 36

Guías de prueba para tracking:
* 031002005633799
* 031002005633800
