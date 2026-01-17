# Test: Generar PDF de Guías de Prueba - Tramaco API
# =====================================================

$baseUrl = "https://wsqa.tramaco.com.ec/dmz-tramaco-comercial-ws/webresources"

Write-Host "`n=======================================" -ForegroundColor Cyan
Write-Host "  GENERAR PDF DE GUIAS - TRAMACO API" -ForegroundColor Cyan
Write-Host "=======================================`n" -ForegroundColor Cyan

# Paso 1: Autenticación
Write-Host "[1/2] Autenticando con Tramaco..." -ForegroundColor Yellow

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
    
    # Intentar obtener token de diferentes estructuras
    if ($authResponse.salidaAutenticarUsuarioJWTWs.token) {
        $token = $authResponse.salidaAutenticarUsuarioJWTWs.token
    } elseif ($authResponse.cuerpoRespuesta.salidaAutenticarUsuarioJWTWs.token) {
        $token = $authResponse.cuerpoRespuesta.salidaAutenticarUsuarioJWTWs.token
    } elseif ($authResponse.token) {
        $token = $authResponse.token
    }

    if ($token) {
        Write-Host "      ✓ Token obtenido exitosamente" -ForegroundColor Green
        Write-Host "      Token: $($token.Substring(0, 50))...`n" -ForegroundColor Gray
    } else {
        Write-Host "      ✗ No se pudo obtener el token" -ForegroundColor Red
        Write-Host "`nRespuesta completa:" -ForegroundColor Gray
        $authResponse | ConvertTo-Json -Depth 10
        exit
    }

} catch {
    Write-Host "      ✗ Error en autenticación: $($_.Exception.Message)" -ForegroundColor Red
    exit
}

# Paso 2: Generar PDF de Guías
Write-Host "[2/2] Generando PDF de guías..." -ForegroundColor Yellow
Write-Host "      Guías: 031002005633799, 031002005633800`n" -ForegroundColor Gray

$guiasPrueba = @("031002005633799", "031002005633800")

$pdfBody = @{
    guias = $guiasPrueba
} | ConvertTo-Json

Write-Host "Request Body:" -ForegroundColor Gray
Write-Host $pdfBody -ForegroundColor White
Write-Host ""

try {
    # Usar Invoke-WebRequest en lugar de Invoke-RestMethod para obtener los bytes raw
    $pdfResponse = Invoke-WebRequest -Uri "$baseUrl/guiaTk/generarPdf" `
        -Method Post `
        -ContentType "application/json" `
        -Headers @{ Authorization = $token } `
        -Body $pdfBody `
        -SkipCertificateCheck
    
    Write-Host "--- RESPUESTA DE LA API ---" -ForegroundColor Cyan
    Write-Host "Status Code: $($pdfResponse.StatusCode)" -ForegroundColor White
    Write-Host "Content-Type: $($pdfResponse.Headers.'Content-Type')" -ForegroundColor White
    Write-Host "Content-Length: $($pdfResponse.Content.Length) bytes" -ForegroundColor White
    
    # Verificar si el contenido es un PDF
    $isPdf = $pdfResponse.Content[0..3] -join '' -match '%PDF' -or 
             ([System.Text.Encoding]::UTF8.GetString($pdfResponse.Content[0..10])) -match '%PDF'
    
    Write-Host "`n--- RESULTADO ---" -ForegroundColor Cyan
    
    if ($isPdf -or $pdfResponse.StatusCode -eq 200) {
        Write-Host "✓ ¡PDF GENERADO EXITOSAMENTE!" -ForegroundColor Green
        
        try {
            # Guardar el PDF directamente
            $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
            $pdfPath = Join-Path $PSScriptRoot "guias-tramaco-$timestamp.pdf"
            [System.IO.File]::WriteAllBytes($pdfPath, $pdfResponse.Content)
            
            $fileSizeKB = [math]::Round($pdfResponse.Content.Length / 1024, 2)
            
            Write-Host "`n📄 ARCHIVO PDF GUARDADO:" -ForegroundColor Green
            Write-Host "   Ruta: $pdfPath" -ForegroundColor White
            Write-Host "   Tamaño: $fileSizeKB KB" -ForegroundColor White
            Write-Host "   Guías incluidas: $($guiasPrueba -join ', ')" -ForegroundColor White
            
            # Intentar abrir el PDF
            Write-Host "`n¿Desea abrir el PDF? (S/N): " -ForegroundColor Yellow -NoNewline
            $respuesta = Read-Host
            if ($respuesta -eq 'S' -or $respuesta -eq 's') {
                Start-Process $pdfPath
                Write-Host "✓ PDF abierto" -ForegroundColor Green
            }
            
        } catch {
            Write-Host "`n✗ Error al guardar el PDF: $($_.Exception.Message)" -ForegroundColor Red
        }
        
    } else {
        Write-Host "✗ ERROR AL GENERAR PDF" -ForegroundColor Red
        Write-Host "La respuesta no parece ser un PDF válido" -ForegroundColor Yellow
        
        # Intentar parsear como JSON para ver si hay un mensaje de error
        try {
            $jsonResponse = $pdfResponse.Content | ConvertFrom-Json
            if ($jsonResponse.cuerpoRespuesta.mensaje) {
                Write-Host "Mensaje: $($jsonResponse.cuerpoRespuesta.mensaje)" -ForegroundColor Yellow
            }
        } catch {
            Write-Host "No se pudo parsear la respuesta" -ForegroundColor Gray
        }
    }
    
} catch {
    Write-Host "`n✗ Error en la petición de PDF:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
    
    if ($_.ErrorDetails) {
        Write-Host "`nDetalles del error:" -ForegroundColor Gray
        Write-Host $_.ErrorDetails.Message -ForegroundColor White
    }
}

Write-Host "`n=======================================" -ForegroundColor Cyan
Write-Host "  FIN DE LA PRUEBA" -ForegroundColor Cyan
Write-Host "=======================================`n" -ForegroundColor Cyan
