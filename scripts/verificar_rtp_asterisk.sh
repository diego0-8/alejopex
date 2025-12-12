#!/bin/bash

# Script para verificar configuración RTP en Asterisk
# Uso: ./verificar_rtp_asterisk.sh

echo "═══════════════════════════════════════════════════════"
echo "🔍 VERIFICACIÓN DE CONFIGURACIÓN RTP EN ASTERISK"
echo "═══════════════════════════════════════════════════════"
echo ""

# Verificar que Asterisk está corriendo
echo "1️⃣ Verificando que Asterisk está corriendo..."
if systemctl is-active --quiet asterisk; then
    echo "   ✅ Asterisk está corriendo"
else
    echo "   ❌ Asterisk NO está corriendo"
    echo "   Ejecuta: sudo systemctl start asterisk"
    exit 1
fi
echo ""

# Verificar configuración RTP
echo "2️⃣ Verificando configuración RTP..."
if [ -f /etc/asterisk/rtp.conf ]; then
    echo "   ✅ Archivo rtp.conf existe"
    echo ""
    echo "   📋 Configuración actual:"
    grep -E "^rtpstart|^rtpend|^rtptimeout|^rtpkeepalive|^external_rtp_ip" /etc/asterisk/rtp.conf | sed 's/^/      /'
    echo ""
    
    # Verificar que los puertos están configurados
    if grep -q "^rtpstart=" /etc/asterisk/rtp.conf && grep -q "^rtpend=" /etc/asterisk/rtp.conf; then
        RTP_START=$(grep "^rtpstart=" /etc/asterisk/rtp.conf | cut -d'=' -f2)
        RTP_END=$(grep "^rtpend=" /etc/asterisk/rtp.conf | cut -d'=' -f2)
        echo "   ✅ Puertos RTP configurados: $RTP_START - $RTP_END"
    else
        echo "   ⚠️  Puertos RTP NO están configurados"
        echo "   Agrega estas líneas a /etc/asterisk/rtp.conf:"
        echo "      rtpstart=10000"
        echo "      rtpend=20000"
    fi
else
    echo "   ⚠️  Archivo rtp.conf NO existe"
    echo "   Crea el archivo con:"
    echo "      sudo nano /etc/asterisk/rtp.conf"
    echo "      [general]"
    echo "      rtpstart=10000"
    echo "      rtpend=20000"
fi
echo ""

# Verificar configuración desde Asterisk
echo "3️⃣ Verificando configuración RTP desde Asterisk..."
if command -v asterisk &> /dev/null; then
    echo "   📋 Configuración RTP actual:"
    asterisk -rx "rtp show settings" 2>/dev/null | sed 's/^/      /'
    echo ""
else
    echo "   ⚠️  Comando 'asterisk' no encontrado"
fi
echo ""

# Verificar puertos abiertos
echo "4️⃣ Verificando puertos abiertos..."
if command -v netstat &> /dev/null; then
    echo "   📋 Puertos UDP abiertos (RTP):"
    netstat -tulpn 2>/dev/null | grep -E "10000|10001|18168" | grep UDP | sed 's/^/      /' || echo "      ⚠️  No se encontraron puertos RTP activos"
    echo ""
    echo "   📋 Puerto TCP 8089 (WSS):"
    netstat -tulpn 2>/dev/null | grep "8089" | grep TCP | sed 's/^/      /' || echo "      ⚠️  Puerto 8089 no está abierto"
else
    echo "   ⚠️  Comando 'netstat' no encontrado"
fi
echo ""

# Verificar firewall
echo "5️⃣ Verificando firewall..."
if command -v ufw &> /dev/null; then
    if ufw status | grep -q "Status: active"; then
        echo "   ⚠️  Firewall UFW está activo"
        echo "   Verificando reglas para RTP..."
        if ufw status | grep -q "10000:20000"; then
            echo "   ✅ Puertos RTP (10000-20000) están permitidos"
        else
            echo "   ❌ Puertos RTP NO están permitidos"
            echo "   Ejecuta: sudo ufw allow 10000:20000/udp"
        fi
        if ufw status | grep -q "8089"; then
            echo "   ✅ Puerto 8089 (WSS) está permitido"
        else
            echo "   ❌ Puerto 8089 NO está permitido"
            echo "   Ejecuta: sudo ufw allow 8089/tcp"
        fi
    else
        echo "   ℹ️  Firewall UFW está inactivo"
    fi
elif command -v firewall-cmd &> /dev/null; then
    if firewall-cmd --state 2>/dev/null | grep -q "running"; then
        echo "   ⚠️  Firewall firewalld está activo"
        if firewall-cmd --list-ports 2>/dev/null | grep -q "10000-20000"; then
            echo "   ✅ Puertos RTP (10000-20000) están permitidos"
        else
            echo "   ❌ Puertos RTP NO están permitidos"
            echo "   Ejecuta: sudo firewall-cmd --permanent --add-port=10000-20000/udp"
            echo "           sudo firewall-cmd --reload"
        fi
    else
        echo "   ℹ️  Firewall firewalld está inactivo"
    fi
else
    echo "   ℹ️  No se detectó firewall (puede estar desactivado o usando otro)"
fi
echo ""

# Verificar sesiones RTP activas
echo "6️⃣ Verificando sesiones RTP activas..."
if command -v asterisk &> /dev/null; then
    RTP_COUNT=$(asterisk -rx "rtp show" 2>/dev/null | grep -c "Session" || echo "0")
    if [ "$RTP_COUNT" -gt 0 ]; then
        echo "   ✅ Hay $RTP_COUNT sesión(es) RTP activa(s)"
        echo ""
        echo "   📋 Sesiones RTP activas:"
        asterisk -rx "rtp show" 2>/dev/null | head -20 | sed 's/^/      /'
    else
        echo "   ℹ️  No hay sesiones RTP activas (esto es normal si no hay llamadas)"
        echo "   💡 Haz una llamada y ejecuta este script de nuevo para verificar"
    fi
else
    echo "   ⚠️  Comando 'asterisk' no encontrado"
fi
echo ""

# Resumen y recomendaciones
echo "═══════════════════════════════════════════════════════"
echo "📊 RESUMEN Y RECOMENDACIONES"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "✅ Verificaciones completadas"
echo ""
echo "📝 PRÓXIMOS PASOS:"
echo ""
echo "1. Si los puertos RTP no están configurados:"
echo "   sudo nano /etc/asterisk/rtp.conf"
echo "   Agrega:"
echo "      [general]"
echo "      rtpstart=10000"
echo "      rtpend=20000"
echo "      rtptimeout=60"
echo "      rtpkeepalive=60"
echo ""
echo "2. Si el firewall está bloqueando:"
echo "   sudo ufw allow 10000:20000/udp"
echo "   sudo ufw allow 8089/tcp"
echo ""
echo "3. Reinicia Asterisk después de cambios:"
echo "   sudo systemctl restart asterisk"
echo ""
echo "4. Durante una llamada, verifica sesiones RTP:"
echo "   asterisk -rx 'rtp show'"
echo ""
echo "═══════════════════════════════════════════════════════"



