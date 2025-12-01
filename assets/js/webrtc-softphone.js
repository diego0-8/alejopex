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
        this.preferredAsteriskRtpPort = config?.preferredRtpPort || 10000;
        this.lastMediaStream = null;
        this.mediaStreamFactory = this._mediaStreamFactory.bind(this);
        this.preAcquiredStream = null; // Stream pre-adquirido para interceptar SIP.js
        this.originalGetUserMedia = null; // Referencia al método original
        this.remoteAudioElement = null; // Elemento de audio para reproducir audio remoto
        this.ringAudio = null; // Para el tono de llamada entrante
        this.ringbackAudio = null; // Para el tono de espera (cuando llamas a alguien)
        
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
        
        // Crear elementos de audio para los tonos
        this.log('🔊 Inicializando elementos de audio para tonos...');
        
        // Determinar la ruta base del sitio
        // Obtener la ruta base del proyecto desde la URL actual
        let basePath = window.location.pathname;
        
        // Si la ruta termina con un archivo (ej: index.php, asesor_gestionar.php), removerlo
        if (basePath.match(/\/[^/]+\.[^/]+$/)) {
            basePath = basePath.replace(/\/[^/]+\.[^/]+$/, '');
        }
        
        // Asegurar que termine con /
        if (!basePath.endsWith('/')) {
            basePath += '/';
        }
        
        // Construir rutas de audio
        const ringtonePath = basePath + 'assets/audio/ringtone.mp3';
        const ringbackPath = basePath + 'assets/audio/ringback.mp3';
        
        this.log('   📍 Base path detectado:', basePath);
        this.log('   📍 Ruta completa ringtone:', window.location.origin + ringtonePath);
        this.log('   📍 Ruta completa ringback:', window.location.origin + ringbackPath);
        
        try {
            // Ringtone (llamada entrante) - Usar ruta relativa al proyecto
            this.log('   🎵 Intentando cargar ringtone desde:', ringtonePath);
            this.ringAudio = new Audio(ringtonePath);
            this.ringAudio.loop = true;
            this.ringAudio.volume = 0.5;
            this.ringAudio.preload = 'auto';
            
            // Eventos de diagnóstico para ringtone
            this.ringAudio.addEventListener('loadstart', () => {
                this.log('📥 ringtone.mp3: Iniciando carga desde:', this.ringAudio.src);
            });
            this.ringAudio.addEventListener('loadedmetadata', () => {
                this.log('📊 ringtone.mp3: Metadata cargada, duración:', this.ringAudio.duration, 'segundos');
            });
            this.ringAudio.addEventListener('loadeddata', () => {
                this.log('✅ ringtone.mp3: Datos cargados correctamente');
            });
            this.ringAudio.addEventListener('canplay', () => {
                this.log('✅ ringtone.mp3: Listo para reproducir');
                this.log('   📊 Estado:', this.ringAudio.readyState, '(HAVE_ENOUGH_DATA = 4)');
            });
            this.ringAudio.addEventListener('canplaythrough', () => {
                this.log('✅ ringtone.mp3: Puede reproducirse completamente sin interrupciones');
            });
            this.ringAudio.addEventListener('error', (e) => {
                this.log('❌ Error al cargar ringtone.mp3');
                this.log('   📍 Ruta intentada:', this.ringAudio.src);
                this.log('   📍 Ruta completa:', window.location.origin + ringtonePath);
                this.log('   💡 Verifica que el archivo existe en:', ringtonePath);
                this.log('   💡 El softphone funcionará sin sonido de llamada entrante');
                if (this.ringAudio.error) {
                    this.log('   🔍 Código de error:', this.ringAudio.error.code);
                    this.log('   🔍 Mensaje de error:', this.ringAudio.error.message);
                }
                this.ringAudio = null;
            });
            
            // Ringback (llamada saliente) - Usar ruta relativa al proyecto
            this.log('   🎵 Intentando cargar ringback desde:', ringbackPath);
            this.ringbackAudio = new Audio(ringbackPath);
            this.ringbackAudio.loop = true;
            this.ringbackAudio.volume = 0.5;
            this.ringbackAudio.preload = 'auto';
            
            // Eventos de diagnóstico para ringback
            this.ringbackAudio.addEventListener('loadstart', () => {
                this.log('📥 ringback.mp3: Iniciando carga desde:', this.ringbackAudio.src);
            });
            this.ringbackAudio.addEventListener('loadedmetadata', () => {
                this.log('📊 ringback.mp3: Metadata cargada, duración:', this.ringbackAudio.duration, 'segundos');
            });
            this.ringbackAudio.addEventListener('loadeddata', () => {
                this.log('✅ ringback.mp3: Datos cargados correctamente');
            });
            this.ringbackAudio.addEventListener('canplay', () => {
                this.log('✅ ringback.mp3: Listo para reproducir');
                this.log('   📊 Estado:', this.ringbackAudio.readyState, '(HAVE_ENOUGH_DATA = 4)');
            });
            this.ringbackAudio.addEventListener('canplaythrough', () => {
                this.log('✅ ringback.mp3: Puede reproducirse completamente sin interrupciones');
            });
            this.ringbackAudio.addEventListener('error', (e) => {
                this.log('❌ Error al cargar ringback.mp3');
                this.log('   📍 Ruta intentada:', this.ringbackAudio.src);
                this.log('   📍 Ruta completa:', window.location.origin + ringbackPath);
                this.log('   💡 Verifica que el archivo existe en:', ringbackPath);
                this.log('   💡 El softphone funcionará sin sonido de espera');
                if (this.ringbackAudio.error) {
                    this.log('   🔍 Código de error:', this.ringbackAudio.error.code);
                    this.log('   🔍 Mensaje de error:', this.ringbackAudio.error.message);
                }
                this.ringbackAudio = null;
            });
            
            this.log('✅ Elementos de audio para tonos inicializados');
            this.log('   📁 Ruta ringtone: ' + ringtonePath);
            this.log('   📁 Ruta ringback: ' + ringbackPath);
            this.log('   📁 URL completa ringtone: ' + window.location.origin + ringtonePath);
            this.log('   📁 URL completa ringback: ' + window.location.origin + ringbackPath);
        } catch (error) {
            this.log('⚠️ Error al inicializar elementos de audio:', error);
            this.ringAudio = null;
            this.ringbackAudio = null;
        }
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
        
        // PRIORIDAD 2: Si hay configuración de STUN desde PHP (stun_server)
        if (this.config && this.config.stun_server) {
            const stunUrl = this.config.stun_server.startsWith('stun:') 
                ? this.config.stun_server 
                : `stun:${this.config.stun_server}`;
            iceServers.push({ urls: stunUrl });
            this.log(`   ✅ Agregado servidor STUN desde configuración: ${stunUrl}`);
        }
        
        // PRIORIDAD 3: Servidores STUN públicos de Google (fallback por defecto)
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
                        this.log(`   ❌ CRÍTICO: No se detectaron codecs PCMU/PCMA - Asterisk requiere estos codecs!`);
                        this.log(`   🔧 SOLUCIÓN: Verificar configuración de codecs en Asterisk:`);
                        this.log(`      - En /etc/asterisk/sip.conf o pjsip.conf: allow=ulaw,alaw`);
                        this.log(`      - Reiniciar Asterisk después de cambios`);
                    }
                }
            } else {
                this.log(`   ❌ CRÍTICO: No se encontró línea m=audio en SDP ${tipo}`);
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
                this.log('🔔 Estado de llamada saliente:', newState);
                
                const stateStr = String(newState);
                
                // Estado: Progress (180 Ringing) - El teléfono remoto está sonando
                if (stateStr === 'Progress' || stateStr === '2' || newState === 'Progress') {
                    this.log('📞 Estado Progress detectado - Iniciando ringback tone');
                    // ✅ SOLUCIÓN: Iniciar Tono de Ringback (el teléfono remoto está timbrando)
                    if (this.ringbackAudio) {
                        this.log('   📊 Estado del audio ringback:', {
                            readyState: this.ringbackAudio.readyState,
                            networkState: this.ringbackAudio.networkState,
                            paused: this.ringbackAudio.paused,
                            src: this.ringbackAudio.src,
                            error: this.ringbackAudio.error ? {
                                code: this.ringbackAudio.error.code,
                                message: this.ringbackAudio.error.message
                            } : null
                        });
                        
                        // Verificar si hay errores de carga
                        if (this.ringbackAudio.error) {
                            this.log('❌ El archivo de ringback tiene errores:', this.ringbackAudio.error);
                            this.log('   💡 Verifica que el archivo existe y es accesible');
                        } else if (this.ringbackAudio.readyState >= 2) {
                            // readyState >= 2 significa que hay datos suficientes para reproducir
                            this.ringbackAudio.currentTime = 0; // Reiniciar desde el principio
                            this.ringbackAudio.play()
                                .then(() => {
                                    this.log('✅ Ringback tone reproducido exitosamente');
                                })
                                .catch(e => {
                                    this.log('⚠️ No se pudo iniciar el ringback tone');
                                    this.log('   Error name:', e.name);
                                    this.log('   Error message:', e.message);
                                    this.log('   💡 Nota: El error puede ser por la política de autoplay de Chrome');
                                });
                        } else {
                            this.log('⚠️ El archivo de ringback aún no está listo (readyState:', this.ringbackAudio.readyState + ')');
                            this.log('   💡 Esperando a que el archivo se cargue...');
                            // Esperar a que el archivo esté listo
                            const tryPlayWhenReady = () => {
                                if (this.ringbackAudio && this.ringbackAudio.readyState >= 2) {
                                    this.ringbackAudio.currentTime = 0;
                                    this.ringbackAudio.play()
                                        .then(() => {
                                            this.log('✅ Ringback tone reproducido después de esperar carga');
                                        })
                                        .catch(e => {
                                            this.log('⚠️ Error al reproducir ringback después de carga:', e);
                                        });
                                } else if (this.ringbackAudio && this.ringbackAudio.readyState < 4) {
                                    setTimeout(tryPlayWhenReady, 100);
                                }
                            };
                            this.ringbackAudio.addEventListener('canplay', tryPlayWhenReady, { once: true });
                        }
                    } else {
                        this.log('⚠️ ringbackAudio no está disponible (archivo no cargado o no existe)');
                        this.log('   💡 Verifica que el archivo /assets/audio/ringback.mp3 existe');
                    }
                    this.updateCallInfo(this.currentNumber, 'Sonando...');
                } 
                // Estado: Established - Llamada conectada
                else if (stateStr === 'Established' || stateStr === '4' || newState === 'Established') {
                    this.log('✅ Llamada establecida - Deteniendo ringback tone');
                    // ✅ Detener Tono de Ringback al contestar
                    if (this.ringbackAudio) {
                        this.ringbackAudio.pause();
                        this.ringbackAudio.currentTime = 0; // Reiniciar
                        this.log('✅ Ringback tone detenido');
                    }
                    this.onCallStarted();
                    this.startCallTimer();
                } 
                // Estado: Terminated - Llamada terminada
                else if (stateStr === 'Terminated' || stateStr === '5' || newState === 'Terminated') {
                    this.log('📴 Llamada terminada - Deteniendo ringback tone');
                    // ✅ Detener Tono de Ringback al colgar
                    if (this.ringbackAudio) {
                        this.ringbackAudio.pause();
                        this.ringbackAudio.currentTime = 0; // Reiniciar
                        this.log('✅ Ringback tone detenido');
                    }
                    this.onCallEnded();
                } 
                // Otros estados
                else if (stateStr === 'Initial' || stateStr === '0') {
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
                            
                            // OBTENER ESTADÍSTICAS RTP Y VERIFICAR CANDIDATOS ICE
                            try {
                                const stats = await pc.getStats();
                                let audioBytesReceived = 0;
                                let audioPacketsReceived = 0;
                                let audioBytesSent = 0;
                                let audioPacketsSent = 0;
                                let hasInboundRtp = false;
                                let relayCandidatesFound = 0;
                                let srflxCandidatesFound = 0;
                                let hostCandidatesFound = 0;
                                let selectedCandidateType = null;
                                
                                // Verificar candidatos ICE
                                stats.forEach((report) => {
                                    if (report.type === 'local-candidate' || report.type === 'remote-candidate') {
                                        if (report.candidateType === 'relay') {
                                            relayCandidatesFound++;
                                        } else if (report.candidateType === 'srflx') {
                                            srflxCandidatesFound++;
                                        } else if (report.candidateType === 'host') {
                                            hostCandidatesFound++;
                                        }
                                    }
                                    
                                    // Verificar qué candidato se está usando
                                    if (report.type === 'candidate-pair' && report.state === 'succeeded') {
                                        const localCandidateId = report.localCandidateId;
                                        stats.forEach((candidateReport) => {
                                            if (candidateReport.id === localCandidateId) {
                                                selectedCandidateType = candidateReport.candidateType;
                                                this.log(`🎯 Candidato seleccionado para conexión: ${selectedCandidateType}`);
                                                if (selectedCandidateType === 'relay') {
                                                    this.log('   ✅ USANDO TURN (relay) - Conexión a través de servidor TURN');
                                                } else if (selectedCandidateType === 'srflx') {
                                                    this.log('   ⚠️ Usando STUN (srflx) - Conexión directa con IP pública');
                                                    this.log('   ⚠️ Si no hay audio, puede ser que el firewall bloquee RTP');
                                                } else {
                                                    this.log('   ⚠️ Usando conexión directa (host) - Solo funciona en red local');
                                                }
                                            }
                                        });
                                    }
                                });
                                
                                this.log(`📊 Candidatos ICE encontrados: ${relayCandidatesFound} relay, ${srflxCandidatesFound} srflx, ${hostCandidatesFound} host`);
                                if (relayCandidatesFound === 0) {
                                    this.log('   ⚠️ ADVERTENCIA: No se encontraron candidatos RELAY (TURN)');
                                    this.log('   Esto significa que el servidor TURN no está funcionando o no se está usando');
                                    this.log('   Posibles causas:');
                                    this.log('   1. Credenciales TURN incorrectas');
                                    this.log('   2. Servidor TURN no accesible desde tu red');
                                    this.log('   3. Firewall bloqueando conexión al TURN');
                                }
                                
                                // Información detallada de puertos RTP
                                let localRtpPort = null;
                                let remoteRtpPort = null;
                                let localRtpIp = null;
                                let remoteRtpIp = null;
                                
                                stats.forEach((report) => {
                                    if (report.type === 'inbound-rtp' && report.mediaType === 'audio') {
                                        hasInboundRtp = true;
                                        audioBytesReceived = report.bytesReceived || 0;
                                        audioPacketsReceived = report.packetsReceived || 0;
                                        remoteRtpPort = report.port || null;
                                        remoteRtpIp = report.remoteId ? (() => {
                                            // Buscar la IP remota en los candidatos
                                            let remoteIp = null;
                                            stats.forEach((candidateReport) => {
                                                if (candidateReport.id === report.remoteId && candidateReport.ip) {
                                                    remoteIp = candidateReport.ip;
                                                }
                                            });
                                            return remoteIp;
                                        })() : null;
                                        
                                        this.log(`   📊 RTP Remoto: ${audioBytesReceived} bytes recibidos, ${audioPacketsReceived} paquetes recibidos`);
                                        if (remoteRtpIp && remoteRtpPort) {
                                            this.log(`   📊 Puerto RTP Remoto (Asterisk): ${remoteRtpIp}:${remoteRtpPort}`);
                                        }
                                        this.log(`   📊 Jitter: ${report.jitter || 'N/A'}, PacketsLost: ${report.packetsLost || 0}`);
                                        if (report.codecId) {
                                            this.log(`   📊 Codec ID: ${report.codecId}`);
                                        }
                                    }
                                    if (report.type === 'outbound-rtp' && report.mediaType === 'audio') {
                                        audioBytesSent = report.bytesSent || 0;
                                        audioPacketsSent = report.packetsSent || 0;
                                        localRtpPort = report.port || null;
                                        localRtpIp = report.localId ? (() => {
                                            // Buscar la IP local en los candidatos
                                            let localIp = null;
                                            stats.forEach((candidateReport) => {
                                                if (candidateReport.id === report.localId && candidateReport.ip) {
                                                    localIp = candidateReport.ip;
                                                }
                                            });
                                            return localIp;
                                        })() : null;
                                        
                                        this.log(`   📊 RTP Local: ${audioBytesSent} bytes enviados, ${audioPacketsSent} paquetes enviados`);
                                        if (localRtpIp && localRtpPort) {
                                            this.log(`   📊 Puerto RTP Local (Cliente): ${localRtpIp}:${localRtpPort}`);
                                        }
                                    }
                                });
                                
                                // Mostrar información de puertos si está disponible
                                if (localRtpPort && remoteRtpPort) {
                                    this.log(`   🔌 Conexión RTP: Cliente ${localRtpIp || 'N/A'}:${localRtpPort} ↔ Asterisk ${remoteRtpIp || 'N/A'}:${remoteRtpPort}`);
                                    if (audioBytesReceived === 0 && audioBytesSent === 0) {
                                        this.log(`   ⚠️ ADVERTENCIA: Los puertos están configurados pero NO hay tráfico RTP`);
                                        this.log(`   Esto indica que Asterisk puede no estar enviando audio o hay un firewall bloqueando`);
                                    }
                                }
                                
                                if (!hasInboundRtp) {
                                    this.log('❌ PROBLEMA CRÍTICO: No se encontró reporte inbound-rtp.');
                                    this.log('   Esto significa que NO se están recibiendo datos de audio de Asterisk.');
                                    this.log('   Posibles causas:');
                                    this.log('   1. Asterisk no está enviando audio (verificar configuración RTP)');
                                    this.log('   2. Firewall bloqueando puertos RTP (10000-20000 UDP)');
                                    this.log('   3. NAT simétrico que requiere TURN (pero TURN no se está usando)');
                                    this.log('   4. Problema de conectividad de red');
                                } else if (audioBytesReceived === 0 && audioPacketsReceived === 0) {
                                    this.log('❌ PROBLEMA CRÍTICO: Reporte inbound-rtp existe pero NO hay datos.');
                                    this.log('   Esto significa que el canal RTP está abierto pero no hay paquetes llegando.');
                                    this.log('   Posibles causas:');
                                    this.log('   1. Asterisk no está enviando audio (verificar en Asterisk)');
                                    this.log('   2. Firewall bloqueando paquetes RTP específicos');
                                    this.log('   3. Problema de codec/negociación');
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
                                                                
                                                                // VERIFICAR ESTADÍSTICAS RTP Y CANDIDATOS ICE PARA DIAGNÓSTICO COMPLETO
                                                                try {
                                                                    const stats = await pc.getStats();
                                                                    let totalBytesReceived = 0;
                                                                    let totalPacketsReceived = 0;
                                                                    let totalBytesSent = 0;
                                                                    let totalPacketsSent = 0;
                                                                    let usingRelay = false;
                                                                    let usingSrflx = false;
                                                                    let selectedCandidateInfo = null;
                                                                    let relayCandidatesCount = 0;
                                                                    let srflxCandidatesCount = 0;
                                                                    
                                                                    // Contar candidatos disponibles
                                                                    stats.forEach((report) => {
                                                                        if (report.type === 'local-candidate') {
                                                                            if (report.candidateType === 'relay') {
                                                                                relayCandidatesCount++;
                                                                            } else if (report.candidateType === 'srflx') {
                                                                                srflxCandidatesCount++;
                                                                            }
                                                                        }
                                                                        
                                                                        // Verificar qué candidato se está usando
                                                                        if (report.type === 'candidate-pair' && report.state === 'succeeded') {
                                                                            const localCandidateId = report.localCandidateId;
                                                                            stats.forEach((candidateReport) => {
                                                                                if (candidateReport.id === localCandidateId) {
                                                                                    selectedCandidateInfo = {
                                                                                        type: candidateReport.candidateType,
                                                                                        ip: candidateReport.ip,
                                                                                        port: candidateReport.port
                                                                                    };
                                                                                    if (candidateReport.candidateType === 'relay') {
                                                                                        usingRelay = true;
                                                                                    } else if (candidateReport.candidateType === 'srflx') {
                                                                                        usingSrflx = true;
                                                                                    }
                                                                                }
                                                                            });
                                                                        }
                                                                    });
                                                                    
                                                                    // Obtener información de puertos RTP
                                                                    let localRtpPort = null;
                                                                    let remoteRtpPort = null;
                                                                    let localRtpIp = null;
                                                                    let remoteRtpIp = null;
                                                                    
                                                                    stats.forEach((report) => {
                                                                        if (report.type === 'inbound-rtp' && report.mediaType === 'audio') {
                                                                            totalBytesReceived += (report.bytesReceived || 0);
                                                                            totalPacketsReceived += (report.packetsReceived || 0);
                                                                            remoteRtpPort = report.port || null;
                                                                            // Buscar IP remota en candidatos
                                                                            if (report.remoteId) {
                                                                                stats.forEach((candidateReport) => {
                                                                                    if (candidateReport.id === report.remoteId && candidateReport.ip) {
                                                                                        remoteRtpIp = candidateReport.ip;
                                                                                    }
                                                                                });
                                                                            }
                                                                        }
                                                                        if (report.type === 'outbound-rtp' && report.mediaType === 'audio') {
                                                                            totalBytesSent += (report.bytesSent || 0);
                                                                            totalPacketsSent += (report.packetsSent || 0);
                                                                            localRtpPort = report.port || null;
                                                                            // Buscar IP local en candidatos
                                                                            if (report.localId) {
                                                                                stats.forEach((candidateReport) => {
                                                                                    if (candidateReport.id === report.localId && candidateReport.ip) {
                                                                                        localRtpIp = candidateReport.ip;
                                                                                    }
                                                                                });
                                                                            }
                                                                        }
                                                                    });
                                                                    
                                                                    // DIAGNÓSTICO COMPLETO
                                                                    this.log('═══════════════════════════════════════════════════════');
                                                                    this.log('📊 DIAGNÓSTICO COMPLETO (después de 2 segundos):');
                                                                    this.log('═══════════════════════════════════════════════════════');
                                                                    this.log(`📤 Audio enviado: ${totalBytesSent} bytes, ${totalPacketsSent} paquetes`);
                                                                    this.log(`📥 Audio recibido: ${totalBytesReceived} bytes, ${totalPacketsReceived} paquetes`);
                                                                    this.log(`🎯 Candidatos disponibles: ${relayCandidatesCount} relay, ${srflxCandidatesCount} srflx`);
                                                                    
                                                                    // Información de puertos RTP
                                                                    if (localRtpPort && remoteRtpPort) {
                                                                        this.log(`🔌 Puertos RTP: Cliente ${localRtpIp || 'N/A'}:${localRtpPort} ↔ Asterisk ${remoteRtpIp || 'N/A'}:${remoteRtpPort}`);
                                                                        this.log(`   💡 Asterisk está usando el puerto ${remoteRtpPort} para RTP`);
                                                                        this.log(`   💡 Cliente está usando el puerto ${localRtpPort} para RTP`);
                                                                        if (this.preferredAsteriskRtpPort) {
                                                                            this.log(`   🎯 Puerto preferido configurado en cliente: ${this.preferredAsteriskRtpPort}`);
                                                                        }
                                                                        
                                                                        // Verificar si los puertos están en el rango esperado
                                                                        if (remoteRtpPort < 10000 || remoteRtpPort > 20000) {
                                                                            this.log(`   ⚠️ ADVERTENCIA: Puerto de Asterisk (${remoteRtpPort}) está FUERA del rango típico (10000-20000)`);
                                                                            this.log(`   Esto puede indicar un problema de configuración en Asterisk`);
                                                                        }
                                                                        
                                                                        // Explicar que es normal que los puertos sean diferentes
                                                                        this.log(`   ℹ️ NOTA: Es NORMAL que los puertos sean diferentes:`);
                                                                        this.log(`      - Asterisk usa puertos 10000-20000 (configurado en rtp.conf)`);
                                                                        this.log(`      - El cliente usa puertos dinámicos asignados por el sistema operativo`);
                                                                        this.log(`      - El puerto del cliente (${localRtpPort}) puede estar fuera del rango 10000-20000`);
                                                                        this.log(`      - Esto es CORRECTO y NO es un problema`);
                                                                    } else {
                                                                        this.log(`   ⚠️ No se pudo determinar los puertos RTP`);
                                                                        if (this.preferredAsteriskRtpPort) {
                                                                            this.log(`   🎯 Verifica que Asterisk esté enviando audio usando un puerto del rango 10000-20000 (ej. ${this.preferredAsteriskRtpPort})`);
                                                                        }
                                                                    }
                                                                    
                                                                    if (selectedCandidateInfo) {
                                                                        this.log(`🔗 Conexión activa: ${selectedCandidateInfo.type.toUpperCase()} - ${selectedCandidateInfo.ip}:${selectedCandidateInfo.port}`);
                                                                        if (selectedCandidateInfo.type === 'relay') {
                                                                            this.log('   ✅ USANDO TURN (relay) - Esto debería resolver problemas de firewall');
                                                                        } else if (selectedCandidateInfo.type === 'srflx') {
                                                                            this.log('   ⚠️ Usando STUN (srflx) - Puede fallar si hay firewall estricto');
                                                                        } else {
                                                                            this.log('   ✅ Usando conexión directa (host) - Correcto para servidor local');
                                                                        }
                                                                    } else {
                                                                        this.log('   ⚠️ No se pudo determinar el tipo de conexión activa');
                                                                    }
                                                                    
                                                                    if (totalBytesReceived === 0 && totalPacketsReceived === 0) {
                                                                        this.log('');
                                                                        this.log('❌ PROBLEMA CRÍTICO: NO hay datos RTP llegando');
                                                                        this.log('');
                                                                        this.log('📋 ANÁLISIS DEL PROBLEMA:');
                                                                        this.log('   1. ✅ Conexión ICE establecida (ambos están conectados)');
                                                                        this.log('   2. ✅ Puertos RTP configurados correctamente');
                                                                        if (localRtpPort && remoteRtpPort) {
                                                                            this.log(`   3. ✅ Puerto Cliente: ${localRtpPort}, Puerto Asterisk: ${remoteRtpPort}`);
                                                                        }
                                                                        this.log('   4. ❌ NO hay tráfico RTP (0 bytes enviados/recibidos)');
                                                                        this.log('');
                                                                        this.log('🎯 CAUSA MÁS PROBABLE:');
                                                                        this.log('   Asterisk NO está enviando audio al cliente');
                                                                        this.log('');
                                                                        this.log('🔧 SOLUCIONES (en orden de prioridad):');
                                                                        this.log('   1. VERIFICAR EN ASTERISK (en el servidor):');
                                                                        this.log('      asterisk -rx "rtp show"');
                                                                        this.log('      Deberías ver una sesión RTP activa con:');
                                                                        if (remoteRtpPort && localRtpIp && localRtpPort) {
                                                                            this.log(`      Local: 192.168.65.190:${remoteRtpPort}`);
                                                                            this.log(`      Remote: ${localRtpIp}:${localRtpPort}`);
                                                                        } else {
                                                                            this.log('      Local: 192.168.65.190:XXXX');
                                                                            this.log('      Remote: X.X.X.X:XXXX');
                                                                        }
                                                                        this.log('');
                                                                        this.log('   2. Si NO hay sesión RTP en Asterisk:');
                                                                        this.log('      - Verificar configuración RTP en /etc/asterisk/rtp.conf');
                                                                        this.log('      - Verificar que el canal está activo: asterisk -rx "core show channels"');
                                                                        this.log('      - Verificar que no hay silencio en el otro extremo de la llamada');
                                                                        this.log('');
                                                                        this.log('   3. Si HAY sesión RTP pero no hay datos:');
                                                                        if (remoteRtpPort) {
                                                                            this.log(`      - Verificar firewall: sudo ufw allow ${remoteRtpPort}/udp`);
                                                                        }
                                                                        this.log('      - Verificar firewall: sudo ufw allow 10000:20000/udp');
                                                                        this.log('      - Verificar que el puerto usado por Asterisk está abierto');
                                                                        this.log('');
                                                                        this.log('   4. Verificar configuración de codecs:');
                                                                        this.log('      asterisk -rx "rtp show stats"');
                                                                    } else {
                                                                        this.log(`✅ Datos de audio confirmados: ${totalBytesReceived} bytes, ${totalPacketsReceived} paquetes`);
                                                                        if (self.remoteAudioElement.currentTime === 0) {
                                                                            this.log('⚠️ Hay datos RTP pero el tiempo no avanza. Puede ser un problema del codec.');
                                                                        } else {
                                                                            this.log('✅ Audio funcionando correctamente');
                                                                        }
                                                                    }
                                                                    this.log('═══════════════════════════════════════════════════════');
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
        const caller = invitation.remoteIdentity?.uri?.user || 'Desconocido';
        this.log('📞 Llamada entrante de:', caller);
        
        // 1. Guardar la sesión actual
        this.currentSession = invitation;
        this.currentNumber = caller;
        
        // 2. Actualizar UI
        this.show(); // Asegurar que el softphone sea visible
        this.updateCallInfo(caller, 'Llamada Entrante...');
        this.updateStatus('in-call', 'Llamando...'); // Poner punto azul
        
        // 3. Mostrar botones de Contestar/Rechazar
        const normalControls = document.getElementById('normal-controls');
        const incomingControls = document.getElementById('incoming-controls');
        const activeControls = document.getElementById('active-controls');
        const callControls = document.getElementById('call-controls');
        if (normalControls) normalControls.style.display = 'none';
        if (activeControls) activeControls.style.display = 'none';
        if (callControls) callControls.style.display = 'none';
        if (incomingControls) incomingControls.style.display = 'flex'; // Mostrar botones de contestar
        
        // 4. Notificación de navegador
        this.showNotification('📞 Llamada Entrante', `De: ${caller}`);
        
        // 5. ✅ SOLUCIÓN: Reproducir Tono de Alerta (Ringtone)
        this.log('🔔 Intentando reproducir ringtone...');
        if (this.ringAudio) {
            this.log('✅ ringAudio está disponible');
            this.log('   📊 Estado del audio:', {
                readyState: this.ringAudio.readyState,
                networkState: this.ringAudio.networkState,
                paused: this.ringAudio.paused,
                src: this.ringAudio.src,
                error: this.ringAudio.error ? {
                    code: this.ringAudio.error.code,
                    message: this.ringAudio.error.message
                } : null
            });
            
            // Verificar si hay errores de carga
            if (this.ringAudio.error) {
                this.log('❌ El archivo de audio tiene errores:', this.ringAudio.error);
                this.log('   💡 Código de error:', this.ringAudio.error.code);
                this.log('   💡 Mensaje:', this.ringAudio.error.message);
                this.log('   💡 Verifica que el archivo existe y es accesible');
            } else if (this.ringAudio.readyState >= 2) {
                // readyState >= 2 significa que hay datos suficientes para reproducir
                this.ringAudio.currentTime = 0; // Reiniciar desde el principio
                this.ringAudio.play()
                    .then(() => {
                        this.log('✅ Ringtone reproducido exitosamente');
                        this.log('   📊 Estado después de play:', {
                            paused: this.ringAudio.paused,
                            currentTime: this.ringAudio.currentTime,
                            readyState: this.ringAudio.readyState
                        });
                    })
                    .catch(e => {
                        this.log('⚠️ No se pudo iniciar el ringtone');
                        this.log('   Error name:', e.name);
                        this.log('   Error message:', e.message);
                        this.log('   💡 Nota: El error puede ser por la política de autoplay de Chrome');
                        this.log('   💡 El usuario debe haber interactuado con la página primero');
                        this.log('   💡 Solución: El usuario debe hacer clic en algún botón antes de recibir llamadas');
                    });
            } else {
                this.log('⚠️ El archivo de audio aún no está listo (readyState:', this.ringAudio.readyState + ')');
                this.log('   💡 Esperando a que el archivo se cargue completamente...');
                // Esperar a que el archivo esté listo
                const tryPlayWhenReady = () => {
                    if (this.ringAudio && this.ringAudio.readyState >= 2) {
                        this.ringAudio.currentTime = 0;
                        this.ringAudio.play()
                            .then(() => {
                                this.log('✅ Ringtone reproducido después de esperar carga');
                            })
                            .catch(e => {
                                this.log('⚠️ Error al reproducir después de carga:', e);
                            });
                    } else if (this.ringAudio && this.ringAudio.readyState < 4) {
                        setTimeout(tryPlayWhenReady, 100);
                    }
                };
                this.ringAudio.addEventListener('canplay', tryPlayWhenReady, { once: true });
            }
        } else {
            this.log('⚠️ ringAudio no está disponible (archivo no cargado o no existe)');
            this.log('   💡 Verifica que el archivo /assets/audio/ringtone.mp3 existe');
            this.log('   💡 Verifica la consola al cargar la página para ver si hubo errores de carga');
        }
        
        // 6. Manejar cancelación si el cliente cuelga antes de que contestemos
        invitation.stateChange.addListener((newState) => {
            const stateStr = String(newState);
            this.log('Estado de invitación entrante:', stateStr);
            
            if (stateStr === 'Terminated' || stateStr === 'Canceled') {
                this.log('Llamada entrante cancelada por el origen');
                this.onCallEnded(); // Restaurar interfaz
            } else if (stateStr === 'Established') {
                // Si se estableció (por ejemplo si contestamos en otro tab)
                this.onCallStarted();
            }
        });
    }
    
    async answerIncomingCall() {
        if (!this.currentSession) return;
        this.log('✅ Usuario presionó Contestar');
        
        try {
            // Reutilizamos la misma configuración robusta de ICE y Audio que usas para llamar
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
                        this.log('🎤 ===== mediaStreamFactory LLAMADA PARA CONTESTAR =====');
                        // Adquirir stream antes de contestar
                        if (!this.preAcquiredStream || !this.preAcquiredStream.active) {
                            this.log('Adquiriendo MediaStream para contestar...');
                            const audioConstraints = await this._determineAudioConstraints();
                            this.preAcquiredStream = await this._mediaStreamFactory(audioConstraints);
                            this.lastMediaStream = this.preAcquiredStream;
                        }
                        if (this.preAcquiredStream && this.preAcquiredStream.active) {
                            this.log('✅ Retornando stream pre-adquirido activo para contestar');
                            const audioTracks = this.preAcquiredStream.getAudioTracks();
                            this.log(`   Stream tiene ${audioTracks.length} track(s) de audio`);
                            return this.preAcquiredStream;
                        }
                        this.log('⚠️ Stream pre-adquirido no disponible, adquiriendo nuevo...');
                        return await this._mediaStreamFactory();
                    }
                }
            };
            
            // Aceptar la llamada
            await this.currentSession.accept(options);
            
            // Actualizar UI a "En llamada"
            this.onCallStarted();
            
        } catch (error) {
            this.log('❌ Error al contestar:', error);
            this.showNotification('Error', 'No se pudo contestar la llamada', 'error');
            this.onCallEnded();
        }
    }
    
    rejectIncomingCall() {
        if (!this.currentSession) return;
        
        this.log('⛔ Usuario presionó Rechazar');
        this.currentSession.reject();
        this.onCallEnded();
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
        
        // Mostrar controles de llamada activa
        document.getElementById('call-controls').style.display = 'grid'; // Mute, Hold, etc
        document.getElementById('active-controls').style.display = 'flex'; // Botón colgar grande
        
        // Ocultar otros controles
        document.getElementById('normal-controls').style.display = 'none';
        document.getElementById('incoming-controls').style.display = 'none';
        
        this.updateCallInfo(this.currentNumber, 'Conectado');
        
        // Detener ringtone si estuviera sonando
        if (this.ringAudio) {
            this.ringAudio.pause();
            this.ringAudio.currentTime = 0;
        }
        
        // Iniciar temporizador de llamada
        this.startCallTimer();
    }
    
    onCallEnded() {
        this.stopCallTimer();
        this.updateStatus('connected', 'En línea');
        document.getElementById('call-info').classList.remove('active');
        
        // Restaurar estado inicial
        document.getElementById('call-controls').style.display = 'none';
        document.getElementById('active-controls').style.display = 'none';
        document.getElementById('incoming-controls').style.display = 'none';
        
        // Mostrar teclado y botón de llamar
        document.getElementById('normal-controls').style.display = 'flex';
        
        this.currentSession = null;
        this.currentNumber = '';
        this.updateNumberDisplay();
        this._releaseLastMediaStream();
        
        // Limpiar stream pre-adquirido y audio remoto
        this.preAcquiredStream = null;
        if (this.remoteAudioElement) {
            this.remoteAudioElement.srcObject = null;
            this.remoteAudioElement.pause();
            this.log('🔇 Audio remoto limpiado');
        }
        
        // ✅ Limpieza de Tonos
        this.log('🔇 Deteniendo todos los tonos...');
        if (this.ringAudio) {
            try {
                this.ringAudio.pause();
                this.ringAudio.currentTime = 0;
                this.log('✅ Ringtone detenido');
            } catch (e) {
                this.log('⚠️ Error al detener ringtone:', e);
            }
        } else {
            this.log('ℹ️ ringAudio no está disponible');
        }
        
        if (this.ringbackAudio) {
            try {
                this.ringbackAudio.pause();
                this.ringbackAudio.currentTime = 0;
                this.log('✅ Ringback tone detenido');
            } catch (e) {
                this.log('⚠️ Error al detener ringback tone:', e);
            }
        } else {
            this.log('ℹ️ ringbackAudio no está disponible');
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
            // Si el panel ya existe (inline), configurarlo
            panel.className = 'webrtc-softphone-panel inline';
        } else {
            // Si no existe, crear uno flotante
            panel = document.createElement('div');
            panel.className = 'webrtc-softphone-panel hidden';
            panel.id = 'webrtc-softphone';
            document.body.appendChild(panel);
        }

        this.panel = panel;

        const isInline = panel.classList.contains('inline');
        panel.innerHTML = `
            <div class="softphone-header">
                <h3><i class="fas fa-headset"></i> <span class="header-title">Softphone</span></h3>
                <div class="softphone-header-actions">
                    <button class="softphone-btn-icon" data-action="toggleCompact" title="Modo compacto">
                        <i class="fas fa-compress-alt"></i>
                    </button>
                    ${!isInline ? `<button class="softphone-btn-icon" data-action="toggle" title="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>` : ''}
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
                
                <div class="action-buttons" id="normal-controls">
                    <button class="action-btn delete-btn" data-action="deleteDigit">
                        <i class="fas fa-backspace"></i>
                    </button>
                    <button class="action-btn call-btn" id="btn-call" data-action="makeCall">
                        <i class="fas fa-phone"></i> Llamar
                    </button>
                </div>
                <div class="action-buttons" id="incoming-controls" style="display: none;">
                    <button class="action-btn" style="background: #28a745; color: white;" data-action="answerIncomingCall">
                        <i class="fas fa-phone"></i> Contestar
                    </button>
                    <button class="action-btn" style="background: #dc3545; color: white;" data-action="rejectIncomingCall">
                        <i class="fas fa-phone-slash"></i> Rechazar
                    </button>
                </div>
                <div class="action-buttons" id="active-controls" style="display: none;">
                    <button class="action-btn hangup-btn" id="btn-hangup" data-action="hangup" style="width: 100%;">
                        <i class="fas fa-phone-slash"></i> Colgar Llamada
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
     * Establecer número en el dialpad sin hacer la llamada
     * Útil para copiar números desde otras partes de la interfaz
     * @param {string} number - Número de teléfono a establecer
     */
    setNumber(number) {
        if (!number || typeof number !== 'string') {
            this.log('⚠️ setNumber: Número inválido:', number);
            return false;
        }
        
        // Limpiar el número (remover espacios, guiones, etc.)
        const numeroLimpio = number.trim().replace(/[\s\-\(\)]/g, '');
        
        if (numeroLimpio === '') {
            this.log('⚠️ setNumber: Número vacío después de limpiar');
            return false;
        }
        
        this.currentNumber = numeroLimpio;
        this.updateNumberDisplay();
        
        // Asegurar que el panel esté visible
        if (this.panel && this.panel.classList.contains('hidden')) {
            this.show();
        }
        
        this.log('✅ Número establecido en el dialpad:', numeroLimpio);
        return true;
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
