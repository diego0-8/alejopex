/**
 * WebRTC Softphone Class
 * Maneja la lógica de SIP.js 0.16.1 y aplica parches de clone() para evitar errores
 * al crear URIs con signo + o URIs de usuario sin clone implementado.
 */
class WebRTCSoftphone {
    constructor(config) {
        this.config = config;
        this.userAgent = null;
        this.currentSession = null;
        this.currentNumber = '';
        this.callDuration = 0;
        this.callTimer = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.isRegistered = false;
        this.handleKeyPress = this.handleKeyPress.bind(this);
        this.audioDevices = [];
        this.preferredAudioDeviceId = config?.preferredAudioDeviceId || null;
        this.preferredAudioLabel = config?.preferredAudioLabel || null;
        this.lastMediaStream = null;
        this.mediaStreamFactory = this._mediaStreamFactory.bind(this);
        this.preAcquiredStream = null; // Stream pre-adquirido para interceptar SIP.js
        this.originalGetUserMedia = null; // Referencia al método original
        this.remoteAudioElement = null; // Elemento de audio para reproducir audio remoto
        
        // Inicializar
        this.init();
    }
    
    /**
     * Inicializar el softphone
     */
    init() {
        this.log('Inicializando softphone...');
        this.createRemoteAudioElement();
        this.createPanel();
        this.attachEventListeners();
        this.initializeSIPjs();
        this.checkAudioPermissions();
    }
    
    /**
     * Crea un elemento de audio oculto para reproducir el audio remoto
     */
    createRemoteAudioElement() {
        // Crear elemento de audio si no existe
        if (!this.remoteAudioElement) {
            this.remoteAudioElement = document.createElement('audio');
            this.remoteAudioElement.id = 'webrtc-softphone-remote-audio';
            this.remoteAudioElement.autoplay = true;
            this.remoteAudioElement.playsInline = true;
            this.remoteAudioElement.volume = 1.0; // Volumen al máximo
            this.remoteAudioElement.muted = false; // No silenciado
            this.remoteAudioElement.style.display = 'none';
            document.body.appendChild(this.remoteAudioElement);
            this.log('✅ Elemento de audio remoto creado');
            
            // Agregar listeners para diagnosticar problemas
            this.remoteAudioElement.addEventListener('play', () => {
                this.log('🎵 Evento play disparado en elemento de audio');
            });
            
            this.remoteAudioElement.addEventListener('playing', () => {
                this.log('▶️ Evento playing disparado - audio realmente reproduciéndose');
            });
            
            this.remoteAudioElement.addEventListener('pause', () => {
                this.log('⏸️ Evento pause disparado');
            });
            
            this.remoteAudioElement.addEventListener('error', (error) => {
                this.log('❌ Error en elemento de audio:', error);
            });
            
            this.remoteAudioElement.addEventListener('loadedmetadata', () => {
                this.log('📊 Metadata cargada en elemento de audio');
            });
        }
    }

    

    /**
     * Verifica si el navegador detecta algún micrófono disponible.
     * IMPORTANTE: Pide permisos primero para obtener los deviceId reales.
     * Sin permisos, los deviceId son genéricos y no funcionan al hacer la llamada.
     */
    async checkAudioPermissions() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            this.log('⚠️ navigator.mediaDevices no disponible (¿HTTPS requerido o navegador antiguo?).');
            return false;
        }

        try {
            // PASO CRÍTICO: Pedir permisos primero con getUserMedia genérico
            // Esto "revela" los deviceId reales en la siguiente enumeración
            this.log('🔐 Solicitando permisos de micrófono para obtener deviceId reales...');
            let tempStream = null;
            
            try {
                tempStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                this.log('✅ Permisos concedidos, enumerando dispositivos con deviceId reales...');
                
                // Cerrar el stream inmediatamente (solo necesitábamos los permisos)
                tempStream.getTracks().forEach(track => track.stop());
                tempStream = null;
            } catch (permError) {
                this.log('⚠️ No se pudieron obtener permisos de micrófono:', permError.name);
                // Continuar de todas formas, pero los deviceId serán genéricos
            }

            // Ahora enumerar dispositivos - los deviceId serán reales si se otorgaron permisos
            const devices = await navigator.mediaDevices.enumerateDevices();
            this.audioDevices = devices.filter(d => d.kind === 'audioinput');
            
            if (!this.audioDevices.length) {
                this.log('⚠️ No se detectó ningún micrófono conectado.');
                return false;
            }

            // Log detallado de dispositivos encontrados
            this.log('📋 Micrófonos detectados:', this.audioDevices.map(d => ({
                label: d.label || '(sin nombre)',
                deviceId: d.deviceId ? d.deviceId.substring(0, 20) + '...' : 'default'
            })));

            // Buscar y guardar el dispositivo USB preferido
            if (!this.preferredAudioDeviceId) {
                const matched = this._matchPreferredAudioDevice();
                if (matched && matched.deviceId && matched.deviceId !== 'default') {
                    this.preferredAudioDeviceId = matched.deviceId;
                    this.log('🎯 Micrófono preferido fijado automáticamente:', matched.label, `(${matched.deviceId.substring(0, 20)}...)`);
                } else if (this.audioDevices.length > 0) {
                    // Si no hay match, usar el primero que no sea default
                    const firstReal = this.audioDevices.find(d => d.deviceId && d.deviceId !== 'default') || this.audioDevices[0];
                    if (firstReal && firstReal.deviceId) {
                        this.preferredAudioDeviceId = firstReal.deviceId;
                        this.log('🎯 Usando primer micrófono disponible:', firstReal.label || 'Sin nombre');
                    }
                }
            }

            return true;
        } catch (error) {
            this.log('❌ Error verificando dispositivos de audio:', error);
            return false;
        }
    }

    /**
     * Solicita permisos de audio previo a crear el INVITE.
     * Lanza una excepción si no se puede acceder al micrófono.
     */
    async requireMicrophoneAccess() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('El navegador no soporta getUserMedia (requiere HTTPS o navegador moderno).');
        }

        try {
            this.log('Verificando acceso al micrófono con getUserMedia...');
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            stream.getTracks().forEach(track => track.stop());
            this.log('✅ Permiso de micrófono concedido.');
        } catch (error) {
            if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                throw new Error('No se encontró ningún micrófono conectado.');
            }
            if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
                throw new Error('Acceso al micrófono denegado por el navegador.');
            }
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                throw new Error('WebRTC requiere HTTPS para usar el micrófono en producción.');
            }
            throw error;
        }
    }

    /**
     * Parchea una instancia de URI para garantizar que cuente con el método clone()
     * incluso cuando la versión de SIP.js no lo agrega automáticamente.
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
                self.log('Error recreando URI en clone():', cloneErr);
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
        this.log(`🔧 URI parchado con clone(): ${originalString}`);
        return uri;
    }

    /**
     * Determina el dispositivo de audio preferido según config o coincidencias por etiqueta.
     */
    _matchPreferredAudioDevice() {
        if (!Array.isArray(this.audioDevices) || this.audioDevices.length === 0) {
            return null;
        }

        const byId = this.audioDevices.find(device => device.deviceId === this.preferredAudioDeviceId);
        if (byId) return byId;

        const labelHint = this.preferredAudioLabel || this.config?.preferredAudioLabel;
        if (labelHint) {
            const normalizedHint = labelHint.toLowerCase();
            const byLabel = this.audioDevices.find(device =>
                (device.label || '').toLowerCase().includes(normalizedHint)
            );
            if (byLabel) return byLabel;
        }

        return this.audioDevices[0];
    }

    /**
     * Construye la configuración de servidores ICE (STUN/TURN).
     * Estos servidores son esenciales para que el audio se transporte correctamente
     * cuando los clientes están detrás de NAT/firewalls.
     * 
     * STUN: Permite descubrir la IP pública del cliente
     * TURN: Actúa como retransmisor cuando la conexión directa falla (NAT simétrico)
     */
    _getIceServers() {
        const iceServers = [];
        
        // PRIORIDAD 1: Si hay configuración personalizada de iceServers, usarla
        if (this.config && this.config.iceServers && Array.isArray(this.config.iceServers)) {
            this.log('📡 Usando servidores ICE personalizados de la configuración');
            this.config.iceServers.forEach(server => {
                if (server.urls) {
                    iceServers.push(server);
                    this.log(`   ✅ Agregado servidor ICE: ${server.urls}`);
                }
            });
        }
        
        // PRIORIDAD 2: Si hay configuración específica de TURN, agregarla
        if (this.config && this.config.turnServer) {
            const turnConfig = {
                urls: this.config.turnServer.url || this.config.turnServer.urls,
                username: this.config.turnServer.username,
                credential: this.config.turnServer.credential || this.config.turnServer.password
            };
            
            if (turnConfig.urls) {
                iceServers.push(turnConfig);
                this.log(`   ✅ Agregado servidor TURN: ${turnConfig.urls}`);
            }
        }
        
        // PRIORIDAD 3: Si hay configuración de STUN desde PHP (stun_server)
        if (this.config && this.config.stun_server) {
            const stunUrl = this.config.stun_server.startsWith('stun:') 
                ? this.config.stun_server 
                : `stun:${this.config.stun_server}`;
            iceServers.push({ urls: stunUrl });
            this.log(`   ✅ Agregado servidor STUN desde configuración: ${stunUrl}`);
        }
        
        // PRIORIDAD 4: Servidores STUN públicos de Google (fallback por defecto)
        // Solo agregar si no hay configuración personalizada
        if (iceServers.length === 0 || !this.config || !this.config.iceServers) {
            iceServers.push(
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            );
            this.log('   ✅ Agregados servidores STUN públicos de Google (fallback)');
        }
        
        this.log(`📡 Configuración ICE: ${iceServers.length} servidor(es) configurado(s)`);
        iceServers.forEach((server, index) => {
            this.log(`   ${index + 1}. ${server.urls}${server.username ? ` (TURN con auth)` : ''}`);
        });
        
        return iceServers;
    }

    /**
     * Construye constraints basadas en micrófonos disponibles.
     * Usa preferencia "ideal" en lugar de "exact" para permitir fallback automático.
     */
    async _determineAudioConstraints() {
        const fallback = { audio: true, video: false };

        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            this.log('⚠️ enumerateDevices no disponible, usando constraints por defecto');
            return fallback;
        }

        try {
            // CRÍTICO: Pedir permisos primero para asegurar que los deviceId sean reales
            // Esto es necesario porque los deviceId pueden cambiar si no se han pedido permisos recientemente
            try {
                const tempStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                tempStream.getTracks().forEach(track => track.stop());
                this.log('✅ Permisos verificados antes de enumerar dispositivos');
            } catch (permError) {
                this.log('⚠️ No se pudieron verificar permisos:', permError.name);
                // Continuar de todas formas
            }

            const devices = await navigator.mediaDevices.enumerateDevices();
            this.audioDevices = devices.filter((d) => d.kind === 'audioinput');
            
            // Log para ver qué está encontrando realmente
            this.log('Listado de AudioInputs:', this.audioDevices.map((d) => ({
                label: d.label || '(sin nombre)',
                deviceId: d.deviceId ? d.deviceId.substring(0, 20) + '...' : 'default'
            })));

            if (!this.audioDevices.length) {
                this.log('⚠️ enumerateDevices no retornó micrófonos, constraints por defecto.');
                return fallback;
            }

            let targetDevice = null;

            // PRIORIDAD 1: Usar el deviceId que guardamos durante la inicialización
            // Este es el deviceId REAL que se obtuvo después de pedir permisos
            if (this.preferredAudioDeviceId) {
                targetDevice = this.audioDevices.find((d) => 
                    d.deviceId && d.deviceId === this.preferredAudioDeviceId
                );
                if (targetDevice) {
                    this.log(`✅ Usando micrófono guardado: ${targetDevice.label || targetDevice.deviceId}`);
                } else {
                    this.log('⚠️ El deviceId guardado ya no está disponible, buscando alternativas...');
                    // Limpiar el deviceId guardado si ya no existe
                    this.preferredAudioDeviceId = null;
                }
            }

            // PRIORIDAD 2: Buscar USB explícitamente (si no encontramos el guardado)
            if (!targetDevice) {
                targetDevice = this.audioDevices.find((d) => {
                    const label = (d.label || '').toLowerCase();
                    return d.deviceId && d.deviceId !== 'default' && label.includes('usb');
                });
            }

            // PRIORIDAD 3: Si no hay USB, buscar comunicaciones
            if (!targetDevice) {
                targetDevice = this.audioDevices.find((d) => {
                    const label = (d.label || '').toLowerCase();
                    return d.deviceId && d.deviceId !== 'default' && label.includes('communications');
                });
            }

            // PRIORIDAD 4: Si falla, tomar el primero que no sea default
            if (!targetDevice) {
                targetDevice = this.audioDevices.find((d) => d.deviceId && d.deviceId !== 'default');
            }

            // PRIORIDAD 5: Último recurso: usar el primero disponible
            if (!targetDevice) {
                targetDevice = this.audioDevices[0];
            }

            if (targetDevice && targetDevice.deviceId) {
                if (!this.preferredAudioDeviceId) {
                    this.preferredAudioDeviceId = targetDevice.deviceId;
                }
                this.log(`🎤 Preferencia de micrófono: ${targetDevice.label || targetDevice.deviceId} (${targetDevice.deviceId.substring(0, 20)}...)`);
                
                return {
                    audio: { 
                        // CAMBIO CRÍTICO: Quitamos { exact: ... }
                        // Al pasar el string directo, es una "preferencia ideal".
                        // Si falla, Chrome usará otro mic en vez de lanzar NotFoundError.
                        deviceId: targetDevice.deviceId,
                        
                        // Opcional: Desactivar procesamiento extra que a veces causa fallos en HTTP
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    },
                    video: false
                };
            }
        } catch (error) {
            this.log('⚠️ Error determinando constraints, usando default.', error);
        }

        return fallback;
    }

    /**
     * Factory personalizada para crear MediaStreams con las constraints correctas.
     * Esta función es llamada por SIP.js cuando necesita adquirir el micrófono.
     */
    async _mediaStreamFactory(constraintsFromSIP = {}) {
        this.log('🎤 ===== mediaStreamFactory LLAMADA POR SIP.js =====');
        this.log('Constraints recibidas de SIP.js:', constraintsFromSIP);
        
        const finalConstraints = await this._determineAudioConstraints();
        this.log('Constraints finales que usaremos:', finalConstraints);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('getUserMedia no disponible en este navegador/contexto.');
        }

        try {
            // Liberar stream anterior si existe
            this._releaseLastMediaStream();
            
            this.log('🔍 Intentando adquirir MediaStream con constraints:', JSON.stringify(finalConstraints));
            
            // Intentar adquirir el stream con nuestras constraints
            const stream = await navigator.mediaDevices.getUserMedia(finalConstraints);
            this.lastMediaStream = stream;
            
            // Log de los tracks adquiridos
            const audioTracks = stream.getAudioTracks();
            this.log(`✅ MediaStream adquirido exitosamente. Tracks de audio: ${audioTracks.length}`);
            if (audioTracks.length > 0) {
                this.log(`   Track ID: ${audioTracks[0].id}, Label: ${audioTracks[0].label}, Enabled: ${audioTracks[0].enabled}`);
            }
            
            return stream;
        } catch (error) {
            this.log('❌ mediaStreamFactory no pudo abrir el micrófono:', error);
            this.log('   Error name:', error.name);
            this.log('   Error message:', error.message);
            
            // Si falla con el dispositivo preferido, intentar con fallback simple
            if (error.name === 'NotFoundError' || error.name === 'NotAllowedError') {
                this.log('⚠️ Intentando fallback con constraints simples...');
                try {
                    const fallbackConstraints = { audio: true, video: false };
                    const fallbackStream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                    this.lastMediaStream = fallbackStream;
                    
                    const audioTracks = fallbackStream.getAudioTracks();
                    this.log(`✅ MediaStream adquirido con fallback. Tracks: ${audioTracks.length}`);
                    
                    return fallbackStream;
                } catch (fallbackError) {
                    this.log('❌ Fallback también falló:', fallbackError);
                    this.log('   Fallback error name:', fallbackError.name);
                    this.log('   Fallback error message:', fallbackError.message);
                    throw fallbackError;
                }
            }
            throw error;
        }
    }

    /**
     * Libera el último MediaStream adquirido.
     */
    _releaseLastMediaStream() {
        if (this.lastMediaStream) {
            this.lastMediaStream.getTracks().forEach((track) => {
                track.stop();
            });
            this.lastMediaStream = null;
            this.log('🔇 MediaStream liberado');
        }
    }

    /**
     * Verifica los codecs en el SDP y los registra en los logs
     */
    _verifyCodecs(sdp, tipo = 'local') {
        if (!sdp || typeof sdp !== 'string') return;
        
        try {
            // Buscar línea m=audio que contiene los codecs
            const audioLineMatch = sdp.match(/m=audio\s+\d+\s+[^\r\n]+/);
            if (audioLineMatch) {
                const audioLine = audioLineMatch[0];
                this.log(`🎵 Codecs en SDP ${tipo}: ${audioLine}`);
                
                // Extraer los números de payload (codecs)
                const codecNumbers = audioLine.match(/\d+/g);
                if (codecNumbers && codecNumbers.length > 2) {
                    const payloads = codecNumbers.slice(2); // Saltar puerto y protocolo
                    this.log(`   Payloads (codecs): ${payloads.join(', ')}`);
                    
                    // Mapear números a nombres de codecs
                    const codecMap = {
                        '0': 'PCMU (G.711 μ-law)',
                        '8': 'PCMA (G.711 A-law)',
                        '9': 'G.722',
                        '13': 'CN (Comfort Noise)',
                        '63': 'RED (Redundancy)',
                        '110': 'telephone-event/48000',
                        '111': 'Opus/48000',
                        '126': 'telephone-event/8000'
                    };
                    
                    const codecNames = payloads.map(p => {
                        const name = codecMap[p] || `Desconocido (${p})`;
                        return `${p} (${name})`;
                    });
                    
                    this.log(`   Codecs detectados: ${codecNames.join(', ')}`);
                    
                    // Verificar si hay codecs compatibles con Asterisk (PCMU/PCMA)
                    const hasPCMU = payloads.includes('0');
                    const hasPCMA = payloads.includes('8');
                    
                    if (hasPCMU || hasPCMA) {
                        this.log(`   ✅ Codecs compatibles con Asterisk detectados: ${hasPCMU ? 'PCMU' : ''} ${hasPCMA ? 'PCMA' : ''}`);
                    } else {
                        this.log(`   ⚠️ No se detectaron codecs PCMU/PCMA - puede haber problemas de compatibilidad`);
                    }
                }
            }
        } catch (error) {
            this.log(`⚠️ Error verificando codecs: ${error.message}`);
        }
    }

    /**
     * Parchea el SDP remoto para agregar rtcp-mux si no está presente.
     * Esto soluciona la incompatibilidad con Asterisk que no envía rtcp-mux.
     */
    _patchRemoteSDP(sdp) {
        if (!sdp || typeof sdp !== 'string') {
            return sdp;
        }

        // Verificar si ya tiene rtcp-mux
        if (sdp.includes('a=rtcp-mux')) {
            this.log('✅ SDP remoto ya tiene rtcp-mux');
            return sdp;
        }

        this.log('🔧 Parcheando SDP remoto: agregando rtcp-mux...');
        this.log('   SDP original (primeras 300 chars):', sdp.substring(0, 300));
        
        // Detectar el separador de líneas usado en el SDP
        const hasCRLF = sdp.includes('\r\n');
        const lineSeparator = hasCRLF ? '\r\n' : '\n';
        
        // Dividir el SDP en líneas
        const lines = sdp.split(lineSeparator);
        const patchedLines = [];
        let inAudioSection = false;
        let rtcpMuxAdded = false;
        let setupIndex = -1;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            patchedLines.push(line);
            
            // Detectar inicio de sección de audio
            if (line.startsWith('m=audio')) {
                inAudioSection = true;
                rtcpMuxAdded = false;
                setupIndex = -1;
                this.log('   📍 Sección de audio detectada en línea', i);
                continue;
            }

            // Detectar fin de sección de audio (siguiente m= o línea vacía seguida de otra sección)
            if (inAudioSection && line.startsWith('m=') && !line.startsWith('m=audio')) {
                // Agregar rtcp-mux antes de cerrar la sección
                if (!rtcpMuxAdded) {
                    if (setupIndex >= 0) {
                        // Insertar después de a=setup
                        patchedLines.splice(setupIndex + 1, 0, 'a=rtcp-mux');
                        this.log('✅ rtcp-mux agregado después de a=setup (antes de cerrar sección)');
                    } else {
                        // Insertar antes de la última línea de atributos
                        patchedLines.splice(patchedLines.length - 1, 0, 'a=rtcp-mux');
                        this.log('✅ rtcp-mux agregado antes del fin de la sección de audio');
                    }
                    rtcpMuxAdded = true;
                }
                inAudioSection = false;
                continue;
            }

            // Si estamos en la sección de audio
            if (inAudioSection) {
                // Si encontramos a=setup, guardar su índice
                if (line.startsWith('a=setup:') && setupIndex === -1) {
                    setupIndex = patchedLines.length - 1;
                    this.log('   📍 a=setup encontrado en línea', i, 'índice en patchedLines:', setupIndex);
                    
                    // Agregar rtcp-mux justo después de a=setup
                    if (!rtcpMuxAdded) {
                        patchedLines.push('a=rtcp-mux');
                        rtcpMuxAdded = true;
                        this.log('✅ rtcp-mux agregado inmediatamente después de a=setup');
                    }
                }
            }
        }

        // Si aún no se agregó rtcp-mux y estamos al final de la sección de audio
        if (inAudioSection && !rtcpMuxAdded) {
            if (setupIndex >= 0) {
                patchedLines.splice(setupIndex + 1, 0, 'a=rtcp-mux');
                this.log('✅ rtcp-mux agregado después de a=setup (al final del procesamiento)');
            } else {
                // Agregar al final de la sección de audio
                patchedLines.push('a=rtcp-mux');
                this.log('✅ rtcp-mux agregado al final de la sección de audio');
            }
            rtcpMuxAdded = true;
        }

        const patchedSDP = patchedLines.join(lineSeparator);
        if (rtcpMuxAdded) {
            this.log('✅ SDP remoto parcheado exitosamente con rtcp-mux');
            this.log('   SDP parcheado (primeras 300 chars):', patchedSDP.substring(0, 300));
        } else {
            this.log('⚠️ No se pudo agregar rtcp-mux al SDP remoto');
        }
        return patchedSDP;
    }

    
    /**
     * Realizar una llamada asegurando que todas las URIs tengan clone().
     */
    async makeCall() {
        if (!this.currentNumber) {
            this.showNotification('Error', 'Ingrese un número de destino', 'error');
            return;
        }

        if (!this.userAgent || !this.isRegistered) {
            this.showNotification('Error', 'El softphone no está registrado. Verifique la conexión', 'error');
            return;
        }

        try {
            // Verificación rápida de micrófonos (no bloquea, solo advierte)
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const audioInputs = devices.filter(d => d.kind === 'audioinput');
                if (audioInputs.length === 0) {
                    this.log('⚠️ Advertencia: No se detectaron micrófonos. La llamada puede fallar.');
                }
            } catch (e) {
                // Ignorar errores de enumeración, el mediaStreamFactory los manejará
                this.log('⚠️ No se pudo verificar micrófonos:', e);
            }

            if (!this.currentNumber.trim()) {
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

            const targetUriString = `sip:${this.currentNumber.trim()}@${this.config.sip_domain.trim()}`;
            this.log(`Creando URI destino: ${targetUriString}`);
            
            let targetUri = SIP.UserAgent.makeURI(targetUriString);
            if (!targetUri) {
                throw new Error('No se pudo crear la URI de destino');
            }

            targetUri = this._patchUriClone(targetUri);
            this.log(`✅ URI destino parchado: ${targetUri.toString()}`);

            this.log('Creando Inviter...');
            const inviter = new SIP.Inviter(this.userAgent, targetUri);
            if (!inviter) {
                throw new Error('No se pudo crear el Inviter');
            }

            this.currentSession = inviter;
            this.log('✅ Inviter creado exitosamente');

            // Función para interceptar setRemoteDescription y configurar audio remoto
            const interceptSetRemoteDescription = () => {
                if (inviter.sessionDescriptionHandler) {
                    const sdh = inviter.sessionDescriptionHandler;
                    const pc = sdh.peerConnection;

                    if (pc && !pc._sdpInterceptorPatched) {
                        this.log('🔧 Interceptando setRemoteDescription para parchear SDP remoto...');
                        
                        // Interceptar setRemoteDescription
                        const originalSetRemoteDescription = pc.setRemoteDescription.bind(pc);
                        const self = this;

                        pc.setRemoteDescription = async function(description) {
                            self.log('🔧 ===== setRemoteDescription INTERCEPTADO =====');
                            
                            if (description && description.sdp) {
                                self.log('📝 SDP remoto recibido, verificando rtcp-mux...');
                                self.log('   Tipo:', description.type);
                                self.log('   SDP contiene rtcp-mux:', description.sdp.includes('a=rtcp-mux'));
                                
                                // VERIFICAR CODECS NEGOCIADOS
                                if (typeof self._verifyCodecs === 'function') {
                                    self._verifyCodecs(description.sdp, 'remoto');
                                } else {
                                    self.log('⚠️ _verifyCodecs no está disponible');
                                }
                                
                                // Parchear el SDP
                                const patchedSDP = self._patchRemoteSDP(description.sdp);
                                
                                if (patchedSDP !== description.sdp) {
                                    self.log('✅ SDP remoto parcheado, aplicando cambios...');
                                    // Crear una nueva descripción con el SDP parcheado
                                    const patchedDescription = new RTCSessionDescription({
                                        type: description.type,
                                        sdp: patchedSDP
                                    });
                                    return await originalSetRemoteDescription(patchedDescription);
                                } else {
                                    self.log('✅ SDP remoto ya tiene rtcp-mux, no se necesita parchear');
                                }
                            }
                            
                            // Si no hay cambios, usar el método original
                            return await originalSetRemoteDescription(description);
                        };
                        
                        // INTERCEPTAR createOffer PARA VERIFICAR CODECS LOCALES
                        if (pc && pc.createOffer && !pc._createOfferIntercepted) {
                            const originalCreateOffer = pc.createOffer.bind(pc);
                            const self = this;
                            
                            pc.createOffer = async function(options) {
                                const offer = await originalCreateOffer(options);
                                
                                if (offer && offer.sdp) {
                                    self.log('🎵 ===== SDP LOCAL (OFFER) CREADO =====');
                                    if (typeof self._verifyCodecs === 'function') {
                                        self._verifyCodecs(offer.sdp, 'local (offer)');
                                    } else {
                                        self.log('⚠️ _verifyCodecs no está disponible');
                                    }
                                }
                                
                                return offer;
                            };
                            
                            pc._createOfferIntercepted = true;
                            this.log('✅ createOffer interceptado para verificar codecs');
                        }
                        
                        // VERIFICAR Y CONFIGURAR ICE SERVERS EN EL PEERCONNECTION
                        // Asegurar que el PeerConnection tenga la configuración ICE correcta
                        if (pc && pc.getConfiguration) {
                            const currentConfig = pc.getConfiguration();
                            const iceServers = this._getIceServers();
                            
                            // Verificar si los iceServers están configurados
                            if (!currentConfig.iceServers || currentConfig.iceServers.length === 0) {
                                this.log('⚠️ PeerConnection no tiene iceServers configurados, aplicando configuración...');
                                try {
                                    pc.setConfiguration({
                                        iceServers: iceServers,
                                        iceTransportPolicy: 'all',
                                        bundlePolicy: 'max-bundle',
                                        rtcpMuxPolicy: 'require'
                                    });
                                    this.log('✅ Configuración ICE aplicada al PeerConnection');
                                } catch (configError) {
                                    this.log('⚠️ No se pudo aplicar configuración ICE al PeerConnection:', configError);
                                }
                            } else {
                                this.log(`✅ PeerConnection ya tiene ${currentConfig.iceServers.length} servidor(es) ICE configurado(s)`);
                                // Verificar que los servidores sean los correctos
                                const hasStun = currentConfig.iceServers.some(s => s.urls && s.urls.includes('stun:'));
                                if (!hasStun) {
                                    this.log('⚠️ PeerConnection no tiene servidores STUN, agregando...');
                                    try {
                                        const updatedConfig = {
                                            ...currentConfig,
                                            iceServers: [...currentConfig.iceServers, ...iceServers]
                                        };
                                        pc.setConfiguration(updatedConfig);
                                        this.log('✅ Servidores STUN agregados al PeerConnection');
                                    } catch (configError) {
                                        this.log('⚠️ No se pudo agregar servidores STUN:', configError);
                                    }
                                }
                            }
                        }
                        
                        // CONFIGURAR AUDIO REMOTO: Escuchar cuando se agrega un track remoto
                        // Usar ontrack en lugar de addEventListener para asegurar que se capture
                        if (!pc._audioTrackListenerAdded) {
                            const handleRemoteTrack = (event) => {
                                self.log('🎵 ===== TRACK REMOTO AGREGADO =====');
                                self.log('   Tipo de track:', event.track.kind);
                                self.log('   Track ID:', event.track.id);
                                self.log('   Track label:', event.track.label);
                                self.log('   Track enabled:', event.track.enabled);
                                self.log('   Track readyState:', event.track.readyState);
                                
                                if (event.track.kind === 'audio') {
                                    // Asegurar que el track esté habilitado
                                    if (!event.track.enabled) {
                                        event.track.enabled = true;
                                        self.log('   🔧 Track habilitado manualmente en listener');
                                    }
                                    
                                    // Conectar el track remoto al elemento de audio
                                    if (self.remoteAudioElement) {
                                        // Asegurar volumen y no silenciado
                                        self.remoteAudioElement.volume = 1.0;
                                        self.remoteAudioElement.muted = false;
                                        
                                        // Si ya hay un stream, agregar el track; si no, crear uno nuevo
                                        if (self.remoteAudioElement.srcObject) {
                                            const existingStream = self.remoteAudioElement.srcObject;
                                            const existingTracks = existingStream.getAudioTracks();
                                            if (!existingTracks.find(t => t.id === event.track.id)) {
                                                existingStream.addTrack(event.track);
                                                self.log('✅ Track agregado a stream existente');
                                            }
                                        } else {
                                            const remoteStream = new MediaStream([event.track]);
                                            self.remoteAudioElement.srcObject = remoteStream;
                                            self.log('✅ Audio remoto conectado al elemento de audio (nuevo stream)');
                                            self.log(`   Stream activo: ${remoteStream.active}, tracks: ${remoteStream.getAudioTracks().length}`);
                                        }
                                        
                                        // Intentar reproducir
                                        self.remoteAudioElement.play()
                                            .then(() => {
                                                self.log('✅ Audio remoto reproduciéndose (desde listener track)');
                                                self.log(`   Elemento paused: ${self.remoteAudioElement.paused}, volumen: ${self.remoteAudioElement.volume}`);
                                            })
                                            .catch((error) => {
                                                self.log('⚠️ Error al reproducir audio remoto:', error);
                                            });
                                    } else {
                                        self.log('⚠️ Elemento de audio remoto no disponible');
                                    }
                                }
                            };
                            
                            // Usar tanto addEventListener como ontrack para asegurar captura
                            pc.addEventListener('track', handleRemoteTrack);
                            pc.ontrack = handleRemoteTrack;
                            
                            pc._audioTrackListenerAdded = true;
                            this.log('✅ Listener de tracks remotos agregado (addEventListener + ontrack)');
                            this.log(`   PeerConnection estado: ${pc.connectionState}, ICE estado: ${pc.iceConnectionState}`);
                            
                            // Verificar si ya hay tracks remotos
                            if (pc.getReceivers) {
                                const receivers = pc.getReceivers();
                                this.log(`   Receivers existentes al agregar listener: ${receivers.length}`);
                                if (receivers.length > 0) {
                                    this.log('   ⚠️ Ya hay receivers, el track puede haberse agregado antes del listener');
                                }
                            }
                            
                            // Verificar tracks existentes después de un breve delay
                            setTimeout(() => {
                                // Verificar senders (audio local)
                                if (pc.getSenders) {
                                    const senders = pc.getSenders();
                                    self.log(`🔍 Verificando senders (audio local): ${senders.length}`);
                                    senders.forEach((sender, index) => {
                                        const track = sender.track;
                                        if (track && track.kind === 'audio') {
                                            self.log(`   📤 Sender ${index}: ${track.kind}, ID: ${track.id}, enabled: ${track.enabled}, readyState: ${track.readyState}, muted: ${track.muted}`);
                                            
                                            // Asegurar que el track local esté habilitado
                                            if (!track.enabled) {
                                                track.enabled = true;
                                                self.log(`   🔧 Track local ${index} habilitado manualmente`);
                                            }
                                            
                                            // Verificar que el track esté realmente enviando datos
                                            if (track.readyState === 'ended') {
                                                self.log(`   ⚠️ ADVERTENCIA: Track local ${index} está en estado 'ended'`);
                                            }
                                        }
                                    });
                                }
                                
                                // Verificar receivers (audio remoto)
                                if (pc.getReceivers) {
                                    const receivers = pc.getReceivers();
                                    self.log(`🔍 Verificando receivers (audio remoto): ${receivers.length}`);
                                    receivers.forEach((receiver, index) => {
                                        const track = receiver.track;
                                        if (track && track.kind === 'audio') {
                                            self.log(`   📥 Receiver ${index}: ${track.kind}, ID: ${track.id}, enabled: ${track.enabled}, readyState: ${track.readyState}`);
                                            // Asegurar que el track esté habilitado
                                            if (!track.enabled) {
                                                track.enabled = true;
                                                self.log(`   🔧 Track remoto ${index} habilitado manualmente`);
                                            }
                                            
                                            // Si hay un track pero no está conectado, conectarlo
                                            if (self.remoteAudioElement && !self.remoteAudioElement.srcObject) {
                                                const remoteStream = new MediaStream([track]);
                                                self.remoteAudioElement.srcObject = remoteStream;
                                                self.remoteAudioElement.volume = 1.0;
                                                self.remoteAudioElement.muted = false;
                                                self.log('✅ Track existente conectado al elemento de audio');
                                                self.remoteAudioElement.play()
                                                    .then(() => {
                                                        self.log('✅ Audio remoto (track existente) reproduciéndose');
                                                    })
                                                    .catch((error) => {
                                                        self.log('⚠️ Error al reproducir audio remoto (track existente):', error);
                                                    });
                                            }
                                        }
                                    });
                                }
                                
                                // Verificar streams remotos
                                if (pc.getRemoteStreams) {
                                    const remoteStreams = pc.getRemoteStreams();
                                    self.log(`🔍 Verificando remote streams: ${remoteStreams.length}`);
                                    remoteStreams.forEach((stream, index) => {
                                        const audioTracks = stream.getAudioTracks();
                                        self.log(`   Stream ${index}: ${audioTracks.length} track(s) de audio`);
                                        if (audioTracks.length > 0 && self.remoteAudioElement && !self.remoteAudioElement.srcObject) {
                                            self.remoteAudioElement.srcObject = stream;
                                            self.log('✅ Stream remoto conectado al elemento de audio');
                                            self.remoteAudioElement.play()
                                                .then(() => {
                                                    self.log('✅ Audio remoto reproduciéndose (desde stream)');
                                                })
                                                .catch((error) => {
                                                    self.log('⚠️ Error al reproducir audio remoto (desde stream):', error);
                                                });
                                        }
                                    });
                                }
                            }, 500);
                        }

                        pc._sdpInterceptorPatched = true;
                        this.log('✅ setRemoteDescription interceptado exitosamente');
                        return true;
                    } else if (pc && pc._sdpInterceptorPatched) {
                        this.log('✅ setRemoteDescription ya está interceptado');
                        return true;
                    }
                }
                return false;
            };

            // Intentar interceptar inmediatamente (puede que aún no esté disponible)
            if (!interceptSetRemoteDescription()) {
                // Si no está disponible, intentar después de un delay
                setTimeout(() => {
                    if (!interceptSetRemoteDescription()) {
                        this.log('⚠️ SessionDescriptionHandler no disponible aún, se intentará en onAccept');
                    }
                }, 500);
            }

            // SOLUCIÓN CRÍTICA: Interceptar getUserMedia globalmente ANTES de la llamada
            // Esto captura TODAS las llamadas que hace SIP.js y las reemplaza con nuestro stream
            this.log('🎤 ===== INTERCEPTANDO getUserMedia GLOBALMENTE =====');
            
            try {
                const audioConstraints = await this._determineAudioConstraints();
                this.log('Constraints que usaremos:', JSON.stringify(audioConstraints));
                
                // Adquirir el stream ANTES de llamar a invite()
                this.preAcquiredStream = await this._mediaStreamFactory(audioConstraints);
                this.log('✅ MediaStream adquirido exitosamente antes de la llamada');
                
                // Guardar el stream
                this.lastMediaStream = this.preAcquiredStream;
                
                // INTERCEPTAR getUserMedia globalmente
                // Guardar la referencia original solo la primera vez
                if (!this.originalGetUserMedia) {
                    this.originalGetUserMedia = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
                }
                
                const self = this;
                
                navigator.mediaDevices.getUserMedia = function(constraints) {
                    self.log('🚨 ===== getUserMedia INTERCEPTADO =====');
                    self.log('   Constraints recibidas:', JSON.stringify(constraints));
                    
                    // Si tenemos un stream pre-adquirido activo, retornarlo
                    if (self.preAcquiredStream && self.preAcquiredStream.active) {
                        self.log('✅ Retornando stream pre-adquirido en lugar de adquirir nuevo');
                        const audioTracks = self.preAcquiredStream.getAudioTracks();
                        self.log(`   Stream tiene ${audioTracks.length} track(s) de audio`);
                        if (audioTracks.length > 0) {
                            self.log(`   Track ID: ${audioTracks[0].id}, Label: ${audioTracks[0].label}`);
                        }
                        return Promise.resolve(self.preAcquiredStream);
                    }
                    
                    // Si no hay stream pre-adquirido, usar el método original
                    self.log('⚠️ No hay stream pre-adquirido, usando método original');
                    return self.originalGetUserMedia(constraints);
                };
                
                this.log('✅ getUserMedia interceptado globalmente');
                
            } catch (streamError) {
                this.log('❌ Error adquiriendo MediaStream:', streamError);
                throw new Error(`No se pudo acceder al micrófono: ${streamError.message}`);
            }

            inviter.stateChange.addListener((newState) => {
                this.log('Estado de llamada:', newState);
                
                const stateStr = String(newState);
                
                if (stateStr === 'Established' || stateStr === '4' || newState === 'Established') {
                    this.onCallStarted();
                    this.startCallTimer();
                } else if (stateStr === 'Terminated' || stateStr === '5' || newState === 'Terminated') {
                    this.onCallEnded();
                } else if (stateStr === 'Initial' || stateStr === '0') {
                    this.updateCallInfo(this.currentNumber, 'Iniciando...');
                } else if (stateStr === 'Sent' || stateStr === '1') {
                    this.updateCallInfo(this.currentNumber, 'Llamando...');
                } else if (stateStr === 'Establishing' || stateStr === '3') {
                    this.updateCallInfo(this.currentNumber, 'Conectando...');
                }
            });
            
            const requestOptions = {
                requestDelegate: {
                    onAccept: () => {
                        this.log('Llamada aceptada por destino');
                        const self = this;
                        
                        // FUNCIÓN PARA CONECTAR AUDIO REMOTO Y VERIFICAR AUDIO LOCAL
                        const connectRemoteAudio = async (pc) => {
                            if (!pc) return;
                            
                            this.log('🔍 Verificando tracks en onAccept...');
                            this.log(`   Estado ICE: ${pc.iceConnectionState}`);
                            this.log(`   Estado conexión: ${pc.connectionState}`);
                            
                            // OBTENER ESTADÍSTICAS RTP PARA VERIFICAR SI HAY DATOS LLEGANDO
                            try {
                                const stats = await pc.getStats();
                                let audioBytesReceived = 0;
                                let audioPacketsReceived = 0;
                                let audioBytesSent = 0;
                                let audioPacketsSent = 0;
                                let hasInboundRtp = false;
                                
                                stats.forEach((report) => {
                                    if (report.type === 'inbound-rtp' && report.mediaType === 'audio') {
                                        hasInboundRtp = true;
                                        audioBytesReceived = report.bytesReceived || 0;
                                        audioPacketsReceived = report.packetsReceived || 0;
                                        this.log(`   📊 RTP Remoto: ${audioBytesReceived} bytes recibidos, ${audioPacketsReceived} paquetes recibidos`);
                                        this.log(`   📊 Jitter: ${report.jitter || 'N/A'}, PacketsLost: ${report.packetsLost || 0}`);
                                        if (report.codecId) {
                                            this.log(`   📊 Codec ID: ${report.codecId}`);
                                        }
                                    }
                                    if (report.type === 'outbound-rtp' && report.mediaType === 'audio') {
                                        audioBytesSent = report.bytesSent || 0;
                                        audioPacketsSent = report.packetsSent || 0;
                                        this.log(`   📊 RTP Local: ${audioBytesSent} bytes enviados, ${audioPacketsSent} paquetes enviados`);
                                    }
                                });
                                
                                if (!hasInboundRtp) {
                                    this.log('⚠️ ADVERTENCIA: No se encontró reporte inbound-rtp. Esto puede ser normal al inicio de la llamada.');
                                } else if (audioBytesReceived === 0 && audioPacketsReceived === 0) {
                                    this.log('❌ PROBLEMA CRÍTICO: No se están recibiendo paquetes RTP de audio.');
                                    this.log('   Posibles causas:');
                                    this.log('   1. Firewall bloqueando puertos RTP (10000-20000 UDP)');
                                    this.log('   2. NAT simétrico que requiere TURN');
                                    this.log('   3. Problema de conectividad de red');
                                    this.log('   SOLUCIÓN: Configurar servidor TURN o verificar firewall');
                                } else {
                                    this.log(`✅ Se están recibiendo datos de audio: ${audioBytesReceived} bytes, ${audioPacketsReceived} paquetes`);
                                }
                            } catch (error) {
                                this.log('⚠️ No se pudieron obtener estadísticas RTP:', error);
                            }
                            
                            // PRIMERO: Verificar audio local (senders)
                            if (pc.getSenders) {
                                const senders = pc.getSenders();
                                this.log(`   📤 Senders (audio local): ${senders.length}`);
                                senders.forEach((sender, index) => {
                                    const track = sender.track;
                                    if (track && track.kind === 'audio') {
                                        this.log(`   📤 Sender ${index}: enabled: ${track.enabled}, readyState: ${track.readyState}, muted: ${track.muted}`);
                                        
                                        // Asegurar que el track local esté habilitado
                                        if (!track.enabled) {
                                            track.enabled = true;
                                            this.log(`   🔧 Track local ${index} habilitado manualmente`);
                                        }
                                        
                                        if (track.muted) {
                                            track.muted = false;
                                            this.log(`   🔧 Track local ${index} des-silenciado manualmente`);
                                        }
                                        
                                        if (track.readyState === 'ended') {
                                            this.log(`   ⚠️ ADVERTENCIA CRÍTICA: Track local ${index} está en estado 'ended' - no se está enviando audio`);
                                        }
                                    }
                                });
                            }
                            
                            // Verificar receivers inmediatamente
                            if (pc.getReceivers) {
                                const receivers = pc.getReceivers();
                                this.log(`   📥 Receivers encontrados: ${receivers.length}`);
                                
                                receivers.forEach((receiver, index) => {
                                    const track = receiver.track;
                                    if (track && track.kind === 'audio') {
                                        this.log(`   📻 Receiver ${index}: audio track encontrado`);
                                        this.log(`      Track ID: ${track.id}, enabled: ${track.enabled}, readyState: ${track.readyState}`);
                                        
                                        // Asegurar que el track esté habilitado y no silenciado
                                        if (!track.enabled) {
                                            track.enabled = true;
                                            this.log('   🔧 Track habilitado manualmente');
                                        }
                                        
                                        // Conectar al elemento de audio
                                        if (self.remoteAudioElement) {
                                            // Asegurar que el elemento de audio tenga volumen y no esté silenciado
                                            self.remoteAudioElement.volume = 1.0;
                                            self.remoteAudioElement.muted = false;
                                            
                                            // Intentar usar el stream remoto directamente del PeerConnection primero
                                            let streamToUse = null;
                                            if (pc.getRemoteStreams && pc.getRemoteStreams().length > 0) {
                                                streamToUse = pc.getRemoteStreams()[0];
                                                this.log('   ✅ Usando stream remoto directamente del PeerConnection');
                                            } else {
                                                // Si no hay stream remoto, crear uno nuevo con el track
                                                streamToUse = new MediaStream([track]);
                                                this.log('   ✅ Creando nuevo MediaStream con el track');
                                            }
                                            
                                            if (!self.remoteAudioElement.srcObject || 
                                                self.remoteAudioElement.srcObject.getAudioTracks().length === 0) {
                                                self.remoteAudioElement.srcObject = streamToUse;
                                                this.log('✅ Audio remoto conectado al elemento de audio (desde onAccept)');
                                                this.log(`   Elemento audio volumen: ${self.remoteAudioElement.volume}, muted: ${self.remoteAudioElement.muted}`);
                                                this.log(`   Stream activo: ${streamToUse.active}, tracks: ${streamToUse.getAudioTracks().length}`);
                                                
                                                // Verificar estado del track antes de reproducir
                                                const audioTracks = streamToUse.getAudioTracks();
                                                audioTracks.forEach((audioTrack, idx) => {
                                                    this.log(`   Track ${idx} antes de play - enabled: ${audioTrack.enabled}, muted: ${audioTrack.muted}, readyState: ${audioTrack.readyState}`);
                                                    if (!audioTrack.enabled) {
                                                        audioTrack.enabled = true;
                                                        this.log(`   🔧 Track ${idx} habilitado manualmente`);
                                                    }
                                                });
                                                
                                                // Forzar reproducción
                                                const tryPlay = () => {
                                                    self.remoteAudioElement.play()
                                                        .then(() => {
                                                            this.log('✅ Audio remoto reproduciéndose (desde onAccept)');
                                                            this.log(`   Elemento paused: ${self.remoteAudioElement.paused}, currentTime: ${self.remoteAudioElement.currentTime}`);
                                                            this.log(`   Elemento readyState: ${self.remoteAudioElement.readyState}`);
                                                            
                                                            // Verificar después de un breve delay y verificar estadísticas RTP
                                                            setTimeout(async () => {
                                                                this.log(`   Estado después de 500ms - paused: ${self.remoteAudioElement.paused}, readyState: ${self.remoteAudioElement.readyState}`);
                                                                this.log(`   Elemento currentTime: ${self.remoteAudioElement.currentTime}`);
                                                                
                                                                // Verificar tracks del stream
                                                                const currentTracks = self.remoteAudioElement.srcObject.getAudioTracks();
                                                                currentTracks.forEach((audioTrack, idx) => {
                                                                    this.log(`   Track ${idx} después de play - enabled: ${audioTrack.enabled}, muted: ${audioTrack.muted}, readyState: ${audioTrack.readyState}`);
                                                                });
                                                                
                                                                // VERIFICAR ESTADÍSTICAS RTP PARA DIAGNÓSTICO
                                                                try {
                                                                    const stats = await pc.getStats();
                                                                    let totalBytesReceived = 0;
                                                                    let totalPacketsReceived = 0;
                                                                    stats.forEach((report) => {
                                                                        if (report.type === 'inbound-rtp' && report.mediaType === 'audio') {
                                                                            totalBytesReceived += (report.bytesReceived || 0);
                                                                            totalPacketsReceived += (report.packetsReceived || 0);
                                                                        }
                                                                    });
                                                                    
                                                                    if (totalBytesReceived === 0 && totalPacketsReceived === 0) {
                                                                        this.log('❌ PROBLEMA DETECTADO: El elemento dice "playing" pero NO hay datos RTP llegando.');
                                                                        this.log('   Esto confirma que el problema es de conectividad de red, NO del elemento de audio.');
                                                                        this.log('   SOLUCIÓN RECOMENDADA: Configurar servidor TURN o verificar firewall/port forwarding.');
                                                                    } else {
                                                                        this.log(`✅ Datos de audio confirmados: ${totalBytesReceived} bytes, ${totalPacketsReceived} paquetes recibidos`);
                                                                        if (self.remoteAudioElement.currentTime === 0) {
                                                                            this.log('⚠️ Hay datos RTP pero el tiempo no avanza. Puede ser un problema del codec o del elemento de audio.');
                                                                        }
                                                                    }
                                                                } catch (err) {
                                                                    this.log('⚠️ Error al verificar estadísticas:', err);
                                                                }
                                                                
                                                                // Si está pausado, intentar reproducir de nuevo
                                                                if (self.remoteAudioElement.paused) {
                                                                    this.log('⚠️ Elemento de audio está pausado, intentando reproducir de nuevo...');
                                                                    tryPlay();
                                                                } else if (self.remoteAudioElement.currentTime === 0 && self.remoteAudioElement.readyState >= 2) {
                                                                    // Si el tiempo no avanza, puede haber un problema
                                                                    this.log('⚠️ El tiempo del audio no avanza, puede haber un problema de reproducción');
                                                                }
                                                            }, 2000); // Aumentar a 2 segundos para dar tiempo a que lleguen datos
                                                        })
                                                        .catch((error) => {
                                                            this.log('⚠️ Error al reproducir audio remoto (desde onAccept):', error);
                                                            this.log(`   Error details: ${error.name} - ${error.message}`);
                                                            // Intentar de nuevo después de un delay
                                                            setTimeout(() => {
                                                                this.log('🔄 Reintentando reproducir audio remoto...');
                                                                tryPlay();
                                                            }, 1000);
                                                        });
                                                };
                                                
                                                tryPlay();
                                            } else {
                                                // Agregar track al stream existente si no está presente
                                                const existingStream = self.remoteAudioElement.srcObject;
                                                const existingTracks = existingStream.getAudioTracks();
                                                if (!existingTracks.find(t => t.id === track.id)) {
                                                    existingStream.addTrack(track);
                                                    this.log('✅ Track agregado al stream existente (desde onAccept)');
                                                    
                                                    // Asegurar que el elemento siga reproduciéndose
                                                    if (self.remoteAudioElement.paused) {
                                                        self.remoteAudioElement.play()
                                                            .then(() => {
                                                                this.log('✅ Audio remoto reproduciéndose después de agregar track');
                                                            })
                                                            .catch((error) => {
                                                                this.log('⚠️ Error al reproducir después de agregar track:', error);
                                                            });
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                            
                            // Verificar getLocalStreams (audio local)
                            if (pc.getLocalStreams) {
                                const localStreams = pc.getLocalStreams();
                                this.log(`   📤 Local streams encontrados: ${localStreams.length}`);
                                localStreams.forEach((stream, index) => {
                                    const audioTracks = stream.getAudioTracks();
                                    this.log(`   Local Stream ${index}: ${audioTracks.length} track(s) de audio`);
                                    audioTracks.forEach((track, trackIndex) => {
                                        this.log(`      Track ${trackIndex}: enabled: ${track.enabled}, readyState: ${track.readyState}, muted: ${track.muted}`);
                                        if (!track.enabled) {
                                            track.enabled = true;
                                            this.log(`      🔧 Track local ${trackIndex} habilitado manualmente`);
                                        }
                                    });
                                });
                            }
                            
                            // Verificar getRemoteStreams
                            if (pc.getRemoteStreams) {
                                const remoteStreams = pc.getRemoteStreams();
                                this.log(`   📡 Remote streams encontrados: ${remoteStreams.length}`);
                                remoteStreams.forEach((stream, index) => {
                                    const audioTracks = stream.getAudioTracks();
                                    this.log(`   Remote Stream ${index}: ${audioTracks.length} track(s) de audio`);
                                    if (audioTracks.length > 0 && self.remoteAudioElement && !self.remoteAudioElement.srcObject) {
                                        self.remoteAudioElement.srcObject = stream;
                                        self.remoteAudioElement.volume = 1.0;
                                        self.remoteAudioElement.muted = false;
                                        this.log('✅ Stream remoto conectado al elemento de audio (desde onAccept)');
                                        self.remoteAudioElement.play()
                                            .then(() => {
                                                this.log('✅ Audio remoto reproduciéndose (stream desde onAccept)');
                                            })
                                            .catch((error) => {
                                                this.log('⚠️ Error al reproducir audio remoto (stream desde onAccept):', error);
                                            });
                                    }
                                });
                            }
                            
                            // Escuchar cambios en el estado de conexión ICE
                            if (!pc._iceListenerAdded) {
                                pc.addEventListener('iceconnectionstatechange', async () => {
                                    this.log(`🔗 ICE connection state cambió a: ${pc.iceConnectionState}`);
                                    if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                                        this.log('🔗 ICE conectado, verificando tracks remotos nuevamente...');
                                        await connectRemoteAudio(pc);
                                    }
                                });
                                pc._iceListenerAdded = true;
                            }
                        };
                        
                        // Verificar inmediatamente y después de un breve delay
                        setTimeout(async () => {
                            if (inviter.sessionDescriptionHandler) {
                                const sdh = inviter.sessionDescriptionHandler;
                                const pc = sdh.peerConnection;
                                
                                if (pc) {
                                    await connectRemoteAudio(pc);
                                    
                                    // También verificar después de más tiempo por si acaso
                                    setTimeout(async () => {
                                        await connectRemoteAudio(pc);
                                    }, 1000);
                                }
                            }
                        }, 200);
                        
                        this.updateCallInfo(this.currentNumber, 'Conectado');
                    },
                    onReject: (response) => {
                        this.log('Llamada rechazada:', response);
                        this.onCallEnded();
                        
                        let razon = 'El destino no contestó';
                        if (response && response.message && response.message.statusCode) {
                            const codigo = response.message.statusCode;
                            if (codigo === 486) razon = 'Ocupado';
                            else if (codigo === 487) razon = 'Cancelada';
                            else if (codigo === 408) razon = 'No hay respuesta';
                            else if (codigo === 480) razon = 'Temporalmente no disponible';
                            else if (codigo === 404) razon = 'Número no encontrado';
                        }
                        
                        this.showNotification('Llamada rechazada', razon, 'error');
                    },
                    onTrying: () => {
                        this.log('Intentando conectar...');
                        this.updateCallInfo(this.currentNumber, 'Conectando...');
                    }
                },
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    // Configuración ICE para esta llamada específica
                    iceServers: this._getIceServers(),
                    // Configuración RTC para WebRTC
                    rtcConfiguration: {
                        iceServers: this._getIceServers(),
                        iceTransportPolicy: 'all', // Permitir tanto STUN como TURN
                        bundlePolicy: 'max-bundle', // Agrupar audio/video en un solo transporte
                        rtcpMuxPolicy: 'require' // Requerir RTCP multiplexing
                    },
                    // Pasar mediaStreamFactory que retorna el stream pre-adquirido
                    mediaStreamFactory: async () => {
                        this.log('🎤 ===== mediaStreamFactory LLAMADA POR SIP.js =====');
                        if (preAcquiredStream && preAcquiredStream.active) {
                            this.log('✅ Retornando stream pre-adquirido activo');
                            const audioTracks = preAcquiredStream.getAudioTracks();
                            this.log(`   Stream tiene ${audioTracks.length} track(s) de audio`);
                            return preAcquiredStream;
                        }
                        // Si el stream no está disponible, intentar adquirirlo de nuevo
                        this.log('⚠️ Stream pre-adquirido no disponible, adquiriendo nuevo...');
                        return await this._mediaStreamFactory();
                    }
                }
            };

            this.log('📞 Enviando INVITE con MediaStream pre-adquirido');
            
            this.log('Enviando INVITE...');
            
            // Interceptar setRemoteDescription justo después de que se crea el PeerConnection
            // El SessionDescriptionHandler se crea cuando se llama a invite()
            const invitePromise = inviter.invite(requestOptions);
            
            // Interceptar después de que invite() se ejecuta (el SDH ya está creado)
            // Usar múltiples intentos para asegurar que se intercepte
            let interceptAttempts = 0;
            const maxInterceptAttempts = 10;
            
            const tryIntercept = () => {
                interceptAttempts++;
                
                if (inviter.sessionDescriptionHandler) {
                    const sdh = inviter.sessionDescriptionHandler;
                    const pc = sdh.peerConnection;

                    if (pc && !pc._sdpInterceptorPatched) {
                        this.log(`🔧 Interceptando setRemoteDescription (intento ${interceptAttempts})...`);
                        
                        // MONITOREAR CANDIDATOS ICE PARA VERIFICAR SI SE USAN RELAY
                        if (!pc._iceCandidateListenerAdded) {
                            pc.addEventListener('icecandidate', (event) => {
                                if (event.candidate) {
                                    const candidate = event.candidate.candidate;
                                    if (candidate.includes('typ relay')) {
                                        this.log(`✅ CANDIDATO RELAY DETECTADO: ${candidate}`);
                                        this.log('   ✅ El servidor TURN está funcionando correctamente');
                                    } else if (candidate.includes('typ srflx')) {
                                        this.log(`📡 Candidato srflx (STUN): ${candidate.substring(0, 100)}...`);
                                    } else if (candidate.includes('typ host')) {
                                        // No loguear host, son demasiados
                                    }
                                } else {
                                    this.log('🔍 ICE candidate gathering completado');
                                }
                            });
                            
                            // Monitorear qué candidato se está usando finalmente
                            pc.addEventListener('iceconnectionstatechange', () => {
                                if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                                    this.log(`🔗 ICE Connection State: ${pc.iceConnectionState}`);
                                    
                                    // Obtener el par de candidatos seleccionado
                                    pc.getStats().then((stats) => {
                                        stats.forEach((report) => {
                                            if (report.type === 'candidate-pair' && report.state === 'succeeded') {
                                                const localCandidate = report.localCandidateId;
                                                const remoteCandidate = report.remoteCandidateId;
                                                
                                                // Buscar los detalles de los candidatos
                                                stats.forEach((candidateReport) => {
                                                    if (candidateReport.id === localCandidate) {
                                                        this.log(`📊 Candidato local seleccionado: ${candidateReport.candidateType} - ${candidateReport.ip}:${candidateReport.port}`);
                                                        if (candidateReport.candidateType === 'relay') {
                                                            this.log('   ✅ USANDO TURN (relay) - Esto es correcto para resolver problemas de red');
                                                        } else if (candidateReport.candidateType === 'srflx') {
                                                            this.log('   ⚠️ Usando STUN (srflx) - Puede no funcionar si hay firewall estricto');
                                                        } else {
                                                            this.log('   ⚠️ Usando conexión directa (host) - Puede fallar si hay NAT');
                                                        }
                                                    }
                                                    if (candidateReport.id === remoteCandidate) {
                                                        this.log(`📊 Candidato remoto seleccionado: ${candidateReport.candidateType} - ${candidateReport.ip}:${candidateReport.port}`);
                                                    }
                                                });
                                            }
                                        });
                                    }).catch(err => {
                                        this.log('⚠️ Error al obtener estadísticas de candidatos:', err);
                                    });
                                }
                            });
                            
                            pc._iceCandidateListenerAdded = true;
                        }
                        
                        const originalSetRemoteDescription = pc.setRemoteDescription.bind(pc);
                        const self = this;

                        pc.setRemoteDescription = async function(description) {
                            self.log('🔧 ===== setRemoteDescription INTERCEPTADO =====');
                            
                            if (description && description.sdp) {
                                self.log('📝 SDP remoto recibido, verificando rtcp-mux...');
                                self.log('   Tipo:', description.type);
                                self.log('   SDP contiene rtcp-mux:', description.sdp.includes('a=rtcp-mux'));
                                
                                // VERIFICAR CODECS NEGOCIADOS
                                if (typeof self._verifyCodecs === 'function') {
                                    self._verifyCodecs(description.sdp, 'remoto');
                                } else {
                                    self.log('⚠️ _verifyCodecs no está disponible');
                                }
                                
                                // Parchear el SDP
                                const patchedSDP = self._patchRemoteSDP(description.sdp);
                                
                                if (patchedSDP !== description.sdp) {
                                    self.log('✅ SDP remoto parcheado, aplicando cambios...');
                                    const patchedDescription = new RTCSessionDescription({
                                        type: description.type,
                                        sdp: patchedSDP
                                    });
                                    return await originalSetRemoteDescription(patchedDescription);
                                } else {
                                    self.log('✅ SDP remoto ya tiene rtcp-mux, no se necesita parchear');
                                }
                            }
                            
                            return await originalSetRemoteDescription(description);
                        };

                        pc._sdpInterceptorPatched = true;
                        this.log('✅ setRemoteDescription interceptado exitosamente');
                        return true;
                    } else if (pc && pc._sdpInterceptorPatched) {
                        this.log('✅ setRemoteDescription ya está interceptado');
                        return true;
                    }
                }
                
                return false;
            };
            
            // Intentar interceptar inmediatamente
            if (!tryIntercept() && interceptAttempts < maxInterceptAttempts) {
                // Si no está disponible, intentar múltiples veces
                const interceptInterval = setInterval(() => {
                    if (tryIntercept() || interceptAttempts >= maxInterceptAttempts) {
                        clearInterval(interceptInterval);
                        if (interceptAttempts >= maxInterceptAttempts) {
                            this.log('⚠️ No se pudo interceptar setRemoteDescription después de múltiples intentos');
                        }
                    }
                }, 100);
            }
            
            invitePromise
                .then(() => {
                    this.log('✅ INVITE enviado exitosamente');
                    this.updateCallInfo(this.currentNumber, 'Llamando...');
                    this.showNotification('Llamada', `Llamando a ${this.currentNumber}`, 'info');
                })
                .catch((error) => {
                    this.log('❌ Error al enviar INVITE:', error);
                    
                    // Mensaje de error más descriptivo
                    let errorMessage = error.message || 'No se pudo realizar la llamada';
                    
                    if (error.message && error.message.includes('NotFoundError')) {
                        errorMessage = 'No se encontró ningún micrófono. Por favor, conecte un micrófono e intente nuevamente.';
                    } else if (error.message && error.message.includes('NotAllowedError')) {
                        errorMessage = 'Permiso de micrófono denegado. Por favor, permita el acceso al micrófono en la configuración del navegador.';
                    } else if (error.message && error.message.includes('Unable to acquire streams')) {
                        errorMessage = 'No se pudo acceder al micrófono. Verifique que esté conectado y que tenga permisos.';
                    }
                    
                    this.showNotification('Error de llamada', errorMessage, 'error');
                    this.onCallEnded();
                });
        } catch (error) {
            this.log('❌ Error en makeCall:', error);
            this.showNotification('Error', `Error al realizar llamada: ${error.message}`, 'error');
            this.onCallEnded();
        }
    }
    
    /**
     * Métodos auxiliares para mantener funcionalidad completa
     */
    
    initializeSIPjs() {
        this.log('Inicializando SIP.js...');
        this.updateStatus('connecting', 'Conectando...');
        
        try {
            if (typeof SIP === 'undefined') {
                throw new Error('SIP.js no está cargado');
            }
            
            if (typeof SIP.UserAgent === 'undefined') {
                throw new Error('SIP.UserAgent no está disponible');
            }
            
            // Validar configuración
            if (!this.config.extension || !this.config.sip_domain) {
                throw new Error('Configuración incompleta');
            }
            
            const userUriString = `sip:${this.config.extension}@${this.config.sip_domain}`;
            let userUri = SIP.UserAgent.makeURI(userUriString);

            if (userUri) {
                userUri = this._patchUriClone(userUri);
                this.log('✅ URI del usuario parchado correctamente');
            } else {
                this.log('⚠️ No se pudo crear objeto URI, usando string');
                userUri = userUriString;
            }

            // Verificar que mediaStreamFactory esté disponible antes de crear UserAgent
            this.log('🔍 Verificando mediaStreamFactory antes de crear UserAgent...');
            this.log('   Tipo de mediaStreamFactory:', typeof this.mediaStreamFactory);
            this.log('   Es función:', typeof this.mediaStreamFactory === 'function');
            
            // Configurar servidores ICE (STUN/TURN) - CRÍTICO para transporte de audio
            const iceServers = this._getIceServers();
            
            const factoryOptions = {
                mediaStreamFactory: this.mediaStreamFactory,
                // Configuración ICE para WebRTC - esencial para NAT traversal
                iceServers: iceServers,
                // Configuración de codecs preferidos (PCMU/PCMA para compatibilidad con Asterisk)
                rtcConfiguration: {
                    iceServers: iceServers,
                    iceTransportPolicy: 'all', // Permitir tanto STUN como TURN
                    bundlePolicy: 'max-bundle', // Agrupar audio/video en un solo transporte
                    rtcpMuxPolicy: 'require' // Requerir RTCP multiplexing (ya lo parcheamos en SDP)
                }
            };
            
            this.log('   Opciones de factory que se pasarán:', {
                tieneMediaStreamFactory: typeof factoryOptions.mediaStreamFactory === 'function',
                tieneIceServers: Array.isArray(factoryOptions.iceServers) && factoryOptions.iceServers.length > 0,
                numeroIceServers: factoryOptions.iceServers.length
            });
            
            this.userAgent = new SIP.UserAgent({
                uri: userUri,
                authorizationUsername: this.config.extension,
                authorizationPassword: this.config.password,
                transportOptions: {
                    server: this.config.wss_server
                },
                sessionDescriptionHandlerFactoryOptions: factoryOptions,
                delegate: {
                    onInvite: (invitation) => {
                        this.handleIncomingCall(invitation);
                    }
                }
            });
            
            this.userAgent.start()
                .then(() => {
                    this.log('✔ Registro SIP exitoso');
                    this.isRegistered = true;
                    this.updateStatus('connected', 'En línea');
                })
                .catch((error) => {
                    this.log('❌ Error al registrar:', error);
                    this.updateStatus('disconnected', 'Error de conexión');
                });
                
        } catch (error) {
            this.log('Error al inicializar SIP.js:', error);
            this.updateStatus('disconnected', 'Error de conexión');
        }
    }
    
    handleIncomingCall(invitation) {
        this.log('Llamada entrante de:', invitation.remoteIdentity?.uri?.user);
        const caller = invitation.remoteIdentity?.uri?.user || 'Desconocido';
        this.showNotification('Llamada entrante', `De: ${caller}`);
        
        this.currentSession = invitation;
        
        setTimeout(() => {
            invitation.accept()
                .then(() => {
                    this.log('Llamada aceptada');
                    this.onCallStarted();
                })
                .catch((error) => {
                    this.log('Error al aceptar llamada:', error);
                });
        }, 1000);
        
        invitation.stateChange.addListener((newState) => {
            const stateStr = String(newState);
            if (stateStr === 'Established' || stateStr === '4') {
                this.onCallStarted();
                this.startCallTimer();
            } else if (stateStr === 'Terminated' || stateStr === '5') {
                this.onCallEnded();
            }
        });
    }
    
    // Resto de métodos auxiliares...
    hangup() {
        if (this.currentSession) {
            this.log('Colgando llamada...');
            this.currentSession.bye();
            this.currentSession = null;
        }
        this._releaseLastMediaStream();
    }
    
    onCallStarted() {
        this.updateStatus('in-call', 'En llamada');
        document.getElementById('call-info').classList.add('active');
        document.getElementById('call-controls').style.display = 'grid';
        document.getElementById('btn-call').style.display = 'none';
        document.getElementById('btn-hangup').style.display = 'flex';
        this.updateCallInfo(this.currentNumber, 'Conectado');
    }
    
    onCallEnded() {
        this.stopCallTimer();
        this.updateStatus('connected', 'En línea');
        document.getElementById('call-info').classList.remove('active');
        document.getElementById('call-controls').style.display = 'none';
        document.getElementById('btn-call').style.display = 'flex';
        document.getElementById('btn-hangup').style.display = 'none';
        this.currentSession = null;
        this.currentNumber = '';
        this.updateNumberDisplay();
        this._releaseLastMediaStream();
        
        // Limpiar stream pre-adquirido después de la llamada
        this.preAcquiredStream = null;
        
        // Limpiar audio remoto
        if (this.remoteAudioElement) {
            this.remoteAudioElement.srcObject = null;
            this.remoteAudioElement.pause();
            this.log('🔇 Audio remoto limpiado');
        }
    }
    
    startCallTimer() {
        this.callDuration = 0;
        this.callTimer = setInterval(() => {
            this.callDuration++;
            this.updateCallDuration();
        }, 1000);
    }
    
    stopCallTimer() {
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
        this.callDuration = 0;
    }
    
    updateCallDuration() {
        const minutes = Math.floor(this.callDuration / 60);
        const seconds = this.callDuration % 60;
        const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        document.getElementById('call-duration').textContent = formatted;
    }
    
    updateCallInfo(number, status) {
        document.getElementById('call-number').textContent = number;
        document.getElementById('call-status').textContent = status;
    }
    
    updateStatus(status, text) {
        const dot = document.getElementById('status-dot');
        dot.className = `status-dot ${status}`;
        document.getElementById('status-text').textContent = text;
    }
    
    // Métodos de UI (createPanel, attachEventListeners, etc.) se mantienen iguales...
    createPanel() {
        let panel = document.getElementById('webrtc-softphone');
        if (panel) {
            this.panel = panel;
            return;
        }
        
        panel = document.createElement('div');
        panel.className = 'webrtc-softphone-panel hidden';
        panel.id = 'webrtc-softphone';
        
        panel.innerHTML = `
            <div class="softphone-header">
                <h3><i class="fas fa-headset"></i> <span class="header-title">Softphone</span></h3>
                <div class="softphone-header-actions">
                    <button class="softphone-btn-icon" data-action="toggleCompact" title="Modo compacto">
                        <i class="fas fa-compress-alt"></i>
                    </button>
                    <button class="softphone-btn-icon" data-action="toggle" title="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="softphone-body">
                <div class="softphone-status">
                    <div class="status-indicator">
                        <span class="status-dot disconnected" id="status-dot"></span>
                        <span id="status-text">Desconectado</span>
                    </div>
                </div>
                
                <div class="call-info" id="call-info">
                    <div class="call-info-number" id="call-number">+57 300 1234567</div>
                    <div class="call-info-duration" id="call-duration">00:00</div>
                    <div class="call-info-status" id="call-status">En llamada...</div>
                </div>
                
                <div class="number-input-container">
                    <div class="number-display" id="number-display"></div>
                </div>
                
                <div class="dialpad">
                    <button class="dialpad-btn" data-digit="1">1</button>
                    <button class="dialpad-btn" data-digit="2">2 <span class="dialpad-btn-letter">ABC</span></button>
                    <button class="dialpad-btn" data-digit="3">3 <span class="dialpad-btn-letter">DEF</span></button>
                    <button class="dialpad-btn" data-digit="4">4 <span class="dialpad-btn-letter">GHI</span></button>
                    <button class="dialpad-btn" data-digit="5">5 <span class="dialpad-btn-letter">JKL</span></button>
                    <button class="dialpad-btn" data-digit="6">6 <span class="dialpad-btn-letter">MNO</span></button>
                    <button class="dialpad-btn" data-digit="7">7 <span class="dialpad-btn-letter">PQRS</span></button>
                    <button class="dialpad-btn" data-digit="8">8 <span class="dialpad-btn-letter">TUV</span></button>
                    <button class="dialpad-btn" data-digit="9">9 <span class="dialpad-btn-letter">WXYZ</span></button>
                    <button class="dialpad-btn" data-digit="*">*</button>
                    <button class="dialpad-btn" data-digit="0">0 <span class="dialpad-btn-letter">+</span></button>
                    <button class="dialpad-btn" data-digit="#">#</button>
                </div>
                
                <div class="action-buttons">
                    <button class="action-btn delete-btn" data-action="deleteDigit">
                        <i class="fas fa-backspace"></i>
                    </button>
                    <button class="action-btn call-btn" id="btn-call" data-action="makeCall">
                        <i class="fas fa-phone"></i> Llamar
                    </button>
                    <button class="action-btn hangup-btn" id="btn-hangup" data-action="hangup" style="display: none;">
                        <i class="fas fa-phone-slash"></i> Colgar
                    </button>
                </div>
                
                <div class="call-controls" id="call-controls" style="display: none;">
                    <button class="control-btn" id="btn-mute" data-action="toggleMute">
                        <i class="fas fa-microphone"></i> Silenciar
                    </button>
                    <button class="control-btn" id="btn-hold" data-action="toggleHold">
                        <i class="fas fa-pause"></i> Pausar
                    </button>
                    <button class="control-btn transfer-btn-main" data-action="showTransferModal">
                        <i class="fas fa-exchange-alt"></i> Transferir
                    </button>
                    <button class="control-btn" data-action="toggleDTMF">
                        <i class="fas fa-th"></i> DTMF
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(panel);
        this.panel = panel;
    }
    
    attachEventListeners() {
        this.panel.addEventListener('click', (e) => {
            const target = e.target.closest('button');
            if (!target) return;
            
            if (target.classList.contains('dialpad-btn') && target.hasAttribute('data-digit')) {
                const digit = target.getAttribute('data-digit');
                this.addDigit(digit);
                return;
            }
            
            if (target.hasAttribute('data-action')) {
                const action = target.getAttribute('data-action');
                e.preventDefault();
                e.stopPropagation();
                
                if (typeof this[action] === 'function') {
                    this[action]();
                }
            }
        });

        document.addEventListener('keydown', this.handleKeyPress);
    }
    
    addDigit(digit) {
        this.currentNumber += digit;
        this.updateNumberDisplay();
    }
    
    deleteDigit() {
        this.currentNumber = this.currentNumber.slice(0, -1);
        this.updateNumberDisplay();
    }
    
    updateNumberDisplay() {
        document.getElementById('number-display').textContent = this.currentNumber;
    }

    handleKeyPress(event) {
        if (!this.panel || this.panel.classList.contains('hidden')) {
            return;
        }

        const target = event.target;
        const tag = target && target.tagName ? target.tagName.toLowerCase() : '';

        if (tag === 'input' || tag === 'textarea' || target.isContentEditable) {
            return;
        }

        const key = event.key;

        const isDigit = /^[0-9]$/.test(key);
        const isStar = key === '*';
        const isHash = key === '#';
        const isPlus = key === '+';

        if (isDigit || isStar || isHash || isPlus) {
            event.preventDefault();
            this.addDigit(key);
            return;
        }

        if (key === 'Backspace' || key === 'Delete') {
            event.preventDefault();
            this.deleteDigit();
            return;
        }

        if (key === 'Enter') {
            event.preventDefault();
            this.makeCall();
        }
    }
    
    callNumber(number) {
        this.currentNumber = number;
        this.updateNumberDisplay();
        this.show();
        this.makeCall();
    }
    
    /**
     * Mostrar/Ocultar panel
     */
    toggle() {
        if (!this.panel) {
            this.log('❌ Panel no existe, recreando...');
            this.createPanel();
        }
        
        const panelEnDOM = document.getElementById('webrtc-softphone');
        if (!panelEnDOM) {
            this.log('❌ Panel no está en el DOM, recreando...');
            this.createPanel();
        }
        
        this.panel = document.getElementById('webrtc-softphone');
        if (!this.panel) {
            this.log('❌ ERROR: No se pudo obtener el panel del DOM');
            return;
        }
        
        const estabaOculto = this.panel.classList.contains('hidden');
        this.panel.classList.toggle('hidden');
        const estaOculto = this.panel.classList.contains('hidden');
        
        this.log(`Panel toggle: ${estabaOculto ? 'Oculto' : 'Visible'} → ${estaOculto ? 'Oculto' : 'Visible'}`);
        
        const navbarBtn = document.querySelector('.webrtc-toggle-btn');
        if (navbarBtn) {
            if (estaOculto) {
                navbarBtn.classList.remove('open');
            } else {
                navbarBtn.classList.add('open');
                this.panel.classList.remove('compact');
                this.panel.classList.remove('collapsed');
            }
        }
        
        if (!estaOculto) {
            const display = window.getComputedStyle(this.panel).display;
            const visibility = window.getComputedStyle(this.panel).visibility;
            this.log(`Panel visible - display: ${display}, visibility: ${visibility}`);
            
            if (display === 'none' || visibility === 'hidden') {
                this.log('⚠️ Panel tiene clase hidden removida pero CSS lo oculta');
                this.panel.style.display = 'block';
                this.panel.style.visibility = 'visible';
            }
        }
    }
    
    /**
     * Mostrar panel
     */
    show() {
        if (!this.panel) {
            this.log('❌ Panel no existe, recreando...');
            this.createPanel();
        }
        
        this.panel = document.getElementById('webrtc-softphone');
        if (!this.panel) {
            this.log('❌ ERROR: No se pudo obtener el panel del DOM');
            return;
        }
        
        this.panel.classList.remove('hidden');
        this.panel.classList.remove('compact');
        this.panel.classList.remove('collapsed');
        
        this.panel.style.display = 'block';
        this.panel.style.visibility = 'visible';
        
        const navbarBtn = document.querySelector('.webrtc-toggle-btn');
        if (navbarBtn) {
            navbarBtn.classList.add('open');
        }
        
        this.log('Panel mostrado - display: ' + window.getComputedStyle(this.panel).display);
    }
    
    /**
     * Modo compacto
     */
    toggleCompact() {
        if (this.panel) {
            this.panel.classList.toggle('compact');
            this.log('Modo compacto:', this.panel.classList.contains('compact'));
        }
    }
    
    /**
     * Mostrar notificación
     */
    showNotification(title, body, type = 'info') {
        if ("Notification" in window && Notification.permission === "granted") {
            try {
                new Notification(title, {
                    body: body,
                    icon: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMzIiIGN5PSIzMiIgcj0iMzIiIGZpbGw9IiMwMDIzNDEiLz48cGF0aCBkPSJNMzIgMTJjLTExIDAtMjAgOS0yMCAyMHY4YzAgMTEgOSAyMCAyMCAyMGg0YzExIDAgMjAtOSAyMC0yMHYtOGMwLTExLTktMjAtMjAtMjB6IiBmaWxsPSJ3aGl0ZSIvPjwvc3ZnPg=='
                });
            } catch (e) {
                console.log(`[${type.toUpperCase()}] ${title}: ${body}`);
            }
        }
        console.log(`[${type.toUpperCase()}] ${title}: ${body}`);
    }
    
    /**
     * Silenciar/Activar micrófono
     */
    toggleMute() {
        if (!this.currentSession) return;
        
        try {
            if (this.currentSession.isMuted && this.currentSession.isMuted()) {
                this.currentSession.unmute();
                const btn = document.getElementById('btn-mute');
                if (btn) {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-microphone"></i> Silenciar';
                }
                this.log('Micrófono activado');
            } else {
                this.currentSession.mute();
                const btn = document.getElementById('btn-mute');
                if (btn) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-microphone-slash"></i> Activar';
                }
                this.log('Micrófono silenciado');
            }
        } catch (error) {
            this.log('Error al cambiar mute:', error);
        }
    }
    
    /**
     * Pausar/Reanudar llamada
     */
    toggleHold() {
        if (!this.currentSession) return;
        
        try {
            const stateStr = String(this.currentSession.state);
            if (stateStr === 'Hold' || stateStr === '3') {
                this.currentSession.unhold();
                const btn = document.getElementById('btn-hold');
                if (btn) {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-pause"></i> Pausar';
                }
                this.log('Llamada reanudada');
            } else {
                this.currentSession.hold();
                const btn = document.getElementById('btn-hold');
                if (btn) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-play"></i> Reanudar';
                }
                this.log('Llamada en pausa');
            }
        } catch (error) {
            this.log('Error al cambiar hold:', error);
        }
    }
    
    /**
     * Mostrar modal de transferencia
     */
    showTransferModal() {
        if (!this.currentSession) {
            this.showNotification('Error', 'No hay llamada activa', 'error');
            return;
        }
        // El modal se crea en createPanel si es necesario
        this.log('Modal de transferencia - funcionalidad pendiente');
    }
    
    /**
     * Toggle DTMF
     */
    toggleDTMF() {
        this.showNotification('Info', 'Use el teclado para enviar DTMF');
    }
    
    log(...args) {
        if (this.config && this.config.debug_mode) {
            console.log('[WebRTC Softphone]', ...args);
        }
    }
}

if (window && window.isSecureContext !== undefined) {
    console.log('[Diagnóstico] isSecureContext:', window.isSecureContext);
}
if (navigator && navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
    navigator.mediaDevices.enumerateDevices().then((devices) => {
        console.log('[Diagnóstico] Dispositivos detectados:', devices);
    }).catch((err) => {
        console.error('[Diagnóstico] Error enumerando dispositivos:', err);
    });
} else {
    console.warn('[Diagnóstico] enumerateDevices no disponible');
}

// Variable global para acceso
let webrtcSoftphone = null;
