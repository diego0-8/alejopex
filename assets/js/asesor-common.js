/**
 * Funciones Comunes para Vistas del Asesor
 * Este archivo contiene funciones compartidas entre asesor_dashboard.php y asesor_gestionar.php
 * para evitar duplicación de código
 */

/**
 * Función para abrir/cerrar modal de tiempo de sesión
 */
function toggleTiempoModal() {
    const modalTiempo = document.getElementById('modal-tiempo-sesion');
    const modalPausa = document.getElementById('modal-pausa');
    
    // Si está en pausa, mostrar el modal de pausa en vez del de tiempo
    if (window.asesorTiemposGlobal && window.asesorTiemposGlobal.estaPausado) {
        if (modalPausa) {
            modalPausa.style.display = 'flex';
        }
        // No abrir el modal de tiempo si está en pausa
        return;
    }
    
    // Si no está en pausa, mostrar el modal de tiempo normal
    if (modalTiempo) {
        modalTiempo.style.display = modalTiempo.style.display === 'none' ? 'flex' : 'none';
    }
}

/**
 * Funciones para iniciar diferentes tipos de pausas
 */
function iniciarPausaBreak() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarPausa('break');
    }
}

function iniciarPausaAlmuerzo() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarPausa('almuerzo');
    }
}

function iniciarPausaBano() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarPausa('bano');
    }
}

function iniciarPausaMantenimiento() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarPausa('mantenimiento');
    }
}

function iniciarPausaActiva() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarPausa('pausa_activa');
    }
}

/**
 * Función para finalizar pausa
 */
function finalizarPausa() {
    if (window.asesorTiempos) {
        window.asesorTiempos.finalizarPausa();
    }
}

/**
 * Funciones para actividad extra
 */
function iniciarActividadExtra() {
    if (window.asesorTiempos) {
        window.asesorTiempos.iniciarActividadExtra();
    }
}

function finalizarActividadExtra() {
    if (window.asesorTiempos) {
        window.asesorTiempos.finalizarActividadExtra();
    }
}

/**
 * Función para comunicarse con el softphone del padre (layout_asesor.php)
 * Permite hacer llamadas desde el iframe hacia el softphone persistente
 */
function llamarDesdeWebRTC(numero) {
    // Si estamos en un iframe, enviar mensaje al padre
    if (window.parent && window.parent !== window) {
        console.log('📞 Enviando solicitud de llamada al softphone del padre:', numero);
        window.parent.postMessage({
            type: 'makeCall',
            number: numero
        }, '*'); // Usa el dominio específico en producción
    } else {
        // Si no estamos en iframe, intentar usar el softphone local (fallback)
        if (typeof window.webrtcSoftphone !== 'undefined' && 
            window.webrtcSoftphone !== null && 
            window.webrtcSoftphone.callNumber) {
            window.webrtcSoftphone.callNumber(numero);
        } else {
            console.warn('⚠️ Softphone no disponible. Asegúrate de estar en el layout principal.');
        }
    }
}

/**
 * Funciones para verificación de contraseña al reanudar pausa
 */
let intentosVerificacion = 3;

function mostrarModalVerificacion() {
    const modal = document.getElementById('modal-verificacion-contrasena');
    if (modal) {
        modal.style.display = 'flex';
        const inputContrasena = document.getElementById('input-contrasena-verificacion');
        const mensajeError = document.getElementById('mensaje-error-verificacion');
        const intentosRestantes = document.getElementById('intentos-restantes');
        
        if (inputContrasena) inputContrasena.value = '';
        if (mensajeError) mensajeError.style.display = 'none';
        intentosVerificacion = 3;
        if (intentosRestantes) intentosRestantes.textContent = '3';
    }
}

function cerrarModalVerificacion() {
    const modal = document.getElementById('modal-verificacion-contrasena');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function verificarContrasena() {
    const inputContrasena = document.getElementById('input-contrasena-verificacion');
    const mensajeError = document.getElementById('mensaje-error-verificacion');
    const intentosRestantes = document.getElementById('intentos-restantes');
    
    if (!inputContrasena) {
        console.error('Input de contraseña no encontrado');
        return;
    }
    
    const contrasena = inputContrasena.value;
    
    if (!contrasena) {
        alert('Por favor ingrese su contraseña');
        return;
    }
    
    try {
        const response = await fetch('index.php?action=verificar_contrasena', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contrasena: contrasena
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Contraseña correcta, cerrar modal de verificación
            cerrarModalVerificacion();
            
            // Finalizar la pausa
            if (window.asesorTiempos) {
                window.asesorTiempos.finalizarPausa();
            }
            
            intentosVerificacion = 3;
        } else {
            // Contraseña incorrecta
            intentosVerificacion--;
            
            if (intentosVerificacion > 0) {
                if (mensajeError) {
                    mensajeError.style.display = 'block';
                }
                if (intentosRestantes) {
                    intentosRestantes.textContent = intentosVerificacion;
                }
                if (inputContrasena) {
                    inputContrasena.value = '';
                }
            } else {
                alert('Demasiados intentos fallidos. La cuenta será bloqueada temporalmente por seguridad.');
                window.location.href = 'index.php?action=logout';
            }
        }
    } catch (error) {
        console.error('Error al verificar contraseña:', error);
        alert('Error al verificar la contraseña. Por favor intente nuevamente.');
    }
}

/**
 * Función para notificar al padre (layout_asesor.php) sobre cambios de acción
 * Esto actualiza el título y el navbar en el layout principal
 */
function notificarCambioAccion(action, title) {
    if (window.parent && window.parent !== window) {
        // Notificar cambio de navegación
        window.parent.postMessage({
            type: 'iframeNavigation',
            title: title || 'Asesor',
            action: action
        }, '*');
        
        // También notificar cambio de acción para actualizar el navbar
        window.parent.postMessage({
            type: 'actionChanged',
            action: action
        }, '*');
    }
}

