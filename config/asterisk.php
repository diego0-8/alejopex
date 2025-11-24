<?php
/**
 * Configuración de Asterisk/Issabel para WebRTC Softphone
 * 
 * IMPORTANTE: Modifica estos valores según tu servidor PBX
 */

// =====================================================================
// CONFIGURACIÓN DEL SERVIDOR ASTERISK/ISSABEL
// =====================================================================

// URL del servidor WebSocket de Asterisk
// Usa 'ws://' si NO tienes SSL configurado
// Usa 'wss://' si tienes SSL/certificado configurado
// Servidor configurado para Control Next App
define('ASTERISK_WSS_SERVER', 'wss://192.168.65.190:8089/ws');

// Dominio SIP (debe coincidir con el 'realm' en pjsip.conf)
// Dominio del servidor PBX
define('ASTERISK_SIP_DOMAIN', '192.168.65.190');

// Puerto WSS de Asterisk (por defecto 8089)
define('ASTERISK_WSS_PORT', 8089);

// Servidor STUN para NAT Traversal
// Ayuda a clientes detrás de NAT a descubrir su IP pública
// Puedes usar servidores públicos de Google o configurar el tuyo
define('ASTERISK_STUN_SERVER', 'stun:stun.l.google.com:19302');

// =====================================================================
// CONFIGURACIÓN DE SERVIDORES TURN
// =====================================================================

// ¿Usar servidor TURN? (true/false)
// IMPORTANTE: Un servidor TURN es ALTAMENTE RECOMENDADO para garantizar audio bidireccional
// cuando hay firewalls estrictos o NATs simétricos
// ACTIVADO CON SERVIDOR PÚBLICO TEMPORAL (para pruebas)
define('ASTERISK_USE_TURN', true);

// Configuración del servidor TURN
// OPCIÓN 1: Servidor TURN propio (RECOMENDADO para producción)
// Si tienes un servidor TURN propio, configura aquí:
define('ASTERISK_TURN_SERVER', ''); // Ejemplo: 'turn:192.168.65.190:3478' o 'turn:tu-dominio.com:3478'
define('ASTERISK_TURN_USERNAME', ''); // Usuario para autenticación TURN
define('ASTERISK_TURN_CREDENTIAL', ''); // Contraseña para autenticación TURN

// OPCIÓN 2: Servidor TURN público temporal (solo para pruebas rápidas)
// Metered.ca ofrece un servidor TURN público gratuito para pruebas
// IMPORTANTE: Este servidor es solo para pruebas, NO para producción
// Puede tener limitaciones de ancho de banda y latencia
// ACTIVADO TEMPORALMENTE PARA RESOLVER PROBLEMA DE RED
define('ASTERISK_USE_PUBLIC_TURN', true); // Activado para usar TURN público temporal
// Si ASTERISK_USE_PUBLIC_TURN = true, se usará el servidor de Metered.ca automáticamente

// Servidores ICE (STUN/TURN) para WebRTC
// Estos servidores son esenciales para que el audio se transporte correctamente
// cuando los clientes están detrás de NAT/firewalls
// Formato: array de arrays con 'urls', opcionalmente 'username' y 'credential' para TURN
// IMPORTANTE: Debe ser un array PHP válido, NO una cadena vacía
// 
// NOTA: En PHP < 7.0, define() no soporta arrays directamente, por lo que usamos una función
function getAsteriskIceServers() {
    $servers = [
        // STUN Servers (para descubrir la IP pública - NECESARIO)
        // Múltiples servidores STUN para mayor confiabilidad
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        ['urls' => 'stun:stun2.l.google.com:19302'],
        ['urls' => 'stun:stun3.l.google.com:19302'],
        // Servidores STUN alternativos (backup)
        ['urls' => 'stun:stun.stunprotocol.org:3478'],
    ];
    
    // Agregar servidor TURN si está configurado
    if (defined('ASTERISK_USE_TURN') && ASTERISK_USE_TURN) {
        $turnServer = defined('ASTERISK_TURN_SERVER') ? ASTERISK_TURN_SERVER : '';
        $turnUsername = defined('ASTERISK_TURN_USERNAME') ? ASTERISK_TURN_USERNAME : '';
        $turnCredential = defined('ASTERISK_TURN_CREDENTIAL') ? ASTERISK_TURN_CREDENTIAL : '';
        $usePublicTurn = defined('ASTERISK_USE_PUBLIC_TURN') ? ASTERISK_USE_PUBLIC_TURN : false;
        
        // Si hay un servidor TURN propio configurado, usarlo
        if (!empty($turnServer)) {
            $turnConfig = ['urls' => $turnServer];
            
            // Agregar credenciales si están configuradas
            if (!empty($turnUsername) && !empty($turnCredential)) {
                $turnConfig['username'] = $turnUsername;
                $turnConfig['credential'] = $turnCredential;
            }
            
            $servers[] = $turnConfig;
        }
        // Si no hay servidor propio pero se permite usar público, usar Metered.ca
        elseif ($usePublicTurn) {
            // Servidor TURN público de Metered.ca (solo para pruebas)
            // Obtén credenciales gratuitas en: https://www.metered.ca/tools/openrelay/
            // NOTA: Estas credenciales son de ejemplo, debes obtener las tuyas
            $servers[] = [
                'urls' => [
                    'turn:a.relay.metered.ca:80',
                    'turn:a.relay.metered.ca:80?transport=tcp',
                    'turn:a.relay.metered.ca:443',
                    'turn:a.relay.metered.ca:443?transport=tcp'
                ],
                'username' => 'openrelayproject', // Credenciales públicas de ejemplo
                'credential' => 'openrelayproject'
            ];
        }
    }
    
    return $servers;
}

// Para compatibilidad, también definimos como constante si PHP >= 7.0
if (PHP_VERSION_ID >= 70000 && !defined('ASTERISK_ICE_SERVERS')) {
    define('ASTERISK_ICE_SERVERS', getAsteriskIceServers());
}

// Modo de depuración (true = muestra logs en consola del navegador)
define('ASTERISK_DEBUG_MODE', true);

// =====================================================================
// FUNCIÓN PARA OBTENER LA CONFIGURACIÓN
// =====================================================================

/**
 * Retorna la configuración de WebRTC como array
 * @return array Configuración del softphone
 */
function getWebRTCConfig() {
    // Obtener servidores ICE (compatible con todas las versiones de PHP)
    $iceServers = (defined('ASTERISK_ICE_SERVERS') && is_array(ASTERISK_ICE_SERVERS)) 
        ? ASTERISK_ICE_SERVERS 
        : getAsteriskIceServers();
    
    // Validar que sea un array y no esté vacío
    if (!is_array($iceServers) || empty($iceServers)) {
        // Fallback: usar servidores STUN públicos por defecto
        $iceServers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302']
        ];
    }
    
    return [
        'wss_server' => ASTERISK_WSS_SERVER,
        'sip_domain' => ASTERISK_SIP_DOMAIN,
        'wss_port' => ASTERISK_WSS_PORT,
        'stun_server' => ASTERISK_STUN_SERVER,
        'iceServers' => $iceServers, // Configuración de servidores ICE (array válido)
        'debug_mode' => ASTERISK_DEBUG_MODE,
    ];
}

// =====================================================================
// NOTAS DE CONFIGURACIÓN
// =====================================================================
/*
 * PASOS PARA CONFIGURAR TU SERVIDOR ASTERISK:
 * 
 * 1. En tu servidor Issabel/Asterisk, edita: /etc/asterisk/http.conf
 *    [general]
 *    enabled=yes
 *    bindaddr=0.0.0.0
 *    tlsenable=yes
 *    tlsbindaddr=0.0.0.0:8089
 *    tlscertfile=/etc/asterisk/keys/asterisk.pem
 * 
 * 2. Edita: /etc/asterisk/pjsip.conf
 *    [transport-wss]
 *    type=transport
 *    protocol=wss
 *    bind=0.0.0.0:8089
 * 
 * 3. Genera certificado SSL si no tienes:
 *    cd /etc/asterisk/keys/
 *    openssl req -x509 -newkey rsa:4096 -keyout asterisk.key -out asterisk.crt -days 365 -nodes
 *    cat asterisk.key asterisk.crt > asterisk.pem
 * 
 * 4. Reinicia Asterisk:
 *    systemctl restart asterisk
 * 
 * 5. Configura las extensiones en Issabel FreePBX con:
 *    - Transport: transport-wss
 *    - Encryption: yes (SRTP)
 * 
 * =====================================================================
 * CONFIGURACIÓN DE SERVIDOR TURN (COTURN) - ALTAMENTE RECOMENDADO
 * =====================================================================
 * 
 * Para garantizar audio bidireccional, es CRÍTICO configurar un servidor TURN.
 * 
 * INSTALACIÓN DE COTURN (en el mismo servidor o uno separado):
 * 
 * 1. Instalar Coturn:
 *    # En Debian/Ubuntu:
 *    sudo apt update
 *    sudo apt install coturn
 * 
 *    # En CentOS/RHEL:
 *    sudo yum install coturn
 * 
 * 2. Configurar Coturn (/etc/turnserver.conf):
 *    listening-port=3478
 *    tls-listening-port=5349
 *    listening-ip=0.0.0.0
 *    external-ip=TU_IP_PUBLICA  # IMPORTANTE: Tu IP pública
 *    realm=tu-dominio.com  # O tu IP pública
 *    server-name=tu-dominio.com
 *    
 *    # Autenticación (recomendado)
 *    lt-cred-mech
 *    user=usuario_turn:password_turn  # Cambia estos valores
 *    
 *    # Habilitar relay
 *    relay-ip=0.0.0.0
 *    no-cli
 *    no-tls
 *    no-dtls
 *    fingerprint
 *    lt-cred-mech
 *    use-auth-secret
 *    static-auth-secret=tu_secret_key_aqui  # Genera una clave segura
 *    
 *    # Límites (ajusta según tu servidor)
 *    max-bps=1000000
 *    max-allocate-timeout=60
 * 
 * 3. Abrir puertos en el firewall:
 *    # UDP 3478 (STUN/TURN)
 *    # UDP 49152-65535 (rango de relay)
 *    # TCP 3478 (opcional, para TURN sobre TCP)
 *    
 *    sudo ufw allow 3478/udp
 *    sudo ufw allow 49152:65535/udp
 * 
 * 4. Iniciar y habilitar Coturn:
 *    sudo systemctl enable coturn
 *    sudo systemctl start coturn
 *    sudo systemctl status coturn
 * 
 * 5. Probar el servidor TURN:
 *    # Instalar herramientas de prueba:
 *    sudo apt install stun-client
 *    
 *    # Probar STUN:
 *    stun stun.l.google.com
 *    
 *    # Probar TURN (requiere herramienta adicional):
 *    # Puedes usar: https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/
 * 
 * 6. Configurar en este archivo (asterisk.php):
 *    ASTERISK_USE_TURN = true
 *    ASTERISK_TURN_SERVER = 'turn:TU_IP_PUBLICA:3478'  # O tu dominio
 *    ASTERISK_TURN_USERNAME = 'usuario_turn'
 *    ASTERISK_TURN_CREDENTIAL = 'password_turn'
 * 
 * NOTA: Si no tienes un servidor TURN propio, puedes usar servicios públicos
 * como Metered.ca (https://www.metered.ca/tools/openrelay/) para pruebas,
 * pero NO es recomendado para producción debido a limitaciones y latencia.
 */
?>

