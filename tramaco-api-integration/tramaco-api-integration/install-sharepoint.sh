#!/bin/bash

# Script de instalación para SharePoint Integration
# Plugin Tramaco

echo "╔════════════════════════════════════════════╗"
echo "║  Instalación SharePoint - Plugin Tramaco  ║"
echo "╚════════════════════════════════════════════╝"
echo ""

# Verificar si Composer está instalado
if ! command -v composer &> /dev/null; then
    echo "❌ Error: Composer no está instalado"
    echo ""
    echo "Instala Composer desde: https://getcomposer.org"
    exit 1
fi

echo "✅ Composer encontrado"
echo ""

# Instalar dependencias
echo "📦 Instalando PhpSpreadsheet..."
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Instalación completada exitosamente"
    echo ""
    echo "📋 Próximos pasos:"
    echo "   1. Ve a WordPress Admin > Tramaco API > SharePoint"
    echo "   2. Configura las credenciales de Azure AD"
    echo "   3. Prueba la conexión"
    echo "   4. Habilita la sincronización automática"
    echo ""
    echo "📖 Documentación: SHAREPOINT-IMPLEMENTATION.md"
else
    echo ""
    echo "❌ Error durante la instalación"
    echo "   Verifica que tienes conexión a internet"
    echo "   y permisos de escritura en la carpeta"
    exit 1
fi
