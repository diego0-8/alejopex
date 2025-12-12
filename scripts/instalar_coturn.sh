#!/bin/bash
# Script de instalación y configuración de Coturn (Servidor TURN/STUN)
# Para WebRTC Softphone - Sistema APEX2
#
# IMPORTANTE: Este script debe ejecutarse con permisos de root
# Uso: sudo bash scripts/instalar_coturn.sh

set -e

echo "=========================================="
echo "Instalación de Coturn (Servidor TURN/STUN)"
echo "=========================================="
echo ""

# Detectar sistema operativo
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    echo "❌ No se pudo detectar el sistema operativo"
    exit 1
fi

echo "📦 Sistema operativo detectado: $OS"
echo ""

# Instalar Coturn según el sistema operativo
if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    echo "📥 Actualizando repositorios..."
    apt-get update
    
    echo "📥 Instalando Coturn..."
    apt-get install -y coturn
    
elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "fedora" ]; then
    echo "📥 Instalando Coturn..."
    if command -v dnf &> /dev/null; then
        dnf install -y coturn
    else
        yum install -y coturn
    fi
else
    echo "❌ Sistema operativo no soportado: $OS"
    echo "Por favor, instala Coturn manualmente"
    exit 1
fi

echo ""
echo "✅ Coturn instalado correctamente"
echo ""

# Obtener IP pública
echo "🔍 Detectando IP pública..."
PUBLIC_IP=$(curl -s ifconfig.me || curl -s icanhazip.com || echo "TU_IP_PUBLICA")
echo "   IP pública detectada: $PUBLIC_IP"
echo "   ⚠️  Si esta IP no es correcta, deberás configurarla manualmente"
echo ""

# Generar credenciales aleatorias
TURN_USER="turn_user_$(openssl rand -hex 4)"
TURN_PASS="$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-24)"
TURN_SECRET="$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)"

echo "🔐 Credenciales generadas:"
echo "   Usuario: $TURN_USER"
echo "   Contraseña: $TURN_PASS"
echo "   Secret: $TURN_SECRET"
echo "   ⚠️  GUARDA ESTAS CREDENCIALES - Las necesitarás para configurar el softphone"
echo ""

# Configurar Coturn
CONFIG_FILE="/etc/turnserver.conf"
BACKUP_FILE="/etc/turnserver.conf.backup.$(date +%Y%m%d_%H%M%S)"

echo "📝 Configurando Coturn..."
if [ -f "$CONFIG_FILE" ]; then
    echo "   Creando backup: $BACKUP_FILE"
    cp "$CONFIG_FILE" "$BACKUP_FILE"
fi

# Crear configuración
cat > "$CONFIG_FILE" << EOF
# Configuración de Coturn para WebRTC Softphone APEX2
# Generada automáticamente el $(date)

# Puerto de escucha
listening-port=3478
tls-listening-port=5349

# Direcciones de escucha
listening-ip=0.0.0.0

# IP pública externa (IMPORTANTE: Cambia si no es correcta)
external-ip=$PUBLIC_IP

# Realm y nombre del servidor
realm=$PUBLIC_IP
server-name=$PUBLIC_IP

# Autenticación
lt-cred-mech
user=$TURN_USER:$TURN_PASS
use-auth-secret
static-auth-secret=$TURN_SECRET

# Relay
relay-ip=0.0.0.0
no-cli

# Seguridad
no-tls
no-dtls
fingerprint

# Límites
max-bps=1000000
max-allocate-timeout=60

# Logging
log-file=/var/log/turnserver.log
verbose

# Deshabilitar funciones no necesarias
no-stdout-log
no-multicast-peers
no-loopback-peers
EOF

echo "✅ Configuración guardada en: $CONFIG_FILE"
echo ""

# Habilitar Coturn para iniciar al arrancar
echo "🔧 Habilitando Coturn..."
systemctl enable coturn

# Abrir puertos en firewall (si está activo)
if command -v ufw &> /dev/null; then
    echo "🔥 Configurando firewall (UFW)..."
    ufw allow 3478/udp comment "Coturn STUN/TURN"
    ufw allow 3478/tcp comment "Coturn STUN/TURN TCP"
    ufw allow 49152:65535/udp comment "Coturn relay ports"
    echo "✅ Puertos abiertos en UFW"
elif command -v firewall-cmd &> /dev/null; then
    echo "🔥 Configurando firewall (firewalld)..."
    firewall-cmd --permanent --add-port=3478/udp
    firewall-cmd --permanent --add-port=3478/tcp
    firewall-cmd --permanent --add-port=49152-65535/udp
    firewall-cmd --reload
    echo "✅ Puertos abiertos en firewalld"
else
    echo "⚠️  No se detectó firewall. Asegúrate de abrir los puertos manualmente:"
    echo "   - UDP 3478 (STUN/TURN)"
    echo "   - TCP 3478 (STUN/TURN TCP)"
    echo "   - UDP 49152-65535 (relay ports)"
fi

echo ""

# Iniciar Coturn
echo "🚀 Iniciando Coturn..."
systemctl restart coturn

# Verificar estado
if systemctl is-active --quiet coturn; then
    echo "✅ Coturn está ejecutándose correctamente"
else
    echo "❌ Error al iniciar Coturn. Revisa los logs:"
    echo "   journalctl -u coturn -n 50"
    exit 1
fi

echo ""
echo "=========================================="
echo "✅ Instalación completada"
echo "=========================================="
echo ""
echo "📋 CONFIGURACIÓN PARA APEX2:"
echo ""
echo "Edita el archivo: config/asterisk.php"
echo ""
echo "Define las siguientes constantes:"
echo "  define('ASTERISK_USE_TURN', true);"
echo "  define('ASTERISK_TURN_SERVER', 'turn:$PUBLIC_IP:3478');"
echo "  define('ASTERISK_TURN_USERNAME', '$TURN_USER');"
echo "  define('ASTERISK_TURN_CREDENTIAL', '$TURN_PASS');"
echo ""
echo "O si prefieres usar el secret:"
echo "  define('ASTERISK_TURN_SERVER', 'turn:$PUBLIC_IP:3478');"
echo "  define('ASTERISK_TURN_USERNAME', 'usuario');"
echo "  define('ASTERISK_TURN_CREDENTIAL', '$TURN_SECRET');"
echo ""
echo "🔍 Verificar que Coturn funciona:"
echo "   systemctl status coturn"
echo "   tail -f /var/log/turnserver.log"
echo ""
echo "🧪 Probar el servidor TURN:"
echo "   Visita: https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/"
echo "   Agrega el servidor: turn:$PUBLIC_IP:3478"
echo "   Usuario: $TURN_USER"
echo "   Contraseña: $TURN_PASS"
echo ""



