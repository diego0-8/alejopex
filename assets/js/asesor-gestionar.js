// ========================================
// GESTIÓN DE CLIENTES - ASESOR (DATOS REALES)
// ========================================

let clienteId = null;
let clienteData = null;
let inicioGestion = null; // Registro de inicio de gestión
let sesionIdGestion = null; // ID de sesión para esta gestión

// Inicializar la vista cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Asesor_gestionar.js: Inicializando vista de gestión');
    
    // Obtener ID del cliente desde la URL
    const urlParams = new URLSearchParams(window.location.search);
    clienteId = urlParams.get('cliente_id');
    
    if (!clienteId) {
        console.error('Asesor_gestionar.js: No se encontró ID del cliente');
        mostrarError('No se encontró el ID del cliente');
        return;
    }
    
    console.log('Asesor_gestionar.js: Cargando datos del cliente:', clienteId);
    
    // Registrar inicio de gestión del cliente
    iniciarGestionCliente();
    
    // Cargar datos del cliente
    cargarDatosCliente();
    
    // Cargar contratos
    cargarContratos();
    
    // Cargar historial
    cargarHistorial();
});

// ========================================
// CARGA DE DATOS REALES
// ========================================

function cargarDatosCliente() {
    console.log('Asesor_gestionar.js: Cargando datos del cliente:', clienteId);
    
    // Hacer petición AJAX para obtener datos reales
    fetch(`index.php?action=obtener_datos_cliente&cliente_id=${clienteId}`)
        .then(response => {
            console.log('Asesor_gestionar.js: Respuesta recibida:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Asesor_gestionar.js: Datos recibidos:', data);
            
            if (data.success) {
                console.log('Asesor_gestionar.js: Datos del cliente obtenidos:', data.cliente);
                const datosCliente = data.cliente;
                
                // Validar que datosCliente existe y tiene las propiedades necesarias
                if (!datosCliente) {
                    throw new Error('Datos del cliente no válidos');
                }
                
                // Actualizar datos personales con validaciones
                document.getElementById('cliente-nombre-completo').textContent = datosCliente.nombre || 'N/A';
                document.getElementById('cliente-cedula').textContent = datosCliente.cc || datosCliente.identificacion || 'N/A';
                
                // Configurar teléfonos con validación (cel1 a cel6)
                configurarTelefonos(datosCliente);
                
                // Configurar email (solo mostrar si existe)
                configurarEmail(datosCliente);
                
                clienteData = datosCliente;
                console.log('Asesor_gestionar.js: Datos del cliente cargados exitosamente');
            } else {
                console.error('Asesor_gestionar.js: Error al cargar datos:', data.message);
                mostrarError('Error al cargar datos del cliente: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Asesor_gestionar.js: Error en la petición:', error);
            mostrarError('Error de conexión al cargar datos del cliente: ' + error.message);
        });
}

function cargarContratos() {
    console.log('Asesor_gestionar.js: Cargando contratos del cliente:', clienteId);
    
    // Hacer petición AJAX para obtener contratos reales
    fetch(`index.php?action=obtener_contratos_cliente&cliente_id=${clienteId}`)
        .then(response => {
            console.log('Asesor_gestionar.js: Respuesta de contratos recibida:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Asesor_gestionar.js: Datos recibidos:', data);
            
            if (data.success) {
                // Priorizar obligaciones, pero mantener compatibilidad con facturas y contratos
                let obligacionesData = [];
                if (data.obligaciones && Array.isArray(data.obligaciones)) {
                    obligacionesData = data.obligaciones;
                } else if (data.facturas && Array.isArray(data.facturas)) {
                    obligacionesData = data.facturas;
                } else if (data.contratos && Array.isArray(data.contratos)) {
                    obligacionesData = data.contratos;
                }
                
                console.log('Asesor_gestionar.js: Obligaciones obtenidas:', obligacionesData);
                mostrarContratos(obligacionesData);
            } else {
                console.error('Asesor_gestionar.js: Error al cargar obligaciones:', data.message);
                mostrarErrorContratos('Error al cargar obligaciones: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Asesor_gestionar.js: Error en la petición de obligaciones:', error);
            mostrarErrorContratos('Error de conexión al cargar obligaciones: ' + error.message);
        });
}

function mostrarContratos(obligaciones) {
    const container = document.getElementById('contratos-container');
    const titulo = document.getElementById('contratos-titulo');
    
    // Validar que los elementos existen
    if (!container) {
        console.error('Asesor_gestionar.js: No se encontró el contenedor de obligaciones');
        return;
    }
    
    if (!titulo) {
        console.error('Asesor_gestionar.js: No se encontró el título de obligaciones');
        return;
    }
    
    // Asegurar que obligaciones sea un array válido
    if (!Array.isArray(obligaciones)) {
        console.warn('Asesor_gestionar.js: obligaciones no es un array:', obligaciones);
        obligaciones = [];
    }
    
    if (obligaciones.length === 0) {
        titulo.textContent = 'Obligaciones (0)';
        container.innerHTML = `
            <div class="sin-contratos">
                <i class="fas fa-file-invoice-dollar"></i>
                <p>No hay obligaciones registradas para este cliente</p>
            </div>
        `;
        return;
    }
    
    // Calcular total usando saldo_total
    const total = obligaciones.reduce((sum, obligacion) => {
        const valor = parseFloat(obligacion.saldo_total || obligacion.SALDO_TOTAL || 0);
        return sum + valor;
    }, 0);
    titulo.textContent = `Obligaciones (${obligaciones.length}) - Total: $${total.toLocaleString()}`;
    
    // Generar HTML de obligaciones (en una sola columna)
    let html = '';
    obligaciones.forEach((obligacion, index) => {
        const diasMora = obligacion.dias_mora || obligacion.DIAS_MORA || 0;
        const saldoTotal = parseFloat(obligacion.saldo_total || obligacion.SALDO_TOTAL || 0);
        const saldoCapital = parseFloat(obligacion.saldo_capital || obligacion.SALDO_CAPITAL || 0);
        const numeroObligacion = obligacion.numero_obligacion || obligacion.NUMERO_OBLIGACION || 'N/A';
        const producto = obligacion.producto || obligacion.PRODUCTO || 'N/A';
        const estado = obligacion.estado || obligacion.ESTADO || 'vigente';
        
        html += `
            <div class="contrato-item">
                <div class="contrato-header">
                    <h4><i class="fas fa-file-invoice-dollar"></i> Obligación #${index + 1}</h4>
                </div>
                <div class="contrato-info">
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-hashtag"></i> Número de Obligación:</span>
                        <span class="info-value">${numeroObligacion}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-box"></i> Producto:</span>
                        <span class="info-value">${producto}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-coins"></i> Saldo Total:</span>
                        <span class="info-value total-cartera">$${saldoTotal.toLocaleString()}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-money-bill-wave"></i> Saldo Capital:</span>
                        <span class="info-value">$${saldoCapital.toLocaleString()}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-calendar-times"></i> Días en Mora:</span>
                        <span class="dias-mora">${diasMora} días</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-check-circle"></i> Estado:</span>
                        <span class="estado-activo">${estado}</span>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Llenar el selector de obligaciones en el árbol de tipificación
    llenarSelectorContratos(obligaciones);
}

function llenarSelectorContratos(obligaciones) {
    const select = document.getElementById('contrato-gestionar');
    const opcionesTodas = document.getElementById('opciones-todas-facturas');
    
    if (!select) {
        console.error('Asesor_gestionar.js: No se encontró el selector de obligaciones');
        return;
    }
    
    // Limpiar opciones existentes pero mantener las opciones base
    select.innerHTML = '<option value="">Selecciona una obligación (opcional)</option>';
    select.innerHTML += '<option value="ninguna">Ninguna (Cliente no quiso pagar ninguna)</option>';
    
    // Agregar opciones según la cantidad de obligaciones
    if (obligaciones && obligaciones.length > 0) {
        // Solo agregar opción "Todas" si hay MÁS DE UNA obligación
        if (obligaciones.length > 1) {
            const optionTodas = document.createElement('option');
            optionTodas.value = 'todas';
            optionTodas.textContent = 'Todas las obligaciones';
            select.appendChild(optionTodas);
        }
        
        // Asegurar que las opciones estén ocultas inicialmente
        if (opcionesTodas) {
            opcionesTodas.style.display = 'none';
        }
        
        // Agregar opciones para cada obligación
        obligaciones.forEach((obligacion, index) => {
            const numero = obligacion.numero_obligacion || obligacion.NUMERO_OBLIGACION || 'N/A';
            const valor = parseFloat(obligacion.saldo_total || obligacion.SALDO_TOTAL || 0);
            const diasMora = obligacion.dias_mora || obligacion.DIAS_MORA || 0;
            
            const option = document.createElement('option');
            option.value = numero;
            option.textContent = `${numero} - $${valor.toLocaleString()} (${diasMora} días mora)`;
            select.appendChild(option);
        });
        
        // Solo agregar listener si hay más de una obligación (cuando existe opción "Todas")
        if (obligaciones.length > 1) {
            // Remover listeners previos si existen
            select.removeEventListener('change', manejarCambioSelectorFacturas);
            select.addEventListener('change', manejarCambioSelectorFacturas);
        }
    } else {
        // Ocultar opciones si no hay obligaciones
        if (opcionesTodas) {
            opcionesTodas.style.display = 'none';
        }
    }
    
    console.log('Asesor_gestionar.js: Selector de obligaciones llenado con', obligaciones ? obligaciones.length : 0, 'obligaciones');
    console.log('Asesor_gestionar.js: Opción "Todas" agregada:', obligaciones && obligaciones.length > 1 ? 'Sí' : 'No');
}

// Función para manejar el cambio en el selector de facturas
function manejarCambioSelectorFacturas() {
    const select = document.getElementById('contrato-gestionar');
    const opcionesTodas = document.getElementById('opciones-todas-facturas');
    
    if (!select || !opcionesTodas) {
        return;
    }
    
    if (select.value === 'todas') {
        // Mostrar opciones de tipificar todas o ninguna
        opcionesTodas.style.display = 'block';
    } else {
        // Ocultar opciones cuando se selecciona una factura individual
        opcionesTodas.style.display = 'none';
        // Limpiar selección de radio buttons
        const radioTodas = document.getElementById('radio-todas');
        const radioNinguna = document.getElementById('radio-ninguna');
        if (radioTodas) radioTodas.checked = false;
        if (radioNinguna) radioNinguna.checked = false;
    }
}

// Función para manejar la selección de tipificar todas o ninguna
function manejarSeleccionFacturas(opcion) {
    const select = document.getElementById('contrato-gestionar');
    
    if (!select || select.value !== 'todas') {
        return;
    }
    
    if (opcion === 'todas') {
        console.log('Asesor_gestionar.js: Se seleccionó tipificar todas las facturas');
        // Mantener la selección de "Todas" en el selector
        select.value = 'todas';
    } else if (opcion === 'ninguna') {
        console.log('Asesor_gestionar.js: Se seleccionó no tipificar ninguna factura');
        // Limpiar selección del selector
        select.value = '';
        // Ocultar opciones
        const opcionesTodas = document.getElementById('opciones-todas-facturas');
        if (opcionesTodas) {
            opcionesTodas.style.display = 'none';
        }
    }
}

function mostrarErrorContratos(mensaje) {
    const container = document.getElementById('contratos-container');
    const titulo = document.getElementById('contratos-titulo');
    
    // Validar que los elementos existen
    if (!container) {
        console.error('Asesor_gestionar.js: No se encontró el contenedor de contratos (error)');
        return;
    }
    
    if (titulo) {
        titulo.textContent = 'Contratos - Error';
    }
    
    container.innerHTML = `
        <div class="error-contratos">
            <i class="fas fa-exclamation-triangle"></i>
            <p>${mensaje}</p>
        </div>
    `;
}

// ========================================
// FUNCIONES DE TELÉFONOS
// ========================================

function configurarTelefonos(datosCliente) {
    console.log('Asesor_gestionar.js: Configurando teléfonos');
    console.log('Asesor_gestionar.js: datosCliente:', datosCliente);
    console.log('Asesor_gestionar.js: datosCliente.telefonos:', datosCliente.telefonos);
    console.log('Asesor_gestionar.js: Tipo de telefonos:', typeof datosCliente.telefonos);
    console.log('Asesor_gestionar.js: Es array:', Array.isArray(datosCliente.telefonos));
    
    const container = document.getElementById('telefonos-cliente');
    
    // Validar que datosCliente existe
    if (!datosCliente) {
        console.error('Asesor_gestionar.js: datosCliente es null o undefined');
        container.innerHTML = '<span>Error: Datos del cliente no disponibles</span>';
        return;
    }
    
    // Extraer celulares del cliente (cel1 a cel6)
    let celulares = [];
    for (let i = 1; i <= 6; i++) {
        const celular = datosCliente[`cel${i}`];
        if (celular && celular.trim() !== '' && celular !== '0' && celular !== 'NULL' && celular !== 'null') {
            celulares.push({
                numero: celular,
                tipo: `Celular ${i}`
            });
        }
    }
    
    console.log('Asesor_gestionar.js: Celulares encontrados:', celulares);
    
    if (celulares.length === 0) {
        container.innerHTML = '<span>No hay celulares registrados</span>';
        return;
    }
    
    // Crear desplegable de celulares + campo clickeable para copiar al softphone
    let html = '<div class="telefono-selector-container">';
    
    // Selector de teléfono
    html += '<div>';
    html += '<label for="telefono-select">Teléfono:</label>';
    html += '<select id="telefono-select" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-weight: 500; background: white; cursor: pointer; width: 100%; box-sizing: border-box;">';
    
    celulares.forEach((celular, index) => {
        html += `<option value="${celular.numero}">${celular.numero}</option>`;
    });
    
    html += '</select>';
    html += '</div>';
    
    // Campo de texto clickeable para copiar al softphone
    html += '<div>';
    html += '<label for="telefono-softphone">Llamar:</label>';
    html += '<div style="position: relative; width: 100%; box-sizing: border-box;">';
    html += '<input type="text" id="telefono-softphone" readonly class="telefono-softphone-input" style="width: 100%; padding: 10px 40px 10px 12px; border: 2px solid #007bff; border-radius: 4px; font-size: 14px; background: #f8f9fa; font-weight: 600; color: #007bff; cursor: pointer; transition: all 0.3s ease; box-sizing: border-box;" placeholder="Haz clic para copiar al softphone" title="Haz clic para copiar este número al softphone">';
    html += '<i class="fas fa-phone-alt" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #007bff; pointer-events: none;"></i>';
    html += '</div>';
    html += '</div>';
    
    html += '</div>';
    
    container.innerHTML = html;
    
    // Configurar eventos
    const select = document.getElementById('telefono-select');
    const campoSoftphone = document.getElementById('telefono-softphone');
    
    if (select && campoSoftphone) {
        // Función para copiar número al softphone e iniciar llamada automáticamente
        const copiarAlSoftphone = function(numero) {
            console.log('Asesor_gestionar.js: Copiando número al softphone e iniciando llamada:', numero);
            
            // Verificar que el softphone esté disponible
            if (typeof window.webrtcSoftphone === 'undefined' || window.webrtcSoftphone === null) {
                console.warn('Asesor_gestionar.js: Softphone no está disponible aún');
                mostrarMensajeTemporal('El softphone se está inicializando. Por favor, espera un momento.', 'warning');
                return false;
            }
            
            // Usar callNumber() que establece el número e inicia la llamada automáticamente
            if (typeof window.webrtcSoftphone.callNumber === 'function') {
                window.webrtcSoftphone.callNumber(numero);
                mostrarMensajeTemporal('Llamada iniciada automáticamente.', 'success');
                return true;
            } 
            // Fallback: si callNumber no existe, usar setNumber y luego makeCall
            else if (typeof window.webrtcSoftphone.setNumber === 'function' && typeof window.webrtcSoftphone.makeCall === 'function') {
                window.webrtcSoftphone.setNumber(numero);
                setTimeout(() => {
                    window.webrtcSoftphone.makeCall();
                }, 100);
                mostrarMensajeTemporal('Llamada iniciada automáticamente.', 'success');
                return true;
            } 
            // Fallback alternativo: establecer directamente el número y actualizar el display
            else if (typeof window.webrtcSoftphone.currentNumber !== 'undefined') {
                window.webrtcSoftphone.currentNumber = numero;
                if (typeof window.webrtcSoftphone.updateNumberDisplay === 'function') {
                    window.webrtcSoftphone.updateNumberDisplay();
                }
                if (typeof window.webrtcSoftphone.makeCall === 'function') {
                    setTimeout(() => {
                        window.webrtcSoftphone.makeCall();
                    }, 100);
                    mostrarMensajeTemporal('Llamada iniciada automáticamente.', 'success');
                } else {
                    mostrarMensajeTemporal('Número copiado al softphone. Presiona el botón de llamar.', 'success');
                }
                return true;
            } else {
                console.error('Asesor_gestionar.js: No se pudo copiar el número al softphone');
                mostrarMensajeTemporal('Error al copiar el número. Intenta nuevamente.', 'error');
                return false;
            }
        };
        
        // Evento para actualizar el campo cuando cambia la selección
        select.addEventListener('change', function() {
            const numeroSeleccionado = this.value;
            campoSoftphone.value = numeroSeleccionado;
            console.log('Asesor_gestionar.js: Celular seleccionado:', numeroSeleccionado);
        });
        
        // Evento de clic en el campo de texto para copiar al softphone
        campoSoftphone.addEventListener('click', function() {
            const numero = this.value;
            if (numero && numero.trim() !== '') {
                copiarAlSoftphone(numero);
                
                // Efecto visual de feedback
                this.style.background = '#d4edda';
                this.style.borderColor = '#28a745';
                setTimeout(() => {
                    this.style.background = '#f8f9fa';
                    this.style.borderColor = '#007bff';
                }, 500);
            } else {
                mostrarMensajeTemporal('No hay número seleccionado', 'warning');
            }
        });
        
        // También permitir copiar al portapapeles con doble clic
        campoSoftphone.addEventListener('dblclick', function() {
            const numero = this.value;
            if (numero && numero.trim() !== '') {
                // Copiar al portapapeles
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(numero).then(() => {
                        mostrarMensajeTemporal('Número copiado al portapapeles', 'success');
                    }).catch(() => {
                        // Fallback para navegadores antiguos
                        this.select();
                        document.execCommand('copy');
                        mostrarMensajeTemporal('Número copiado al portapapeles', 'success');
                    });
                } else {
                    // Fallback para navegadores antiguos
                    this.select();
                    document.execCommand('copy');
                    mostrarMensajeTemporal('Número copiado al portapapeles', 'success');
                }
            }
        });
        
        // Seleccionar automáticamente el primer celular
        if (celulares.length > 0) {
            select.value = celulares[0].numero;
            campoSoftphone.value = celulares[0].numero;
            console.log('Asesor_gestionar.js: Primer celular seleccionado:', celulares[0].numero);
        }
    }
    
    console.log('Asesor_gestionar.js: Desplegable de celulares configurado exitosamente');
}

/**
 * Configurar email del cliente (solo mostrar si existe)
 * @param {object} datosCliente - Datos del cliente
 */
function configurarEmail(datosCliente) {
    console.log('Asesor_gestionar.js: Configurando email del cliente');
    
    const emailContainer = document.getElementById('cliente-email-container');
    const emailSpan = document.getElementById('cliente-email');
    
    if (!emailContainer || !emailSpan) {
        console.warn('Asesor_gestionar.js: No se encontraron los elementos del email');
        return;
    }
    
    // Obtener email del cliente
    const email = datosCliente.email || datosCliente.EMAIL || null;
    
    // Validar que el email no esté vacío
    const tieneEmail = email && email.trim() !== '' && email !== 'null' && email !== 'NULL' && email.toLowerCase() !== 'null';
    
    if (tieneEmail) {
        // Mostrar el contenedor y establecer el email
        emailContainer.style.display = 'flex';
        emailSpan.textContent = email.trim();
        emailSpan.style.color = '#007bff';
        emailSpan.style.fontWeight = '500';
        console.log('Asesor_gestionar.js: Email mostrado:', email.trim());
    } else {
        // Ocultar el contenedor si no hay email
        emailContainer.style.display = 'none';
        console.log('Asesor_gestionar.js: Cliente sin email registrado, campo oculto');
    }
}

// Función auxiliar para mostrar mensajes temporales
function mostrarMensajeTemporal(mensaje, tipo = 'info') {
    // Crear elemento de mensaje si no existe
    let mensajeDiv = document.getElementById('mensaje-temporal-telefono');
    if (!mensajeDiv) {
        mensajeDiv = document.createElement('div');
        mensajeDiv.id = 'mensaje-temporal-telefono';
        mensajeDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;';
        document.body.appendChild(mensajeDiv);
    }
    
    // Colores según el tipo
    const colores = {
        success: { bg: '#d4edda', border: '#28a745', text: '#155724' },
        error: { bg: '#f8d7da', border: '#dc3545', text: '#721c24' },
        warning: { bg: '#fff3cd', border: '#ffc107', text: '#856404' },
        info: { bg: '#d1ecf1', border: '#17a2b8', text: '#0c5460' }
    };
    
    const color = colores[tipo] || colores.info;
    mensajeDiv.style.background = color.bg;
    mensajeDiv.style.border = `2px solid ${color.border}`;
    mensajeDiv.style.color = color.text;
    mensajeDiv.textContent = mensaje;
    mensajeDiv.style.display = 'block';
    mensajeDiv.style.opacity = '1';
    
    // Ocultar después de 3 segundos
    setTimeout(() => {
        mensajeDiv.style.opacity = '0';
        setTimeout(() => {
            mensajeDiv.style.display = 'none';
        }, 300);
    }, 3000);
}

// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

function calcularDiasMora(fechaVencimiento) {
    if (!fechaVencimiento) return 0;
    
    const hoy = new Date();
    const vencimiento = new Date(fechaVencimiento);
    const diffTime = hoy - vencimiento;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return Math.max(0, diffDays);
}

function obtenerFranja(diasMora) {
    if (diasMora <= 30) return 'MENOR A 30';
    if (diasMora <= 60) return '30 A 60';
    if (diasMora <= 90) return '60 A 90';
    return 'MAYOR A 90';
}

function formatearFecha(fecha) {
    if (!fecha) return 'N/A';
    
    const date = new Date(fecha);
    return date.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function cargarHistorial() {
    console.log('Asesor_gestionar.js: Cargando historial del cliente:', clienteId);
    
    // Hacer petición AJAX para obtener historial
    fetch(`index.php?action=obtener_historial_gestiones&cliente_id=${clienteId}`)
        .then(response => {
            console.log('Asesor_gestionar.js: Respuesta de historial recibida:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Asesor_gestionar.js: Datos de historial recibidos:', data);
            
            if (data.success) {
                console.log('Asesor_gestionar.js: Gestiones obtenidas:', data.gestiones);
                mostrarHistorial(data.gestiones);
            } else {
                console.error('Asesor_gestionar.js: Error al cargar historial:', data.message);
                mostrarErrorHistorial('Error al cargar historial: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Asesor_gestionar.js: Error en la petición de historial:', error);
            mostrarErrorHistorial('Error de conexión al cargar historial: ' + error.message);
        });
}

function mostrarHistorial(gestiones) {
    const container = document.getElementById('historial-container');
    
    if (!container) {
        console.error('Asesor_gestionar.js: No se encontró el contenedor de historial');
        return;
    }
    
    if (gestiones.length === 0) {
        container.innerHTML = `
            <div class="historial-vacio">
                <i class="fas fa-info-circle"></i>
                <p>Sin historial: Este cliente no tiene gestiones registradas aún.</p>
            </div>
        `;
        return;
    }
    
    // Generar HTML del historial
    let html = '<div class="historial-lista">';
    
    gestiones.forEach((gestion, index) => {
        const fecha = formatearFecha(gestion.fecha_creacion);
        const canal = gestion.canal_contacto || 'No especificado';
        const nivel2 = obtenerNivel2(gestion); // Solo mostrar nivel 2
        const canalesAuth = obtenerCanalesAutorizados(gestion);
        const asesor = gestion.asesor_nombre || 'Asesor no identificado';
        
        html += `
            <div class="historial-item">
                <div style="margin-bottom: 10px;">
                    <div style="color: #007bff; font-weight: 600;">
                        <i class="fas fa-calendar"></i> ${fecha}
                    </div>
                    <div style="margin-top: 5px;">
                        <span style="background: #6c757d; color: white; padding: 5px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-block;">
                            <i class="fas fa-user"></i> Asesor: ${asesor}
                        </span>
                    </div>
                </div>
                <div class="historial-detalle">
                    <div class="detalle-columna">
                        <p><strong>Canal:</strong> ${canal}</p>
                        <p><strong>Tipificación:</strong> ${nivel2}</p>
                        ${gestion.fecha_pago ? `<p><strong>Fecha de Pago:</strong> ${new Date(gestion.fecha_pago).toLocaleDateString('es-CO')}</p>` : ''}
                        ${gestion.valor_pago ? `<p><strong>Valor de Pago:</strong> $${parseFloat(gestion.valor_pago).toLocaleString('es-CO')}</p>` : ''}
                    </div>
                    <div class="detalle-columna">
                        <p><strong>Canales autorizados:</strong> ${canalesAuth}</p>
                        <p><strong>Factura:</strong> ${gestion.contrato_id === 'ninguna' || !gestion.contrato_id ? 'Ninguna (cliente no quiso pagar ninguna)' : gestion.contrato_id}</p>
                    </div>
                </div>
                ${gestion.observaciones ? `
                <div class="historial-observaciones">
                    <p><strong>Observaciones:</strong></p>
                    <p>${gestion.observaciones}</p>
                </div>
                ` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Función para obtener solo el nivel 2 de la tipificación
function obtenerNivel2(gestion) {
    if (!gestion.nivel2_clasificacion) return 'No especificado';
    
    const nivel2Textos = {
        // LLAMADA SALIENTE
        '1.0': 'YA PAGO',
        '1.1': 'ACUERDO DE PAGO',
        '1.2': 'RECORDATORIO',
        '1.3': 'VOLUNTAD DE PAGO',
        '2.0': 'LOCALIZADO SIN ACUERDO',
        '3.0': 'FALLECIDO',
        '4.0': 'NO CONTACTO',
        
        // WHATSAPP
        'ws_1.0': 'YA PAGO',
        'ws_1.1': 'ACUERDO DE PAGO',
        'ws_1.2': 'RECORDATORIO',
        'ws_1.3': 'VOLUNTAD DE PAGO',
        'ws_2.0': 'LOCALIZADO SIN ACUERDO',
        'ws_3.0': 'FALLECIDO',
        'ws_4.0': 'NO CONTACTO',
        
        // EMAIL
        'em_1.0': 'NO ENTREGADO',
        'em_1.1': 'ENTREGADO',
        'em_1.2': 'ENVIO DE MENSAJE A TITULAR',
        
        // RECIBIR LLAMADA
        'rc_1.0': 'YA PAGO',
        'rc_1.1': 'ACUERDO DE PAGO',
        'rc_1.2': 'VOLUNTAD DE PAGO',
        'rc_2.0': 'LOCALIZADO SIN ACUERDO',
        'rc_3.0': 'FALLECIDO',
        'rc_4.0': 'NO CONTACTO',
        
        // Valores antiguos (compatibilidad)
        '5.0': 'BUZON DE MENSAJE',
        '6.0': 'DESERTO / COLGO / NO ESCUCHA / NO ENTIENDE',
        '6.1': 'NO CONTESTA',
        '7.0': 'AQUI NO VIVE / TRABAJA / EQUIVOCADO',
        '7.1': 'TELEFONO DAÑADO / ERRADO',
        '8.0': 'FALLECIDO / OTROS',
        '2.1': 'INGRESO A PLATAFORMA / CONSULTA OFERTA',
        '2.2': 'CONFIRMA QUE SI A MSG OBJETIVO',
        '2.3': 'CONFIRMA QUE NO A MSG OBJETIVO',
        '3.1': 'RECLAMO / RENUENTE'
    };
    
    return nivel2Textos[gestion.nivel2_clasificacion] || gestion.nivel2_clasificacion;
}

function obtenerTipificacionCompleta(gestion) {
    if (!gestion.nivel1_tipo) return 'No tipificado';
    
    // Mapear Nivel 1 a texto (nueva estructura)
    const nivel1Textos = {
        'llamada_saliente': 'LLAMADA SALIENTE',
        'whatsapp': 'WHATSAPP',
        'email': 'EMAIL',
        'recibir_llamada': 'RECIBIR LLAMADA',
        // Mantener compatibilidad con valores antiguos
        'hacer_llamada': 'HACER LLAMADA',
        'interaccion': 'INTERACCION',
        '1': 'CONTACTADO',
        '2': 'NO CONTACTADO'
    };
    let resultado = nivel1Textos[gestion.nivel1_tipo] || gestion.nivel1_tipo;
    
    // Mapear Nivel 2 a texto (nueva estructura completa)
    if (gestion.nivel2_clasificacion) {
        const nivel2Texto = obtenerNivel2(gestion);
        resultado += ' > ' + nivel2Texto;
    }
    
    // Mapear Nivel 3 a texto (nueva estructura completa)
    if (gestion.nivel3_detalle) {
        const nivel3Textos = {
            // Nueva estructura
            'pago_total': 'PAGO TOTAL',
            'pago_cuota': 'PAGO CUOTA',
            'acuerdo_pago_total': 'ACUERDO PAGO TOTAL',
            'acuerdo_largo_plazo': 'ACUERDO A LARGO PLAZO',
            'acuerdo_aprobado': 'ACUERDO APROBADO COMITÉ',
            'seguimiento': 'SEGUIMIENTO NEGOCIACIÓN VIGENTE',
            'volver_llamar': 'VOLVER A LLAMAR',
            'propuesta_estudio': 'PROPUESTA EN ESTUDIO',
            'posible_negociacion': 'POSIBLE NEGOCIACION',
            'no_reconoce': 'NO RECONOCE LA OBLIGACIÓN',
            'dificultad_pago': 'DIFICULTAD DE PAGO',
            'reclamacion': 'RECLAMACIÓN',
            'renuente': 'RENUENTE',
            'contesta_cuelga': 'CONTESTA Y CUELGA',
            'contacto_tercero': 'CONTACTO CON TERCERO',
            'fallecido': 'FALLECIDO',
            'no_contesta': 'NO CONTESTA',
            'buzon_mensaje': 'BUZÓN DE MENSAJE',
            'fuera_servicio': 'FUERA DE SERVICIO',
            'numero_equivocado': 'NUMERO EQUIVOCADO',
            'telefono_apagado': 'TELÉFONO APAGADO',
            'telefono_danado': 'TELÉFONO DAÑADO',
            'ilocalizado': 'ILOCALIZADO',
            'no_entregado': 'NO ENTREGADO',
            'entregado': 'ENTREGADO',
            'envio_mensaje': 'ENVIO DE MENSAJE A TITULAR',
            
            // Valores antiguos (compatibilidad)
            '1': 'TITULAR / ENCARGADO',
            '2': 'TERCERO VALIDO',
            '3': 'NO CONTACTO',
            '4': 'ILOCALIZADO',
            '1.1.1': 'Informa fecha probable de pago',
            '1.1.2': 'Pagos parciales',
            '1.1.3': 'Inconvenientes plataforma de pago',
            '1.1.4': 'Débito automático no realizado',
            '1.1.5': 'Problemas en facturación',
            '1.1.6': 'Espera ingreso de dinero',
            '1.1.7': 'Paga un Tercero',
            '1.1.8': 'Solicitará cambio modalidad de pago',
            '1.1.9': 'No informa fecha probable'
        };
        
        resultado += ' > ' + (nivel3Textos[gestion.nivel3_detalle] || gestion.nivel3_detalle);
    }
    
    return resultado;
}

function obtenerCanalesAutorizados(gestion) {
    const canales = [];
    
    if (gestion.llamada_telefonica === 'si') canales.push('Llamada');
    if (gestion.whatsapp === 'si') canales.push('WhatsApp');
    if (gestion.correo_electronico === 'si') canales.push('Email');
    if (gestion.sms === 'si') canales.push('SMS');
    if (gestion.correo_fisico === 'si') canales.push('Correo Físico');
    if (gestion.mensajeria_aplicacion === 'si') canales.push('Mensajería');
    
    return canales.length > 0 ? canales.join(', ') : 'Ninguno';
}

function mostrarErrorHistorial(mensaje) {
    const container = document.getElementById('historial-container');
    
    if (!container) {
        console.error('Asesor_gestionar.js: No se encontró el contenedor de historial (error)');
        return;
    }
    
    container.innerHTML = `
        <div class="error-historial">
            <i class="fas fa-exclamation-triangle"></i>
            <p>${mensaje}</p>
        </div>
    `;
}

function mostrarError(mensaje) {
    console.error('Asesor_gestionar.js: Error:', mensaje);
    alert('Error: ' + mensaje);
}

// ========================================
// FUNCIONES DE NAVEGACIÓN
// ========================================

function volverTareas() {
    console.log('Asesor_gestionar.js: Volviendo a tareas');
    window.location.href = 'index.php?action=asesor_dashboard';
}

function irDashboard() {
    console.log('Asesor_gestionar.js: Yendo al dashboard');
    window.location.href = 'index.php?action=asesor_dashboard';
}

function guardarGestion() {
    console.log('Asesor_gestionar.js: Guardando gestión');
    
    // Obtener datos del formulario
    const canalContacto = document.getElementById('canal-contacto').value;
    const contratoGestionar = document.getElementById('contrato-gestionar').value;
    const nivel1 = document.getElementById('tipo-contacto-nivel1').value;
    const nivel2 = document.getElementById('tipo-contacto-nivel2').value;
    const nivel3 = document.getElementById('tipo-contacto-nivel3').value;
    const observaciones = document.getElementById('observaciones-texto').value;
    
    // Obtener fecha y valor si corresponde (solo para ACUERDO DE PAGO)
    let fechaPago = null;
    let valorPago = null;
    if (nivel2 === '1.1' || nivel2 === 'ws_1.1' || nivel2 === 'rc_1.1') {
        const fechaPagoInput = document.getElementById('fecha-pago');
        const valorPagoInput = document.getElementById('valor-pago');
        if (fechaPagoInput && fechaPagoInput.value) {
            fechaPago = fechaPagoInput.value;
        }
        if (valorPagoInput && valorPagoInput.value) {
            // Remover el símbolo $ y los separadores de miles, luego convertir a número
            const valorLimpio = valorPagoInput.value.replace(/[^\d]/g, '');
            valorPago = valorLimpio ? parseFloat(valorLimpio) : null;
        }
    }
    
    // Verificar si se seleccionó "Todas"
    if (contratoGestionar === 'todas') {
        const radioTodas = document.getElementById('radio-todas');
        const radioNinguna = document.getElementById('radio-ninguna');
        
        // Validar que se haya seleccionado una opción
        if (!radioTodas.checked && !radioNinguna.checked) {
            alert('Por favor seleccione si desea tipificar todas las facturas o ninguna');
            return;
        }
        
        // Si seleccionó "Ninguna", no hacer nada
        if (radioNinguna.checked) {
            alert('No se guardará gestión para ninguna factura');
            return;
        }
        
        // Si seleccionó "Tipificar todas", proceder con la lógica de múltiples gestiones
        // Obtener todas las facturas desde el selector (excluyendo "Todas")
        const selectFacturas = document.getElementById('contrato-gestionar');
        const todasFacturas = Array.from(selectFacturas.options)
            .filter(opt => opt.value !== '' && opt.value !== 'todas')
            .map(opt => opt.value); // Obtener solo los números de factura
        
        if (todasFacturas.length === 0) {
            alert('No hay facturas disponibles para gestionar');
            return;
        }
        
        // Guardar gestión para todas las facturas
        guardarGestionMultiplesFacturas(todasFacturas, canalContacto, nivel1, nivel2, nivel3, observaciones);
        return;
    }
    
    // Validar campos obligatorios para factura individual
    if (!nivel1) {
        alert('Por favor seleccione el Tipo de Contacto (Nivel 1)');
        return;
    }
    
    // Si no se selecciona factura, se guardará como "ninguna" (cliente no quiso pagar ninguna)
    // No es obligatorio seleccionar factura
    
    // Obtener canales de comunicación autorizados
    const canales = {
        llamada: document.getElementById('canal-llamada')?.checked || false,
        whatsapp: document.getElementById('canal-whatsapp')?.checked || false,
        email: document.getElementById('canal-email')?.checked || false,
        sms: document.getElementById('canal-sms')?.checked || false,
        correo: document.getElementById('canal-correo')?.checked || false,
        mensajeria: document.getElementById('canal-mensajeria')?.checked || false
    };
    
    // Calcular duración de la gestión
    let duracionSegundos = 0;
    if (inicioGestion) {
        const finGestion = new Date();
        duracionSegundos = Math.floor((finGestion - inicioGestion) / 1000);
        console.log('Duración de la gestión:', duracionSegundos, 'segundos');
    }
    
    // Preparar datos para enviar
    // Si no se selecciona factura o se selecciona "ninguna", guardar como "ninguna"
    const contratoIdFinal = (contratoGestionar && contratoGestionar !== '' && contratoGestionar !== 'ninguna') 
        ? contratoGestionar 
        : 'ninguna';
    
    const datosGestion = {
        canal_contacto: canalContacto || null,
        contrato_id: contratoIdFinal,
        nivel1_tipo: nivel1 || null,
        nivel2_clasificacion: nivel2 || null,
        nivel3_detalle: nivel3 || null,
        observaciones: observaciones || null,
        canales: canales,
        duracion_segundos: duracionSegundos,
        fecha_pago: fechaPago || null,
        valor_pago: valorPago || null
    };
    
    console.log('Datos de gestión a enviar:', datosGestion);
    
    // Validar que nivel1 no esté vacío
    if (!nivel1 || nivel1.trim() === '') {
        alert('Por favor seleccione el Tipo de Contacto (Nivel 1)');
        return;
    }
    
    // Enviar petición AJAX
    enviarGestion(clienteId, datosGestion);
}

// Función para guardar gestión de múltiples facturas
function guardarGestionMultiplesFacturas(facturasIds, canalContacto, nivel1, nivel2, nivel3, observaciones) {
    // Validar campos obligatorios
    if (!nivel1) {
        alert('Por favor seleccione el Tipo de Contacto (Nivel 1)');
        return;
    }
    
    // Obtener canales de comunicación autorizados
    const canales = {
        llamada: document.getElementById('canal-llamada')?.checked || false,
        whatsapp: document.getElementById('canal-whatsapp')?.checked || false,
        email: document.getElementById('canal-email')?.checked || false,
        sms: document.getElementById('canal-sms')?.checked || false,
        correo: document.getElementById('canal-correo')?.checked || false,
        mensajeria: document.getElementById('canal-mensajeria')?.checked || false
    };
    
    // Calcular duración de la gestión
    let duracionSegundos = 0;
    if (inicioGestion) {
        const finGestion = new Date();
        duracionSegundos = Math.floor((finGestion - inicioGestion) / 1000);
        console.log('Duración de la gestión:', duracionSegundos, 'segundos');
    }
    
    // Confirmar con el usuario
    const confirmacion = confirm(`¿Desea tipificar todas las ${facturasIds.length} factura(s) con los mismos datos?`);
    if (!confirmacion) {
        return;
    }
    
    // Mostrar mensaje de progreso
    alert(`Guardando gestión para ${facturasIds.length} factura(s). Por favor espere...`);
    
    // Obtener fecha y valor si corresponde
    let fechaPago = null;
    let valorPago = null;
    if (nivel2 === '1.1' || nivel2 === '1.2') {
        const fechaPagoInput = document.getElementById('fecha-pago');
        const valorPagoInput = document.getElementById('valor-pago');
        if (fechaPagoInput && fechaPagoInput.value) {
            fechaPago = fechaPagoInput.value;
        }
        if (valorPagoInput && valorPagoInput.value) {
            // Remover el símbolo $ y los separadores de miles, luego convertir a número
            const valorLimpio = valorPagoInput.value.replace(/[^\d]/g, '');
            valorPago = valorLimpio ? parseFloat(valorLimpio) : null;
        }
    }
    
    // Preparar datos base
    const datosBase = {
        canal_contacto: canalContacto || null,
        nivel1_tipo: nivel1 || null,
        nivel2_clasificacion: nivel2 || null,
        nivel3_detalle: nivel3 || null,
        observaciones: observaciones || null,
        canales: canales,
        duracion_segundos: duracionSegundos,
        fecha_pago: fechaPago || null,
        valor_pago: valorPago || null
    };
    
    // Enviar una gestión por cada factura
    let gestionesGuardadas = 0;
    let gestionesError = 0;
    const totalGestiones = facturasIds.length;
    
    // Usar Promise.all para enviar todas las gestiones
    const promesas = facturasIds.map(facturaId => {
        const datosGestion = {
            ...datosBase,
            contrato_id: facturaId
        };
        
        return fetch('index.php?action=guardar_gestion', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cliente_id: clienteId,
                datos: datosGestion
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gestionesGuardadas++;
                return { success: true, facturaId };
            } else {
                gestionesError++;
                return { success: false, facturaId, error: data.message };
            }
        })
        .catch(error => {
            gestionesError++;
            return { success: false, facturaId, error: error.message };
        });
    });
    
    // Esperar a que todas las promesas se resuelvan
    Promise.all(promesas)
        .then(async resultados => {
            // Finalizar el tiempo de gestión
            await finalizarGestionCliente();
            
            // Mostrar mensaje de resultado
            if (gestionesGuardadas === totalGestiones) {
                alert(`Gestión guardada exitosamente para todas las ${totalGestiones} factura(s)`);
            } else {
                alert(`Se guardaron ${gestionesGuardadas} de ${totalGestiones} gestión(es). ${gestionesError} error(es).`);
            }
            
            // Recargar historial después de guardar
            cargarHistorial();
            
            // Mostrar los nuevos botones después de guardar
            if (window.mostrarBotonesDespuesGuardar) {
                window.mostrarBotonesDespuesGuardar();
            }
        })
        .catch(error => {
            console.error('Error al guardar gestiones múltiples:', error);
            alert('Error al guardar algunas gestiones. Por favor revise el historial.');
        });
}

// Función auxiliar para enviar una gestión individual
function enviarGestion(clienteId, datosGestion) {
    fetch('index.php?action=guardar_gestion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            cliente_id: clienteId,
            datos: datosGestion
        })
    })
    .then(response => response.json())
    .then(async data => {
        console.log('Respuesta del servidor:', data);
        
        if (data.success) {
            // Finalizar el tiempo de gestión antes de mostrar el alert
            await finalizarGestionCliente();
            
            alert('Gestión guardada exitosamente');
            
            // Recargar historial después de guardar
            cargarHistorial();
            
            // Mostrar los nuevos botones después de guardar
            if (window.mostrarBotonesDespuesGuardar) {
                window.mostrarBotonesDespuesGuardar();
            }
        } else {
            alert('Error al guardar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error al guardar gestión:', error);
        alert('Error al conectar con el servidor');
    });
}

// ========================================
// FUNCIONES PARA AGREGAR MÁS INFORMACIÓN
// ========================================

// Agregar event listener al botón cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Botón agregar información
    const btnAgregarInfo = document.querySelector('.btn-agregar-info');
    if (btnAgregarInfo) {
        btnAgregarInfo.addEventListener('click', function() {
            mostrarModalAgregarInfo();
        });
    }
    
    // Árbol de tipificación
    configurarArbolTipificacion();
    
    // Configurar fecha mínima a hoy (solo fechas futuras)
    const fechaPagoInput = document.getElementById('fecha-pago');
    if (fechaPagoInput) {
        const hoy = new Date();
        const fechaMinima = hoy.toISOString().split('T')[0];
        fechaPagoInput.setAttribute('min', fechaMinima);
    }
    
    // Configurar formato de pesos colombianos para el input de valor
    const valorPagoInput = document.getElementById('valor-pago');
    if (valorPagoInput) {
        // Formatear mientras el usuario escribe
        valorPagoInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, ''); // Solo números
            
            if (value) {
                // Formatear con separadores de miles
                value = parseInt(value, 10).toLocaleString('es-CO');
                e.target.value = value;
            } else {
                e.target.value = '';
            }
        });
        
        // Formatear al perder el foco
        valorPagoInput.addEventListener('blur', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = parseInt(value, 10).toLocaleString('es-CO');
                e.target.value = value;
            }
        });
    }
});

// ========================================
// ÁRBOL DE TIPIFICACIÓN
// ========================================

function configurarArbolTipificacion() {
    const nivel1 = document.getElementById('tipo-contacto-nivel1');
    const nivel2 = document.getElementById('tipo-contacto-nivel2');
    const nivel3 = document.getElementById('tipo-contacto-nivel3');
    const nivel2Container = document.getElementById('nivel2-container');
    const nivel3Container = document.getElementById('nivel3-container');
    
    if (nivel1) {
        nivel1.addEventListener('change', function() {
            const valor = this.value;
            
            if (valor) {
                // Mostrar Nivel 2
                if (nivel2Container) nivel2Container.style.display = 'block';
                
                // Limpiar y actualizar Nivel 2
                if (nivel2) {
                    nivel2.innerHTML = '<option value="">Cargando...</option>';
                    actualizarNivel2(valor);
                }
                
                // Ocultar y limpiar Nivel 3
                if (nivel3Container) nivel3Container.style.display = 'none';
                if (nivel3) {
                    nivel3.innerHTML = '<option value="">Primero selecciona el Nivel 2</option>';
                }
            } else {
                // Ocultar ambos niveles
                if (nivel2Container) nivel2Container.style.display = 'none';
                if (nivel3Container) nivel3Container.style.display = 'none';
            }
        });
    }
    
    if (nivel2) {
        // Remover listener anterior si existe para evitar duplicados
        const nuevoListener = function() {
            const valor = this.value;
            const camposFechaValor = document.getElementById('campos-fecha-valor');
            
            // Mostrar/ocultar campos de fecha y valor (solo para ACUERDO DE PAGO)
            if (valor === '1.1' || valor === 'ws_1.1' || valor === 'rc_1.1') {
                if (camposFechaValor) camposFechaValor.style.display = 'block';
            } else {
                if (camposFechaValor) camposFechaValor.style.display = 'none';
            }
            
            if (valor) {
                // Mostrar Nivel 3
                if (nivel3Container) nivel3Container.style.display = 'block';
                
                // Limpiar y actualizar Nivel 3
                if (nivel3) {
                    nivel3.innerHTML = '<option value="">Cargando...</option>';
                    actualizarNivel3(valor);
                }
            } else {
                // Ocultar Nivel 3
                if (nivel3Container) nivel3Container.style.display = 'none';
            }
        };
        
        nivel2.addEventListener('change', nuevoListener);
    }
}

function actualizarNivel2(valorNivel1) {
    const nivel2 = document.getElementById('tipo-contacto-nivel2');
    const camposFechaValor = document.getElementById('campos-fecha-valor');
    
    if (!nivel2) return;
    
    // Ocultar campos de fecha y valor inicialmente
    if (camposFechaValor) camposFechaValor.style.display = 'none';
    
    // Limpiar opciones
    nivel2.innerHTML = '<option value="">Selecciona una opción</option>';
    
    if (!valorNivel1) {
        nivel2.innerHTML = '<option value="">Primero selecciona el Nivel 1</option>';
        return;
    }
    
    let opciones = [];
    
    if (valorNivel1 === 'llamada_saliente') {
        // LLAMADA SALIENTE - Nivel 2
        opciones = [
            { value: '1.0', text: 'YA PAGO' },
            { value: '1.1', text: 'ACUERDO DE PAGO' },
            { value: '1.2', text: 'RECORDATORIO' },
            { value: '1.3', text: 'VOLUNTAD DE PAGO' },
            { value: '2.0', text: 'LOCALIZADO SIN ACUERDO' },
            { value: '3.0', text: 'FALLECIDO' },
            { value: '4.0', text: 'NO CONTACTO' }
        ];
    } else if (valorNivel1 === 'whatsapp') {
        // WHATSAPP - Nivel 2
        opciones = [
            { value: 'ws_1.0', text: 'YA PAGO' },
            { value: 'ws_1.1', text: 'ACUERDO DE PAGO' },
            { value: 'ws_1.2', text: 'RECORDATORIO' },
            { value: 'ws_1.3', text: 'VOLUNTAD DE PAGO' },
            { value: 'ws_2.0', text: 'LOCALIZADO SIN ACUERDO' },
            { value: 'ws_3.0', text: 'FALLECIDO' },
            { value: 'ws_4.0', text: 'NO CONTACTO' }
        ];
    } else if (valorNivel1 === 'email') {
        // EMAIL - Nivel 2
        opciones = [
            { value: 'em_1.0', text: 'NO ENTREGADO' },
            { value: 'em_1.1', text: 'ENTREGADO' },
            { value: 'em_1.2', text: 'ENVIO DE MENSAJE A TITULAR' }
        ];
    } else if (valorNivel1 === 'recibir_llamada') {
        // RECIBIR LLAMADA - Nivel 2
        opciones = [
            { value: 'rc_1.0', text: 'YA PAGO' },
            { value: 'rc_1.1', text: 'ACUERDO DE PAGO' },
            { value: 'rc_1.2', text: 'VOLUNTAD DE PAGO' },
            { value: 'rc_2.0', text: 'LOCALIZADO SIN ACUERDO' },
            { value: 'rc_3.0', text: 'FALLECIDO' },
            { value: 'rc_4.0', text: 'NO CONTACTO' }
        ];
    }
    
    opciones.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.textContent = opcion.text;
        nivel2.appendChild(option);
    });
}

function actualizarNivel3(valorNivel2) {
    const nivel3 = document.getElementById('tipo-contacto-nivel3');
    
    if (!nivel3) return;
    
    // Limpiar opciones
    nivel3.innerHTML = '<option value="">Selecciona una opción</option>';
    
    if (!valorNivel2) {
        nivel3.innerHTML = '<option value="">Primero selecciona el Nivel 2</option>';
        return;
    }
    
    // Opciones según el nivel 2
    const opciones = getOpcionesNivel3(valorNivel2);
    
    opciones.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.textContent = opcion.text;
        nivel3.appendChild(option);
    });
}

function getOpcionesNivel3(valorNivel2) {
    // Mapeo de nivel 2 a nivel 3 según la estructura del árbol de tipificación
    const opciones = {
        // ============================================
        // LLAMADA SALIENTE
        // ============================================
        '1.0': [ // YA PAGO
            { value: 'pago_total', text: 'PAGO TOTAL' },
            { value: 'pago_cuota', text: 'PAGO CUOTA' }
        ],
        '1.1': [ // ACUERDO DE PAGO
            { value: 'acuerdo_pago_total', text: 'ACUERDO PAGO TOTAL' },
            { value: 'acuerdo_largo_plazo', text: 'ACUERDO A LARGO PLAZO' },
            { value: 'acuerdo_aprobado', text: 'ACUERDO APROBADO COMITÉ' }
        ],
        '1.2': [ // RECORDATORIO
            { value: 'seguimiento', text: 'SEGUIMIENTO NEGOCIACIÓN VIGENTE' }
        ],
        '1.3': [ // VOLUNTAD DE PAGO
            { value: 'volver_llamar', text: 'VOLVER A LLAMAR' },
            { value: 'propuesta_estudio', text: 'PROPUESTA EN ESTUDIO' },
            { value: 'posible_negociacion', text: 'POSIBLE NEGOCIACION' }
        ],
        '2.0': [ // LOCALIZADO SIN ACUERDO
            { value: 'no_reconoce', text: 'NO RECONOCE LA OBLIGACIÓN' },
            { value: 'dificultad_pago', text: 'DIFICULTAD DE PAGO' },
            { value: 'reclamacion', text: 'RECLAMACIÓN' },
            { value: 'renuente', text: 'RENUENTE' },
            { value: 'contesta_cuelga', text: 'CONTESTA Y CUELGA' },
            { value: 'contacto_tercero', text: 'CONTACTO CON TERCERO' }
        ],
        '3.0': [ // FALLECIDO
            { value: 'fallecido', text: 'FALLECIDO' }
        ],
        '4.0': [ // NO CONTACTO
            { value: 'no_contesta', text: 'NO CONTESTA' },
            { value: 'buzon_mensaje', text: 'BUZÓN DE MENSAJE' },
            { value: 'fuera_servicio', text: 'FUERA DE SERVICIO' },
            { value: 'numero_equivocado', text: 'NUMERO EQUIVOCADO' },
            { value: 'telefono_apagado', text: 'TELÉFONO APAGADO' },
            { value: 'telefono_danado', text: 'TELÉFONO DAÑADO' },
            { value: 'ilocalizado', text: 'ILOCALIZADO' }
        ],
        
        // ============================================
        // WHATSAPP
        // ============================================
        'ws_1.0': [ // YA PAGO
            { value: 'pago_total', text: 'PAGO TOTAL' },
            { value: 'pago_cuota', text: 'PAGO CUOTA' }
        ],
        'ws_1.1': [ // ACUERDO DE PAGO
            { value: 'acuerdo_pago_total', text: 'ACUERDO PAGO TOTAL' },
            { value: 'acuerdo_largo_plazo', text: 'ACUERDO A LARGO PLAZO' },
            { value: 'acuerdo_aprobado', text: 'ACUERDO APROBADO COMITÉ' }
        ],
        'ws_1.2': [ // RECORDATORIO
            { value: 'seguimiento', text: 'SEGUIMIENTO NEGOCIACIÓN VIGENTE' }
        ],
        'ws_1.3': [ // VOLUNTAD DE PAGO
            { value: 'volver_llamar', text: 'VOLVER A LLAMAR' },
            { value: 'propuesta_estudio', text: 'PROPUESTA EN ESTUDIO' },
            { value: 'posible_negociacion', text: 'POSIBLE NEGOCIACION' }
        ],
        'ws_2.0': [ // LOCALIZADO SIN ACUERDO
            { value: 'no_reconoce', text: 'NO RECONOCE LA OBLIGACIÓN' },
            { value: 'dificultad_pago', text: 'DIFICULTAD DE PAGO' },
            { value: 'reclamacion', text: 'RECLAMACIÓN' },
            { value: 'contacto_tercero', text: 'CONTACTO CON TERCERO' }
        ],
        'ws_3.0': [ // FALLECIDO
            { value: 'fallecido', text: 'FALLECIDO' }
        ],
        'ws_4.0': [ // NO CONTACTO
            { value: 'no_contesta', text: 'NO CONTESTA' },
            { value: 'numero_equivocado', text: 'NUMERO EQUIVOCADO' }
        ],
        
        // ============================================
        // EMAIL
        // ============================================
        'em_1.0': [ // NO ENTREGADO
            { value: 'no_entregado', text: 'NO ENTREGADO' }
        ],
        'em_1.1': [ // ENTREGADO
            { value: 'entregado', text: 'ENTREGADO' }
        ],
        'em_1.2': [ // ENVIO DE MENSAJE A TITULAR
            { value: 'envio_mensaje', text: 'ENVIO DE MENSAJE A TITULAR' }
        ],
        
        // ============================================
        // RECIBIR LLAMADA
        // ============================================
        'rc_1.0': [ // YA PAGO
            { value: 'pago_total', text: 'PAGO TOTAL' },
            { value: 'pago_cuota', text: 'PAGO CUOTA' }
        ],
        'rc_1.1': [ // ACUERDO DE PAGO
            { value: 'acuerdo_pago_total', text: 'ACUERDO PAGO TOTAL' },
            { value: 'acuerdo_largo_plazo', text: 'ACUERDO A LARGO PLAZO' },
            { value: 'acuerdo_aprobado', text: 'ACUERDO APROBADO COMITÉ' }
        ],
        'rc_1.2': [ // VOLUNTAD DE PAGO
            { value: 'volver_llamar', text: 'VOLVER A LLAMAR' },
            { value: 'propuesta_estudio', text: 'PROPUESTA EN ESTUDIO' },
            { value: 'posible_negociacion', text: 'POSIBLE NEGOCIACION' }
        ],
        'rc_2.0': [ // LOCALIZADO SIN ACUERDO
            { value: 'dificultad_pago', text: 'DIFICULTAD DE PAGO' },
            { value: 'reclamacion', text: 'RECLAMACIÓN' },
            { value: 'renuente', text: 'RENUENTE' },
            { value: 'contacto_tercero', text: 'CONTACTO CON TERCERO' }
        ],
        'rc_3.0': [ // FALLECIDO
            { value: 'fallecido', text: 'FALLECIDO' }
        ],
        'rc_4.0': [ // NO CONTACTO
            { value: 'numero_equivocado', text: 'NUMERO EQUIVOCADO' }
        ]
    };
    
    return opciones[valorNivel2] || [];
}

function mostrarModalAgregarInfo() {
    // Crear el modal
    const modalHTML = `
        <div id="modal-agregar-info" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: #333;"><i class="fas fa-plus"></i> Agregar Más Información</h2>
                    <span style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px;" onclick="cerrarModalAgregarInfo()">&times;</span>
                </div>
                
                <form id="form-agregar-info">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">
                            <i class="fas fa-envelope"></i> Correo Electrónico:
                        </label>
                        <input type="email" name="nuevo-email" placeholder="ejemplo@correo.com" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 500; color: #333;">
                            <i class="fas fa-phone"></i> Nuevos Teléfonos:
                        </label>
                        <div id="telefonos-contenedor">
                            <div class="telefono-input-group" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                <input type="text" name="nuevo-telefono[]" placeholder="Número de teléfono (opcional)" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <button type="button" onclick="eliminarTelefono(this)" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;" disabled><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <button type="button" onclick="agregarTelefono()" style="background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-size: 14px; width: 100%;">
                            <i class="fas fa-plus"></i> Agregar otro teléfono
                        </button>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                        <button type="button" onclick="cerrarModalAgregarInfo()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; cursor: pointer; font-size: 14px;">
                            Cancelar
                        </button>
                        <button type="submit" style="padding: 10px 20px; border: none; border-radius: 6px; background: #007bff; color: white; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Insertar el modal en el body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Agregar event listener al formulario
    document.getElementById('form-agregar-info').addEventListener('submit', function(e) {
        e.preventDefault();
        guardarNuevaInformacion();
    });
}

function cerrarModalAgregarInfo() {
    const modal = document.getElementById('modal-agregar-info');
    if (modal) {
        modal.remove();
    }
}

function agregarTelefono() {
    const contenedor = document.getElementById('telefonos-contenedor');
    const numTelefonos = contenedor.children.length;
    
    const nuevoTelefono = document.createElement('div');
    nuevoTelefono.className = 'telefono-input-group';
    nuevoTelefono.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
    nuevoTelefono.innerHTML = `
        <input type="text" name="nuevo-telefono[]" placeholder="Número de teléfono (opcional)" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        <button type="button" onclick="eliminarTelefono(this)" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;"><i class="fas fa-trash"></i></button>
    `;
    
    contenedor.appendChild(nuevoTelefono);
    
    // Habilitar botones de eliminar en todos
    actualizarBotonesEliminar();
}

// Función eliminada: actualizarTelefonoPrincipal - Ya no se usa la opción de principal

function eliminarTelefono(btn) {
    const contenedor = document.getElementById('telefonos-contenedor');
    const grupo = btn.closest('.telefono-input-group');
    
    // No permitir eliminar si es el último o si hay solo uno
    if (contenedor.children.length <= 1) {
        alert('Debe tener al menos un teléfono');
        return;
    }
    
    if (grupo) {
        grupo.remove();
        
        // Si solo queda uno, deshabilitar su botón de eliminar
        if (contenedor.children.length === 1) {
            const eliminarBtn = contenedor.querySelector('.telefono-input-group button');
            if (eliminarBtn) {
                eliminarBtn.disabled = true;
            }
        }
    }
}

// Habilitar botón de eliminar en los teléfonos adicionales
function actualizarBotonesEliminar() {
    const contenedor = document.getElementById('telefonos-contenedor');
    if (!contenedor) return;
    
    const grupos = contenedor.querySelectorAll('.telefono-input-group');
    grupos.forEach((grupo, index) => {
        const btnEliminar = grupo.querySelector('button');
        if (btnEliminar) {
            btnEliminar.disabled = grupos.length === 1;
        }
    });
}

function guardarNuevaInformacion() {
    const form = document.getElementById('form-agregar-info');
    const formData = new FormData(form);
    
    // Obtener datos del formulario
    const email = formData.get('nuevo-email');
    const telefonos = formData.getAll('nuevo-telefono[]');
    
    // Construir objeto de datos solo con campos que tienen valor
    const datosToSend = {};
    
    if (email && email.trim() !== '') {
        datosToSend.email = email.trim();
    }
    
    // Procesar teléfonos solo si se ingresaron (sin opción de principal)
    if (telefonos.some(tel => tel && tel.trim() !== '')) {
        datosToSend.telefonos = [];
        
        telefonos.forEach((tel) => {
            if (tel && tel.trim() !== '') {
                datosToSend.telefonos.push({
                    numero: tel.trim()
                });
            }
        });
    }
    
    console.log('Teléfonos ingresados:', telefonos);
    console.log('Datos a enviar:', datosToSend);
    
    console.log('Enviando datos a actualizar:', datosToSend);
    
    // Validar que hay datos para actualizar (todos los campos son opcionales)
    const tieneDatos = datosToSend.email || (datosToSend.telefonos && datosToSend.telefonos.length > 0);
    
    if (!tieneDatos) {
        alert('Por favor ingrese al menos un dato para actualizar');
        return;
    }
    
    // Hacer petición AJAX para guardar en la base de datos
    fetch('index.php?action=actualizar_info_cliente', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            cliente_id: clienteId,
            datos: datosToSend
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Respuesta del servidor:', data);
        
        if (data.success) {
            alert('Información actualizada exitosamente');
            
            // Cerrar el modal
            cerrarModalAgregarInfo();
            
            // Recargar datos del cliente para reflejar cambios (siempre, porque puede haber actualizado cualquier campo)
            if (datosToSend.email || (datosToSend.telefonos && datosToSend.telefonos.length > 0)) {
                cargarDatosCliente();
                // Si se agregó un email, también recargar para mostrarlo
                if (datosToSend.email) {
                    setTimeout(() => {
                        cargarDatosCliente();
                    }, 500);
                }
            }
            
            // Si se agregaron o modificaron teléfonos, recargar también los contratos (que incluyen el selector)
            if (datosToSend.telefonos && datosToSend.telefonos.length > 0) {
                cargarContratos();
            }
        } else {
            alert('Error al actualizar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error al actualizar información:', error);
        alert('Error al conectar con el servidor');
    });
}

// ========================================
// FUNCIONES DE TIEMPO DE GESTIÓN
// ========================================

function iniciarGestionCliente() {
    console.log('Asesor_gestionar.js: Iniciando gestión del cliente:', clienteId);
    
    // Registrar hora de inicio
    inicioGestion = new Date();
    
    // Enviar petición al servidor para iniciar el tiempo de gestión
    fetch('index.php?action=iniciar_gestion_tiempo', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            cliente_id: clienteId
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Respuesta inicio gestión:', data);
        if (data.success) {
            sesionIdGestion = data.sesion_id;
            console.log('Asesor_gestionar.js: Gestión iniciada - Sesión ID:', sesionIdGestion);
        }
    })
    .catch(error => {
        console.error('Error al iniciar gestión:', error);
    });
}

function finalizarGestionCliente() {
    if (!inicioGestion || !sesionIdGestion) {
        console.warn('Asesor_gestionar.js: No hay registro de inicio de gestión');
        return Promise.resolve();
    }
    
    console.log('Asesor_gestionar.js: Finalizando gestión del cliente');
    
    const finGestion = new Date();
    
    // Enviar petición al servidor para finalizar el tiempo de gestión
    return fetch('index.php?action=finalizar_gestion_tiempo', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            sesion_id: sesionIdGestion,
            hora_inicio: inicioGestion.toISOString(),
            hora_fin: finGestion.toISOString()
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Respuesta finalización gestión:', data);
        if (data.success) {
            console.log('Asesor_gestionar.js: Gestión finalizada - Tiempo:', data.tiempo_gestion, 'segundos');
        }
    })
    .catch(error => {
        console.error('Error al finalizar gestión:', error);
    });
}
