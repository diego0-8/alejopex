/**
 * Softphone WebRTC corregido para Issabel
 * Versión optimizada y unificada.
 * 
 * Funciones: conectar/registrar, llamada saliente, llamada entrante (aceptar/rechazar), colgar,
 * manejo de micrófono con mediaStreamFactory y tonos (ring/ringback).
 * 
 * Cambios aplicados:
 * 1. Se eliminó la limpieza de rtcp-mux (ahora se permite).
 * 2. Se añadió .trim() a credenciales para evitar el Error 403.
 * 3. Se eliminó código duplicado y funciones redundantes.
 * 4. Habilitación de traceSip para depuración en consola.
 * 
 * IMPORTANTE: Este softphone requiere HTTPS para funcionar correctamente.
 * El navegador solo permite acceso al micrófono en contextos seguros.
 * 
 * NOTA: RTCP-MUX está habilitado en Asterisk/Issabel, por lo que se mantiene en el SDP.
 */

/**
 * Factory personalizada para SessionDescriptionHandler
 * Mantenemos RTCP-MUX ya que está habilitado en Asterisk/Issabel.
 * Los navegadores modernos lo exigen para WebRTC.
 * 
 * NOTA: Esta factory asegura que el mediaStreamFactory se pase correctamente
 * a todas las instancias de SessionDescriptionHandler.
 */
function createCustomSessionDescriptionHandlerFactory(softphone, peerConnectionConfig) {
    return function customSDHFactory(session, options) {
        const logger = session.userAgent.getLogger('sip.SessionDescriptionHandler', session.id);
        const sdhOptions = Object.assign({}, options || {});
        
        // CRÍTICO: Asegurar que rtcConfiguration o peerConnectionConfiguration esté presente
        if (!sdhOptions.rtcConfiguration && !sdhOptions.peerConnectionConfiguration) {
            sdhOptions.peerConnectionConfiguration = peerConnectionConfig;
        }

        // CRÍTICO: SIEMPRE usar el mediaStreamFactory del softphone (igual que APEX4.2 funcional)
        // Esto asegura que se use nuestro método personalizado en lugar del predeterminado de SIP.js
        sdhOptions.mediaStreamFactory = softphone.mediaStreamFactory;

        const sdh = new SIP.Web.SessionDescriptionHandler(logger, sdhOptions);
        
        // NO modificamos el SDP - mantenemos RTCP-MUX tal como viene
        return sdh;
    };
}

class WebRTCSoftphone {
    constructor(config) {
        this.config = config;
        this._validateConfig();

        this.userAgent = null;
        this.registerer = null;
        this.currentCall = null;
        this.incomingCall = null;
        this.acceptInProgress = false;

        this.status = 'disconnected';
        this.currentNumber = '';

        this.incomingCallAudio = null;
        this.ringbackAudio = null;
        this.remoteAudioElement = null;
        this.lastMediaStream = null;
        this.timer = null;
        this.callStart = null;

        // Estado del micrófono
        this.noAudioDevice = false;        // No hay dispositivo de audio físico
        this.micPermissionDenied = false;  // Usuario denegó el permiso
        this.micPermissionGranted = false; // Usuario otorgó el permiso
        this.audioDevices = [];            // Lista de dispositivos de audio

        this.mediaStreamFactory = this._mediaStreamFactory.bind(this);

        if (typeof SIP === 'undefined' || !SIP.UserAgent) {
                throw new Error('SIP.js no está cargado');
            }
            
        // Validar contexto seguro (HTTPS)
        this._validateSecureContext();

        this._initUI();

        // CRÍTICO: Solicitar micrófono ANTES de conectar al PBX (igual que APEX4.2 funcional)
        // Esto asegura que el permiso esté concedido cuando SIP.js intente adquirir el stream
        this._requestMicrophonePermissionBeforeConnect().then(() => {
            this._connect();
        }).catch((err) => {
            console.warn('⚠️ [Softphone] Error al solicitar permiso de micrófono antes de conectar:', err);
            // Continuar con la conexión aunque falle el permiso (el stream silencioso funcionará)
            this._connect();
        });
    }

    /* -------------------------------------------------------------
     * Validación de contexto seguro (HTTPS)
     * ------------------------------------------------------------- */
    _validateSecureContext() {
        const isSecure = window.isSecureContext ||
            window.location.protocol === 'https:' ||
            window.location.hostname === 'localhost' ||
            window.location.hostname === '127.0.0.1';

        if (!isSecure) {
            console.error('❌ [Softphone] ADVERTENCIA: El sitio no está en HTTPS.');
            console.error('   WebRTC requiere HTTPS para acceder al micrófono.');
            console.error('   URL actual:', window.location.href);

            // Mostrar advertencia visual
            this._showSecurityWarning();
        } else {
            if (this.config.debug_mode) {
                console.log('✅ [Softphone] Contexto seguro verificado:', window.location.protocol);
            }
        }

        return isSecure;
    }

    _showSecurityWarning() {
        const warning = document.createElement('div');
        warning.id = 'softphone-security-warning';
        warning.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#dc3545;color:white;padding:10px;text-align:center;z-index:99999;font-weight:bold;';
        warning.innerHTML = '⚠️ Softphone: Se requiere HTTPS para usar el micrófono. <a href="#" onclick="this.parentElement.remove();return false;" style="color:white;margin-left:20px;">✕ Cerrar</a>';
        document.body.insertBefore(warning, document.body.firstChild);
    }

    /* -------------------------------------------------------------
     * Solicitar permiso de micrófono ANTES de conectar al PBX
     * Esto asegura que el permiso esté concedido cuando SIP.js lo necesite
     * (igual que APEX4.2 funcional - solicita antes de conectar)
     * ------------------------------------------------------------- */
    async _requestMicrophonePermissionBeforeConnect() {
        if (this.config.debug_mode) {
            console.log('🎤 [Softphone] Solicitando permiso de micrófono ANTES de conectar al PBX...');
        }

        // Verificar si getUserMedia está disponible
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('⚠️ [Softphone] getUserMedia no disponible');
            return;
        }

        // Verificar si ya tenemos permiso usando Permissions API
        try {
            if (navigator.permissions && navigator.permissions.query) {
                const result = await navigator.permissions.query({ name: 'microphone' });
                if (this.config.debug_mode) {
                    console.log('🎤 [Softphone] Estado del permiso de micrófono:', result.state);
                }

                if (result.state === 'granted') {
                    this.micPermissionGranted = true;
                    this._updateMicStatus('granted');
                    if (this.config.debug_mode) {
                        console.log('✅ [Softphone] Permiso de micrófono ya concedido');
                    }
                    return;
                } else if (result.state === 'denied') {
                    this.micPermissionDenied = true;
                    this._updateMicStatus('denied');
                    if (this.config.debug_mode) {
                        console.warn('⚠️ [Softphone] Permiso de micrófono denegado previamente');
                    }
                    return;
                }
            }
        } catch (e) {
            // Permissions API puede no estar disponible, continuar
            if (this.config.debug_mode) {
                console.log('ℹ️ [Softphone] Permissions API no disponible, solicitando directamente');
            }
        }

        // Solicitar permiso usando getUserMedia (igual que APEX4.2 funcional)
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            
            // Permiso concedido - detener el stream inmediatamente (solo queríamos el permiso)
            stream.getTracks().forEach(t => t.stop());
            
            this.micPermissionGranted = true;
            this.micPermissionDenied = false;
            this.noAudioDevice = false;
            this._updateMicStatus('granted');
            
            if (this.config.debug_mode) {
                console.log('✅ [Softphone] Permiso de micrófono concedido - Listo para conectar al PBX');
            }
            
        } catch (err) {
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                console.warn('⚠️ [Softphone] Permiso de micrófono DENEGADO por el usuario');
                this.micPermissionDenied = true;
                this._updateMicStatus('denied');
            } else if (err.name === 'NotFoundError') {
                console.warn('⚠️ [Softphone] No se encontró micrófono disponible');
                this.noAudioDevice = true;
                this._updateMicStatus('no-device');
            } else {
                console.error('❌ [Softphone] Error al solicitar permiso:', err);
            }
            // No lanzar el error - continuar con la conexión (el stream silencioso funcionará)
        }
    }

    /* -------------------------------------------------------------
     * Inicialización de dispositivos de audio (mantenido para compatibilidad)
     * ------------------------------------------------------------- */
    async _initializeAudioDevices() {
        try {
            // 1. Detectar dispositivos de audio disponibles
            await this._detectAudioDevices();

            // 2. Si hay dispositivos, solicitar permisos proactivamente
            if (this.audioDevices.length > 0 && !this.noAudioDevice) {
                await this._requestMicrophonePermission();
            }
        } catch (err) {
            console.warn('⚠️ [Softphone] Error inicializando audio:', err);
        }
    }

    async _detectAudioDevices() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                console.warn('⚠️ [Softphone] enumerateDevices no disponible');
                return;
            }

            const devices = await navigator.mediaDevices.enumerateDevices();
            this.audioDevices = devices.filter(d => d.kind === 'audioinput');

                if (this.config.debug_mode) {
                console.log('🎤 [Softphone] Dispositivos de audio detectados:', this.audioDevices.length);
                this.audioDevices.forEach((d, i) => {
                    console.log(`   ${i + 1}. ${d.label || 'Sin nombre'} (${d.deviceId.substring(0, 8)}...)`);
                });
            }

            if (this.audioDevices.length === 0) {
                console.warn('⚠️ [Softphone] No se detectaron micrófonos.');
                this.noAudioDevice = true;
                this._updateMicStatus('no-device');
                                } else {
                this.noAudioDevice = false;
                this._updateMicStatus('pending');
            }

        } catch (err) {
            console.error('❌ [Softphone] Error detectando dispositivos:', err);
        }
    }

    async _requestMicrophonePermission() {
        // No solicitar si ya sabemos que no hay dispositivo o el permiso fue denegado
        if (this.noAudioDevice || this.micPermissionDenied) {
            return false;
        }

        // Verificar si ya tenemos un permiso previo usando Permissions API
        try {
            if (navigator.permissions && navigator.permissions.query) {
                const result = await navigator.permissions.query({ name: 'microphone' });
                    if (this.config.debug_mode) {
                    console.log('🎤 [Softphone] Estado del permiso de micrófono:', result.state);
                }

                if (result.state === 'granted') {
                    this.micPermissionGranted = true;
                    this._updateMicStatus('granted');
                    return true;
                } else if (result.state === 'denied') {
                    this.micPermissionDenied = true;
                    this._updateMicStatus('denied');
                    return false;
                }
                // Si es 'prompt', continuamos para solicitar el permiso
            }
        } catch (e) {
            // Permissions API puede no estar disponible
                        if (this.config.debug_mode) {
                console.log('ℹ️ [Softphone] Permissions API no disponible, solicitando directamente');
            }
        }

        // Solicitar permiso usando getUserMedia
        try {
                    if (this.config.debug_mode) {
                console.log('🎤 [Softphone] Solicitando permiso de micrófono...');
            }

            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });

            // Permiso concedido - detener el stream inmediatamente (solo queríamos el permiso)
            stream.getTracks().forEach(t => t.stop());

            this.micPermissionGranted = true;
            this.micPermissionDenied = false;
            this.noAudioDevice = false;
            this._updateMicStatus('granted');

            if (this.config.debug_mode) {
                console.log('✅ [Softphone] Permiso de micrófono concedido');
            }

            // Re-detectar dispositivos (ahora tendrán etiquetas)
            await this._detectAudioDevices();

            return true;

        } catch (err) {
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                console.warn('⚠️ [Softphone] Permiso de micrófono DENEGADO por el usuario');
                this.micPermissionDenied = true;
                this._updateMicStatus('denied');
            } else if (err.name === 'NotFoundError') {
                console.warn('⚠️ [Softphone] No se encontró micrófono disponible');
                this.noAudioDevice = true;
                this._updateMicStatus('no-device');
                } else {
                console.error('❌ [Softphone] Error al solicitar permiso:', err);
                this._updateMicStatus('error');
            }
            return false;
        }
    }

    _updateMicStatus(status) {
        const indicator = document.getElementById('mic-status-indicator');
        if (!indicator) return;

        const statusMap = {
            'pending': { icon: 'fa-microphone-slash', color: '#ffc107', title: 'Micrófono: pendiente de permiso' },
            'granted': { icon: 'fa-microphone', color: '#28a745', title: 'Micrófono: listo' },
            'denied': { icon: 'fa-microphone-slash', color: '#dc3545', title: 'Micrófono: permiso denegado' },
            'no-device': { icon: 'fa-microphone-slash', color: '#6c757d', title: 'Micrófono: no detectado' },
            'error': { icon: 'fa-exclamation-triangle', color: '#dc3545', title: 'Micrófono: error' }
        };

        const s = statusMap[status] || statusMap['error'];
        indicator.innerHTML = `<i class="fas ${s.icon}" style="color:${s.color}"></i>`;
        indicator.title = s.title;
        indicator.style.cursor = status === 'denied' ? 'pointer' : 'default';

        // Si fue denegado, permitir hacer clic para reintentar
        if (status === 'denied') {
            indicator.onclick = () => this._retryMicrophonePermission();
        } else {
            indicator.onclick = null;
        }
    }

    async _retryMicrophonePermission() {
        // Resetear banderas
        this.micPermissionDenied = false;
        this.noAudioDevice = false;

        // Intentar de nuevo
        const granted = await this._requestMicrophonePermission();

        if (!granted) {
            this._showError('Por favor, permite el acceso al micrófono en la configuración del navegador.');
        }
    }

    /* -------------------------------------------------------------
     * Config & UI
     * ------------------------------------------------------------- */
    _validateConfig() {
        const required = ['extension', 'password', 'wss_server', 'sip_domain'];
        const missing = required.filter((k) => !this.config[k] || String(this.config[k]).trim() === '');
        if (missing.length) throw new Error('Config incompleta: ' + missing.join(', '));
    }

    _initUI() {
        const c = document.getElementById('webrtc-softphone');
        if (!c) return;
        c.innerHTML = `
            <div class="softphone-header">
                <h3>
                    <i class="fas fa-phone"></i> Softphone WebRTC
                    <span id="mic-status-indicator" style="margin-left:10px;font-size:14px;" title="Estado del micrófono">
                        <i class="fas fa-microphone-slash" style="color:#ffc107"></i>
                    </span>
                </h3>
                </div>
            <div class="softphone-body">
                <div class="softphone-status">
                    <span class="status-dot disconnected" id="status-dot"></span>
                    <span id="status-text">Desconectado</span>
                </div>
                <div class="number-input-container">
                    <div class="number-display" id="number-display">Ingrese número</div>
                </div>
                <div class="dialpad" id="dialpad">
                    ${['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'].map(n => `<button class="dialpad-btn" data-number="${n}">${n}</button>`).join('')}
                </div>
                <div class="action-buttons">
                    <button class="action-btn delete-btn" id="btn-delete">Borrar</button>
                    <button class="action-btn call-btn" id="btn-call">Llamar</button>
                    <button class="action-btn hangup-btn" id="btn-hangup" style="display:none;">Colgar</button>
                </div>
                <div class="call-info" id="call-info" style="display:none;">
                    <div id="call-info-number"></div>
                    <div id="call-info-duration">00:00</div>
                    <div id="call-info-status">Llamando...</div>
                </div>
            </div>
        `;
        
        const dialpad = c.querySelector('#dialpad');
        dialpad?.addEventListener('click', (e) => {
            const btn = e.target.closest('.dialpad-btn');
            if (btn) this._addDigit(btn.dataset.number);
        });
        c.querySelector('#btn-delete')?.addEventListener('click', () => this._deleteLastDigit());
        c.querySelector('#btn-call')?.addEventListener('click', () => this.makeCall());
        c.querySelector('#btn-hangup')?.addEventListener('click', () => this.hangup());
        
        // Configurar eventos de teclado para marcar con el teclado físico
        this._setupKeyboardEvents();
    }

    /* -------------------------------------------------------------
     * Conexión y registro
     * ------------------------------------------------------------- */
    _connect() {
        const iceServers = this._getIceServers();
        
        // LIMPIEZA CRÍTICA: Elimina espacios que causan el Error 403
        const extensionStr = String(this.config.extension).trim();
        const passwordStr = String(this.config.password).trim();
        const domainStr = String(this.config.sip_domain).trim();
        
        // DEBUG: Verificar valores antes de usarlos
            if (this.config.debug_mode) {
            console.log(`📝 [WebRTC] Intentando registro: ${extensionStr} @ ${domainStr}`);
            console.log('🔍 [SOFTPHONE _connect] Verificando credenciales:');
            console.log('  - extensionStr:', extensionStr);
            console.log('  - passwordStr longitud:', passwordStr.length);
            console.log('  - domainStr:', domainStr);
        }
        
        const uriString = `sip:${extensionStr}@${domainStr}`;
        let userURI = SIP.UserAgent.makeURI(uriString);
        userURI = this._patchUriClone(userURI);

        // Configuración RTC para el PeerConnection
        // RTCP-MUX está habilitado en Asterisk/Issabel, así que lo mantenemos
        const rtcConfig = {
            iceServers: iceServers,
                        iceTransportPolicy: 'all',
                        bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'negotiate' // Negociar RTCP-MUX (más flexible que 'require')
        };

        // Usar nuestro SessionDescriptionHandlerFactory personalizado
        const customSDHFactory = createCustomSessionDescriptionHandlerFactory(this, rtcConfig);
        
        this.userAgent = new SIP.UserAgent({
            uri: userURI,
            authorizationUsername: extensionStr,
            authorizationPassword: passwordStr,
            transportOptions: { 
                server: this.config.wss_server, 
                keepAliveInterval: 30,
                traceSip: this.config.debug_mode // Habilitar traza SIP para debugging
            },
            sessionDescriptionHandlerFactory: customSDHFactory,
            sessionDescriptionHandlerFactoryOptions: {
                // CRÍTICO: Pasar mediaStreamFactory en sessionDescriptionHandlerFactoryOptions (igual que APEX4.2 funcional)
                // Esto asegura que todas las sesiones usen nuestro mediaStreamFactory
                mediaStreamFactory: this.mediaStreamFactory,
                iceServers,
                // CRÍTICO: Usar rtcConfiguration en lugar de peerConnectionConfiguration (igual que APEX4.2 funcional)
                rtcConfiguration: {
                    iceServers: iceServers,
                    iceTransportPolicy: 'all',
                    bundlePolicy: 'max-bundle',
                    rtcpMuxPolicy: 'negotiate'
                }
            },
            delegate: { onInvite: (inv) => this._onIncoming(inv) }
        });

        this.userAgent.onInvite = (inv) => this._onIncoming(inv);
        this._setupTransportEvents();

        this._updateStatus('connecting', 'Conectando...');
        this.userAgent.start()
            .then(() => {
                const registrarURI = this._patchUriClone(SIP.UserAgent.makeURI(`sip:${domainStr}`));
                
                // Mejorar manejo de errores de registro, especialmente 403
                this.registerer = new SIP.Registerer(this.userAgent, { 
                    registrar: registrarURI,
                    expires: 600,
                    requestDelegate: {
                        onReject: (response) => {
                            const code = response.message.statusCode;
                            const reason = response.message.reasonPhrase || 'Sin razón especificada';
                            
                            console.error(`❌ [WebRTC Softphone] Registro RECHAZADO. Código: ${code} - ${reason}`);
                            
                            if (code === 403) {
                                console.warn('⚠️ REVISIÓN REQUERIDA:');
                                console.warn('   1. Clave incorrecta: Verifica que la contraseña en PHP sea idéntica al "secret" en el PBX');
                                console.warn('   2. IP bloqueada: Verifica que el campo "permit" de la extensión esté VACÍO');
                                console.warn('   3. Transporte WSS: Verifica que la extensión tenga "Transport: wss" activado');
                                console.warn('   4. Realm: Verifica que el "Realm" en pjsip.conf coincida con ASTERISK_SIP_DOMAIN');
                                this._updateStatus('disconnected', 'Error 403: Verificar credenciales');
                            }
                        }
                    }
                });
                
                this.registerer.stateChange.addListener((st) => {
                    console.log('🔄 [WebRTC] Estado SIP:', st);
                    if (st === SIP.RegistererState.Registered) {
                        this._updateStatus('connected', 'En línea');
                        console.log('🎉 [WebRTC] ¡Softphone registrado con éxito!');
                    } else if (st === SIP.RegistererState.Unregistered) {
                        this._updateStatus('disconnected', 'Sin registro');
                    }
                });
                
                return this.registerer.register();
            })
            .catch((err) => {
                console.error('❌ Conexión/Registro falló:', err);
                this._updateStatus('disconnected', 'Error de conexión');
            });
    }

    _setupTransportEvents() {
        if (!this.userAgent?.transport) return;
        this.userAgent.transport.stateChange.addListener((st) => {
            if (st === 'Connected') {
            if (this.config.debug_mode) {
                    console.log('✅ [WebRTC] WebSocket Conectado');
                }
                this._updateStatus('connected', 'En línea');
            }
            if (st === 'Disconnected') {
            if (this.config.debug_mode) {
                    console.warn('❌ [WebRTC] WebSocket Desconectado');
                }
                this._updateStatus('disconnected', 'Desconectado');
            }
        });
    }

    /* -------------------------------------------------------------
     * Llamada saliente
     * ------------------------------------------------------------- */
    async makeCall() {
        if (!this.currentNumber.trim()) {
            this._showError('Ingrese un número');
            return;
        }
        if (!this.userAgent || !this.registerer) {
            this._showError('No está conectado');
            return;
        }
        const regState = this.registerer.state;
        if (regState !== SIP.RegistererState.Registered && regState !== 'Registered') {
            this._showError('No está registrado');
                return;
            }
        if (this.currentCall) {
            this._showError('Ya hay una llamada en curso');
                return;
            }
            
        // LIMPIEZA: Asegurar que el número y dominio no tengan espacios
        const number = this.currentNumber.trim();
        const domainStr = String(this.config.sip_domain).trim();
        const targetUri = this._patchUriClone(SIP.UserAgent.makeURI(`sip:${number}@${domainStr}`));

        // Configuración RTC
        // RTCP-MUX está habilitado en Asterisk/Issabel
        const rtcConfig = {
            iceServers: this._getIceServers(),
            iceTransportPolicy: 'all',
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'negotiate' // Negociar RTCP-MUX (más flexible que 'require')
        };

        const inviter = new SIP.Inviter(this.userAgent, targetUri, {
            sessionDescriptionHandlerOptions: {
                // CRÍTICO: Constraints explícitos (igual que APEX4.2 funcional)
                constraints: {
                    audio: true,
                    video: false
                },
                iceServers: this._getIceServers(),
                // CRÍTICO: Usar rtcConfiguration (no peerConnectionConfiguration) en Inviter
                rtcConfiguration: {
                    iceServers: this._getIceServers(),
                    iceTransportPolicy: 'all',
                    bundlePolicy: 'max-bundle',
                    rtcpMuxPolicy: 'negotiate' // Negociar RTCP-MUX (más flexible que 'require')
                },
                // CRÍTICO: Pasar mediaStreamFactory como función async (igual que APEX4.2 funcional)
                // Este es el ÚNICO método que debe usarse para adquirir el stream
                mediaStreamFactory: async () => {
                    if (this.config.debug_mode) {
                        console.log('🎤 [Softphone] mediaStreamFactory llamada para hacer llamada');
                    }
                    return await this._mediaStreamFactory();
                }
            },
            requestDelegate: {
                onAccept: () => {
                    this._updateStatus('in-call', 'En llamada');
                    this._showCallInfo(number);
                    this._startCallTimer();
                    this._stopRingback();
                },
                onReject: () => {
                    this._showError('Llamada rechazada');
                    this.endCall();
                }
            }
        });

        this.currentCall = inviter;
        this._updateStatus('in-call', 'Llamando...');
        this._showCallInfo(number);

        inviter.stateChange.addListener((st) => {
            const s = String(st);
            if (s === 'Established' || s === '4') {
                this._updateStatus('in-call', 'En llamada');
                this._stopRingback();
                this._startCallTimer();
                this._setupAudio(inviter);
            } else if (s === 'Progress' || s === '2' || s === 'Establishing' || s === '3' || s === 'Ringing' || s === '1') {
                this._playRingback();
            } else if (s === 'Terminated' || s === '5') {
                this.endCall();
            }
        });

        try {
                    if (this.config.debug_mode) {
                console.log(`🚀 [Softphone] Llamando a ${number}...`);
                    }
            await inviter.invite();
                if (this.config.debug_mode) {
                console.log('✅ [Softphone] INVITE enviado');
            }
        } catch (err) {
            console.error('❌ Error INVITE:', err);
            const msg = String(err?.message || err);
            if (msg.includes('NotFoundError')) {
                console.warn('⚠️ INVITE falló por falta de dispositivo de audio (NotFoundError).');
                this._showError('No se encontró micrófono. Verifica que esté conectado.');
            } else {
                this._showError('Error al invitar: ' + msg);
            }
            this.endCall();
        }
    }

    /* -------------------------------------------------------------
     * Llamada entrante
     * ------------------------------------------------------------- */
    _onIncoming(invitation) {
        if (this.currentCall) {
            invitation.reject();
            return;
        }
        this.incomingCall = invitation;
        this.currentNumber = this._extractCaller(invitation) || 'Desconocido';
        this._showIncomingNotification(this.currentNumber);
        this._playIncoming();

        invitation.stateChange.addListener((st) => {
            const s = String(st);
            if (s === 'Established' || s === '4') {
                this.currentCall = invitation;
                this.incomingCall = null;
                this._updateStatus('in-call', 'En llamada');
                this._hideIncomingNotification();
                this._stopIncoming();
                this._showCallInfo(this.currentNumber);
                this._startCallTimer();
                setTimeout(() => this._setupAudio(invitation), 300);
            } else if (s === 'Terminated' || s === '5' || s === 'Canceled') {
                this._hideIncomingNotification();
                this.endCall();
            }
        });
    }

    _showIncomingNotification(caller) {
        let notif = document.getElementById('incoming-call-notification');
        if (!notif) {
            notif = document.createElement('div');
            notif.id = 'incoming-call-notification';
            notif.style.cssText = 'position:fixed;top:20px;right:20px;background:linear-gradient(135deg,#28a745,#20c997);color:white;padding:20px 30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;min-width:300px;';
            document.body.appendChild(notif);
        }
        notif.innerHTML = `
            <div style="display:flex;align-items:center;gap:15px;">
                <div style="flex:1;">
                    <div style="font-size:14px;opacity:0.9;margin-bottom:5px;">Llamada Entrante</div>
                    <div style="font-size:20px;font-weight:700;">${this._escapeHtml(caller)}</div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button onclick="window.webrtcSoftphone?.acceptIncomingCall()" style="background:white;color:#28a745;border:none;border-radius:50%;width:50px;height:50px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button onclick="window.webrtcSoftphone?.rejectIncomingCall()" style="background:#dc3545;color:white;border:none;border-radius:50%;width:50px;height:50px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                </div>
            </div>
        `;
        notif.style.display = 'block';
    }

    _hideIncomingNotification() {
        const notif = document.getElementById('incoming-call-notification');
        if (notif) notif.style.display = 'none';
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async acceptIncomingCall() {
        if (!this.incomingCall || this.acceptInProgress) return;
        const state = String(this.incomingCall.state);
        if (state === 'Established' || state === '4' || state === 'Establishing' || state === '3') {
            this.currentCall = this.incomingCall;
            this._hideIncomingNotification();
            return;
        }
        if (state === 'Terminated' || state === '5' || state === 'Canceled') {
            this._hideIncomingNotification();
                        this.endCall();
            return;
                    }
        this.acceptInProgress = true;
        try {
            await this.incomingCall.accept({
                sessionDescriptionHandlerOptions: {
                    // CRÍTICO: Constraints explícitos (igual que APEX4.2 funcional)
                    constraints: {
                        audio: true,
                        video: false
                    },
                    iceServers: this._getIceServers(),
                    // CRÍTICO: Usar rtcConfiguration (no peerConnectionConfiguration) en accept
                    rtcConfiguration: {
                        iceServers: this._getIceServers(),
                        iceTransportPolicy: 'all',
                        bundlePolicy: 'max-bundle',
                        rtcpMuxPolicy: 'negotiate' // Negociar RTCP-MUX (más flexible que 'require')
                    },
                    // CRÍTICO: Pasar mediaStreamFactory como función async (igual que APEX4.2 funcional)
                    // Este es el ÚNICO método que debe usarse para adquirir el stream
                    mediaStreamFactory: async () => {
                        if (this.config.debug_mode) {
                            console.log('🎤 [Softphone] mediaStreamFactory LLAMADA PARA CONTESTAR');
                        }
                        // Adquirir stream antes de contestar
                        return await this._mediaStreamFactory();
                    }
                }
            });
            this.currentCall = this.incomingCall;
            this.incomingCall = null;
            this._hideIncomingNotification();
            this._stopIncoming();
            this._showCallInfo(this.currentNumber);
        } catch (e) {
            console.error('❌ Error al aceptar:', e);
            const msg = String(e.message || e);
            if (!msg.includes('Invalid session state')) {
                this._showError('No se pudo aceptar la llamada');
            }
            this._hideIncomingNotification();
            if (!msg.includes('Invalid session state')) {
                    this.endCall();
            }
        } finally {
            this.acceptInProgress = false;
        }
    }

    rejectIncomingCall() {
        if (!this.incomingCall) return;
        try { this.incomingCall.reject(); } catch (_) { }
        this._hideIncomingNotification();
        this._stopIncoming();
        this.incomingCall = null;
        this._updateStatus('connected', 'En línea');
    }

    /* -------------------------------------------------------------
     * Colgar / finalizar
     * ------------------------------------------------------------- */
    hangup() {
        if (!this.currentCall) return;
            try {
                const state = String(this.currentCall.state);
            if (state === 'Establishing' || state === '3' || state === 'Progress' || state === '2') {
                this.currentCall.cancel?.();
                    } else {
                this.currentCall.bye?.();
            }
        } catch (e) {
            console.warn('⚠️ error al colgar', e);
        }
                this.endCall();
    }
    
    endCall() {
        this._stopIncoming();
        this._stopRingback();
        this._hideIncomingNotification();
        if (this.remoteAudioElement) {
                this.remoteAudioElement.pause();
                this.remoteAudioElement.srcObject = null;
        }
        this._releaseLastMediaStream();
        this.currentCall = null;
        this.incomingCall = null;
        this.currentNumber = '';
        this._stopCallTimer();
        this._hideCallInfo();
        this._updateStatus('connected', 'En línea');
    }

    /* -------------------------------------------------------------
     * Audio y media
     * ------------------------------------------------------------- */
    async _mediaStreamFactory(constraintsFromSIP = {}) {
        if (this.config.debug_mode) {
            console.log('🎤 [Softphone] mediaStreamFactory LLAMADA POR SIP.js');
        }
        
        // Constraints finales: audio simple (igual que APEX4.2 funcional)
        const finalConstraints = { audio: true, video: false };
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (this.config.debug_mode) {
                console.error('❌ [Softphone] getUserMedia no disponible en este navegador/contexto.');
            }
            // Igual que APEX4.2 funcional: lanzar error en lugar de devolver stream silencioso
            throw new Error('getUserMedia no disponible en este navegador/contexto.');
        }
        
        try {
            // Liberar stream anterior si existe
            this._releaseLastMediaStream();
            
            // Intentar adquirir el stream (igual que APEX4.2 funcional)
            const stream = await navigator.mediaDevices.getUserMedia(finalConstraints);
            this.lastMediaStream = stream;
            
            // Actualizar estado
            this.micPermissionGranted = true;
            this.micPermissionDenied = false;
            this.noAudioDevice = false;
            this._updateMicStatus('granted');
            
            if (this.config.debug_mode) {
                const audioTracks = stream.getAudioTracks();
                console.log(`✅ [Softphone] MediaStream adquirido. Tracks: ${audioTracks.length}`);
                audioTracks.forEach((t, i) => {
                    console.log(`   Track ${i}: ${t.label || 'Sin nombre'} (${t.readyState})`);
                });
            }
            
            return stream;
            
        } catch (error) {
            if (this.config.debug_mode) {
                console.error('❌ [Softphone] mediaStreamFactory no pudo abrir el micrófono:', error);
            }
            
            // Actualizar estado según el error
            if (error && error.name === 'NotFoundError') {
                console.warn('⚠️ [Softphone] No se encontró dispositivo de audio.');
                this.noAudioDevice = true;
                this._updateMicStatus('no-device');
            } else if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
                console.warn('⚠️ [Softphone] Permiso de micrófono denegado.');
                this.micPermissionDenied = true;
                this._updateMicStatus('denied');
            } else {
                console.error('❌ [Softphone] Error desconocido:', error.message);
                this._updateMicStatus('error');
            }
            
            // Igual que APEX4.2 funcional: lanzar el error en lugar de devolver stream silencioso
            // Esto permite que SIP.js maneje el error correctamente
            throw error;
        }
    }

    _releaseLastMediaStream() {
        if (this.lastMediaStream) {
            this.lastMediaStream.getTracks().forEach((t) => t.stop());
            this.lastMediaStream = null;
        }
    }

    _setupAudio(session) {
        if (!session?.sessionDescriptionHandler) return;
        const pc = session.sessionDescriptionHandler.peerConnection;
        if (!pc) return;

        if (!this.remoteAudioElement) {
            this.remoteAudioElement = document.createElement('audio');
            this.remoteAudioElement.autoplay = true;
            this.remoteAudioElement.playsInline = true;
            this.remoteAudioElement.style.display = 'none';
            document.body.appendChild(this.remoteAudioElement);
        }

        const attach = () => {
            const receivers = pc.getReceivers ? pc.getReceivers() : [];
            receivers.forEach((r) => {
                if (r.track && r.track.kind === 'audio') {
                    const ms = new MediaStream([r.track]);
                    this.remoteAudioElement.srcObject = ms;
                    this.remoteAudioElement.play().catch(() => { });
                }
            });
        };
        attach();
        pc.addEventListener('track', (ev) => {
            if (ev.track?.kind === 'audio') {
                const ms = new MediaStream([ev.track]);
                this.remoteAudioElement.srcObject = ms;
                this.remoteAudioElement.play().catch(() => { });
            }
        });
    }

    /* -------------------------------------------------------------
     * UI helpers
     * ------------------------------------------------------------- */
    /* -------------------------------------------------------------
     * Configurar eventos de teclado para marcar con el teclado físico
     * (igual que APEX4.2 funcional)
     * ------------------------------------------------------------- */
    _setupKeyboardEvents() {
        // Solo capturar teclas cuando no hay un input activo (para no interferir con modales)
        document.addEventListener('keydown', (e) => {
            // Ignorar si hay un input, textarea o modal activo
            const activeElement = document.activeElement;
            const isInputActive = activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.isContentEditable ||
                activeElement.closest('.softphone-modal') ||
                activeElement.closest('.modal')
            );
            
            // Ignorar si hay una llamada en curso
            if (this.currentCall) {
                return;
            }
            
            // Si hay un input activo, no procesar las teclas
            if (isInputActive) {
                return;
            }
            
            // Capturar números del teclado (0-9, *, #)
            const key = e.key;
            
            // Números del 0 al 9
            if (key >= '0' && key <= '9') {
                e.preventDefault();
                this._addDigit(key);
                if (this.config.debug_mode) {
                    console.log('⌨️ [Softphone] Dígito agregado desde teclado:', key);
                }
            }
            // Asterisco
            else if (key === '*' || (key === '8' && e.shiftKey)) {
                e.preventDefault();
                this._addDigit('*');
                if (this.config.debug_mode) {
                    console.log('⌨️ [Softphone] Dígito agregado desde teclado: *');
                }
            }
            // Numeral
            else if (key === '#' || (key === '3' && e.shiftKey)) {
                e.preventDefault();
                this._addDigit('#');
                if (this.config.debug_mode) {
                    console.log('⌨️ [Softphone] Dígito agregado desde teclado: #');
                }
            }
            // Backspace para borrar
            else if (key === 'Backspace' || key === 'Delete') {
                e.preventDefault();
                this._deleteLastDigit();
                if (this.config.debug_mode) {
                    console.log('⌨️ [Softphone] Último dígito borrado desde teclado');
                }
            }
            // Enter para llamar
            else if (key === 'Enter' && this.currentNumber && this.currentNumber.trim() !== '') {
                e.preventDefault();
                this.makeCall();
                if (this.config.debug_mode) {
                    console.log('⌨️ [Softphone] Llamada iniciada desde teclado (Enter)');
                }
            }
        });
        
        if (this.config.debug_mode) {
            console.log('⌨️ [Softphone] Eventos de teclado configurados');
        }
    }

    _addDigit(d) { this.currentNumber += d; this._updateNumberDisplay(); }
    _deleteLastDigit() { this.currentNumber = this.currentNumber.slice(0, -1); this._updateNumberDisplay(); }
    _updateNumberDisplay() { const el = document.getElementById('number-display'); if (el) el.textContent = this.currentNumber || 'Ingrese número'; }
    _updateStatus(status, text) {
        this.status = status;
        const dot = document.getElementById('status-dot');
        const txt = document.getElementById('status-text');
        if (dot) dot.className = `status-dot ${status}`;
        if (txt) txt.textContent = text || status;
    }
    _showCallInfo(num) {
        const ci = document.getElementById('call-info'); const n = document.getElementById('call-info-number');
        const btnC = document.getElementById('btn-call'); const btnH = document.getElementById('btn-hangup');
        if (ci) ci.style.display = 'block';
        if (n) n.textContent = num;
        if (btnC) btnC.style.display = 'none';
        if (btnH) btnH.style.display = 'inline-block';
    }
    _hideCallInfo() {
        const ci = document.getElementById('call-info');
        const btnC = document.getElementById('btn-call'); const btnH = document.getElementById('btn-hangup');
        if (ci) ci.style.display = 'none';
        if (btnC) btnC.style.display = 'inline-block';
        if (btnH) btnH.style.display = 'none';
        this._updateNumberDisplay();
    }
    _startCallTimer() {
        this._stopCallTimer();
        this.callStart = Date.now();
        this.timer = setInterval(() => {
            const s = Math.floor((Date.now() - this.callStart) / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2, '0');
            const ss = String(s % 60).padStart(2, '0');
            const el = document.getElementById('call-info-duration');
            if (el) el.textContent = `${mm}:${ss}`;
                        }, 1000);
    }
    _stopCallTimer() { if (this.timer) clearInterval(this.timer); this.timer = null; }

    /* -------------------------------------------------------------
     * Tonos
     * ------------------------------------------------------------- */
    _playIncoming() {
        this._stopRingback();
        if (!this.incomingCallAudio) {
            this.incomingCallAudio = new Audio('assets/audio/ringtone.mp3');
            this.incomingCallAudio.loop = true;
            this.incomingCallAudio.volume = 0.7;
        }
        this.incomingCallAudio.currentTime = 0;
        this.incomingCallAudio.play().catch(() => { });
    }
    _stopIncoming() { if (this.incomingCallAudio) { this.incomingCallAudio.pause(); this.incomingCallAudio.currentTime = 0; } }
    _playRingback() {
        this._stopIncoming();
        if (!this.ringbackAudio) {
            this.ringbackAudio = new Audio('assets/audio/ringback.mp3');
            this.ringbackAudio.loop = true;
            this.ringbackAudio.volume = 0.6;
        }
        this.ringbackAudio.currentTime = 0;
        this.ringbackAudio.play().catch(() => { });
    }
    _stopRingback() { if (this.ringbackAudio) { this.ringbackAudio.pause(); this.ringbackAudio.currentTime = 0; } }

    /* -------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------- */
    _getIceServers() {
        if (this.config.iceServers && Array.isArray(this.config.iceServers) && this.config.iceServers.length) return this.config.iceServers;
        if (this.config.stun_server) return [{ urls: this.config.stun_server.startsWith('stun:') ? this.config.stun_server : `stun:${this.config.stun_server}` }];
        return [{ urls: 'stun:stun.l.google.com:19302' }];
    }
    _patchUriClone(uri) {
        if (!uri || typeof uri !== 'object') return uri;
        if (typeof uri.clone === 'function') return uri;
        const raw = uri.toString();
        uri.clone = () => SIP.UserAgent.makeURI(raw) || uri;
        return uri;
    }
    _extractCaller(inv) {
        try {
            const uri = inv.remoteIdentity?.uri;
            if (uri?.user) return uri.user;
            const h = inv.request?.headers?.From;
            const m = h && h.match(/sip:(\d+)@/);
            if (m && m[1]) return m[1];
        } catch (_) { }
        return null;
    }
    _showError(msg) {
        console.error('❌ Softphone:', msg);
        if (this.config.debug_mode) alert(msg);
    }

    // Stream silencioso cuando no hay micrófono disponible
    _createSilentAudioStream() {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const dest = ctx.createMediaStreamDestination();
        const source = ctx.createBufferSource();
        source.buffer = ctx.createBuffer(1, 1, ctx.sampleRate); // silencio
        source.loop = true;
        source.connect(dest);
        source.start();
        return dest.stream;
    }
}

if (typeof window !== 'undefined') {
    window.WebRTCSoftphone = WebRTCSoftphone;
    window.webrtcSoftphone = null; // Se asignará cuando se instancie
}
