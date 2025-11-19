/**
 * Sistema de Medición de Tiempo para Asesores
 * Registra y monitorea el tiempo de operación de los asesores
 * Funciona en todas las vistas del asesor (compartido)
 */

// Variable global para mantener el estado del reloj entre vistas
// Cargar desde localStorage si existe
function cargarEstadoLocalStorage() {
    try {
        const estado = localStorage.getItem('asesorTiemposGlobal');
        const userLoggedIn = localStorage.getItem('asesorLoggedIn');
        
        // Solo cargar estado si el usuario sigue logueado
        if (estado && userLoggedIn === 'true') {
            const parsed = JSON.parse(estado);
            // Convertir fechas de string a Date si existen
            if (parsed.inicioSesion) {
                parsed.inicioSesion = new Date(parsed.inicioSesion);
            }
            if (parsed.inicioPausa) {
                parsed.inicioPausa = new Date(parsed.inicioPausa);
            }
            // Asegurar estructura de pausas acumuladas
            if (!parsed.pausasAcumuladas) {
                parsed.pausasAcumuladas = {
                    break: 0,
                    almuerzo: 0,
                    pausa_activa: 0,
                    actividad_extra: 0,
                    bano: 0,
                    mantenimiento: 0
                };
            }
            // Reinicializar intervalos como null
            parsed.intervaloActualizacion = null;
            parsed.intervaloReloj = null;
            parsed.intervaloPausa = null;
            console.log('AsesorTiempos: Estado cargado desde localStorage');
            return parsed;
        } else {
            // Usuario no está logueado o primera vez, resetear todo
            console.log('AsesorTiempos: Inicializando nuevo estado (usuario desconectado o primera vez)');
            if (estado) {
                localStorage.removeItem('asesorTiemposGlobal');
            }
        }
    } catch (e) {
        console.error('Error al cargar estado de localStorage:', e);
    }
    
    return {
        inicializado: false,
        sesionId: null,
        inicioSesion: null,
        tiempoTotal: 0,
        tiempoPausas: 0,
        pausasAcumuladas: { break: 0, almuerzo: 0, pausa_activa: 0, actividad_extra: 0, bano: 0, mantenimiento: 0 },
        estaPausado: false,
        tipoPausa: null,
        inicioPausa: null,
        tiempoPausaActual: 0,
        intervaloActualizacion: null,
        intervaloReloj: null,
        intervaloPausa: null
    };
}

// Guardar estado en localStorage
function guardarEstadoLocalStorage(estado) {
    try {
        // No guardar referencias a intervalos
        const estadoSerializable = {
            inicializado: estado.inicializado,
            sesionId: estado.sesionId,
            inicioSesion: estado.inicioSesion ? estado.inicioSesion.toISOString() : null,
            tiempoTotal: estado.tiempoTotal,
            tiempoPausas: estado.tiempoPausas,
            pausasAcumuladas: estado.pausasAcumuladas || { break: 0, almuerzo: 0, pausa_activa: 0, actividad_extra: 0, bano: 0, mantenimiento: 0 },
            estaPausado: estado.estaPausado,
            tipoPausa: estado.tipoPausa,
            inicioPausa: estado.inicioPausa ? estado.inicioPausa.toISOString() : null,
            tiempoPausaActual: estado.tiempoPausaActual
        };
        localStorage.setItem('asesorTiemposGlobal', JSON.stringify(estadoSerializable));
    } catch (e) {
        console.error('Error al guardar estado en localStorage:', e);
    }
}

window.asesorTiemposGlobal = cargarEstadoLocalStorage();

class AsesorTiempos {
    constructor() {
        // Usar estado global si existe
        this.sesionId = window.asesorTiemposGlobal.sesionId;
        
        // Convertir inicioSesion a Date si es string
        if (window.asesorTiemposGlobal.inicioSesion) {
            this.inicioSesion = window.asesorTiemposGlobal.inicioSesion instanceof Date 
                ? window.asesorTiemposGlobal.inicioSesion 
                : new Date(window.asesorTiemposGlobal.inicioSesion);
        } else {
            this.inicioSesion = null;
        }
        
        this.tiempoTotal = window.asesorTiemposGlobal.tiempoTotal || 0;
        this.tiempoPausas = window.asesorTiemposGlobal.tiempoPausas || 0;
        this.pausasAcumuladas = window.asesorTiemposGlobal.pausasAcumuladas || { break: 0, almuerzo: 0, pausa_activa: 0, actividad_extra: 0, bano: 0, mantenimiento: 0 };
        this.estaPausado = window.asesorTiemposGlobal.estaPausado || false;
        this.tipoPausa = window.asesorTiemposGlobal.tipoPausa;
        
        // Convertir inicioPausa a Date si es string
        if (window.asesorTiemposGlobal.inicioPausa) {
            this.inicioPausa = window.asesorTiemposGlobal.inicioPausa instanceof Date 
                ? window.asesorTiemposGlobal.inicioPausa 
                : new Date(window.asesorTiemposGlobal.inicioPausa);
        } else {
            this.inicioPausa = null;
        }
        
        this.intervaloActualizacion = null;
        this.intervaloReloj = null;
        this.intervaloActividadExtra = null;
        this.intervaloBloqueo = null;
        this.intervaloPausa = null;
        this.tiempoActividadExtra = 0;
        this.tiempoPausaActual = window.asesorTiemposGlobal.tiempoPausaActual || 0;
        
        // Referencias a elementos DOM
        this.elementos = {
            reloj: null,
            contador: null,
            btnPausa: null,
            btnResume: null,
            modalPausa: null
        };
        
        this.init();
    }

    /**
     * Inicializar el sistema
     */
    init() {
        console.log('AsesorTiempos: Iniciando sistema de medición de tiempo');
        
        // Obtener elementos DOM (pueden no existir en ciertas vistas)
        this.elementos.reloj = document.getElementById('reloj-activo');
        this.elementos.contador = document.getElementById('tiempo-sesion');
        this.elementos.btnPausa = document.getElementById('btn-pausa');
        this.elementos.btnResume = document.getElementById('btn-resume');
        this.elementos.modalPausa = document.getElementById('modal-pausa');
        
        // Si el sistema ya está inicializado, solo agregar los intervalos
        if (window.asesorTiemposGlobal.inicializado) {
            console.log('AsesorTiempos: Sistema ya inicializado, reanudando intervalos');
            this.iniciarReloj();
            this.iniciarContador();
            
            // Si estaba en pausa, restaurar el timer de pausa
            if (this.estaPausado && this.tipoPausa && this.inicioPausa) {
                console.log('AsesorTiempos: Restaurando timer de pausa:', this.tipoPausa);
                this.restaurarTimerPausa();
            }
            return;
        }
        
        // Marcar como inicializado
        window.asesorTiemposGlobal.inicializado = true;
        
        // Cargar sesión existente
        this.cargarSesion().then(() => {
            // Iniciar reloj
            this.iniciarReloj();
            
            // Iniciar contador de sesión
            this.iniciarContador();
            
            // Configurar actualización automática
            this.iniciarActualizacionAutomatica();
            
            // IMPORTANTE: No mostrar el modal de pausa automáticamente
            // El modal solo se mostrará cuando el usuario haga clic en el reloj y esté en pausa
        });
    }

    /**
     * Validar que los elementos DOM existen
     */
    validarElementos() {
        // Los elementos pueden no existir en ciertas vistas, eso está bien
        return true;
    }

    /**
     * Sincronizar estado con el global y guardar en localStorage
     */
    sincronizarEstado() {
        window.asesorTiemposGlobal.sesionId = this.sesionId;
        window.asesorTiemposGlobal.inicioSesion = this.inicioSesion;
        window.asesorTiemposGlobal.tiempoTotal = this.tiempoTotal;
        window.asesorTiemposGlobal.tiempoPausas = this.tiempoPausas;
        window.asesorTiemposGlobal.pausasAcumuladas = this.pausasAcumuladas;
        window.asesorTiemposGlobal.estaPausado = this.estaPausado;
        window.asesorTiemposGlobal.tipoPausa = this.tipoPausa;
        window.asesorTiemposGlobal.inicioPausa = this.inicioPausa;
        window.asesorTiemposGlobal.tiempoPausaActual = this.tiempoPausaActual;
        
        // Guardar en localStorage
        guardarEstadoLocalStorage(window.asesorTiemposGlobal);
    }

    /**
     * Cargar sesión existente o crear una nueva
     */
    async cargarSesion() {
        try {
            console.log('AsesorTiempos: Verificando sesión existente...');
            
            // Verificar si el usuario está logueado antes de intentar cargar
            const userLoggedIn = localStorage.getItem('asesorLoggedIn');
            
            // Si no está logueado o es la primera vez, crear nueva sesión
            if (!userLoggedIn || userLoggedIn !== 'true') {
                console.log('AsesorTiempos: Usuario no logueado o primera vez, creando nueva sesión');
                await this.crearSesion();
                return;
            }
            
            // Intentar cargar sesión activa desde el servidor
            const response = await fetch('index.php?action=obtener_sesion_tiempo', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al obtener sesión');
            }
            
            const data = await response.json();
            
            if (data.success && data.sesion) {
                // Cargar sesión activa desde el servidor
                this.sesionId = data.sesion.id;
                this.inicioSesion = new Date(data.sesion.created_at);
                this.tiempoTotal = parseInt(data.sesion.tiempo_total_sesion || 0);
                this.tiempoPausas = parseInt(data.sesion.tiempo_pausas || 0);
                this.estaPausado = data.sesion.estado === 'pausada';
                this.tipoPausa = data.sesion.tipo_pausa;
                
                console.log('AsesorTiempos: Sesión cargada desde servidor:', data.sesion);
            } else {
                // No hay sesión activa en el servidor, crear nueva
                console.log('AsesorTiempos: No hay sesión activa en servidor, creando nueva sesión');
                await this.crearSesion();
            }
            
            // Sincronizar con estado global y guardar
            this.sincronizarEstado();
            
        } catch (error) {
            console.error('AsesorTiempos: Error al cargar sesión:', error);
            await this.crearSesion();
        }
    }

    /**
     * Crear nueva sesión
     */
    async crearSesion() {
        try {
            console.log('AsesorTiempos: Creando nueva sesión...');
            
            const response = await fetch('index.php?action=crear_sesion_tiempo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al crear sesión');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.sesionId = data.sesion_id;
                this.inicioSesion = new Date();
                this.tiempoTotal = 0;
                this.tiempoPausas = 0;
                this.estaPausado = false;
                
                // Marcar que el usuario está logueado
                localStorage.setItem('asesorLoggedIn', 'true');
                
                // Sincronizar con estado global
                this.sincronizarEstado();
                
                console.log('AsesorTiempos: Nueva sesión creada:', this.sesionId);
            }
            
        } catch (error) {
            console.error('AsesorTiempos: Error al crear sesión:', error);
        }
    }

    /**
     * Iniciar reloj en tiempo real
     */
    iniciarReloj() {
        if (this.intervaloReloj) {
            clearInterval(this.intervaloReloj);
        }
        
        this.intervaloReloj = setInterval(() => {
            this.actualizarReloj();
        }, 1000);
    }

    /**
     * Actualizar reloj
     */
    actualizarReloj() {
        if (!this.elementos.reloj) {
            return;
        }
        
        const ahora = new Date();
        const horas = ahora.getHours();
        const minutos = ahora.getMinutes();
        const segundos = ahora.getSeconds();
        
        const ampm = horas >= 12 ? 'PM' : 'AM';
        const horas12 = horas % 12 || 12;
        const minutosStr = minutos.toString().padStart(2, '0');
        const segundosStr = segundos.toString().padStart(2, '0');
        
        this.elementos.reloj.textContent = `${horas12}:${minutosStr} ${ampm}`;
    }

    /**
     * Iniciar contador de sesión
     */
    iniciarContador() {
        if (!this.elementos.contador) {
            return;
        }
        
        setInterval(() => {
            this.actualizarContador();
        }, 1000);
    }

    /**
     * Actualizar contador de sesión
     */
    actualizarContador() {
        if (!this.elementos.contador) {
            return;
        }
        
        // Leer del estado global
        const sesion = window.asesorTiemposGlobal;
        
        // Validar que inicioSesion existe y es válido
        if (!sesion.inicioSesion || isNaN(new Date(sesion.inicioSesion).getTime())) {
            console.warn('AsesorTiempos: No hay sesión válida, mostrando 00:00:00');
            this.elementos.contador.textContent = '00:00:00';
            return;
        }
        
        // CORRECCIÓN: El tiempo de sesión siempre sigue contando, incluso durante pausas
        // No restamos las pausas aquí, solo mostramos el tiempo total transcurrido
        const ahora = new Date();
        const inicio = new Date(sesion.inicioSesion);
        const tiempoTranscurrido = Math.floor((ahora - inicio) / 1000);
        
        // Validar que el tiempo calculado es válido
        if (isNaN(tiempoTranscurrido) || tiempoTranscurrido < 0) {
            console.warn('AsesorTiempos: Tiempo inválido calculado');
            this.elementos.contador.textContent = '00:00:00';
            return;
        }
        
        // Actualizar estado local y global
        this.tiempoTotal = tiempoTranscurrido;
        window.asesorTiemposGlobal.tiempoTotal = tiempoTranscurrido;
        
        const horas = Math.floor(tiempoTranscurrido / 3600);
        const minutos = Math.floor((tiempoTranscurrido % 3600) / 60);
        const segundos = tiempoTranscurrido % 60;
        
        const tiempoFormato = [
            horas.toString().padStart(2, '0'),
            minutos.toString().padStart(2, '0'),
            segundos.toString().padStart(2, '0')
        ].join(':');
        
        this.elementos.contador.textContent = tiempoFormato;
    }

    /**
     * Iniciar actualización automática a la base de datos
     */
    iniciarActualizacionAutomatica() {
        if (this.intervaloActualizacion) {
            clearInterval(this.intervaloActualizacion);
        }
        
        // Actualizar cada minuto
        this.intervaloActualizacion = setInterval(() => {
            this.actualizarTiempoEnBaseDatos();
        }, 60000); // 60 segundos
    }

    /**
     * Actualizar tiempo en la base de datos
     */
    async actualizarTiempoEnBaseDatos() {
        // Usar estado global
        const sesion = window.asesorTiemposGlobal;
        
        if (!sesion.sesionId) {
            return;
        }
        
        try {
            const response = await fetch('index.php?action=actualizar_tiempo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: sesion.sesionId,
                    tiempo_total: sesion.tiempoTotal,
                    tiempo_pausas: sesion.tiempoPausas,
                    estado: sesion.estaPausado ? 'pausada' : 'activa'
                })
            });
            
            if (!response.ok) {
                throw new Error('Error al actualizar tiempo');
            }
            
            const data = await response.json();
            
            if (data.success) {
                console.log('AsesorTiempos: Tiempo actualizado en BD');
            }
            
        } catch (error) {
            console.error('AsesorTiempos: Error al actualizar tiempo:', error);
        }
    }

    /**
     * Iniciar pausa
     */
    async iniciarPausa(tipoPausa) {
        const sesion = window.asesorTiemposGlobal;
        
        if (!sesion.sesionId) {
            console.error('AsesorTiempos: No hay sesión activa');
            return;
        }
        
        // Si ya está en pausa con un tipo diferente, finalizar la pausa actual primero
        if (this.estaPausado && this.tipoPausa !== tipoPausa) {
            console.log('AsesorTiempos: Cambiando tipo de pausa de', this.tipoPausa, 'a', tipoPausa);
            await this.finalizarPausa();
        }
        
        try {
            const response = await fetch('index.php?action=iniciar_pausa', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: sesion.sesionId,
                    tipo_pausa: tipoPausa
                })
            });
            
            if (!response.ok) {
                throw new Error('Error al iniciar pausa');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Actualizar estado global y local
                sesion.estaPausado = true;
                sesion.tipoPausa = tipoPausa;
                sesion.inicioPausa = new Date();
                sesion.tiempoPausaActual = 0;
                
                this.estaPausado = true;
                this.tipoPausa = tipoPausa;
                this.inicioPausa = new Date();
                this.tiempoPausaActual = 0;
                
                // Guardar en localStorage
                this.sincronizarEstado();
                
                this.mostrarModalPausa(tipoPausa);
                console.log('AsesorTiempos: Pausa iniciada:', tipoPausa);
            }
            
        } catch (error) {
            console.error('AsesorTiempos: Error al iniciar pausa:', error);
        }
    }

    /**
     * Finalizar pausa
     */
    async finalizarPausa() {
        const sesion = window.asesorTiemposGlobal;
        
        if (!sesion.sesionId) {
            console.error('AsesorTiempos: No hay sesión activa');
            return;
        }
        
        // Limpiar intervalo de cronómetro si existe (para mantenimiento)
        if (this.intervaloPausa) {
            clearInterval(this.intervaloPausa);
            this.intervaloPausa = null;
        }
        
        try {
            const response = await fetch('index.php?action=finalizar_pausa', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: sesion.sesionId
                })
            });
            
            if (!response.ok) {
                throw new Error('Error al finalizar pausa');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Sumar la pausa actual al acumulado por tipo y al total de pausas
                const segundos = this.tiempoPausaActual || 0;
                if (this.tipoPausa) {
                    if (!this.pausasAcumuladas[this.tipoPausa]) {
                        this.pausasAcumuladas[this.tipoPausa] = 0;
                    }
                    this.pausasAcumuladas[this.tipoPausa] += segundos;
                }
                this.tiempoPausas = (this.tiempoPausas || 0) + segundos;
                sesion.tiempoPausas = this.tiempoPausas;
                sesion.pausasAcumuladas = this.pausasAcumuladas;
                // Actualizar estado global y local
                sesion.estaPausado = false;
                sesion.tipoPausa = null;
                sesion.inicioPausa = null;
                sesion.tiempoPausaActual = 0;
                
                this.estaPausado = false;
                this.tipoPausa = null;
                this.inicioPausa = null;
                this.tiempoPausaActual = 0;
                
                // Guardar en localStorage
                this.sincronizarEstado();
                
                this.cerrarModalPausa();
                console.log('AsesorTiempos: Pausa finalizada');
            }
            
        } catch (error) {
            console.error('AsesorTiempos: Error al finalizar pausa:', error);
        }
    }

    /**
     * Mostrar modal de pausa
     */
    mostrarModalPausa(tipoPausa) {
        if (!this.elementos.modalPausa) {
            console.error('AsesorTiempos: Modal de pausa no encontrado');
            return;
        }
        
        this.elementos.modalPausa.style.display = 'flex';
        
        // Configurar texto del tipo de pausa
        const tipoTextoEl = this.elementos.modalPausa.querySelector('#tipo-pausa-texto');
        if (tipoTextoEl) {
            const textos = {
                'break': 'Break en progreso',
                'almuerzo': 'Almuerzo en progreso',
                'bano': 'Baño en progreso',
                'mantenimiento': 'Mantenimiento en progreso',
                'pausa_activa': 'Pausa Activa en progreso',
                'actividad_extra': 'Actividad Extra'
            };
            tipoTextoEl.textContent = textos[tipoPausa] || 'Pausa en progreso';
        }
        
        const contadorPausa = this.elementos.modalPausa.querySelector('.tiempo-pausa');
        
        // CORRECCIÓN: Calcular tiempo inicial desde inicioPausa (persistente)
        let segundos = 0;
        if (this.inicioPausa) {
            const ahora = new Date();
            const tiempoTranscurrido = Math.floor((ahora - this.inicioPausa) / 1000);
            segundos = Math.max(0, tiempoTranscurrido);
        }
        
        // Mostrar tiempo inicial
        if (contadorPausa) {
            const horas = Math.floor(segundos / 3600);
            const minutos = Math.floor((segundos % 3600) / 60);
            const seg = segundos % 60;
            
            const tiempoFormato = [
                horas.toString().padStart(2, '0'),
                minutos.toString().padStart(2, '0'),
                seg.toString().padStart(2, '0')
            ].join(':');
            
            contadorPausa.textContent = tiempoFormato;
        }
        
        // CORRECCIÓN: Recalcular tiempo en cada tick desde inicioPausa (no usar segundos++)
        // Esto asegura que el tiempo sea preciso incluso si la pestaña está inactiva
        this.intervaloPausa = setInterval(() => {
            // Recalcular tiempo transcurrido desde el inicio de la pausa
            const ahora = new Date();
            const tiempoTranscurrido = Math.floor((ahora - this.inicioPausa) / 1000);
            let segundos = Math.max(0, tiempoTranscurrido);
            
            const horas = Math.floor(segundos / 3600);
            const minutos = Math.floor((segundos % 3600) / 60);
            const seg = segundos % 60;
            
            const tiempoFormato = [
                horas.toString().padStart(2, '0'),
                minutos.toString().padStart(2, '0'),
                seg.toString().padStart(2, '0')
            ].join(':');
            
            if (contadorPausa) {
                contadorPausa.textContent = tiempoFormato;
            }
            
            // Almacenar tiempo total para usar al finalizar
            this.tiempoPausaActual = segundos;
        }, 1000);
    }

    /**
     * Restaurar timer de pausa después de recargar la página
     */
    restaurarTimerPausa() {
        if (!this.elementos.modalPausa) {
            console.error('AsesorTiempos: Modal de pausa no encontrado para restaurar');
            return;
        }
        
        // Mostrar el modal
        this.elementos.modalPausa.style.display = 'flex';
        
        // Configurar texto del tipo de pausa
        const tipoTextoEl = this.elementos.modalPausa.querySelector('#tipo-pausa-texto');
        if (tipoTextoEl) {
            const textos = {
                'break': 'Break en progreso',
                'almuerzo': 'Almuerzo en progreso',
                'bano': 'Baño en progreso',
                'mantenimiento': 'Mantenimiento en progreso',
                'pausa_activa': 'Pausa Activa en progreso',
                'actividad_extra': 'Actividad Extra'
            };
            tipoTextoEl.textContent = textos[this.tipoPausa] || 'Pausa en progreso';
        }
        
        // Restaurar el cronómetro
        const contadorPausa = this.elementos.modalPausa.querySelector('.tiempo-pausa');
        
        // Calcular tiempo transcurrido desde el inicio de la pausa
        let segundos = 0;
        if (this.inicioPausa) {
            const ahora = new Date();
            const tiempoTranscurrido = Math.floor((ahora - this.inicioPausa) / 1000);
            segundos = Math.max(0, tiempoTranscurrido);
        }
        
        // Mostrar tiempo inicial
        if (contadorPausa) {
            const horas = Math.floor(segundos / 3600);
            const minutos = Math.floor((segundos % 3600) / 60);
            const seg = segundos % 60;
            
            const tiempoFormato = [
                horas.toString().padStart(2, '0'),
                minutos.toString().padStart(2, '0'),
                seg.toString().padStart(2, '0')
            ].join(':');
            
            contadorPausa.textContent = tiempoFormato;
        }
        
        // CORRECCIÓN: Continuar el cronómetro recalculando desde inicioPausa en cada tick
        // Esto asegura que el tiempo sea preciso incluso si la pestaña estuvo inactiva
        this.intervaloPausa = setInterval(() => {
            // Recalcular tiempo transcurrido desde el inicio de la pausa
            const ahora = new Date();
            const tiempoTranscurrido = Math.floor((ahora - this.inicioPausa) / 1000);
            let segundos = Math.max(0, tiempoTranscurrido);
            
            const horas = Math.floor(segundos / 3600);
            const minutos = Math.floor((segundos % 3600) / 60);
            const seg = segundos % 60;
            
            const tiempoFormato = [
                horas.toString().padStart(2, '0'),
                minutos.toString().padStart(2, '0'),
                seg.toString().padStart(2, '0')
            ].join(':');
            
            if (contadorPausa) {
                contadorPausa.textContent = tiempoFormato;
            }
            
            // Almacenar tiempo total para usar al finalizar
            this.tiempoPausaActual = segundos;
        }, 1000);
        
        console.log('AsesorTiempos: Timer de pausa restaurado:', this.tipoPausa);
    }

    /**
     * Cerrar modal de pausa
     */
    cerrarModalPausa() {
        if (this.elementos.modalPausa) {
            this.elementos.modalPausa.style.display = 'none';
        }
        
        // Limpiar intervalo de cronómetro si existe
        if (this.intervaloPausa) {
            clearInterval(this.intervaloPausa);
            this.intervaloPausa = null;
        }
    }

    /**
     * Iniciar actividad extra (cronómetro)
     */
    async iniciarActividadExtra() {
        console.log('AsesorTiempos: Iniciando actividad extra (cronómetro)');
        
        // Mostrar modal
        const modal = document.getElementById('modal-actividad-extra');
        if (modal) {
            modal.style.display = 'flex';
            
            // Iniciar cronómetro
            let segundos = 0;
            const contador = modal.querySelector('#tiempo-actividad-extra');
            
            this.intervaloActividadExtra = setInterval(() => {
                segundos++;
                const horas = Math.floor(segundos / 3600);
                const minutos = Math.floor((segundos % 3600) / 60);
                const seg = segundos % 60;
                
                const tiempoFormato = [
                    horas.toString().padStart(2, '0'),
                    minutos.toString().padStart(2, '0'),
                    seg.toString().padStart(2, '0')
                ].join(':');
                
                if (contador) {
                    contador.textContent = tiempoFormato;
                }
                
                // Guardar tiempo transcurrido
                this.tiempoActividadExtra = segundos;
            }, 1000);
        }
    }
    
    /**
     * Finalizar actividad extra
     */
    async finalizarActividadExtra() {
        console.log('AsesorTiempos: Finalizando actividad extra');
        
        // Guardar en base de datos
        try {
            const response = await fetch('index.php?action=guardar_actividad_extra', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: this.sesionId,
                    tipo_pausa: 'actividad_extra',
                    duracion_segundos: this.tiempoActividadExtra || 0
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log('AsesorTiempos: Actividad extra guardada:', this.tiempoActividadExtra, 'segundos');
                }
            }
        } catch (error) {
            console.error('AsesorTiempos: Error al guardar actividad extra:', error);
        }
        
        // Cerrar modal y limpiar
        const modal = document.getElementById('modal-actividad-extra');
        if (modal) {
            modal.style.display = 'none';
        }
        
        if (this.intervaloActividadExtra) {
            clearInterval(this.intervaloActividadExtra);
            this.intervaloActividadExtra = null;
        }
        
        // Sumar a acumulado local y total de pausas
        if (!this.pausasAcumuladas) {
            this.pausasAcumuladas = { break: 0, almuerzo: 0, pausa_activa: 0, actividad_extra: 0, bano: 0, mantenimiento: 0 };
        }
        const segAct = this.tiempoActividadExtra || 0;
        this.pausasAcumuladas['actividad_extra'] = (this.pausasAcumuladas['actividad_extra'] || 0) + segAct;
        this.tiempoPausas = (this.tiempoPausas || 0) + segAct;
        this.sincronizarEstado();

        this.tiempoActividadExtra = 0;
    }

    /**
     * Bloquear asesor por exceso de tiempo en pausa
     */
    async bloquearAsesor(tipoPausa, tiempoEstimado, tiempoExcedido) {
        console.log('AsesorTiempos: Bloqueando asesor por exceso de tiempo en pausa', tipoPausa);
        
        // Cerrar modal de pausa
        this.cerrarModalPausa();
        
        // Calcular tiempo excedido real
        const tiempoRealExcedido = tiempoExcedido > 0 ? tiempoExcedido : 60; // Mínimo 60 segundos excedidos
        
        try {
            const response = await fetch('index.php?action=bloquear_asesor', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: this.sesionId,
                    tipo_pausa: tipoPausa,
                    tiempo_pausa_estimado: tiempoEstimado,
                    tiempo_excedido: tiempoRealExcedido
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log('AsesorTiempos: Asesor bloqueado exitosamente');
                    // Mostrar pantalla de bloqueo
                    this.mostrarPantallaBloqueo(tipoPausa, tiempoRealExcedido);
                }
            }
        } catch (error) {
            console.error('AsesorTiempos: Error al bloquear asesor:', error);
            // Mostrar pantalla de bloqueo de todas formas
            this.mostrarPantallaBloqueo(tipoPausa, tiempoRealExcedido);
        }
    }
    
    /**
     * Mostrar pantalla de bloqueo
     */
    mostrarPantallaBloqueo(tipoPausa, tiempoExcedido) {
        // Ocultar todo el contenido de la página
        const elementosOcultar = document.querySelectorAll('body > *');
        elementosOcultar.forEach(el => {
            if (el.tagName !== 'SCRIPT' && !el.id.includes('pantalla-bloqueo')) {
                el.style.display = 'none';
            }
        });
        
        // Crear pantalla de bloqueo
        const pantallaBloqueo = document.createElement('div');
        pantallaBloqueo.id = 'pantalla-bloqueo';
        pantallaBloqueo.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
            font-family: 'Inter', sans-serif;
        `;
        
        const textosPausa = {
            'break': 'Break',
            'almuerzo': 'Almuerzo',
            'bano': 'Baño',
            'mantenimiento': 'Mantenimiento',
            'pausa_activa': 'Pausa Activa',
            'personal': 'Actividad Extra'
        };
        
        const minutosExcedidos = Math.floor(tiempoExcedido / 60);
        
        pantallaBloqueo.innerHTML = `
            <div style="text-align: center; max-width: 600px; padding: 40px;">
                <i class="fas fa-lock fa-5x" style="margin-bottom: 30px; color: white;"></i>
                <h1 style="font-size: 3rem; margin: 0 0 20px 0; font-weight: 700;">CUENTA BLOQUEADA</h1>
                <p style="font-size: 1.5rem; margin: 0 0 30px 0; opacity: 0.9;">
                    Has excedido el tiempo permitido para la pausa de ${textosPausa[tipoPausa] || tipoPausa}
                </p>
                <div style="background: rgba(255,255,255,0.2); padding: 30px; border-radius: 15px; margin-bottom: 30px;">
                    <p style="font-size: 1.2rem; margin: 0 0 15px 0;">Tiempo Excedido:</p>
                    <p style="font-size: 2.5rem; margin: 0; font-weight: 700;">${minutosExcedidos} minutos</p>
                </div>
                <div style="background: rgba(255,255,255,0.3); padding: 30px; border-radius: 15px; border: 2px solid white;">
                    <i class="fas fa-user-shield fa-3x" style="margin-bottom: 15px;"></i>
                    <p style="font-size: 1.1rem; margin: 0;">
                        Un coordinador ha sido notificado y procederá a desbloquear tu cuenta.
                        <br><br>
                        <strong>Por favor, espera a ser desbloqueado para continuar trabajando.</strong>
                    </p>
                </div>
            </div>
        `;
        
        document.body.appendChild(pantallaBloqueo);
        
        // Verificar periódicamente si ha sido desbloqueado
        this.intervaloBloqueo = setInterval(async () => {
            const desbloqueado = await this.verificarEstadoDesbloqueo();
            if (desbloqueado) {
                clearInterval(this.intervaloBloqueo);
                this.desbloquearPantalla();
            }
        }, 5000); // Verificar cada 5 segundos
    }
    
    /**
     * Verificar si el asesor ha sido desbloqueado
     */
    async verificarEstadoDesbloqueo() {
        try {
            const response = await fetch('index.php?action=verificar_estado_bloqueo');
            if (response.ok) {
                const data = await response.json();
                return data.desbloqueado;
            }
        } catch (error) {
            console.error('AsesorTiempos: Error al verificar estado de desbloqueo:', error);
        }
        return false;
    }
    
    /**
     * Desbloquear pantalla
     */
    desbloquearPantalla() {
        console.log('AsesorTiempos: Pantalla desbloqueada');
        
        // Limpiar intervalo de verificación
        if (this.intervaloBloqueo) {
            clearInterval(this.intervaloBloqueo);
            this.intervaloBloqueo = null;
        }
        
        // Remover pantalla de bloqueo
        const pantallaBloqueo = document.getElementById('pantalla-bloqueo');
        if (pantallaBloqueo) {
            pantallaBloqueo.remove();
        }
        
        // Mostrar contenido nuevamente
        const elementosMostrar = document.querySelectorAll('body > *');
        elementosMostrar.forEach(el => {
            if (el.tagName !== 'SCRIPT' && !el.id.includes('pantalla-bloqueo')) {
                el.style.display = '';
            }
        });
        
        // Recargar página para reiniciar estado
        window.location.reload();
    }

    /**
     * Finalizar sesión
     */
    async finalizarSesion() {
        if (!this.sesionId) {
            console.warn('AsesorTiempos: No hay sesión para finalizar');
            return;
        }
        
        try {
            // Actualizar tiempo final
            await this.actualizarTiempoEnBaseDatos();
            
            const response = await fetch('index.php?action=finalizar_sesion_tiempo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sesion_id: this.sesionId
                })
            });
            
            if (!response.ok) {
                throw new Error('Error al finalizar sesión');
            }
            
            const data = await response.json();
            
            if (data.success) {
                console.log('AsesorTiempos: Sesión finalizada');
                this.limpiar();
            }
            
        } catch (error) {
            console.error('AsesorTiempos: Error al finalizar sesión:', error);
        }
    }

    /**
     * Limpiar intervalos y localStorage
     */
    limpiar() {
        if (this.intervaloReloj) {
            clearInterval(this.intervaloReloj);
        }
        
        if (this.intervaloActualizacion) {
            clearInterval(this.intervaloActualizacion);
        }
        
        // Limpiar localStorage
        try {
            localStorage.removeItem('asesorTiemposGlobal');
            console.log('AsesorTiempos: localStorage limpiado');
        } catch (e) {
            console.error('AsesorTiempos: Error al limpiar localStorage:', e);
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('AsesorTiempos: Inicializando sistema de medición de tiempo');
    window.asesorTiempos = new AsesorTiempos();
});

// Guardar estado antes de cerrar la ventana
window.addEventListener('beforeunload', function() {
    if (window.asesorTiempos) {
        // Guardar estado actual en localStorage antes de cerrar (si está logueado)
        const userLoggedIn = localStorage.getItem('asesorLoggedIn');
        if (userLoggedIn === 'true') {
            window.asesorTiempos.sincronizarEstado();
            console.log('AsesorTiempos: Estado guardado en localStorage antes de cerrar');
        }
    }
});

// Interceptar clic en logout para finalizar sesión correctamente
document.addEventListener('DOMContentLoaded', function() {
    console.log('AsesorTiempos: Registrando listener para logout');
    
    // Intentar obtener el botón de logout
    const checkLogoutButton = () => {
        const logoutLink = document.getElementById('logout-link');
        
        if (logoutLink && !logoutLink.dataset.listenerAdded) {
            console.log('AsesorTiempos: Agregando listener al botón de logout');
            logoutLink.dataset.listenerAdded = 'true';
            
            logoutLink.addEventListener('click', async function(e) {
                e.preventDefault();
                
                console.log('AsesorTiempos: Logout detectado, finalizando sesión...');
                
                try {
                    // Finalizar sesión de tiempo si existe
                    if (window.asesorTiempos && typeof window.asesorTiempos.finalizarSesion === 'function') {
                        console.log('AsesorTiempos: Finalizando sesión de tiempo...');
                        await window.asesorTiempos.finalizarSesion();
                    }
                    
                    // Limpiar localStorage
                    localStorage.removeItem('asesorTiemposGlobal');
                    localStorage.removeItem('asesorLoggedIn');
                    console.log('AsesorTiempos: localStorage limpiado');
                    
                    // Redirigir al logout
                    window.location.href = 'index.php?action=logout';
                    
                } catch (error) {
                    console.error('AsesorTiempos: Error al finalizar sesión:', error);
                    // Limpiar localStorage de todas formas
                    localStorage.removeItem('asesorTiemposGlobal');
                    localStorage.removeItem('asesorLoggedIn');
                    // Redirigir al logout
                    window.location.href = 'index.php?action=logout';
                }
            });
        } else if (!logoutLink) {
            // Intentar nuevamente después de un momento
            setTimeout(checkLogoutButton, 100);
        }
    };
    
    checkLogoutButton();
});

