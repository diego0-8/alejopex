/**
 * Softphone WebRTC para Sistema APEX
 * Implementación completa de softphone usando SIP.js y WebRTC
 * Solo visible para asesores con extensión y clave SIP asignadas
 */

class WebRTCSoftphone {
    constructor(config) {
        // Validar configuración según la guía
        this.validateConfig(config);
        
        this.config = config;
        this.userAgent = null;
        this.registerer = null; // Registerer para mantener el registro SIP activo
        this.currentCall = null;
        this.currentNumber = '';
        this.isConnected = false;
        this.isRegistered = false;
        this.status = 'disconnected'; // disconnected, connecting, connected, in-call
        this.incomingCallInvitation = null; // Llamada entrante pendiente
        this.incomingCallAudio = null; // Audio para sonido de llamada
        this.lastMediaStream = null; // Stream de audio actual
        this.remoteAudioElement = null; // Elemento de audio para reproducir audio remoto
        this.mediaStreamFactory = this._mediaStreamFactory.bind(this); // Factory para MediaStreams (igual que APEX2)
        this.audioDevices = []; // Dispositivos de audio disponibles
        this.preferredAudioDeviceId = null; // ID del dispositivo de audio preferido
        
        // Verificar que SIP.js esté disponible
        if (typeof SIP === 'undefined') {
            console.error('❌ [WebRTC Softphone] SIP.js no está cargado');
            console.error('❌ [WebRTC Softphone] Verifica que sip.min.js se haya cargado antes de este script');
            throw new Error('SIP.js no está disponible. Asegúrate de cargar sip.min.js antes de este script.');
        }
        
        if (this.config.debug_mode) {
            console.log('✅ [WebRTC Softphone] SIP.js cargado correctamente');
            console.log('✅ [WebRTC Softphone] Inicializando softphone...');
        }
        
        // Inicializar UI
        this.initUI();
        
        // Conectar al servidor SIP
        this.connect();
    }
    
    /**
     * Validar configuración según la guía
     */
    validateConfig(config) {
        const errors = [];
        const warnings = [];
        
        // Validar extensión
        if (!config.extension || config.extension.trim() === '') {
            errors.push('Extension está vacía o no definida');
        }
        
        // Validar password
        if (!config.password || config.password.trim() === '') {
            errors.push('Password está vacía o no definida');
        }
        
        // Validar WSS server
        if (!config.wss_server || config.wss_server.trim() === '') {
            errors.push('WSS Server está vacío o no definido');
        } else if (!config.wss_server.startsWith('wss://') && !config.wss_server.startsWith('ws://')) {
            warnings.push('WSS Server debe comenzar con wss:// o ws://');
        }
        
        // Validar SIP domain
        if (!config.sip_domain || config.sip_domain.trim() === '') {
            errors.push('SIP Domain está vacío o no definido');
        }
        
        // Mostrar errores
        if (errors.length > 0) {
            console.error('❌ [WebRTC Softphone] Errores de configuración:');
            errors.forEach(error => console.error('   -', error));
            throw new Error('Configuración inválida: ' + errors.join(', '));
        }
        
        // Mostrar advertencias
        if (warnings.length > 0 && config.debug_mode) {
            console.warn('⚠️ [WebRTC Softphone] Advertencias de configuración:');
            warnings.forEach(warning => console.warn('   -', warning));
        }
        
        if (config.debug_mode) {
            console.log('✅ [WebRTC Softphone] Configuración validada correctamente');
            console.log('📝 [WebRTC Softphone] Configuración:', {
                extension: config.extension,
                password: config.password ? '***' : 'VACIA',
                wss_server: config.wss_server,
                sip_domain: config.sip_domain,
                debug_mode: config.debug_mode
            });
        }
    }
    
    /**
     * Inicializar la interfaz de usuario del softphone
     */
    initUI() {
        const container = document.getElementById('webrtc-softphone');
        if (!container) {
            console.error('❌ No se encontró el contenedor #webrtc-softphone');
            return;
        }
        
        // Crear el HTML del softphone
        container.innerHTML = `
            <div class="softphone-header">
                <h3>
                    <i class="fas fa-phone"></i>
                    Softphone WebRTC
                </h3>
                <div class="softphone-header-actions">
                    <button class="softphone-btn-icon" onclick="window.webrtcSoftphone?.toggle()" title="Minimizar/Maximizar">
                        <i class="fas fa-window-minimize"></i>
                    </button>
                </div>
            </div>
            <div class="softphone-body">
                <!-- Estado de conexión -->
                <div class="softphone-status">
                    <div class="status-indicator">
                        <span class="status-dot disconnected" id="status-dot"></span>
                        <span id="status-text">Desconectado</span>
                    </div>
                </div>
                
                <!-- Display del número -->
                <div class="number-input-container">
                    <div class="number-display" id="number-display">Ingrese número</div>
                </div>
                
                <!-- Dialpad -->
                <div class="dialpad" id="dialpad">
                    <button class="dialpad-btn" data-number="1"><span>1</span></button>
                    <button class="dialpad-btn" data-number="2"><span>2</span><span class="dialpad-btn-letter">ABC</span></button>
                    <button class="dialpad-btn" data-number="3"><span>3</span><span class="dialpad-btn-letter">DEF</span></button>
                    <button class="dialpad-btn" data-number="4"><span>4</span><span class="dialpad-btn-letter">GHI</span></button>
                    <button class="dialpad-btn" data-number="5"><span>5</span><span class="dialpad-btn-letter">JKL</span></button>
                    <button class="dialpad-btn" data-number="6"><span>6</span><span class="dialpad-btn-letter">MNO</span></button>
                    <button class="dialpad-btn" data-number="7"><span>7</span><span class="dialpad-btn-letter">PQRS</span></button>
                    <button class="dialpad-btn" data-number="8"><span>8</span><span class="dialpad-btn-letter">TUV</span></button>
                    <button class="dialpad-btn" data-number="9"><span>9</span><span class="dialpad-btn-letter">WXYZ</span></button>
                    <button class="dialpad-btn" data-number="*"><span>*</span></button>
                    <button class="dialpad-btn" data-number="0"><span>0</span><span class="dialpad-btn-letter">+</span></button>
                    <button class="dialpad-btn" data-number="#"><span>#</span></button>
                </div>
                
                <!-- Botones de acción -->
                <div class="action-buttons">
                    <button class="action-btn delete-btn" id="btn-delete" onclick="window.webrtcSoftphone?.deleteLastDigit()">
                        <i class="fas fa-backspace"></i>
                        <span>Borrar</span>
                    </button>
                    <button class="action-btn call-btn" id="btn-call" onclick="window.webrtcSoftphone?.makeCall()">
                        <i class="fas fa-phone"></i>
                        <span>Llamar</span>
                    </button>
                    <button class="action-btn hangup-btn" id="btn-hangup" onclick="window.webrtcSoftphone?.hangup()" style="display: none;">
                        <i class="fas fa-phone-slash"></i>
                        <span>Colgar</span>
                    </button>
                </div>
                
                <!-- Información de llamada en curso -->
                <div class="call-info" id="call-info" style="display: none;">
                    <div class="call-info-number" id="call-info-number"></div>
                    <div class="call-info-duration" id="call-info-duration">00:00</div>
                    <div class="call-info-status" id="call-info-status">Llamando...</div>
                </div>
                
                <!-- Controles durante la llamada -->
                <div class="call-controls" id="call-controls" style="display: none;">
                    <button class="control-btn" id="btn-mute" onclick="window.webrtcSoftphone?.toggleMute()">
                        <i class="fas fa-microphone"></i>
                        <span>Mute</span>
                    </button>
                    <button class="control-btn" id="btn-speaker" onclick="window.webrtcSoftphone?.toggleSpeaker()">
                        <i class="fas fa-volume-up"></i>
                        <span>Speaker</span>
                    </button>
                </div>
            </div>
        `;
        
        // Configurar eventos del dialpad
        this.setupDialpadEvents();
        
        // Asegurar que tenga la clase inline
        container.classList.add('inline');
    }
    
    /**
     * Configurar eventos del dialpad
     */
    setupDialpadEvents() {
        const dialpad = document.getElementById('dialpad');
        if (!dialpad) return;
        
        dialpad.addEventListener('click', (e) => {
            const btn = e.target.closest('.dialpad-btn');
            if (btn) {
                const number = btn.dataset.number;
                this.addDigit(number);
            }
        });
    }
    
    /**
     * Conectar al servidor SIP
     */
    connect() {
        try {
            if (this.config.debug_mode) {
                console.log('🔌 Conectando al servidor SIP...', this.config);
            }
            
            // Validar configuración
            if (!this.config.extension || !this.config.password) {
                throw new Error('Extensión o contraseña SIP no configuradas');
            }
            
            if (!this.config.wss_server || !this.config.sip_domain) {
                throw new Error('Servidor WSS o dominio SIP no configurados');
            }
            
            // Configurar servidores ICE
            const iceServers = this.config.iceServers || [];
            if (iceServers.length === 0) {
                console.warn('⚠️ No hay servidores ICE configurados');
            }
            
            // Crear URI del usuario usando SIP.UserAgent.makeURI (igual que APEX2)
            const uriString = `sip:${this.config.extension}@${this.config.sip_domain}`;
            
            if (typeof SIP === 'undefined' || typeof SIP.UserAgent === 'undefined') {
                throw new Error('SIP.js no está cargado');
            }
            
            if (typeof SIP.UserAgent.makeURI !== 'function') {
                throw new Error('SIP.UserAgent.makeURI() no está disponible');
            }
            
            let userURI = SIP.UserAgent.makeURI(uriString);
            if (!userURI) {
                throw new Error('No se pudo crear el URI del usuario');
            }
            
            // Parchear URI para agregar método clone() si no lo tiene (igual que APEX2)
            userURI = this._patchUriClone(userURI);
            
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] URI del usuario parchado:', userURI.toString());
            }
            
            // Configuración del UserAgent optimizada para Issabel/Asterisk
            // Basada en APEX2 que funciona correctamente - NO usar register: true
            // El registro se hace automáticamente cuando el transporte se conecta
            const userAgentOptions = {
                uri: userURI,
                authorizationUsername: this.config.extension,
                authorizationPassword: this.config.password,
                displayName: this.config.display_name || this.config.extension,
                transportOptions: {
                    server: this.config.wss_server,
                    keepAliveInterval: 30, // Envía un 'ping' cada 30 segundos para mantener la conexión activa
                    traceSip: this.config.debug_mode || false // Activar trazas SIP si está en modo debug
                },
                sessionDescriptionHandlerFactoryOptions: {
                    mediaStreamFactory: this.mediaStreamFactory,
                    // Configuración ICE para WebRTC - esencial para NAT traversal
                    iceServers: iceServers,
                    // Configuración de codecs preferidos (PCMU/PCMA para compatibilidad con Asterisk)
                    rtcConfiguration: {
                        iceServers: iceServers,
                        iceTransportPolicy: 'all', // Permitir tanto STUN como TURN
                        bundlePolicy: 'max-bundle', // Agrupar audio/video en un solo transporte
                        rtcpMuxPolicy: 'require' // Requerir RTCP multiplexing
                    }
                },
                delegate: {
                    onInvite: (invitation) => {
                        if (this.config.debug_mode) {
                            console.log('🔔 [WebRTC Softphone] INVITACIÓN RECIBIDA (onInvite delegate)');
                        }
                        if (typeof this.handleIncomingCall === 'function') {
                            this.handleIncomingCall(invitation);
                        }
                    }
                }
            };
            
            // Logs detallados de configuración
            console.log('🔧 [WebRTC Softphone] Configuración completa del UserAgent:');
            console.log('  📞 URI String:', uriString);
            console.log('  📞 URI Object:', userURI);
            console.log('  🔢 Extension:', this.config.extension || 'VACIA');
            console.log('  🔑 Password:', this.config.password ? 'DEFINIDA (' + this.config.password.length + ' caracteres)' : 'VACIA');
            console.log('  🌐 WSS Server:', this.config.wss_server || 'VACIO');
            console.log('  🏢 SIP Domain:', this.config.sip_domain || 'VACIO');
            // No hay register: true (igual que APEX2)
            console.log('  🧊 ICE Servers:', iceServers.length);
            console.log('  👤 Display Name:', userAgentOptions.displayName);
            
            // Validar valores críticos antes de continuar
            if (!this.config.extension || this.config.extension.trim() === '') {
                console.error('❌ [WebRTC Softphone] ERROR CRÍTICO: Extension está vacía');
                throw new Error('La extensión SIP no está configurada. Verifica la base de datos.');
            }
            
            if (!this.config.password || this.config.password.trim() === '') {
                console.error('❌ [WebRTC Softphone] ERROR CRÍTICO: Password está vacía');
                throw new Error('La contraseña SIP no está configurada. Verifica la base de datos.');
            }
            
            if (!this.config.wss_server || this.config.wss_server.trim() === '') {
                console.error('❌ [WebRTC Softphone] ERROR CRÍTICO: WSS Server está vacío');
                throw new Error('El servidor WSS no está configurado. Verifica config/asterisk.php');
            }
            
            if (!this.config.sip_domain || this.config.sip_domain.trim() === '') {
                console.error('❌ [WebRTC Softphone] ERROR CRÍTICO: SIP Domain está vacío');
                throw new Error('El dominio SIP no está configurado. Verifica config/asterisk.php');
            }
            
            console.log('✅ [WebRTC Softphone] Todos los valores críticos están presentes');
            
            // Los servidores ICE ya están configurados en sessionDescriptionHandlerFactoryOptions (líneas 309 y 311-316)
            // No es necesario agregarlos nuevamente aquí
            
            // Configuración adicional para debug
            if (this.config.debug_mode) {
                console.log('🔧 [WebRTC Softphone] Configuración UserAgent:', {
                    uri: userURI,
                    extension: this.config.extension,
                    wss_server: this.config.wss_server,
                    sip_domain: this.config.sip_domain,
                    iceServers: iceServers.length
                });
            }
            
            // Crear UserAgent (igual que APEX2 - sin register: true)
            this.userAgent = new SIP.UserAgent(userAgentOptions);
            
            // CRÍTICO PARA PJSIP: Asignar también directamente onInvite al UserAgent (igual que APEX2)
            this.userAgent.onInvite = (invitation) => {
                if (this.config.debug_mode) {
                    console.log('🔔 [WebRTC Softphone] EVENTO onInvite DEL USERAGENT (PJSIP)');
                }
                if (typeof this.handleIncomingCall === 'function') {
                    this.handleIncomingCall(invitation);
                }
            };
            
            // Configurar eventos del transporte (igual que APEX2)
            this.setupUserAgentEvents();
            
            // Iniciar conexión (igual que APEX2 - el registro se hace automáticamente)
            this.updateStatus('connecting', 'Conectando...');
            
            if (this.config.debug_mode) {
                console.log('🔄 [WebRTC Softphone] Iniciando UserAgent...');
                console.log('📝 [WebRTC Softphone] Configuración de conexión:', {
                    uri: userURI,
                    extension: this.config.extension,
                    wss_server: this.config.wss_server,
                    sip_domain: this.config.sip_domain
                });
            }
            
            this.userAgent.start()
                .then(() => {
                    if (this.config.debug_mode) {
                        console.log('✅ [WebRTC Softphone] UserAgent iniciado correctamente');
                        console.log('   UserAgent state:', this.userAgent.state);
                    }
                    
                    // Verificar estado del transporte
                    if (this.userAgent.transport) {
                        if (this.config.debug_mode) {
                            console.log('   Transport state:', this.userAgent.transport.state);
                        }
                        if (this.userAgent.transport.state === 'Connected') {
                            this.isConnected = true;
                            if (this.config.debug_mode) {
                                console.log('✅ Transporte conectado');
                            }
                        }
                    }
                    
                    // CRÍTICO: Crear Registerer para mantener el registro SIP activo
                    // Sin esto, el servidor cierra la conexión porque no hay registro
                    if (this.config.debug_mode) {
                        console.log('📝 [WebRTC Softphone] Creando Registerer para mantener registro SIP activo...');
                    }
                    
                    try {
                        // Crear URI del registrar (debe ser un objeto URI, no un string)
                        let registrarURI = null;
                        if (this.config.sip_domain) {
                            const registrarUriString = `sip:${this.config.sip_domain}`;
                            registrarURI = SIP.UserAgent.makeURI(registrarUriString);
                            if (registrarURI) {
                                // Parchear URI para agregar método clone() si no lo tiene
                                registrarURI = this._patchUriClone(registrarURI);
                                if (this.config.debug_mode) {
                                    console.log('✅ [WebRTC Softphone] URI del registrar creado y parchado:', registrarURI.toString());
                                }
                            } else {
                                if (this.config.debug_mode) {
                                    console.warn('⚠️ [WebRTC Softphone] No se pudo crear URI del registrar, usando string');
                                }
                                registrarURI = registrarUriString;
                            }
                        }
                        
                        // Crear Registerer con las credenciales
                        this.registerer = new SIP.Registerer(this.userAgent, {
                            registrar: registrarURI
                        });
                        
                        // Escuchar cambios de estado del registro
                        this.registerer.stateChange.addListener((newState) => {
                            if (this.config.debug_mode) {
                                console.log('📝 [WebRTC Softphone] Estado del registro:', newState);
                            }
                            
                            if (newState === SIP.RegistererState.Registered) {
                                this.isRegistered = true;
                                this.updateStatus('connected', 'En línea');
                                if (this.config.debug_mode) {
                                    console.log('✅ [WebRTC Softphone] Registro SIP exitoso - Listo para recibir llamadas');
                                }
                            } else if (newState === SIP.RegistererState.Unregistered) {
                                this.isRegistered = false;
                                if (this.config.debug_mode) {
                                    console.warn('⚠️ [WebRTC Softphone] Registro fallido o expirado');
                                }
                            } else if (newState === SIP.RegistererState.Registering) {
                                if (this.config.debug_mode) {
                                    console.log('🔄 [WebRTC Softphone] Registrando...');
                                }
                            }
                        });
                        
                        // Iniciar el registro
                        return this.registerer.register({
                            requestDelegate: {
                                onAccept: (response) => {
                                    if (this.config.debug_mode) {
                                        console.log('✅ [WebRTC Softphone] REGISTER aceptado por el servidor');
                                    }
                                    this.isRegistered = true;
                                    this.updateStatus('connected', 'En línea');
                                },
                                onReject: (response) => {
                                    console.error('❌ [WebRTC Softphone] REGISTER rechazado:', response);
                                    if (response && response.message) {
                                        console.error('   Código:', response.message.statusCode);
                                        console.error('   Razón:', response.message.reasonPhrase);
                                    }
                                    this.isRegistered = false;
                                    this.updateStatus('disconnected', 'Error de registro');
                                    this.showError('Error al registrar en el servidor SIP. Verifica credenciales.');
                                }
                            }
                        });
                    } catch (registerError) {
                        console.error('❌ [WebRTC Softphone] Error al crear Registerer:', registerError);
                        // Continuar sin registro - las llamadas salientes pueden funcionar
                        this.isRegistered = false;
                        this.updateStatus('connected', 'Conectado (sin registro)');
                    }
                })
                .then(() => {
                    // DIAGNÓSTICO: Verificar configuración del servidor
                    if (this.config.debug_mode) {
                        console.log('📞 [WebRTC Softphone] Softphone listo para recibir llamadas entrantes');
                        console.log('🔍 [WebRTC Softphone] Modo diagnóstico activado');
                        console.log('⚠️ [WebRTC Softphone] IMPORTANTE: Si no recibes llamadas, verifica:');
                        console.log('   1. Que la extensión esté registrada en el PBX');
                        console.log('   2. Que el PBX esté configurado para enviar INVITEs al WebSocket');
                        console.log('   3. Que no haya firewalls bloqueando los mensajes SIP');
                        console.log('   4. Que el transporte WebSocket permanezca conectado');
                        console.log('   5. Que el servidor esté enviando INVITEs al WebSocket correcto');
                        
                        // Exponer el UserAgent globalmente para diagnóstico
                        window.sipUserAgent = this.userAgent;
                        window.sipRegisterer = this.registerer;
                        console.log('🔧 [WebRTC Softphone] UserAgent expuesto globalmente como window.sipUserAgent para diagnóstico');
                        console.log('🔧 [WebRTC Softphone] Registerer expuesto globalmente como window.sipRegisterer para diagnóstico');
                    }
                })
                .catch((error) => {
                    console.error('❌ [WebRTC Softphone] Error al iniciar UserAgent o registrar:', error);
                    console.error('❌ [WebRTC Softphone] Detalles del error:', {
                        message: error.message,
                        stack: error.stack
                    });
                    this.updateStatus('disconnected', 'Error de conexión');
                    this.showError('No se pudo conectar al servidor SIP. Verifica la configuración.');
                });
                
        } catch (error) {
            console.error('❌ Error al conectar:', error);
            this.updateStatus('disconnected', 'Error de conexión');
            this.showError('Error al inicializar el softphone: ' + error.message);
        }
    }
    
    /**
     * Configurar eventos del UserAgent (igual que APEX2)
     */
    setupUserAgentEvents() {
        if (!this.userAgent) return;
        
        // CRÍTICO: Agregar listener para el transporte para detectar desconexiones (igual que APEX2)
        if (this.userAgent.transport) {
            // Listener para cambios de estado del transporte (igual que APEX2)
            this.userAgent.transport.stateChange.addListener((newState) => {
                if (this.config.debug_mode) {
                    console.log('🔔 [WebRTC Softphone] Transport state changed:', newState);
                }
                if (newState === 'Connected') {
                    if (this.config.debug_mode) {
                        console.log('✅ [WebRTC Softphone] Transporte conectado');
                    }
                    this.isRegistered = true;
                    this.isConnected = true;
                    this.updateStatus('connected', 'En línea');
                } else if (newState === 'Disconnected') {
                    if (this.config.debug_mode) {
                        console.log('❌ [WebRTC Softphone] Transporte desconectado');
                    }
                    this.isRegistered = false;
                    this.isConnected = false;
                    this.updateStatus('disconnected', 'Desconectado');
                }
            });
            
            // Listener para eventos del WebSocket directamente (igual que APEX2)
            if (this.userAgent.transport.ws) {
                this.userAgent.transport.ws.addEventListener('close', (event) => {
                    if (this.config.debug_mode) {
                        console.log('🔔 [WebRTC Softphone] WebSocket cerrado:', {
                            code: event.code,
                            reason: event.reason,
                            wasClean: event.wasClean
                        });
                    }
                    
                    // Código 1000 = Normal Closure (cierre normal del servidor)
                    if (event.code === 1000) {
                        if (this.config.debug_mode) {
                            console.log('⚠️ [WebRTC Softphone] Servidor cerró la conexión normalmente (posible timeout)');
                        }
                    }
                });
                
                this.userAgent.transport.ws.addEventListener('error', (error) => {
                    console.error('❌ [WebRTC Softphone] Error en WebSocket:', error);
                });
                
                // DIAGNÓSTICO: Interceptar todos los mensajes WebSocket entrantes para ver INVITEs (igual que APEX2)
                if (this.config.debug_mode) {
                    const originalOnMessage = this.userAgent.transport.ws.onmessage;
                    this.userAgent.transport.ws.onmessage = (event) => {
                        if (event.data && typeof event.data === 'string') {
                            // Verificar si es un INVITE entrante (comienza con "INVITE", no "SIP/2.0")
                            if (event.data.trim().startsWith('INVITE')) {
                                console.log('🔔 [WebRTC Softphone] ===== INVITE ENTRANTE EN WEBSOCKET RAW =====');
                                console.log('   ⚠️ ESTE ES UN INVITE ENTRANTE REAL');
                                console.log('   📝 Datos recibidos:', event.data.substring(0, 1000) + (event.data.length > 1000 ? '...' : ''));
                                
                                // Extraer información del INVITE
                                const fromMatch = event.data.match(/From:\s*[^<]*<sip:(\d+)@/);
                                const toMatch = event.data.match(/To:\s*[^<]*<sip:(\d+)@/);
                                const callIdMatch = event.data.match(/Call-ID:\s*([^\r\n]+)/);
                                if (fromMatch) console.log('   📞 Desde (llamante):', fromMatch[1]);
                                if (toMatch) console.log('   📞 Hacia (destino):', toMatch[1]);
                                if (callIdMatch) console.log('   📞 Call-ID:', callIdMatch[1]);
                                
                                // Verificar si el INVITE es para nuestra extensión
                                if (toMatch && toMatch[1] === this.config.extension) {
                                    console.log('   ✅ INVITE ES PARA NUESTRA EXTENSIÓN:', this.config.extension);
                                    console.log('   ⚠️ Si no ves el delegate onInvite ejecutándose, hay un problema');
                                } else {
                                    console.log('   ⚠️ INVITE NO ES PARA NUESTRA EXTENSIÓN');
                                }
                            }
                        }
                        
                        // Llamar al handler original
                        if (originalOnMessage) {
                            originalOnMessage.call(this.userAgent.transport.ws, event);
                        }
                    };
                    console.log('✅ [WebRTC Softphone] Listener de WebSocket raw configurado para diagnóstico');
                }
            }
        }
        
        // El delegate.onInvite ya se configuró antes de crear el UserAgent
        // No necesitamos eventos de registro porque no usamos register: true (igual que APEX2)
    }
    
    /**
     * Manejar llamada entrante
     */
    handleIncomingCall(invitation) {
        if (this.config.debug_mode) {
            console.log('🔔 [WebRTC Softphone] ===== handleIncomingCall LLAMADO =====');
            console.log('   📞 Invitation:', invitation);
            console.log('   📞 Invitation type:', typeof invitation);
        }
        
        // Si ya hay una llamada en curso, rechazar la nueva
        if (this.currentCall) {
            if (this.config.debug_mode) {
                console.log('⚠️ [WebRTC Softphone] Ya hay una llamada en curso, rechazando llamada entrante');
            }
            invitation.reject();
            return;
        }
        
        // Intentar obtener el número del llamante de diferentes formas (igual que APEX2)
        let caller = 'Desconocido';
        
        try {
            // Método 1: Desde remoteIdentity
            if (invitation.remoteIdentity && invitation.remoteIdentity.uri) {
                if (invitation.remoteIdentity.uri.user) {
                    caller = invitation.remoteIdentity.uri.user;
                    if (this.config.debug_mode) {
                        console.log('   📞 Caller desde remoteIdentity.uri.user:', caller);
                    }
                } else if (invitation.remoteIdentity.uri.toString) {
                    const uriString = invitation.remoteIdentity.uri.toString();
                    const match = uriString.match(/sip:(\d+)@/);
                    if (match && match[1]) {
                        caller = match[1];
                        if (this.config.debug_mode) {
                            console.log('   📞 Caller desde remoteIdentity.uri.toString:', caller);
                        }
                    }
                }
            }
            
            // Método 2: Desde request.from
            if (caller === 'Desconocido' && invitation.request && invitation.request.from) {
                const fromHeader = invitation.request.from;
                if (fromHeader.uri && fromHeader.uri.user) {
                    caller = fromHeader.uri.user;
                    if (this.config.debug_mode) {
                        console.log('   📞 Caller desde request.from.uri.user:', caller);
                    }
                } else if (fromHeader.displayName) {
                    caller = fromHeader.displayName;
                    if (this.config.debug_mode) {
                        console.log('   📞 Caller desde request.from.displayName:', caller);
                    }
                }
            }
            
            // Método 3: Desde request.headers.From
            if (caller === 'Desconocido' && invitation.request && invitation.request.headers) {
                const fromHeader = invitation.request.headers.From;
                if (fromHeader) {
                    const match = fromHeader.match(/sip:(\d+)@/);
                    if (match && match[1]) {
                        caller = match[1];
                        if (this.config.debug_mode) {
                            console.log('   📞 Caller desde request.headers.From:', caller);
                        }
                    }
                }
            }
            
            // Método 4: Desde request.from.uri directamente
            if (caller === 'Desconocido' && invitation.request && invitation.request.from && invitation.request.from.uri) {
                const uri = invitation.request.from.uri;
                if (uri.user) {
                    caller = uri.user;
                    if (this.config.debug_mode) {
                        console.log('   📞 Caller desde request.from.uri.user (directo):', caller);
                    }
                }
            }
        } catch (error) {
            if (this.config.debug_mode) {
                console.warn('⚠️ [WebRTC Softphone] Error al extraer número del llamante:', error);
            }
        }
        
        if (this.config.debug_mode) {
            console.log('📞 [WebRTC Softphone] Llamada entrante de:', caller);
            console.log('   📞 Caller final identificado:', caller);
        }
        
        // 1. Guardar la sesión actual
        this.incomingCallInvitation = invitation;
        this.currentNumber = caller;
        
        // 2. Actualizar UI - Mostrar información de llamada entrante
        try {
            this.showCallInfo(caller);
            this.updateCallStatus('Llamada Entrante...');
            this.updateStatus('in-call', 'Llamando...');
        } catch (error) {
            if (this.config.debug_mode) {
                console.warn('⚠️ [WebRTC Softphone] Error al actualizar UI:', error);
            }
        }
        
        // 3. Mostrar notificación visual de llamada entrante (CRÍTICO - debe mostrarse)
        try {
            this.showIncomingCallNotification(caller, caller, invitation);
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] Notificación de llamada entrante mostrada');
            }
        } catch (error) {
            console.error('❌ [WebRTC Softphone] Error al mostrar notificación:', error);
            // Intentar mostrar una alerta como fallback
            alert(`📞 Llamada entrante de: ${caller}`);
        }
        
        // 4. Reproducir sonido de llamada entrante (CRÍTICO - debe sonar)
        try {
            this.playIncomingCallSound();
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] Sonido de llamada entrante iniciado');
            }
        } catch (error) {
            console.error('❌ [WebRTC Softphone] Error al reproducir sonido:', error);
        }
        
        // 5. Configurar eventos de la llamada entrante
        invitation.stateChange.addListener((newState) => {
            const stateStr = String(newState);
            if (this.config.debug_mode) {
                console.log('📞 [WebRTC Softphone] Estado de invitación entrante:', stateStr);
            }
            
            if (stateStr === 'Terminated' || stateStr === 'Canceled') {
                if (this.config.debug_mode) {
                    console.log('📞 [WebRTC Softphone] Llamada entrante terminada o cancelada');
                }
                // Limpiar siempre, independientemente de si es la llamada actual
                this.hideIncomingCallNotification();
                this.stopIncomingCallSound();
                
                // Si es la llamada actual, limpiar todo
                if (this.currentCall === invitation || this.incomingCallInvitation === invitation) {
                    this.endCall();
                } else {
                    // Si no es la llamada actual, solo limpiar la invitación entrante
                    if (this.incomingCallInvitation === invitation) {
                        this.incomingCallInvitation = null;
                    }
                    // Restaurar UI si no hay llamada activa
                    if (!this.currentCall) {
                        this.hideCallInfo();
                        this.updateStatus('connected', 'En línea');
                    }
                }
            } else if (stateStr === 'Established') {
                // Llamada aceptada
                this.currentCall = invitation;
                this.incomingCallInvitation = null; // Ya no es una llamada entrante pendiente
                this.updateStatus('in-call', 'En llamada');
                this.showCallInfo(caller);
                this.startCallTimer();
                this.hideIncomingCallNotification();
                this.stopIncomingCallSound();
                
                // Configurar audio después de un breve delay para asegurar que el PeerConnection esté listo
                setTimeout(() => {
                    this.setupAudioSessionForCall(invitation);
                }, 500);
            }
        });
        
        // 6. Configurar delegado para manejar la sesión de audio
        invitation.delegate = {
            onSessionDescriptionHandler: (sessionDescriptionHandler) => {
                if (this.config.debug_mode) {
                    console.log('🔊 [WebRTC Softphone] SessionDescriptionHandler disponible para llamada entrante');
                }
                this.setupAudioSessionForCall(invitation);
            }
        };
    }
    
    /**
     * Mostrar notificación de llamada entrante
     */
    showIncomingCallNotification(callerName, callerNumber, invitation) {
        // Crear o actualizar el modal de llamada entrante
        let notificationDiv = document.getElementById('incoming-call-notification');
        
        if (!notificationDiv) {
            notificationDiv = document.createElement('div');
            notificationDiv.id = 'incoming-call-notification';
            notificationDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 20px 30px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 10000;
                min-width: 300px;
                animation: slideInRight 0.3s ease-out;
            `;
            document.body.appendChild(notificationDiv);
            
            // Agregar animación CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
            `;
            document.head.appendChild(style);
        }
        
        notificationDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="flex: 1;">
                    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Llamada Entrante</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 3px;">${this.escapeHtml(callerName)}</div>
                    <div style="font-size: 14px; opacity: 0.8;">${this.escapeHtml(callerNumber)}</div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.webrtcSoftphone?.acceptIncomingCall()" 
                            style="background: white; color: #28a745; border: none; border-radius: 50%; width: 50px; height: 50px; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2); animation: pulse 1s infinite;">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button onclick="window.webrtcSoftphone?.rejectIncomingCall()" 
                            style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 50px; height: 50px; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Asegurar que la notificación esté visible
        notificationDiv.style.display = 'block';
        notificationDiv.style.visibility = 'visible';
        notificationDiv.style.opacity = '1';
        
        // Asegurar z-index alto para que esté por encima de todo
        notificationDiv.style.zIndex = '99999';
        
        // Guardar la invitación para aceptar/rechazar (ya está guardada en handleIncomingCall, pero por si acaso)
        this.incomingCallInvitation = invitation;
        
        // El sonido ya se reproduce en handleIncomingCall, pero asegurémonos de que se reproduzca
        if (!this.incomingCallAudio || this.incomingCallAudio.paused) {
            this.playIncomingCallSound();
        }
        
        if (this.config.debug_mode) {
            console.log('✅ [WebRTC Softphone] Notificación de llamada entrante mostrada y visible');
            console.log('   📍 Elemento display:', window.getComputedStyle(notificationDiv).display);
            console.log('   📍 Elemento visibility:', window.getComputedStyle(notificationDiv).visibility);
            console.log('   📍 Elemento z-index:', window.getComputedStyle(notificationDiv).zIndex);
        }
    }
    
    /**
     * Ocultar notificación de llamada entrante
     */
    hideIncomingCallNotification() {
        const notificationDiv = document.getElementById('incoming-call-notification');
        if (notificationDiv) {
            notificationDiv.style.display = 'none';
        }
        this.stopIncomingCallSound();
        this.incomingCallInvitation = null;
    }
    
    /**
     * Aceptar llamada entrante (igual que APEX2)
     */
    async acceptIncomingCall() {
        if (!this.incomingCallInvitation) {
            console.warn('⚠️ [WebRTC Softphone] No hay llamada entrante para aceptar');
            return;
        }
        
        try {
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] Usuario presionó Contestar');
            }
            
            // Reutilizar la misma configuración robusta de ICE y Audio que usamos para llamar (igual que APEX2)
            const options = {
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    iceServers: this._getIceServers(),
                    rtcConfiguration: {
                        iceServers: this._getIceServers(),
                        iceTransportPolicy: 'all',
                        bundlePolicy: 'max-bundle',
                        rtcpMuxPolicy: 'require'
                    },
                    // Pasar mediaStreamFactory que retorna el stream pre-adquirido
                    mediaStreamFactory: async () => {
                        if (this.config.debug_mode) {
                            console.log('🎤 [WebRTC Softphone] mediaStreamFactory LLAMADA PARA CONTESTAR');
                        }
                        // Adquirir stream antes de contestar
                        return await this._mediaStreamFactory();
                    }
                }
            };
            
            // Aceptar la llamada
            await this.incomingCallInvitation.accept(options);
            
            // Actualizar UI a "En llamada"
            this.currentCall = this.incomingCallInvitation;
            this.hideIncomingCallNotification();
            
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] Llamada aceptada exitosamente');
            }
            
        } catch (error) {
            console.error('❌ [WebRTC Softphone] Error al aceptar llamada:', error);
            this.showError('Error al aceptar la llamada: ' + error.message);
            this.hideIncomingCallNotification();
            this.endCall();
        }
    }
    
    /**
     * Rechazar llamada entrante
     */
    rejectIncomingCall() {
        if (!this.incomingCallInvitation) {
            console.warn('⚠️ No hay llamada entrante para rechazar');
            return;
        }
        
        try {
            if (this.config.debug_mode) {
                console.log('❌ Rechazando llamada entrante');
            }
            
            this.incomingCallInvitation.reject();
            this.hideIncomingCallNotification();
            
        } catch (error) {
            console.error('❌ Error al rechazar llamada:', error);
            this.hideIncomingCallNotification();
        }
    }
    
    /**
     * Reproducir sonido de llamada entrante
     */
    playIncomingCallSound() {
        try {
            // Crear elemento de audio para el tono de llamada si no existe
            if (!this.incomingCallAudio) {
                this.incomingCallAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjGH0fPTgjMGHm7A7+OZUA4PVKzn77FbGAg+ltryy3kpBSV+zfLZiTYIGGW57+OeTQ8MTqTj8LZjHAY4kdfyzHksBSR3x/DdkEAKFF606euoVRQKRp/g8r5sIQYxh9Hz04IzBh5uwO/jmVAOD1Ss5++xWxgIPpba8st5KQUlfs3y2Yk2CBhlue/jnk0PDE6k4/C2YxwGOJHX8sx5LAUkd8fw3ZBAC');
                this.incomingCallAudio.loop = true;
                this.incomingCallAudio.volume = 0.7;
                this.incomingCallAudio.preload = 'auto';
                
                if (this.config.debug_mode) {
                    console.log('🔊 [WebRTC Softphone] Elemento de audio creado para llamada entrante');
                }
            }
            
            // Reiniciar el audio desde el principio
            this.incomingCallAudio.currentTime = 0;
            
            // Intentar reproducir
            const playPromise = this.incomingCallAudio.play();
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        if (this.config.debug_mode) {
                            console.log('✅ [WebRTC Softphone] Sonido de llamada entrante reproduciéndose');
                        }
                    })
                    .catch(error => {
                        console.warn('⚠️ [WebRTC Softphone] No se pudo reproducir el sonido de llamada:', error);
                        // Intentar forzar reproducción con un click simulado
                        if (document.body) {
                            document.body.click();
                            setTimeout(() => {
                                this.incomingCallAudio.play().catch(err => {
                                    console.warn('⚠️ [WebRTC Softphone] Error persistente al reproducir sonido:', err);
                                });
                            }, 100);
                        }
                    });
            }
        } catch (error) {
            console.error('❌ [WebRTC Softphone] Error al reproducir sonido de llamada:', error);
        }
    }
    
    /**
     * Detener sonido de llamada entrante
     */
    stopIncomingCallSound() {
        if (this.incomingCallAudio) {
            this.incomingCallAudio.pause();
            this.incomingCallAudio.currentTime = 0;
        }
    }
    
    /**
     * Escapar HTML para prevenir XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Configurar sesión de audio para llamada (entrante o saliente)
     */
    setupAudioSessionForCall(invitation) {
        if (!invitation) {
            if (this.config.debug_mode) {
                console.warn('⚠️ [WebRTC Softphone] setupAudioSessionForCall: invitation es null');
            }
            return;
        }
        
        // Esperar a que el sessionDescriptionHandler esté disponible
        const setupAudio = () => {
            if (!invitation.sessionDescriptionHandler) {
                if (this.config.debug_mode) {
                    console.log('⏳ [WebRTC Softphone] Esperando sessionDescriptionHandler...');
                }
                // Esperar un poco más si aún no está disponible
                setTimeout(setupAudio, 100);
                return;
            }
            
            const sessionDescriptionHandler = invitation.sessionDescriptionHandler;
            const pc = sessionDescriptionHandler.peerConnection;
            
            if (!pc) {
                if (this.config.debug_mode) {
                    console.warn('⚠️ [WebRTC Softphone] PeerConnection no disponible aún');
                }
                setTimeout(setupAudio, 100);
                return;
            }
            
            if (this.config.debug_mode) {
                console.log('🔊 [WebRTC Softphone] Configurando sesión de audio...');
                console.log('   PeerConnection state:', pc.connectionState);
                console.log('   ICE state:', pc.iceConnectionState);
            }
            
            // Obtener elementos de audio del SessionDescriptionHandler
            const localAudio = sessionDescriptionHandler.localAudioElement;
            const remoteAudio = sessionDescriptionHandler.remoteAudioElement;
            
            if (localAudio) {
                localAudio.volume = 0; // Silenciar audio local (evitar feedback)
                localAudio.muted = true;
                
                // Agregar al DOM si no está
                if (!localAudio.parentNode) {
                    document.body.appendChild(localAudio);
                    localAudio.style.display = 'none';
                }
                
                if (this.config.debug_mode) {
                    console.log('✅ [WebRTC Softphone] Audio local configurado');
                }
            } else {
                if (this.config.debug_mode) {
                    console.warn('⚠️ [WebRTC Softphone] localAudioElement no disponible');
                }
            }
            
            // CRÍTICO: Configurar audio remoto desde el PeerConnection directamente
            // El SessionDescriptionHandler puede no tener remoteAudioElement, pero el PeerConnection sí tiene los tracks
            if (pc && pc.getReceivers) {
                const receivers = pc.getReceivers();
                if (this.config.debug_mode) {
                    console.log(`🔍 [WebRTC Softphone] Receivers encontrados: ${receivers.length}`);
                }
                
                receivers.forEach((receiver, index) => {
                    const track = receiver.track;
                    if (track && track.kind === 'audio') {
                        if (this.config.debug_mode) {
                            console.log(`✅ [WebRTC Softphone] Track remoto ${index} encontrado:`, {
                                id: track.id,
                                enabled: track.enabled,
                                readyState: track.readyState
                            });
                        }
                        
                        // Asegurar que el track esté habilitado
                        if (!track.enabled) {
                            track.enabled = true;
                        }
                        
                        // Crear un elemento de audio para reproducir el track remoto
                        if (!this.remoteAudioElement) {
                            this.remoteAudioElement = document.createElement('audio');
                            this.remoteAudioElement.id = 'webrtc-softphone-remote-audio';
                            this.remoteAudioElement.autoplay = true;
                            this.remoteAudioElement.playsInline = true;
                            this.remoteAudioElement.volume = 1.0;
                            this.remoteAudioElement.muted = false;
                            this.remoteAudioElement.style.display = 'none';
                            document.body.appendChild(this.remoteAudioElement);
                            
                            if (this.config.debug_mode) {
                                console.log('✅ [WebRTC Softphone] Elemento de audio remoto creado');
                            }
                        }
                        
                        // Conectar el track al elemento de audio
                        const remoteStream = new MediaStream([track]);
                        this.remoteAudioElement.srcObject = remoteStream;
                        
                        // Intentar reproducir
                        this.remoteAudioElement.play()
                            .then(() => {
                                if (this.config.debug_mode) {
                                    console.log('✅ [WebRTC Softphone] Audio remoto reproduciéndose');
                                }
                            })
                            .catch(error => {
                                console.warn('⚠️ [WebRTC Softphone] Error al reproducir audio remoto:', error);
                            });
                    }
                });
            }
            
            // También intentar usar remoteAudioElement si está disponible
            if (remoteAudio) {
                remoteAudio.autoplay = true;
                remoteAudio.volume = 1.0;
                remoteAudio.muted = false;
                
                // Agregar al DOM si no está
                if (!remoteAudio.parentNode) {
                    document.body.appendChild(remoteAudio);
                    remoteAudio.style.display = 'none';
                }
                
                // Asegurar que el audio se reproduzca
                remoteAudio.play().catch(error => {
                    if (this.config.debug_mode) {
                        console.warn('⚠️ [WebRTC Softphone] Error al reproducir remoteAudioElement:', error);
                    }
                });
                
                if (this.config.debug_mode) {
                    console.log('✅ [WebRTC Softphone] remoteAudioElement configurado');
                }
            }
            
            // Escuchar cuando se agreguen tracks remotos
            if (pc && !pc._audioTrackListenerAdded) {
                pc.addEventListener('track', (event) => {
                    if (event.track && event.track.kind === 'audio') {
                        if (this.config.debug_mode) {
                            console.log('🎵 [WebRTC Softphone] Track remoto agregado:', event.track.id);
                        }
                        
                        // Asegurar que el track esté habilitado
                        event.track.enabled = true;
                        
                        // Conectar al elemento de audio
                        if (this.remoteAudioElement) {
                            const remoteStream = new MediaStream([event.track]);
                            this.remoteAudioElement.srcObject = remoteStream;
                            this.remoteAudioElement.play()
                                .then(() => {
                                    if (this.config.debug_mode) {
                                        console.log('✅ [WebRTC Softphone] Audio remoto (track event) reproduciéndose');
                                    }
                                })
                                .catch(error => {
                                    console.warn('⚠️ [WebRTC Softphone] Error al reproducir audio (track event):', error);
                                });
                        }
                    }
                });
                pc._audioTrackListenerAdded = true;
            }
            
            if (this.config.debug_mode) {
                console.log('🔊 [WebRTC Softphone] Sesión de audio configurada:', {
                    localAudio: !!localAudio,
                    remoteAudio: !!remoteAudio,
                    remoteAudioElement: !!this.remoteAudioElement,
                    invitationState: invitation.state,
                    receivers: pc.getReceivers ? pc.getReceivers().length : 0
                });
            }
        };
        
        // Intentar configurar inmediatamente
        setupAudio();
    }
    
    /**
     * Realizar una llamada (igual que APEX2)
     */
    async makeCall() {
        if (!this.currentNumber || this.currentNumber.trim() === '') {
            this.showError('Por favor ingrese un número');
            return;
        }
        
        if (!this.isRegistered) {
            this.showError('No está conectado al servidor SIP');
            return;
        }
        
        if (this.currentCall) {
            this.showError('Ya hay una llamada en curso');
            return;
        }
        
        try {
            const number = this.currentNumber.trim();
            
            if (!number) {
                throw new Error('Número de destino no válido');
            }
            
            if (!this.config.sip_domain || !this.config.sip_domain.trim()) {
                throw new Error('Dominio SIP no configurado');
            }
            
            if (typeof SIP === 'undefined' || typeof SIP.UserAgent === 'undefined') {
                throw new Error('SIP.js no está cargado correctamente');
            }
            
            if (typeof SIP.UserAgent.makeURI !== 'function') {
                throw new Error('SIP.UserAgent.makeURI() no está disponible');
            }
            
            // Crear URI del destino usando SIP.UserAgent.makeURI (igual que APEX2)
            const targetUriString = `sip:${number}@${this.config.sip_domain.trim()}`;
            
            if (this.config.debug_mode) {
                console.log('📞 [WebRTC Softphone] Creando URI destino:', targetUriString);
            }
            
            let targetUri = SIP.UserAgent.makeURI(targetUriString);
            if (!targetUri) {
                throw new Error('No se pudo crear la URI de destino');
            }
            
            // Parchear URI para agregar método clone() si no lo tiene (igual que APEX2)
            targetUri = this._patchUriClone(targetUri);
            
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] URI destino creado y parchado:', targetUri.toString());
            }
            
            // Crear Inviter (igual que APEX2)
            if (this.config.debug_mode) {
                console.log('📞 [WebRTC Softphone] Creando Inviter...');
            }
            
            const inviterOptions = {
                requestDelegate: {
                    onAccept: () => {
                        if (this.config.debug_mode) {
                            console.log('✅ [WebRTC Softphone] Llamada aceptada');
                        }
                        this.updateStatus('in-call', 'En llamada');
                        this.startCallTimer();
                    },
                    onReject: (response) => {
                        if (this.config.debug_mode) {
                            console.log('❌ [WebRTC Softphone] Llamada rechazada:', response);
                        }
                        let razon = 'El destino no contestó';
                        if (response && response.message && response.message.statusCode) {
                            const codigo = response.message.statusCode;
                            if (codigo === 486) razon = 'Ocupado';
                            else if (codigo === 487) razon = 'Cancelada';
                            else if (codigo === 408) razon = 'No hay respuesta';
                            else if (codigo === 480) razon = 'Temporalmente no disponible';
                            else if (codigo === 404) razon = 'Número no encontrado';
                        }
                        this.showError('Llamada rechazada: ' + razon);
                        this.endCall();
                    }
                },
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    iceServers: this._getIceServers(),
                    rtcConfiguration: {
                        iceServers: this._getIceServers(),
                        iceTransportPolicy: 'all',
                        bundlePolicy: 'max-bundle',
                        rtcpMuxPolicy: 'require'
                    },
                    mediaStreamFactory: async () => {
                        if (this.config.debug_mode) {
                            console.log('🎤 [WebRTC Softphone] mediaStreamFactory llamada para hacer llamada');
                        }
                        return await this._mediaStreamFactory();
                    }
                }
            };
            
            const inviter = new SIP.Inviter(this.userAgent, targetUri, inviterOptions);
            if (!inviter) {
                throw new Error('No se pudo crear el Inviter');
            }
            
            if (this.config.debug_mode) {
                console.log('✅ [WebRTC Softphone] Inviter creado exitosamente');
            }
            
            this.currentCall = inviter;
            this.updateStatus('in-call', 'Llamando...');
            this.showCallInfo(number);
            
            // Configurar eventos de la llamada (igual que APEX2)
            inviter.stateChange.addListener((newState) => {
                if (this.config.debug_mode) {
                    console.log('📞 [WebRTC Softphone] Estado de llamada:', newState);
                }
                
                const stateStr = String(newState);
                
                if (stateStr === 'Established' || stateStr === '4' || newState === 'Established') {
                    this.updateStatus('in-call', 'En llamada');
                    this.startCallTimer();
                    this.setupAudioSessionForCall(inviter);
                } else if (stateStr === 'Terminated' || stateStr === '5' || newState === 'Terminated') {
                    this.endCall();
                } else if (stateStr === 'Progress' || stateStr === '2' || newState === 'Progress') {
                    this.updateCallStatus('Sonando...');
                } else if (stateStr === 'Establishing' || stateStr === '3' || newState === 'Establishing') {
                    this.updateCallStatus('Llamando...');
                }
            });
            
            // Enviar INVITE
            if (this.config.debug_mode) {
                console.log('📞 [WebRTC Softphone] Enviando INVITE...');
            }
            
            inviter.invite()
                .then(() => {
                    if (this.config.debug_mode) {
                        console.log('✅ [WebRTC Softphone] INVITE enviado exitosamente');
                    }
                })
                .catch((error) => {
                    console.error('❌ [WebRTC Softphone] Error al enviar INVITE:', error);
                    this.showError('Error al realizar la llamada: ' + (error.message || 'Desconocido'));
                    this.endCall();
                });
            
        } catch (error) {
            console.error('❌ [WebRTC Softphone] Error al realizar llamada:', error);
            this.showError('Error al realizar la llamada: ' + error.message);
            this.currentCall = null;
            this.updateStatus('connected', 'Conectado');
        }
    }
    
    /**
     * Manejar cambios de estado de la llamada
     */
    handleCallStateChange(newState, invitation) {
        if (this.config.debug_mode) {
            console.log('📞 Estado de llamada:', newState);
        }
        
        switch (newState) {
            case 'Established':
                this.updateCallStatus('En llamada');
                this.startCallTimer();
                // Configurar audio cuando la llamada se establezca
                setTimeout(() => {
                    this.setupAudioSessionForCall(invitation);
                }, 300);
                break;
            case 'Terminated':
                this.endCall();
                break;
            case 'Rejected':
                this.showError('Llamada rechazada');
                this.endCall();
                break;
            case 'Canceled':
                this.showError('Llamada cancelada');
                this.endCall();
                break;
            case 'Initial':
            case 'InviteSent':
                this.updateCallStatus('Llamando...');
                break;
            case 'Ringing':
                this.updateCallStatus('Sonando...');
                break;
        }
    }
    
    
    /**
     * Colgar la llamada (igual que APEX2)
     */
    hangup() {
        if (this.currentCall) {
            try {
                if (this.config.debug_mode) {
                    console.log('📴 [WebRTC Softphone] Colgando llamada...');
                }
                
                // Verificar el estado de la sesión para usar el método correcto
                const state = String(this.currentCall.state);
                if (this.config.debug_mode) {
                    console.log('   📞 Estado de la sesión:', state);
                }
                
                // Si la llamada aún no está establecida (Establishing, Progress), usar cancel()
                // Si la llamada está establecida (Established), usar bye()
                if (state === 'Establishing' || state === '3' || 
                    state === 'Progress' || state === '2' ||
                    this.currentCall.state === 'Establishing' || 
                    this.currentCall.state === 'Progress') {
                    if (this.config.debug_mode) {
                        console.log('   📞 Cancelando llamada en progreso...');
                    }
                    if (typeof this.currentCall.cancel === 'function') {
                        this.currentCall.cancel();
                    } else {
                        // Fallback: intentar con bye() si cancel() no está disponible
                        if (this.config.debug_mode) {
                            console.log('   ⚠️ cancel() no disponible, usando bye()');
                        }
                        this.currentCall.bye();
                    }
                } else {
                    // Llamada establecida, usar bye()
                    if (this.config.debug_mode) {
                        console.log('   📞 Terminando llamada establecida...');
                    }
                    this.currentCall.bye();
                }
                
                this.endCall();
            } catch (error) {
                console.error('❌ [WebRTC Softphone] Error al colgar:', error);
                this.endCall();
            }
        }
    }
    
    /**
     * Finalizar llamada
     */
    endCall() {
        if (this.config.debug_mode) {
            console.log('📴 [WebRTC Softphone] Finalizando llamada...');
        }
        
        // Detener sonido de llamada si está sonando
        this.stopIncomingCallSound();
        
        // Ocultar notificación de llamada entrante si existe
        this.hideIncomingCallNotification();
        
        // Limpiar audio remoto
        if (this.remoteAudioElement) {
            try {
                this.remoteAudioElement.pause();
                this.remoteAudioElement.srcObject = null;
                if (this.config.debug_mode) {
                    console.log('🔇 [WebRTC Softphone] Audio remoto limpiado');
                }
            } catch (error) {
                console.warn('⚠️ [WebRTC Softphone] Error al limpiar audio remoto:', error);
            }
        }
        
        // Limpiar MediaStream
        this._releaseLastMediaStream();
        
        // Limpiar variables
        this.currentCall = null;
        this.incomingCallInvitation = null;
        this.currentNumber = '';
        
        // Restaurar UI
        this.updateStatus('connected', 'En línea');
        this.hideCallInfo();
        this.stopCallTimer();
        this.updateNumberDisplay();
        
        if (this.config.debug_mode) {
            console.log('✅ [WebRTC Softphone] Llamada finalizada y UI restaurada');
        }
    }
    
    /**
     * Agregar dígito al número
     */
    addDigit(digit) {
        this.currentNumber += digit;
        this.updateNumberDisplay();
    }
    
    /**
     * Eliminar último dígito
     */
    deleteLastDigit() {
        if (this.currentNumber.length > 0) {
            this.currentNumber = this.currentNumber.slice(0, -1);
            this.updateNumberDisplay();
        }
    }
    
    /**
     * Establecer número (para click-to-call)
     */
    setNumber(number) {
        this.currentNumber = number.toString().replace(/\D/g, ''); // Solo números
        this.updateNumberDisplay();
    }
    
    /**
     * Llamar a un número (método público para click-to-call)
     */
    callNumber(number) {
        this.setNumber(number);
        setTimeout(() => {
            this.makeCall();
        }, 100);
    }
    
    /**
     * Actualizar display del número
     */
    updateNumberDisplay() {
        const display = document.getElementById('number-display');
        if (display) {
            display.textContent = this.currentNumber || 'Ingrese número';
        }
    }
    
    /**
     * Actualizar estado de conexión
     */
    updateStatus(status, text) {
        this.status = status;
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        
        if (statusDot) {
            statusDot.className = 'status-dot ' + status;
        }
        
        if (statusText) {
            statusText.textContent = text || this.getStatusText(status);
        }
    }
    
    /**
     * Obtener texto del estado
     */
    getStatusText(status) {
        const statusTexts = {
            'disconnected': 'Desconectado',
            'connecting': 'Conectando...',
            'connected': 'Conectado',
            'in-call': 'En llamada'
        };
        return statusTexts[status] || 'Desconectado';
    }
    
    /**
     * Mostrar información de llamada
     */
    showCallInfo(number) {
        const callInfo = document.getElementById('call-info');
        const callInfoNumber = document.getElementById('call-info-number');
        const callControls = document.getElementById('call-controls');
        const btnCall = document.getElementById('btn-call');
        const btnHangup = document.getElementById('btn-hangup');
        
        if (callInfo) {
            callInfo.style.display = 'block';
        }
        
        if (callInfoNumber) {
            callInfoNumber.textContent = number;
        }
        
        if (callControls) {
            callControls.style.display = 'grid';
        }
        
        if (btnCall) {
            btnCall.style.display = 'none';
        }
        
        if (btnHangup) {
            btnHangup.style.display = 'inline-block';
        }
    }
    
    /**
     * Ocultar información de llamada
     */
    hideCallInfo() {
        const callInfo = document.getElementById('call-info');
        const callControls = document.getElementById('call-controls');
        const btnCall = document.getElementById('btn-call');
        const btnHangup = document.getElementById('btn-hangup');
        
        if (callInfo) {
            callInfo.style.display = 'none';
        }
        
        if (callControls) {
            callControls.style.display = 'none';
        }
        
        if (btnCall) {
            btnCall.style.display = 'inline-block';
        }
        
        if (btnHangup) {
            btnHangup.style.display = 'none';
        }
    }
    
    /**
     * Actualizar estado de la llamada
     */
    updateCallStatus(status) {
        const callInfoStatus = document.getElementById('call-info-status');
        if (callInfoStatus) {
            callInfoStatus.textContent = status;
        }
    }
    
    /**
     * Iniciar temporizador de llamada
     */
    startCallTimer() {
        this.callStartTime = Date.now();
        this.callTimerInterval = setInterval(() => {
            const duration = Math.floor((Date.now() - this.callStartTime) / 1000);
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const durationText = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            const callInfoDuration = document.getElementById('call-info-duration');
            if (callInfoDuration) {
                callInfoDuration.textContent = durationText;
            }
        }, 1000);
    }
    
    /**
     * Detener temporizador de llamada
     */
    stopCallTimer() {
        if (this.callTimerInterval) {
            clearInterval(this.callTimerInterval);
            this.callTimerInterval = null;
        }
    }
    
    /**
     * Activar/Desactivar mute
     */
    toggleMute() {
        if (this.currentCall && this.currentCall.sessionDescriptionHandler) {
            const sdh = this.currentCall.sessionDescriptionHandler;
            if (sdh.localAudioStream) {
                const audioTracks = sdh.localAudioStream.getAudioTracks();
                audioTracks.forEach(track => {
                    track.enabled = !track.enabled;
                });
                
                const btnMute = document.getElementById('btn-mute');
                if (btnMute) {
                    btnMute.classList.toggle('active', !audioTracks[0]?.enabled);
                }
            }
        }
    }
    
    /**
     * Activar/Desactivar speaker
     */
    toggleSpeaker() {
        // Esta funcionalidad depende del navegador y permisos
        // Por ahora solo cambiamos el estado visual
        const btnSpeaker = document.getElementById('btn-speaker');
        if (btnSpeaker) {
            btnSpeaker.classList.toggle('active');
        }
    }
    
    /**
     * Mostrar/ocultar softphone
     */
    toggle() {
        const container = document.getElementById('webrtc-softphone');
        if (container) {
            container.classList.toggle('hidden');
        }
    }
    
    /**
     * Mostrar error
     */
    showError(message) {
        console.error('❌ Softphone Error:', message);
        // Puedes implementar un sistema de notificaciones aquí
        if (this.config.debug_mode) {
            alert(message);
        }
    }
    
    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info') {
        console.log(`ℹ️ Softphone: ${message}`);
        // Puedes implementar un sistema de notificaciones aquí
    }
    
    /**
     * Factory personalizada para crear MediaStreams con las constraints correctas (igual que APEX2)
     */
    async _mediaStreamFactory(constraintsFromSIP = {}) {
        if (this.config.debug_mode) {
            console.log('🎤 [WebRTC Softphone] mediaStreamFactory LLAMADA POR SIP.js');
        }
        
        const finalConstraints = { audio: true, video: false };
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('getUserMedia no disponible en este navegador/contexto.');
        }
        
        try {
            // Liberar stream anterior si existe
            this._releaseLastMediaStream();
            
            // Intentar adquirir el stream
            const stream = await navigator.mediaDevices.getUserMedia(finalConstraints);
            this.lastMediaStream = stream;
            
            if (this.config.debug_mode) {
                const audioTracks = stream.getAudioTracks();
                console.log(`✅ [WebRTC Softphone] MediaStream adquirido. Tracks: ${audioTracks.length}`);
            }
            
            return stream;
        } catch (error) {
            if (this.config.debug_mode) {
                console.error('❌ [WebRTC Softphone] mediaStreamFactory no pudo abrir el micrófono:', error);
            }
            throw error;
        }
    }
    
    /**
     * Libera el último MediaStream adquirido
     */
    _releaseLastMediaStream() {
        if (this.lastMediaStream) {
            this.lastMediaStream.getTracks().forEach((track) => {
                track.stop();
            });
            this.lastMediaStream = null;
        }
    }
    
    /**
     * Parchea una instancia de URI para garantizar que cuente con el método clone()
     * incluso cuando la versión de SIP.js no lo agrega automáticamente (igual que APEX2)
     */
    _patchUriClone(uri) {
        if (!uri || typeof uri !== 'object') {
            return uri;
        }

        if (typeof uri.clone === 'function') {
            return uri;
        }

        const originalString = typeof uri.toString === 'function' ? uri.toString() : `${uri}`;
        const self = this;

        function safeClone() {
            try {
                if (typeof SIP !== 'undefined' && SIP.UserAgent && typeof SIP.UserAgent.makeURI === 'function') {
                    const recreated = SIP.UserAgent.makeURI(originalString);
                    if (recreated) {
                        return self._patchUriClone(recreated);
                    }
                }
            } catch (cloneErr) {
                if (self.config.debug_mode) {
                    console.warn('⚠️ [WebRTC Softphone] Error recreando URI en clone():', cloneErr);
                }
            }

            return {
                scheme: uri.scheme,
                user: uri.user,
                host: uri.host,
                port: uri.port,
                toString: () => originalString,
                toRaw: typeof uri.toRaw === 'function' ? () => uri.toRaw() : () => originalString,
                clone: () => safeClone()
            };
        }

        uri.clone = () => safeClone();
        if (this.config.debug_mode) {
            console.log(`🔧 [WebRTC Softphone] URI parchado con clone(): ${originalString}`);
        }
        return uri;
    }
    
    /**
     * Parchea una instancia de URI para garantizar que cuente con el método clone()
     * incluso cuando la versión de SIP.js no lo agrega automáticamente (igual que APEX2)
     */
    _patchUriClone(uri) {
        if (!uri || typeof uri !== 'object') {
            return uri;
        }

        if (typeof uri.clone === 'function') {
            return uri;
        }

        const originalString = typeof uri.toString === 'function' ? uri.toString() : `${uri}`;
        const self = this;

        function safeClone() {
            try {
                if (typeof SIP !== 'undefined' && SIP.UserAgent && typeof SIP.UserAgent.makeURI === 'function') {
                    const recreated = SIP.UserAgent.makeURI(originalString);
                    if (recreated) {
                        return self._patchUriClone(recreated);
                    }
                }
            } catch (cloneErr) {
                if (self.config.debug_mode) {
                    console.warn('⚠️ [WebRTC Softphone] Error recreando URI en clone():', cloneErr);
                }
            }

            return {
                scheme: uri.scheme,
                user: uri.user,
                host: uri.host,
                port: uri.port,
                toString: () => originalString,
                toRaw: typeof uri.toRaw === 'function' ? () => uri.toRaw() : () => originalString,
                clone: () => safeClone()
            };
        }

        uri.clone = () => safeClone();
        if (this.config.debug_mode) {
            console.log(`🔧 [WebRTC Softphone] URI parchado con clone(): ${originalString}`);
        }
        return uri;
    }
    
    /**
     * Construye la configuración de servidores ICE (STUN/TURN) - igual que APEX2
     */
    _getIceServers() {
        const iceServers = [];
        
        // PRIORIDAD 1: Si hay configuración personalizada de iceServers, usarla
        if (this.config && this.config.iceServers && Array.isArray(this.config.iceServers)) {
            if (this.config.debug_mode) {
                console.log('📡 [WebRTC Softphone] Usando servidores ICE personalizados');
            }
            this.config.iceServers.forEach(server => {
                if (server.urls) {
                    iceServers.push(server);
                }
            });
        }
        
        // PRIORIDAD 2: Si hay configuración de STUN desde PHP (stun_server)
        if (this.config && this.config.stun_server) {
            const stunUrl = this.config.stun_server.startsWith('stun:') 
                ? this.config.stun_server 
                : `stun:${this.config.stun_server}`;
            iceServers.push({ urls: stunUrl });
        }
        
        // PRIORIDAD 3: Servidores STUN públicos de Google (fallback por defecto)
        if (iceServers.length === 0) {
            iceServers.push(
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            );
        }
        
        return iceServers;
    }
    
    /**
     * Desconectar y limpiar recursos
     */
    disconnect() {
        // Detener sonido de llamada
        this.stopIncomingCallSound();
        
        // Ocultar notificación de llamada entrante
        this.hideIncomingCallNotification();
        
        // Colgar llamada activa
        if (this.currentCall) {
            this.hangup();
        }
        
        // Rechazar llamada entrante si existe
        if (this.incomingCallInvitation) {
            try {
                this.incomingCallInvitation.reject();
            } catch (error) {
                console.warn('⚠️ Error al rechazar llamada entrante:', error);
            }
        }
        
        // Desregistrar antes de detener
        if (this.registerer) {
            try {
                if (this.config.debug_mode) {
                    console.log('📝 [WebRTC Softphone] Desregistrando antes de desconectar...');
                }
                this.registerer.unregister();
            } catch (error) {
                console.warn('⚠️ [WebRTC Softphone] Error al desregistrar:', error);
            }
            this.registerer = null;
        }
        
        // Detener UserAgent
        if (this.userAgent) {
            this.userAgent.stop();
            this.userAgent = null;
        }
        
        // Limpiar variables
        this.isConnected = false;
        this.isRegistered = false;
        this.currentCall = null;
        this.incomingCallInvitation = null;
        this.updateStatus('disconnected', 'Desconectado');
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.WebRTCSoftphone = WebRTCSoftphone;
}

