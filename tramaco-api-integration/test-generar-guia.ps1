# Test: Generar Guía de Envío - Tramaco API
# ==========================================

$baseUrl = "https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources"

Write-Host "`n=== TEST: GENERAR GUIA DE ENVIO ===" -ForegroundColor Cyan
Write-Host "Endpoint: $baseUrl/guiaTk/generarGuia`n" -ForegroundColor Gray

# Paso 1: Autenticación
Write-Host "[1/2] Autenticando..." -ForegroundColor Yellow

$authBody = @{
    login = "1793191845001"
    password = "MAS.39inter.PIN"
} | ConvertTo-Json

try {
    $authResponse = Invoke-RestMethod -Uri "$baseUrl/usuario/autenticar" `
        -Method Post `
        -ContentType "application/json" `
        -Body $authBody `
        -SkipCertificateCheck

    $token = $null
    
    # Intentar obtener token de ambas estructuras posibles
    if ($authResponse.salidaAutenticarUsuarioJWTWs.token) {
        $token = $authResponse.salidaAutenticarUsuarioJWTWs.token
        Write-Host "✓ Token obtenido (estructura JWT)" -ForegroundColor Green
    }
    elseif ($authResponse.cuerpoRespuesta.salidaAutenticarUsuarioJWTWs.token) {
        $token = $authResponse.cuerpoRespuesta.salidaAutenticarUsuarioJWTWs.token
        Write-Host "✓ Token obtenido (estructura cuerpoRespuesta)" -ForegroundColor Green
    }
    elseif ($authResponse.token) {
        $token = $authResponse.token
        Write-Host "✓ Token obtenido (estructura simple)" -ForegroundColor Green
    }
    else {
        Write-Host "✗ No se pudo obtener el token" -ForegroundColor Red
        Write-Host "Respuesta completa:" -ForegroundColor Gray
        $authResponse | ConvertTo-Json -Depth 10
        exit
    }

    Write-Host "Token: $($token.Substring(0, 50))...`n" -ForegroundColor Gray

} catch {
    Write-Host "✗ Error en autenticación: $($_.Exception.Message)" -ForegroundColor Red
    exit
}

# Paso 2: Generar Guía
Write-Host "[2/2] Generando guía de envío..." -ForegroundColor Yellow

# Datos de la guía de prueba
$guiaBody = @{
    codContrato = "6394"
    codLocalidad = "21580"
    codProducto = "36"
    lstCargaDestino = @(
        @{
            carga = @{
                cantidad = 1
                peso = 2
                alto = 10
                ancho = 10
                largo = 10
                valorDeclarado = 100
            }
            destino = @{
                codParroquia = "316"  # BOLIVAR (GUAYAQUIL)
                nombres = "JUAN"
                apellidos = "PEREZ GARCIA"
                ciudad = "GUAYAQUIL"
                callePrimaria = "AV PRUEBA"
                telefono = "0984652402"
                calleSecundaria = "CALLE SECUNDARIA"
                tipoIden = "05"
                referencia = "CERCA DEL PARQUE"
                ciRuc = "0912345678"
                numero = "123"
            }
        }
    )
    remitente = @{
        codParroquia = "316"  # BOLIVAR (GUAYAQUIL)
        nombres = "EMPRESA"
        apellidos = "REMITENTE SA"
        ciudad = "GUAYAQUIL"
        callePrimaria = "GUIA PRUEBA"
        telefono = "0981234567"
        calleSecundaria = ""
        tipoIden = "06"
        referencia = "REFERENCIA REMITENTE"
        ciRuc = "1793191845001"
        numero = "000000047"
    }
} | ConvertTo-Json -Depth 10

Write-Host "`nDatos de la guía:" -ForegroundColor Gray
Write-Host "- Contrato: 6394" -ForegroundColor Gray
Write-Host "- Producto: 36 (PAQUETERIA EXPRES)" -ForegroundColor Gray
Write-Host "- Destino: GUAYAQUIL - BOLIVAR" -ForegroundColor Gray
Write-Host "- Peso: 2 kg" -ForegroundColor Gray
Write-Host "- Dimensiones: 10x10x10 cm" -ForegroundColor Gray
Write-Host "- Valor Declarado: $100`n" -ForegroundColor Gray

try {
    $guiaResponse = Invoke-RestMethod -Uri "$baseUrl/guiaTk/generarGuia" `
        -Method Post `
        -ContentType "application/json" `
        -Headers @{ Authorization = $token } `
        -Body $guiaBody `
        -SkipCertificateCheck

    Write-Host "`n=== RESPUESTA ===" -ForegroundColor Cyan
    
    # Intentar obtener respuesta de diferentes estructuras
    $responseData = $null
    
    if ($guiaResponse.salidaGenerarGuiaWs) {
        $responseData = $guiaResponse.salidaGenerarGuiaWs
    }
    elseif ($guiaResponse.cuerpoRespuesta.salidaGenerarGuiaWs) {
        $responseData = $guiaResponse.cuerpoRespuesta.salidaGenerarGuiaWs
    }
    elseif ($guiaResponse.cuerpoRespuesta) {
        $responseData = $guiaResponse.cuerpoRespuesta
    }
    else {
        $responseData = $guiaResponse
    }

    # Verificar código de respuesta
    $codigo = $null
    if ($responseData.codigo) {
        $codigo = $responseData.codigo
    }
    elseif ($guiaResponse.codigo) {
        $codigo = $guiaResponse.codigo
    }

    if ($codigo -eq 1) {
        Write-Host "✓ Guía generada exitosamente!" -ForegroundColor Green
        
        # Mostrar número de guía
        if ($responseData.numeroGuia) {
            Write-Host "`nNúmero de Guía: " -NoNewline -ForegroundColor Yellow
            Write-Host "$($responseData.numeroGuia)" -ForegroundColor White -BackgroundColor DarkGreen
        }
        
        # Mostrar detalles adicionales
        if ($responseData.mensaje) {
            Write-Host "Mensaje: $($responseData.mensaje)" -ForegroundColor Gray
        }
        
        # Mostrar toda la información de la guía
        if ($responseData.lstGuias) {
            Write-Host "`nDetalle de guías generadas:" -ForegroundColor Cyan
            foreach ($guia in $responseData.lstGuias) {
                Write-Host "  - Guía: $($guia.numeroGuia)" -ForegroundColor White
                Write-Host "    Estado: $($guia.estado)" -ForegroundColor Gray
                if ($guia.costo) {
                    Write-Host "    Costo: `$$($guia.costo)" -ForegroundColor Gray
                }
            }
        }
        
    }
    else {
        Write-Host "⚠ Respuesta con código: $codigo" -ForegroundColor Yellow
        if ($responseData.mensaje) {
            Write-Host "Mensaje: $($responseData.mensaje)" -ForegroundColor Yellow
        }
    }

    Write-Host "`nRespuesta completa (JSON):" -ForegroundColor Gray
    $guiaResponse | ConvertTo-Json -Depth 10

} catch {
    Write-Host "`n✗ Error al generar guía" -ForegroundColor Red
    Write-Host "Mensaje: $($_.Exception.Message)" -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseText = $reader.ReadToEnd()
        Write-Host "`nRespuesta del servidor:" -ForegroundColor Gray
        Write-Host $responseText -ForegroundColor Gray
    }
}

Write-Host "`n=== FIN DEL TEST ===`n" -ForegroundColor Cyan
