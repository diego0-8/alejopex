#!/bin/bash

echo "=== DIAGNÓSTICO RTP ASTERISK ==="
echo ""

# Verificar si Asterisk está ejecutándose
echo "1. Estado del servicio Asterisk:"
systemctl status asterisk --no-pager -l | head -10
echo ""

# Verificar conexiones RTP activas
echo "2. Conexiones RTP activas:"
asterisk -rx "rtp show" 2>/dev/null || echo "No se pudo conectar a Asterisk CLI"
echo ""

# Verificar configuración RTP
echo "3. Configuración RTP:"
asterisk -rx "rtp show settings" 2>/dev/null || echo "No se pudo obtener configuración RTP"
echo ""

# Verificar codecs disponibles
echo "4. Codecs disponibles:"
asterisk -rx "core show codecs" 2>/dev/null | grep -E "(ulaw|alaw|slin)" || echo "No se encontraron codecs básicos"
echo ""

# Verificar canales activos
echo "5. Canales activos:"
asterisk -rx "core show channels" 2>/dev/null || echo "No hay canales activos"
echo ""

# Verificar puertos RTP en uso
echo "6. Puertos RTP en uso:"
netstat -uln 2>/dev/null | grep -E ':1000[0-9]|:2000[0-9]' | head -10 || echo "No se encontraron puertos RTP en uso"
echo ""

# Verificar firewall
echo "7. Reglas de firewall para RTP:"
iptables -L -n 2>/dev/null | grep -E '10000|20000|udp' || echo "No se encontraron reglas específicas para RTP"
echo ""

echo "=== FIN DEL DIAGNÓSTICO ==="