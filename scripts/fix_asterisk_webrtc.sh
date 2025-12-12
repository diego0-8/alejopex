#!/bin/bash

echo "=== CONFIGURACIÓN WEBRTC ASTERISK ==="
echo ""

# Backup de archivos de configuración
echo "1. Creando backups..."
cp /etc/asterisk/rtp.conf /etc/asterisk/rtp.conf.backup.$(date +%Y%m%d_%H%M%S)
cp /etc/asterisk/pjsip.conf /etc/asterisk/pjsip.conf.backup.$(date +%Y%m%d_%H%M%S)
cp /etc/asterisk/sip.conf /etc/asterisk/sip.conf.backup.$(date +%Y%m%d_%H%M%S)
echo "Backups creados"
echo ""

# Configurar RTP
echo "2. Configurando RTP..."
cat > /etc/asterisk/rtp.conf << 'EOF'
[general]
rtpstart=10000
rtpend=20000
rtcpinterval=5000
strictrtp=yes
icesupport=yes
EOF
echo "RTP configurado"
echo ""

# Configurar PJSIP para WebRTC
echo "3. Configurando PJSIP para WebRTC..."
cat >> /etc/asterisk/pjsip.conf << 'EOF'

; WebRTC Transport
[transport-wss]
type=transport
protocol=wss
bind=0.0.0.0:8089
cert_file=/etc/asterisk/keys/asterisk.pem
priv_key_file=/etc/asterisk/keys/asterisk.key

; WebRTC Endpoint Template
[webrtc-endpoint](!)
type=endpoint
context=from-internal
disallow=all
allow=ulaw
allow=alaw
allow=opus
direct_media=no
send_pai=yes
send_connected_line=yes
connected_line_method=invite
dtmf_mode=rfc4733
media_encryption=dtls
dtls_hash=SHA-256
dtls_setup=actpass
rtcp_mux=yes
ice_support=yes
EOF
echo "PJSIP configurado para WebRTC"
echo ""

# Configurar SIP para compatibilidad
echo "4. Configurando SIP..."
cat >> /etc/asterisk/sip.conf << 'EOF'

[general]
context=default
allowguest=no
allowoverlap=no
bindport=5060
bindaddr=0.0.0.0
srvlookup=yes
disallow=all
allow=ulaw
allow=alaw
allow=gsm
mohinterpret=default
mohsuggest=default
language=es
relaxdtmf=yes
trustrpid=yes
sendrpid=pai
sendrpid=yes
useragent=Asterisk PBX
promiscredir=no
videosupport=no
maxcallbitrate=384
dtmfmode=rfc2833
compactheaders=no
nat=force_rport,comedia
directmedia=no
icesupport=yes
rtcp_mux=yes
EOF
echo "SIP configurado"
echo ""

# Reiniciar Asterisk
echo "5. Reiniciando Asterisk..."
systemctl restart asterisk
sleep 5

# Verificar estado
echo "6. Verificando configuración..."
asterisk -rx "rtp show settings"
echo ""
asterisk -rx "pjsip show transports"
echo ""
asterisk -rx "core show codecs"
echo ""

echo "=== CONFIGURACIÓN COMPLETADA ==="
echo "Prueba la llamada WebRTC nuevamente"