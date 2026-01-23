# 📊 Configuración de SharePoint para Tramaco Plugin

## Guía Paso a Paso con Imágenes

---

## 📍 Tu Situación Actual

Ya tienes:

- ✅ Sitio SharePoint: **ElixirShampoo**
- ✅ Carpeta creada: **Documents > Data Guías**
- ❌ Falta: Crear el archivo Excel y configurar Azure AD

---

## 🎯 Lo que vamos a hacer

1. Crear un archivo Excel en tu carpeta "Data Guías"
2. Registrar una aplicación en Azure AD (para que WordPress pueda conectarse)
3. Obtener los IDs necesarios
4. Configurar el plugin en WordPress

---

## PASO 1: Crear el Archivo Excel en SharePoint

### 1.1 Ve a tu carpeta "Data Guías"

Tu URL es:

```
https://golderiesa.sharepoint.com/sites/ElixirShampoo/Documentos compartidos/Data Guías
```

### 1.2 Crea un nuevo archivo Excel

1. Clic en **"+ New"** (+ Nuevo)
2. Selecciona **"Excel workbook"** (Libro de Excel)
3. Se abrirá Excel Online

### 1.3 Nombra el archivo

1. Clic en el nombre "Book" arriba a la izquierda
2. Cámbialo a: **"Registro-Guias-Tramaco"**

### 1.4 Crea la tabla con las columnas

En la celda A1, escribe estos encabezados (uno por columna):

| Columna | Encabezado   |
| ------- | ------------ |
| A1      | Fecha        |
| B1      | Hora         |
| C1      | Pedido       |
| D1      | Estado       |
| E1      | Total        |
| F1      | Guía         |
| G1      | Fecha_Guia   |
| H1      | Destinatario |
| I1      | Telefono     |
| J1      | Email        |
| K1      | Direccion    |
| L1      | Ciudad       |
| M1      | Parroquia    |
| N1      | Productos    |
| O1      | Cantidad     |
| P1      | Costo_Envio  |
| Q1      | PDF_Guia     |
| R1      | Link_Pedido  |
| S1      | Tracking     |

### 1.5 Convierte el rango en Tabla

1. Selecciona todas las celdas con encabezados (A1:S1)
2. Ve a **Insert > Table** (Insertar > Tabla)
3. Marca ✅ "My table has headers" (Mi tabla tiene encabezados)
4. Clic en **OK**

### 1.6 Nombra la tabla

1. Con la tabla seleccionada, ve a **Table Design** (Diseño de tabla)
2. A la izquierda verás "Table Name:" (Nombre de tabla)
3. Cámbialo a: **TablaPedidos**

### 1.7 Guarda y cierra

El archivo se guarda automáticamente en SharePoint.

---

## PASO 2: Registrar Aplicación en Azure AD

### 2.1 Accede a Azure Portal

1. Ve a: **https://portal.azure.com**
2. Inicia sesión con la misma cuenta de Microsoft 365/SharePoint

### 2.2 Busca "App registrations"

1. En la barra de búsqueda superior, escribe: **"App registrations"**
2. Clic en **"App registrations"** en los resultados

### 2.3 Crea nueva aplicación

1. Clic en **"+ New registration"** (+ Nuevo registro)
2. Completa:
   - **Name**: `Tramaco-WooCommerce-Integration`
   - **Supported account types**: Selecciona la primera opción (Single tenant)
   - **Redirect URI**: Déjalo vacío
3. Clic en **"Register"**

### 2.4 Copia los IDs (¡MUY IMPORTANTE!)

En la página de tu aplicación verás:

```
Application (client) ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx  ← COPIA ESTE
Directory (tenant) ID:   xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx  ← COPIA ESTE
```

**Guarda estos valores**, los necesitarás después.

### 2.5 Crea un secreto (contraseña)

1. En el menú izquierdo, clic en **"Certificates & secrets"**
2. Clic en **"+ New client secret"**
3. Descripción: `WordPress Plugin`
4. Expires: Selecciona **24 months** (24 meses)
5. Clic en **"Add"**

⚠️ **IMPORTANTE**: Copia el **"Value"** (Valor) inmediatamente.
Solo se muestra UNA VEZ. Si lo pierdes, tendrás que crear otro.

```
Value: xXxXxXxXxXxXxXxXxXxXxXxXxXxXxXxXxX  ← COPIA ESTE (Client Secret)
```

### 2.6 Configura los permisos

1. En el menú izquierdo, clic en **"API permissions"**
2. Clic en **"+ Add a permission"**
3. Selecciona **"Microsoft Graph"**
4. Selecciona **"Application permissions"** (NO Delegated)
5. Busca y marca estos permisos:
   - ✅ `Sites.ReadWrite.All`
   - ✅ `Files.ReadWrite.All`
6. Clic en **"Add permissions"**

### 2.7 Concede consentimiento de administrador

1. Clic en **"Grant admin consent for [tu organización]"**
2. Confirma con **"Yes"**

Las marcas verdes ✅ deben aparecer junto a cada permiso.

---

## PASO 3: Obtener los IDs de SharePoint

Necesitas 3 IDs de SharePoint:

- **Site ID**: Identificador del sitio
- **Drive ID**: Identificador de la biblioteca de documentos
- **Item ID**: Identificador del archivo Excel

### 3.1 Obtener Site ID y Drive ID

Abre esta URL en tu navegador (reemplaza con tu sitio):

```
https://graph.microsoft.com/v1.0/sites/golderiesa.sharepoint.com:/sites/ElixirShampoo
```

O usa el **Graph Explorer**:

1. Ve a: **https://developer.microsoft.com/en-us/graph/graph-explorer**
2. Inicia sesión con tu cuenta
3. En la barra de consulta, escribe:
   ```
   https://graph.microsoft.com/v1.0/sites/golderiesa.sharepoint.com:/sites/ElixirShampoo
   ```
4. Clic en **"Run query"**
5. En la respuesta, busca y copia el **"id"**:
   ```json
   {
     "id": "golderiesa.sharepoint.com,xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx,yyyyyyyy-yyyy-yyyy-yyyy-yyyyyyyyyyyy",
     ...
   }
   ```
   El **Site ID** es todo el valor del campo "id".

### 3.2 Obtener Drive ID

En Graph Explorer, ejecuta:

```
https://graph.microsoft.com/v1.0/sites/{site-id}/drives
```

Reemplaza `{site-id}` con el Site ID que copiaste.

En la respuesta, busca el drive que se llama "Documents" o "Documentos":

```json
{
  "value": [
    {
      "id": "b!xxxxxxxxxxxxxxxxxxxxxxxxxxxxx",  ← ESTE ES EL DRIVE ID
      "name": "Documents",
      ...
    }
  ]
}
```

### 3.3 Obtener Item ID del archivo Excel

En Graph Explorer, ejecuta:

```
https://graph.microsoft.com/v1.0/sites/{site-id}/drives/{drive-id}/root:/Data Guías/Registro-Guias-Tramaco.xlsx
```

En la respuesta:

```json
{
  "id": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",  ← ESTE ES EL ITEM ID
  "name": "Registro-Guias-Tramaco.xlsx",
  ...
}
```

---

## PASO 4: Configurar en WordPress

### 4.1 Accede a la configuración

1. Ve a **WordPress Admin → WooCommerce → Ajustes**
2. Clic en la pestaña **"Tramaco API"**
3. Busca la sección **"Integración SharePoint"**

### 4.2 Ingresa los valores

| Campo               | Valor a ingresar                       |
| ------------------- | -------------------------------------- |
| **Client ID**       | El Application (client) ID de Azure AD |
| **Client Secret**   | El Value del secreto que creaste       |
| **Tenant ID**       | El Directory (tenant) ID de Azure AD   |
| **Site ID**         | El ID completo del sitio SharePoint    |
| **Drive ID**        | El ID del drive "Documents"            |
| **Item ID**         | El ID del archivo Excel                |
| **Nombre de Tabla** | `TablaPedidos`                         |

### 4.3 Guarda y prueba

1. Clic en **"Guardar cambios"**
2. Si hay un botón "Probar conexión SharePoint", úsalo
3. Crea un pedido de prueba y verifica que aparezca en el Excel

---

## 📋 Resumen de Valores a Obtener

Crea una nota con estos valores:

```
=== AZURE AD ===
Client ID:      ________________________________
Client Secret:  ________________________________
Tenant ID:      ________________________________

=== SHAREPOINT ===
Site ID:        ________________________________
Drive ID:       ________________________________
Item ID:        ________________________________
Table Name:     TablaPedidos
```

---

## ❓ Solución de Problemas

### "Access Denied" o "Unauthorized"

- Verifica que diste consentimiento de administrador a los permisos
- El Client Secret puede haber expirado

### "Site not found"

- Verifica que el Site ID esté correcto
- El nombre del sitio es case-sensitive

### "File not found"

- Verifica que el archivo Excel existe en la carpeta correcta
- El nombre debe coincidir exactamente

### "Table not found"

- Abre el Excel y verifica que la tabla se llame exactamente "TablaPedidos"
- La tabla debe tener al menos una fila de encabezados

---

## 🔄 Alternativa Más Simple: Power Automate

Si la configuración de Azure AD te parece muy compleja, puedes usar **Power Automate** (incluido en Microsoft 365):

1. Crea un flujo que se active cuando reciba un webhook
2. El plugin envía los datos al webhook
3. Power Automate agrega la fila al Excel

¿Te gustaría que te explique esta alternativa?

---

## 📞 ¿Necesitas Ayuda?

Si tienes problemas con algún paso:

1. **Graph Explorer**: https://developer.microsoft.com/graph/graph-explorer
2. **Azure Portal**: https://portal.azure.com
3. **Documentación Microsoft Graph**: https://docs.microsoft.com/graph/

---

_Guía creada: Enero 2026_
