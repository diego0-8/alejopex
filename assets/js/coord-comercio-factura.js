// ========================================
// COORDINADOR GESTIÓN - BASES Y TAREAS
// ========================================

let currentTab = 'bases';
let selectedFile = null;
let currentUploadType = 'nueva';

// ========================================
// FUNCIONES DE NAVEGACIÓN DE PESTAÑAS
// ========================================

function cambiarTab(tabName) {
    console.log(`Coord_gestion.js: Cambiando a pestaña: ${tabName}`);
    
    // Ocultar todas las pestañas
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Remover clase active de todos los spans de pestañas
    const tabSpans = document.querySelectorAll('.main-tabs span');
    tabSpans.forEach(span => span.classList.remove('active'));
    
    // Mostrar pestaña seleccionada
    const selectedTab = document.getElementById(`tab-${tabName}`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Activar span de pestaña
    const selectedSpan = document.querySelector(`.main-tabs span[onclick="cambiarTab('${tabName}')"]`);
    if (selectedSpan) {
        selectedSpan.classList.add('active');
    }
    
    currentTab = tabName;
    
    // Cargar datos según la pestaña
    switch(tabName) {
        case 'bases':
            cargarBases();
            break;
        case 'tareas':
            cargarTareas();
            break;
        case 'historial':
            cargarHistorial();
            break;
    }
    
    console.log(`Coord_gestion.js: Pestaña ${tabName} activada`);
}

// ========================================
// FUNCIONES DE CARGA DE DATOS
// ========================================

function cargarBases() {
    console.log('Coord_gestion.js: Cargando bases...');
    
    fetch('index.php?action=obtener_bases')
        .then(response => response.json())
        .then(data => {
            console.log('Coord_gestion.js: Bases cargadas:', data);
            
            if (data.success) {
                mostrarBases(data.data);
                actualizarEstadisticasBases(data.estadisticas);
            } else {
                mostrarError('Error al cargar bases: ' + (data.error || data.message));
            }
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al cargar bases:', error);
            mostrarError('Error de conexión al cargar bases');
        });
}

function cargarTareas() {
    console.log('Coord_gestion.js: Inicializando pestaña de tareas...');
    
    // Cargar bases disponibles
    cargarBasesParaAsignacion();
    
    // Cargar asesores disponibles
    cargarAsesoresParaAsignacion();
}

function cargarBasesParaAsignacion() {
    console.log('Coord_gestion.js: Cargando bases para asignación...');
    
    fetch('index.php?action=obtener_bases')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('select-base-clientes');
                select.innerHTML = '<option value="">Seleccione una base de clientes...</option>';
                
                // Filtrar solo bases activas (creadas previamente)
                const basesActivas = data.data.filter(base => base.estado === 'activo');
                
                if (basesActivas.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No hay bases creadas';
                    option.disabled = true;
                    select.appendChild(option);
                } else {
                    basesActivas.forEach(base => {
                        const option = document.createElement('option');
                        option.value = base.id;
                        option.textContent = `${base.nombre} (${base.total_clientes || 0} clientes)`;
                        option.setAttribute('data-nombre', base.nombre);
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al cargar bases:', error);
        });
}

function cargarAsesoresParaAsignacion() {
    // Esta función ahora se llamará dinámicamente cuando se seleccione una base
    // Los asesores se cargarán desde obtener_asesores_con_acceso
    console.log('Coord_gestion.js: Los asesores se cargarán cuando se seleccione una base');
    
    const select = document.getElementById('select-asesor');
    if (select) {
        select.innerHTML = '<option value="">Seleccione primero una base de clientes...</option>';
        select.disabled = true;
    }
}

// Función para cargar asesores con acceso a una base específica
function cargarAsesoresConAccesoTarea(baseId) {
    console.log('Coord_gestion.js: Cargando asesores con acceso para base:', baseId);
    
    const select = document.getElementById('select-asesor');
    if (!select) return;
    
    if (!baseId) {
        select.innerHTML = '<option value="">Seleccione primero una base de clientes...</option>';
        select.disabled = true;
        return;
    }
    
    select.disabled = false;
    select.innerHTML = '<option value="">Cargando asesores...</option>';
    
    fetch(`index.php?action=obtener_asesores_con_acceso&base_id=${baseId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Coord_gestion.js: Asesores con acceso recibidos:', data);
            
            select.innerHTML = '<option value="">Seleccione un asesor...</option>';
            
            if (data.success && data.asesores && data.asesores.length > 0) {
                data.asesores.forEach(asesor => {
                    const option = document.createElement('option');
                    option.value = asesor.asesor_cedula || asesor.cedula;
                    option.textContent = `${asesor.nombre_completo || asesor.usuario} (${asesor.usuario || ''})`;
                    option.setAttribute('data-nombre', asesor.nombre_completo || asesor.usuario);
                    select.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No hay asesores con acceso a esta base';
                option.disabled = true;
                select.appendChild(option);
            }
            
            // Obtener clientes disponibles después de cargar asesores
            obtenerClientesDisponiblesParaAsignacion();
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al cargar asesores con acceso:', error);
            select.innerHTML = '<option value="">Error al cargar asesores</option>';
        });
}

function actualizarClientesDisponibles() {
    const baseId = document.getElementById('select-base-clientes').value;
    const inputClientes = document.getElementById('input-clientes-asignar');
    const infoClientes = document.getElementById('client-total-info');
    
    if (!baseId) {
        if (inputClientes) {
            inputClientes.max = 0;
            inputClientes.value = '';
            inputClientes.disabled = true;
        }
        if (infoClientes) {
            infoClientes.textContent = 'Total disponible: 0';
        }
        
        // Limpiar selector de asesor
        const selectAsesor = document.getElementById('select-asesor');
        if (selectAsesor) {
            selectAsesor.innerHTML = '<option value="">Seleccione primero una base de clientes...</option>';
            selectAsesor.disabled = true;
        }
        
        validarAsignacion();
        return;
    }
    
    // Cargar asesores con acceso a esta base
    cargarAsesoresConAccesoTarea(baseId);
}

function obtenerClientesDisponiblesParaAsignacion() {
    const baseId = document.getElementById('select-base-clientes').value;
    const inputClientes = document.getElementById('input-clientes-asignar');
    const infoClientes = document.getElementById('client-total-info');
    
    if (!baseId) {
        if (inputClientes) {
            inputClientes.max = 0;
            inputClientes.value = '';
            inputClientes.disabled = true;
        }
        if (infoClientes) {
            infoClientes.textContent = 'Total disponible: 0';
        }
        validarAsignacion();
        return;
    }
    
    // Obtener clientes disponibles (sin asignaciones pendientes)
    fetch(`index.php?action=obtener_clientes_disponibles&base_id=${baseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const disponibles = data.clientes_disponibles || 0;
                if (inputClientes) {
                    inputClientes.max = disponibles;
                    inputClientes.disabled = false;
                    inputClientes.placeholder = `Máximo: ${disponibles}`;
                }
                if (infoClientes) {
                    infoClientes.textContent = `Total disponible: ${disponibles} (solo clientes sin asignación pendiente)`;
                }
                
                // Actualizar resumen
                actualizarResumenAsignacion();
            } else {
                if (inputClientes) {
                    inputClientes.max = 0;
                    inputClientes.disabled = true;
                }
                if (infoClientes) {
                    infoClientes.textContent = 'Error al obtener clientes disponibles';
                }
            }
        })
        .catch(error => {
            console.error('Error al obtener clientes disponibles:', error);
            if (inputClientes) {
                inputClientes.max = 0;
                inputClientes.disabled = true;
            }
            if (infoClientes) {
                infoClientes.textContent = 'Error de conexión';
            }
        });
    
    validarAsignacion();
}

function validarAsignacion() {
    const baseSelect = document.getElementById('select-base-clientes');
    const asesorSelect = document.getElementById('select-asesor');
    const cantidadInput = document.getElementById('input-clientes-asignar');
    
    if (!baseSelect || !asesorSelect || !cantidadInput) {
        return;
    }
    
    const baseId = baseSelect.value;
    const asesorId = asesorSelect.value;
    const cantidadClientes = parseInt(cantidadInput.value) || 0;
    const maxClientes = parseInt(cantidadInput.max) || 0;
    
    const btnAsignar = document.getElementById('btn-asignar');
    
    // Validar que todos los campos estén completos
    const valido = baseId && asesorId && cantidadClientes > 0 && cantidadClientes <= maxClientes;
    
    if (btnAsignar) {
        btnAsignar.disabled = !valido;
    }
    
    // Los botones "Limpiar Selección" y "Ver Asignaciones" siempre están activos
    // (ya están activos por defecto en el HTML, no necesitan ser activados)
    
    // Actualizar resumen
    actualizarResumenAsignacion();
}

function actualizarResumenAsignacion() {
    const baseSelect = document.getElementById('select-base-clientes');
    const asesorSelect = document.getElementById('select-asesor');
    const cantidadInput = document.getElementById('input-clientes-asignar');
    
    const baseNombre = baseSelect.options[baseSelect.selectedIndex]?.getAttribute('data-nombre') || baseSelect.options[baseSelect.selectedIndex]?.textContent || '-';
    const asesorNombre = asesorSelect.options[asesorSelect.selectedIndex]?.getAttribute('data-nombre') || asesorSelect.options[asesorSelect.selectedIndex]?.textContent || '-';
    const cantidad = parseInt(cantidadInput.value) || 0;
    const maxDisponibles = parseInt(cantidadInput.max) || 0;
    const restantes = maxDisponibles - cantidad;
    
    document.getElementById('summary-base').textContent = baseNombre;
    document.getElementById('summary-asesor').textContent = asesorNombre;
    document.getElementById('summary-clientes').textContent = cantidad || '-';
    document.getElementById('summary-restantes').textContent = restantes >= 0 ? restantes : '-';
}

function asignarClientes() {
    const baseId = document.getElementById('select-base-clientes').value;
    const asesorCedula = document.getElementById('select-asesor').value;
    const cantidadClientes = parseInt(document.getElementById('input-clientes-asignar').value);
    
    if (!baseId || !asesorCedula || !cantidadClientes) {
        mostrarError('Por favor complete todos los campos requeridos');
        return;
    }
    
    // Obtener coordinador_cedula de la sesión (se enviará desde el servidor)
    const formData = new FormData();
    formData.append('base_id', baseId);
    formData.append('asesor_cedula', asesorCedula);
    formData.append('cantidad_clientes', cantidadClientes);
    
    const btnAsignar = document.getElementById('btn-asignar');
    btnAsignar.disabled = true;
    btnAsignar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asignando...';
    
    fetch('index.php?action=crear_asignacion_clientes', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('success', 'Clientes asignados exitosamente');
            // Actualizar clientes disponibles después de la asignación
            obtenerClientesDisponiblesParaAsignacion();
            limpiarAsignacion();
        } else {
            mostrarError('Error al asignar clientes: ' + (data.message || data.error));
        }
    })
    .catch(error => {
        console.error('Error al asignar clientes:', error);
        mostrarError('Error de conexión al asignar clientes');
    })
    .finally(() => {
        btnAsignar.disabled = false;
        btnAsignar.innerHTML = '<i class="fas fa-user-plus"></i> Asignar Clientes';
    });
}

function limpiarAsignacion() {
    document.getElementById('select-base-clientes').value = '';
    document.getElementById('select-asesor').value = '';
    document.getElementById('input-clientes-asignar').value = '';
    document.getElementById('input-clientes-asignar').max = 0;
    document.getElementById('client-total-info').textContent = 'Total disponible: 0';
    
    validarAsignacion();
    actualizarResumenAsignacion();
}

function verAsignacionesExistentes() {
    console.log('Coord_gestion.js: Ver asignaciones existentes');
    
    // Mostrar modal
    const modal = document.getElementById('modal-ver-asignaciones');
    if (!modal) {
        mostrarError('Modal no encontrado');
        return;
    }
    
    modal.style.display = 'block';
    
    // Mostrar loading
    const tbody = document.getElementById('modal-asignaciones-tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando asignaciones...</td></tr>';
    }
    
    // Cargar asignaciones pendientes
    fetch('index.php?action=obtener_asignaciones_pendientes')
        .then(response => response.json())
        .then(data => {
            console.log('Asignaciones recibidas:', data);
            if (data.success && data.asignaciones) {
                mostrarAsignacionesEnModal(data.asignaciones);
            } else {
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center alert alert-warning">${data.message || 'No hay asignaciones pendientes'}</td></tr>`;
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar asignaciones:', error);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center alert alert-danger">Error al cargar asignaciones</td></tr>';
            }
        });
}

function mostrarAsignacionesEnModal(asignaciones) {
    const tbody = document.getElementById('modal-asignaciones-tbody');
    if (!tbody) return;
    
    if (!asignaciones || asignaciones.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center alert alert-info">No hay asignaciones pendientes</td></tr>';
        return;
    }
    
    tbody.innerHTML = asignaciones.map(asignacion => {
        const clientes = asignacion.clientes_asignados ? JSON.parse(asignacion.clientes_asignados) : { clientes: [] };
        const cantidadClientes = clientes.clientes ? clientes.clientes.length : 0;
        const fechaAsignacion = asignacion.fecha_asignacion ? new Date(asignacion.fecha_asignacion).toLocaleDateString('es-ES') : '-';
        
        let estadoBadge = '';
        let estadoClass = '';
        switch(asignacion.estado) {
            case 'pendiente':
                estadoBadge = 'Pendiente';
                estadoClass = 'badge-warning';
                break;
            case 'en_progreso':
                estadoBadge = 'En Progreso';
                estadoClass = 'badge-info';
                break;
            case 'completada':
                estadoBadge = 'Completada';
                estadoClass = 'badge-success';
                break;
            default:
                estadoBadge = asignacion.estado || '-';
                estadoClass = 'badge-secondary';
        }
        const badgeHtml = `<span class="badge ${estadoClass}" style="padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">${estadoBadge}</span>`;
        
        const botonCompletar = asignacion.estado !== 'completada' 
            ? `<button class="btn btn-sm btn-success" onclick="completarAsignacion(${asignacion.id})" title="Completar">
                    <i class="fas fa-check"></i> Completar
                </button>`
            : '<span class="text-muted">Completada</span>';
        
        return `
            <tr data-asignacion-id="${asignacion.id}">
                <td>${asignacion.id}</td>
                <td>${asignacion.base_nombre || asignacion.base_id || '-'}</td>
                <td>${asignacion.asesor_nombre || asignacion.asesor_cedula || '-'}</td>
                <td>${cantidadClientes}</td>
                <td>${fechaAsignacion}</td>
                <td>${badgeHtml}</td>
                <td>${botonCompletar}</td>
            </tr>
        `;
    }).join('');
}

function completarAsignacion(asignacionId) {
    if (!confirm('¿Está seguro que desea completar esta asignación? Esta acción guardará toda la información en la tabla de asignaciones.')) {
        return;
    }
    
    const fila = document.querySelector(`tr[data-asignacion-id="${asignacionId}"]`);
    const boton = fila ? fila.querySelector('button[onclick*="completarAsignacion"]') : null;
    
    if (boton) {
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    }
    
    fetch('index.php?action=completar_asignacion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `asignacion_id=${asignacionId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('success', 'Asignación completada exitosamente');
            // Recargar asignaciones
            verAsignacionesExistentes();
        } else {
            mostrarError('Error al completar asignación: ' + (data.message || data.error));
            if (boton) {
                boton.disabled = false;
                boton.innerHTML = '<i class="fas fa-check"></i> Completar';
            }
        }
    })
    .catch(error => {
        console.error('Error al completar asignación:', error);
        mostrarError('Error de conexión al completar asignación');
        if (boton) {
            boton.disabled = false;
            boton.innerHTML = '<i class="fas fa-check"></i> Completar';
        }
    });
}

function seleccionarBaseParaTarea(baseId) {
    console.log('seleccionarBaseParaTarea: Base seleccionada:', baseId);
    actualizarClientesDisponibles();
}

function cerrarModalVerAsignaciones() {
    document.getElementById('modal-ver-asignaciones').style.display = 'none';
}

function cargarHistorial() {
    console.log('Coord_gestion.js: Cargando historial...');
    
    fetch('index.php?action=obtener_historial')
        .then(response => response.json())
        .then(data => {
            console.log('Coord_gestion.js: Historial cargado:', data);
            
            if (data.success) {
                mostrarHistorial(data.data);
            } else {
                mostrarError('Error al cargar historial: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al cargar historial:', error);
            mostrarError('Error de conexión al cargar historial');
        });
}

// ========================================
// FUNCIONES DE MOSTRAR DATOS
// ========================================

function mostrarBases(bases) {
    const tbody = document.getElementById('bases-tbody');
    
    if (!bases || bases.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    <i class="fas fa-database"></i>
                    <p>No hay bases de clientes registradas</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = bases.map(base => `
        <tr>
            <td>${base.nombre || '-'}</td>
            <td>${formatearFecha(base.fecha_creacion)}</td>
            <td>${base.total_clientes || 0}</td>
            <td>
                <span class="status-badge ${base.estado === 'activo' ? 'active' : 'inactive'}">
                    ${base.estado || 'activo'}
                </span>
            </td>
            <td>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <button class="btn btn-sm btn-success" onclick="darAccesoBase(${base.id}, '${base.nombre || ''}')" title="Dar Acceso">
                        <i class="fas fa-key"></i>
                    </button>
                    <button class="btn btn-sm btn-info" onclick="verAsesoresAccesoBase(${base.id}, '${base.nombre || ''}')" title="Ver Asesores con Acceso">
                        <i class="fas fa-users"></i>
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="verClientesBase(${base.id}, '${base.nombre || ''}')" title="Ver Clientes">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="eliminarBase(${base.id}, '${base.nombre || ''}')" title="Eliminar Base">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Funciones de mostrar tareas eliminadas - ya no se usan en la nueva estructura

function mostrarHistorial(historial) {
    const tbody = document.getElementById('historial-tbody');
    
    if (!tbody) {
        console.error('mostrarHistorial: Tbody no encontrado');
        return;
    }
    
    // Asegurarse de que historial sea un array
    if (!Array.isArray(historial)) {
        console.error('mostrarHistorial: historial no es un array:', historial);
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error: Formato de datos incorrecto</p>
                </td>
            </tr>
        `;
        return;
    }
    
    if (historial.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No hay actividades registradas</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = historial.map(actividad => {
        // Mapear campos de la tabla historial_actividades
        const tipo_actividad = actividad.tipo_actividad || actividad.tipo || 'Actividad';
        const descripcion = actividad.descripcion || actividad.actividad || '-';
        const fecha = actividad.fecha_actividad || actividad.fecha_creacion || actividad.fecha || '-';
        const estado = actividad.estado || 'completado';
        const archivo_tarea = actividad.archivo_tarea || actividad.archivo || '-';
        const usuario_nombre = actividad.usuario_nombre || actividad.nombre_usuario || 'Sistema';
        const base_nombre = actividad.base_nombre || actividad.base || '-';
        
        // Formatear fecha
        let fechaFormateada = '-';
        if (fecha && fecha !== '-') {
            try {
                fechaFormateada = new Date(fecha).toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                fechaFormateada = fecha;
            }
        }
        
        // Badge de estado
        let estadoBadge = '';
        switch(estado.toLowerCase()) {
            case 'exitoso':
            case 'completado':
            case 'completada':
                estadoBadge = '<span class="status-badge active">Completado</span>';
                break;
            case 'error':
            case 'fallido':
                estadoBadge = '<span class="status-badge inactive">Error</span>';
                break;
            default:
                estadoBadge = `<span class="status-badge">${estado}</span>`;
        }
        
        return `
            <tr>
                <td>${tipo_actividad}</td>
                <td>
                    <div>
                        <strong>${descripcion}</strong>
                        ${archivo_tarea && archivo_tarea !== '-' ? `<br><small>Archivo: ${archivo_tarea}</small>` : ''}
                        ${base_nombre && base_nombre !== '-' ? `<br><small>Base: ${base_nombre}</small>` : ''}
                    </div>
                </td>
                <td>${fechaFormateada}</td>
                <td>
                    ${estadoBadge}
                    ${usuario_nombre && usuario_nombre !== 'Sistema' ? `<br><small>Por: ${usuario_nombre}</small>` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

// ========================================
// FUNCIONES DE ESTADÍSTICAS
// ========================================

function actualizarEstadisticasBases(estadisticas) {
    if (!estadisticas) return;
    
    document.getElementById('stat-total-bases').textContent = estadisticas.total_bases || 0;
    document.getElementById('stat-clientes-totales').textContent = estadisticas.total_clientes || 0;
    document.getElementById('stat-bases-activas').textContent = estadisticas.bases_activas || 0;
}

// Función de estadísticas de tareas eliminada - ya no se usa en la nueva estructura

// ========================================
// FUNCIONES DE CARGA DE ARCHIVOS
// ========================================

function selectUploadType(tipo) {
    console.log(`Coord_gestion.js: Seleccionando tipo de carga: ${tipo}`);
    
    // Remover clase active de todos los botones
    document.querySelectorAll('.upload-type-btn').forEach(btn => btn.classList.remove('active'));
    
    // Activar botón seleccionado
    const selectedBtn = document.getElementById(`btn-carga-${tipo}`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Mostrar/ocultar formularios
    document.getElementById('form-carga-nueva').style.display = tipo === 'nueva' ? 'block' : 'none';
    document.getElementById('form-carga-existente').style.display = tipo === 'existente' ? 'block' : 'none';
    
    currentUploadType = tipo;
    
    // Si se selecciona carga existente, cargar las bases disponibles
    if (tipo === 'existente') {
        cargarBasesExistentes();
    }
    
    // Validar formulario después del cambio
    validarFormulario();
    
    console.log(`Coord_gestion.js: Tipo de carga cambiado a: ${tipo}`);
}

function handleFileSelect(event, tipo) {
    console.log(`Coord_gestion.js: Archivo seleccionado para carga ${tipo}`);
    
    const file = event.target.files[0];
    if (file) {
        console.log(`Coord_gestion.js: Detalles del archivo - Nombre: ${file.name}, Tamaño: ${file.size}, Tipo: ${file.type}`);
        
        // Validar tipo de archivo
        if (!file.name.endsWith('.csv') && file.type !== 'text/csv') {
            console.error('Coord_gestion.js: Tipo de archivo inválido');
            alert('Por favor selecciona un archivo CSV válido');
            return;
        }
        
        selectedFile = file;
        showFileInfo(file, tipo);
        validarFormulario();
        
        // Habilitar botón de subir archivo
        const btnSubir = document.getElementById('btn-subir');
        if (btnSubir) {
            btnSubir.disabled = false;
        }
        
        console.log('Coord_gestion.js: Archivo procesado exitosamente');
    }
}

function dropHandler(ev, tipo) {
    console.log(`Coord_gestion.js: Archivo arrastrado para carga ${tipo}`);
    ev.preventDefault();
    
    const files = ev.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        console.log(`Coord_gestion.js: Archivo arrastrado - Nombre: ${file.name}, Tipo: ${file.type}`);
        
        if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
            selectedFile = file;
            showFileInfo(file, tipo);
            validarFormulario();
            console.log('Coord_gestion.js: Archivo arrastrado exitosamente');
        } else {
            console.error('Coord_gestion.js: Tipo de archivo inválido arrastrado');
            alert('Por favor selecciona un archivo CSV válido');
        }
    }
}

function dragOverHandler(ev) {
    ev.preventDefault();
    ev.currentTarget.classList.add('drag-over');
}

function dragLeaveHandler(ev) {
    ev.currentTarget.classList.remove('drag-over');
}

function showFileInfo(file, tipo) {
    const fileInfo = document.getElementById('file-info');
    if (fileInfo) {
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').textContent = formatFileSize(file.size);
        document.getElementById('file-type').textContent = file.type;
        fileInfo.style.display = 'block';
    }
}

function validarFormulario() {
    const tipoCarga = currentUploadType;
    let valido = false;
    
    if (tipoCarga === 'nueva') {
        const nombreArchivo = document.getElementById('nombre-archivo').value.trim();
        valido = nombreArchivo.length > 0 && selectedFile !== null;
    } else if (tipoCarga === 'existente') {
        const baseDatos = document.getElementById('base-datos-existente').value;
        valido = baseDatos.length > 0 && selectedFile !== null;
    }
    
    const btnSubir = document.getElementById('btn-subir');
    if (btnSubir) {
        btnSubir.disabled = !valido;
    }
    
    return valido;
}

// ========================================
// FUNCIONES DE HABILITACIÓN DE BOTONES
// ========================================

function enableButtons() {
    console.log('Coord_gestion.js: Habilitando botones...');
    
    const btnSubir = document.getElementById('btn-subir');
    const btnLimpiar = document.getElementById('btn-limpiar');
    
    if (btnSubir) btnSubir.disabled = false;
    if (btnLimpiar) btnLimpiar.disabled = false;
    
    console.log('Coord_gestion.js: Botones habilitados');
}

function disableButtons() {
    console.log('Coord_gestion.js: Deshabilitando botones...');
    
    const btnSubir = document.getElementById('btn-subir');
    const btnLimpiar = document.getElementById('btn-limpiar');
    
    if (btnSubir) btnSubir.disabled = true;
    if (btnLimpiar) btnLimpiar.disabled = false; // Limpiar siempre debe estar habilitado
    
    console.log('Coord_gestion.js: Botones deshabilitados');
}

// ========================================
// FUNCIONES DE CARGA DE BASES EXISTENTES
// ========================================

function cargarBasesExistentes() {
    console.log('Coord_gestion.js: Cargando bases de datos existentes...');
    
    fetch('index.php?action=obtener_bases')
        .then(response => {
            console.log('Coord_gestion.js: Respuesta recibida de obtener_bases');
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Coord_gestion.js: Datos de bases recibidos:', data);
            
            // El endpoint puede devolver data.data o data.bases dependiendo de qué caso se ejecute
            const bases = data.data || data.bases || [];
            
            if (data.success && Array.isArray(bases) && bases.length > 0) {
                const select = document.getElementById('base-datos-existente');
                if (select) {
                    // Limpiar opciones existentes excepto la primera
                    select.innerHTML = '<option value="">Seleccione una base de datos...</option>';
                    
                    // Agregar bases de datos
                    bases.forEach(base => {
                        const option = document.createElement('option');
                        option.value = base.id;
                        const totalClientes = base.total_clientes || 0;
                        option.textContent = `${base.nombre} (${totalClientes} clientes)`;
                        select.appendChild(option);
                    });
                    
                    console.log(`Coord_gestion.js: ${bases.length} bases cargadas en el select`);
                } else {
                    console.warn('Coord_gestion.js: Elemento base-datos-existente no encontrado');
                }
            } else {
                console.warn('Coord_gestion.js: No se recibieron datos de bases o respuesta inválida. Data:', data);
                // Si no hay bases, al menos limpiar el select
                const select = document.getElementById('base-datos-existente');
                if (select) {
                    select.innerHTML = '<option value="">No hay bases disponibles</option>';
                }
            }
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al cargar bases:', error);
            const select = document.getElementById('base-datos-existente');
            if (select) {
                select.innerHTML = '<option value="">Error al cargar bases</option>';
            }
        });
}

function subirArchivo() {
    console.log('Coord_gestion.js: Iniciando proceso de subida de archivo...');
    
    if (!validarFormulario()) {
        mostrarNotificacion('error', 'Por favor complete todos los campos requeridos');
        return;
    }
    
    if (!selectedFile) {
        mostrarNotificacion('error', 'Por favor seleccione un archivo CSV para subir');
        return;
    }
    
    // Validar tamaño del archivo (aumentado a 500MB para archivos grandes)
    const maxSize = 500 * 1024 * 1024; // 500MB
    if (selectedFile.size > maxSize) {
        mostrarNotificacion('error', 'El archivo es demasiado grande. Máximo permitido: 500MB');
        return;
    }
    
    // Mostrar indicador de carga
    mostrarNotificacion('info', 'Subiendo archivo...');
    
    // Deshabilitar botón durante la carga
    const btnSubir = document.getElementById('btn-subir');
    btnSubir.disabled = true;
    btnSubir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
    
    // Preparar datos del formulario
    const formData = new FormData();
    formData.append('csv_file', selectedFile);
    formData.append('tipo_carga', currentUploadType);
    
    // Agregar parámetros según el tipo de carga
    if (currentUploadType === 'nueva') {
        const nombreArchivo = document.getElementById('nombre-archivo').value.trim();
        formData.append('nombre_archivo', nombreArchivo);
    } else if (currentUploadType === 'existente') {
        const baseDatosId = document.getElementById('base-datos-existente').value;
        if (baseDatosId) {
            formData.append('base_datos_id', baseDatosId);
        }
    }
    
    // Procesar archivo
    procesarArchivoReal(formData);
}

function procesarArchivoReal(formData) {
    console.log('Coord_gestion.js: Enviando archivo al servidor para procesamiento...');
    
    // Mostrar indicador de progreso para archivos grandes
    const fileSize = selectedFile ? selectedFile.size : 0;
    const isLargeFile = fileSize > 50 * 1024 * 1024; // > 50MB
    
    if (isLargeFile) {
        mostrarNotificacion('info', 'Procesando archivo grande. Esto puede tardar varios minutos. Por favor espere...');
        
        // Actualizar barra de progreso
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        if (progressFill) {
            progressFill.style.width = '10%';
        }
        if (progressText) {
            progressText.textContent = 'Iniciando procesamiento de archivo grande...';
        }
    }
    
    // Usar XMLHttpRequest en lugar de fetch para mejor manejo de errores en archivos grandes
    const xhr = new XMLHttpRequest();
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                console.log('Coord_gestion.js: Processing result received:', data);
                
                // Actualizar estadísticas de procesamiento
                actualizarEstadisticasProcesamiento(data);
                
                // Actualizar log de errores
                actualizarLogErrores(data.errores || []);
                
                // Restaurar botón primero
                restaurarBotonSubir();
                
                if (data.success) {
                    console.log('Coord_gestion.js: File processed successfully');
                    mostrarResultado(data, 'success');
                    
                    // Recargar bases existentes si es necesario
                    if (currentUploadType === 'nueva') {
                        cargarBasesExistentes();
                    }
                    
                    // Limpiar formulario después de un pequeño delay para que se vea el resultado
                    setTimeout(() => {
                        limpiarFormulario();
                    }, 3000); // 3 segundos para que el usuario vea el resultado
                } else {
                    console.error('Coord_gestion.js: File processing failed:', data.message || data.mensaje || 'Error desconocido');
                    // Mostrar mensaje más descriptivo si hay errores
                    if (data.errores && data.errores.length > 0) {
                        console.error('Coord_gestion.js: Errores detallados:', data.errores);
                    }
                    mostrarResultado(data, 'error');
                }
            } catch (e) {
                console.error('Coord_gestion.js: Error parsing response:', e);
                restaurarBotonSubir();
                mostrarResultado({
                    success: false,
                    message: 'Error al procesar respuesta del servidor'
                }, 'error');
            }
        } else {
            console.error('Coord_gestion.js: HTTP error:', xhr.status);
            restaurarBotonSubir();
            mostrarResultado({
                success: false,
                message: 'Error de servidor (HTTP ' + xhr.status + ')'
            }, 'error');
        }
    };
    
    xhr.onerror = function() {
        console.error('Coord_gestion.js: Network error during file processing');
        restaurarBotonSubir();
        mostrarResultado({
            success: false,
            message: 'Error de conexión. Por favor verifique su conexión a internet.'
        }, 'error');
    };
    
    xhr.onloadend = function() {
        console.log('Coord_gestion.js: File processing completed');
    };
    
    xhr.open('POST', 'index.php?action=cargar_csv', true);
    xhr.send(formData);
}

// Función para actualizar estadísticas de procesamiento
function actualizarEstadisticasProcesamiento(data) {
    const rowsProcessed = document.getElementById('rows-processed');
    const totalEmpresas = document.getElementById('total-empresas');
    const totalObligaciones = document.getElementById('total-obligaciones');
    const clientesCreados = document.getElementById('clientes-creados');
    const obligacionesCreadas = document.getElementById('obligaciones-creadas');
    const rowsErrors = document.getElementById('rows-errors');
    
    if (rowsProcessed) {
        rowsProcessed.textContent = data.procesado || data.total_filas || 0;
    }
    if (totalEmpresas) {
        totalEmpresas.textContent = data.clientes_unicos || data.clientes_creados || 0;
    }
    if (totalObligaciones) {
        totalObligaciones.textContent = data.obligaciones_unicas || data.obligaciones_creadas || 0;
    }
    if (clientesCreados) {
        clientesCreados.textContent = data.clientes_creados || 0;
    }
    if (obligacionesCreadas) {
        obligacionesCreadas.textContent = data.obligaciones_creadas || 0;
    }
    if (rowsErrors) {
        rowsErrors.textContent = (data.errores && data.errores.length) || 0;
    }
}

// Función para actualizar log de errores
function actualizarLogErrores(errores) {
    const errorLog = document.getElementById('error-log');
    if (!errorLog) return;
    
    if (!errores || errores.length === 0) {
        errorLog.innerHTML = '<p class="log-empty">No hay errores</p>';
        return;
    }
    
    // Mostrar hasta 100 errores con scroll
    const erroresMostrar = errores.slice(0, 100);
    const htmlErrores = erroresMostrar.map((error, index) => {
        return `<div class="log-entry" style="padding: 8px; margin-bottom: 4px; border-left: 3px solid #dc3545; background: #fff5f5;">
                    <span style="color: #dc3545; font-weight: bold;">Error ${index + 1}:</span> 
                    <span style="color: #333;">${error}</span>
                </div>`;
    }).join('');
    
    const mensajeFinal = errores.length > 100 
        ? `<div style="padding: 8px; color: #856404; font-style: italic;">... y ${errores.length - 100} errores más</div>`
        : '';
    
    errorLog.innerHTML = `
        <div style="max-height: 400px; overflow-y: auto;">
            ${htmlErrores}
            ${mensajeFinal}
        </div>
    `;
}

function mostrarResultado(data, tipo) {
    console.log(`Coord_gestion.js: Mostrando resultado ${tipo}:`, data);
    
    const resultadoDiv = document.getElementById('resultado-carga');
    const titulo = document.getElementById('resultado-titulo');
    const mensaje = document.getElementById('resultado-mensaje');
    const detalles = document.getElementById('resultado-detalles');
    
    if (tipo === 'success') {
        titulo.textContent = 'Carga Exitosa';
        titulo.className = 'text-success';
        
        mensaje.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                ${data.mensaje || 'Archivo procesado correctamente'}
            </div>
        `;
        
                    detalles.innerHTML = `
            <div class="resultado-detalles">
                <div class="detalle-item">
                    <span class="detalle-label">Total filas en archivo:</span>
                    <span class="detalle-valor">${data.total_filas || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Filas procesadas:</span>
                    <span class="detalle-valor">${data.procesado || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Total Clientes (IDs Únicos):</span>
                    <span class="detalle-valor">${data.clientes_unicos || data.clientes_creados || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Total Obligaciones:</span>
                    <span class="detalle-valor">${data.obligaciones_unicas || data.obligaciones_creadas || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Clientes Nuevos Creados:</span>
                    <span class="detalle-valor">${data.clientes_creados || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Obligaciones Nuevas Creadas:</span>
                    <span class="detalle-valor">${data.obligaciones_creadas || 0}</span>
                </div>
                ${data.errores && data.errores.length > 0 ? `
                    <div class="detalle-item">
                        <span class="detalle-label">Errores (mostrando primeros 20):</span>
                        <span class="detalle-valor text-warning">${Math.min(data.errores.length, 20)}</span>
                    </div>
                    ${data.errores.length <= 20 ? `
                        <div class="errores-detalle" style="max-height: 200px; overflow-y: auto; margin-top: 10px;">
                            <ul style="text-align: left; font-size: 12px;">
                                ${data.errores.slice(0, 20).map(error => `<li style="color: #dc3545;">${error}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                ` : ''}
            </div>
        `;
        
        // Mostrar notificación de éxito con mensaje detallado
        const mensajeExito = data.mensaje || `Archivo procesado exitosamente. Clientes: ${data.clientes_unicos || 0}, Obligaciones: ${data.obligaciones_unicas || 0}`;
        mostrarNotificacion('success', mensajeExito);
    } else {
        titulo.textContent = 'Error en la Carga';
        titulo.className = 'text-danger';
        
        const mensajeError = data.message || data.mensaje || 'Error al procesar el archivo';
        
        mensaje.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                ${mensajeError}
            </div>
        `;
        
        // Mostrar detalles del procesamiento aunque haya fallado
        detalles.innerHTML = `
            <div class="resultado-detalles">
                <div class="detalle-item">
                    <span class="detalle-label">Total filas en archivo:</span>
                    <span class="detalle-valor">${data.total_filas || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Filas procesadas:</span>
                    <span class="detalle-valor">${data.procesado || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Total Clientes (IDs Únicos):</span>
                    <span class="detalle-valor">${data.clientes_unicos || data.clientes_creados || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Total Obligaciones:</span>
                    <span class="detalle-valor">${data.obligaciones_unicas || data.obligaciones_creadas || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Clientes Nuevos Creados:</span>
                    <span class="detalle-valor">${data.clientes_creados || 0}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Obligaciones Nuevas Creadas:</span>
                    <span class="detalle-valor">${data.obligaciones_creadas || 0}</span>
                </div>
                ${data.errores && data.errores.length > 0 ? `
                    <div class="detalle-item">
                        <span class="detalle-label">Errores encontrados:</span>
                        <span class="detalle-valor text-warning">${data.errores.length}</span>
                    </div>
                    <div class="errores-detalle" style="max-height: 200px; overflow-y: auto; margin-top: 10px;">
                        <h5>Detalles de errores:</h5>
                        <ul style="text-align: left; font-size: 12px;">
                            ${data.errores.slice(0, 50).map(error => `<li style="color: #dc3545;">${error}</li>`).join('')}
                            ${data.errores.length > 50 ? `<li style="color: #856404;">... y ${data.errores.length - 50} errores más</li>` : ''}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
        
        mostrarNotificacion('error', mensajeError);
    }
    
    resultadoDiv.style.display = 'block';
}

function limpiarFormulario() {
    console.log('Coord_gestion.js: Limpiando formulario...');
    
    // Limpiar archivo seleccionado
    selectedFile = null;
    
    // Limpiar inputs de archivo
    document.getElementById('csv-file-nueva').value = '';
    document.getElementById('csv-file-existente').value = '';
    
    // Limpiar nombre de archivo
    document.getElementById('nombre-archivo').value = '';
    
    // Ocultar información del archivo
    const fileInfo = document.getElementById('file-info');
    if (fileInfo) {
        fileInfo.style.display = 'none';
    }
    
    // Ocultar resultado
    const resultadoDiv = document.getElementById('resultado-carga');
    if (resultadoDiv) {
        resultadoDiv.style.display = 'none';
    }
    
    // Deshabilitar botón
    const btnSubir = document.getElementById('btn-subir');
    if (btnSubir) {
        btnSubir.disabled = true;
    }
    
    console.log('Coord_gestion.js: Formulario limpiado');
}

// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

function formatearNumero(numero) {
    return new Intl.NumberFormat('es-CO').format(numero);
}

function formatearFecha(fecha) {
    return new Date(fecha).toLocaleDateString('es-CO');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Función para restaurar el botón de subir
function restaurarBotonSubir() {
    const btnSubir = document.getElementById('btn-subir');
    if (btnSubir) {
        btnSubir.disabled = false;
        btnSubir.innerHTML = '<i class="fas fa-upload"></i> Subir Archivo';
    }
}

function mostrarNotificacion(tipo, mensaje) {
    // Crear notificación visual
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        max-width: 400px;
        animation: slideInRight 0.3s ease-out;
    `;
    
    const colores = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notificacion.style.backgroundColor = colores[tipo] || colores.info;
    
    const iconos = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos.info}"></i>
        <span style="margin-left: 8px;">${mensaje}</span>
        <button onclick="this.parentElement.remove()" style="
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            margin-left: 10px;
            cursor: pointer;
            opacity: 0.8;
        ">&times;</button>
    `;
    
    document.body.appendChild(notificacion);
    
    // Duración de la notificación según el tipo
    const duracion = tipo === 'success' ? 8000 : tipo === 'error' ? 10000 : 5000; // 8s para éxito, 10s para error, 5s para otros
    
    setTimeout(() => {
        if (notificacion.parentElement) {
            notificacion.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notificacion.parentElement) {
                    notificacion.remove();
                }
            }, 300);
        }
    }, duracion);
    
    console.log(`Notificación ${tipo}: ${mensaje}`);
}

function mostrarError(mensaje) {
    mostrarNotificacion('error', mensaje);
}

// ========================================
// FUNCIONES DE ACCIONES (eliminadas - ahora están arriba)
// ========================================

// Función filtrarTareas eliminada - ya no se usa en la nueva estructura

function exportarBases() {
    console.log('Coord_gestion.js: Exportar bases');
    window.location.href = 'index.php?action=exportar_bases';
}

// Funciones para modales (placeholders)
function openModal(modalId) {
    console.log(`Coord_gestion.js: Abrir modal ${modalId}`);
    // Implementar lógica de modal según sea necesario
    if (modalId === 'crear-base') {
        alert('Función de crear base - Por implementar');
    } else if (modalId === 'importar-base') {
        abrirPestañaCarga();
    }
}

function darAccesoBase(baseId, baseNombre) {
    console.log(`Coord_gestion.js: Dar acceso a base ID: ${baseId}, Nombre: ${baseNombre}`);
    
    // Guardar datos de la base para el modal
    window.currentBaseAcceso = {
        id: baseId,
        nombre: baseNombre
    };
    
    // Abrir modal de dar acceso
    openModalAccesoBase(baseId, baseNombre);
}

function verClientesBase(baseId, baseNombre) {
    console.log(`verClientesBase: Ver clientes de base ID: ${baseId}, Nombre: ${baseNombre}`);
    
    // Verificar que el modal existe
    const modal = document.getElementById('modal-ver-clientes');
    if (!modal) {
        console.error('verClientesBase: Modal modal-ver-clientes no encontrado');
        mostrarError('Error: Modal no encontrado');
        return;
    }
    
    // Abrir modal PRIMERO
    openModalVerClientes(baseId, baseNombre);
    
    // Obtener elementos del modal
    const modalBody = document.getElementById('modal-ver-clientes-body');
    const nombreElement = document.getElementById('modal-ver-clientes-nombre');
    const tbody = document.getElementById('modal-clientes-tbody');
    
    // Verificar que los elementos existen
    if (!modalBody || !nombreElement || !tbody) {
        console.error('verClientesBase: Elementos del modal no encontrados', {
            modalBody: !!modalBody,
            nombre: !!nombreElement,
            tbody: !!tbody
        });
        if (modalBody) {
            modalBody.innerHTML = '<div class="alert alert-danger">Error: Elementos del modal no encontrados</div>';
        }
        return;
    }
    
    // Establecer nombre
    nombreElement.textContent = baseNombre;
    
    // Mostrar loading en el tbody (no en todo el modal body)
    tbody.innerHTML = '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando clientes...</td></tr>';
    
    console.log('verClientesBase: Cargando clientes para base ID:', baseId);
    
    // Cargar clientes
    fetch(`index.php?action=obtener_clientes_base&base_id=${baseId}`)
        .then(response => {
            console.log('verClientesBase: Respuesta recibida. Status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('verClientesBase: Datos recibidos:', data);
            
            if (data.success && data.clientes) {
                console.log('verClientesBase: Mostrando', data.clientes.length, 'clientes');
                mostrarClientesEnModal(data.clientes, baseNombre);
            } else {
                console.error('verClientesBase: Error del servidor:', data);
                const errorMsg = data.message || data.error || 'Error desconocido al cargar clientes';
                tbody.innerHTML = `<tr><td colspan="3" class="text-center alert alert-danger">Error: ${errorMsg}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('verClientesBase: Error al cargar clientes:', error);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center alert alert-danger">Error de conexión al cargar clientes</td></tr>';
            } else if (modalBody) {
                modalBody.innerHTML = '<div class="alert alert-danger">Error de conexión al cargar clientes</div>';
            }
        });
}

function eliminarBase(baseId, baseNombre) {
    console.log(`Coord_gestion.js: Eliminar base ID: ${baseId}`);
    
    if (!confirm(`¿Está seguro que desea eliminar la base "${baseNombre}"?\n\nEsta acción eliminará la base y todos los clientes asociados.\n\nEsta acción NO se puede deshacer.`)) {
        return;
    }
    
    fetch(`index.php?action=eliminar_base`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `base_id=${baseId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('success', 'Base eliminada exitosamente');
            cargarBases(); // Recargar la lista de bases
        } else {
            mostrarError('Error al eliminar base: ' + (data.message || data.error));
        }
    })
    .catch(error => {
        console.error('Error al eliminar base:', error);
        mostrarError('Error de conexión al eliminar base');
    });
}

function openModalAccesoBase(baseId, baseNombre) {
    console.log('openModalAccesoBase: Abriendo modal. Base ID:', baseId, 'Nombre:', baseNombre);
    
    // Verificar que el modal existe
    const modal = document.getElementById('modal-acceso-base');
    if (!modal) {
        console.error('openModalAccesoBase: Modal modal-acceso-base no encontrado');
        mostrarError('Error: Modal no encontrado');
        return;
    }
    
    // Establecer ID y nombre en el modal PRIMERO (antes de cargar asesores)
    const nombreElement = document.getElementById('modal-acceso-base-nombre');
    const idElement = document.getElementById('modal-acceso-base-id');
    
    if (!nombreElement || !idElement) {
        console.error('openModalAccesoBase: Elementos del modal no encontrados', {
            nombre: !!nombreElement,
            id: !!idElement
        });
        mostrarError('Error: Elementos del modal no encontrados');
        return;
    }
    
    nombreElement.textContent = baseNombre;
    idElement.value = baseId;
    
    console.log('openModalAccesoBase: Valores establecidos. Base ID:', idElement.value, 'Nombre:', nombreElement.textContent);
    
    // Mostrar modal
    modal.style.display = 'block';
    console.log('openModalAccesoBase: Modal mostrado');
    
    // Mostrar loading
    const asesoresList = document.getElementById('asesores-acceso-list');
    if (asesoresList) {
        asesoresList.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando asesores...</p></div>';
    }
    
    // Cargar solo los asesores que NO tienen acceso a esta base
    // Esto permite agregar múltiples asesores sin revocar los existentes
    console.log('openModalAccesoBase: Cargando asesores sin acceso...');
    
    fetch(`index.php?action=obtener_asesores_sin_acceso&base_id=${baseId}`)
        .then(response => {
            console.log('openModalAccesoBase: Respuesta de asesores sin acceso. Status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('openModalAccesoBase: Datos de asesores sin acceso:', data);
            
            if (data.success && data.asesores) {
                if (asesoresList) {
                    console.log('openModalAccesoBase: Creando lista de asesores sin acceso. Total:', data.asesores.length);
                    if (data.asesores.length === 0) {
                        asesoresList.innerHTML = `
                            <div class="empty-state" style="padding: 12px 8px; color: #6c757d;">
                                <i class="fas fa-info-circle"></i> Todos los asesores ya tienen acceso a esta base
                            </div>
                        `;
                    } else {
                        asesoresList.innerHTML = data.asesores.map(asesor => {
                            const cedula = String(asesor.cedula || '');
                            const nombre = asesor.nombre_completo || asesor.usuario || 'Sin nombre';
                            return `
                                <div class="asesor-checkbox-item">
                                    <label style="display: flex; align-items: center; padding: 8px; cursor: pointer;">
                                        <input type="checkbox" value="${cedula}" class="asesor-checkbox" id="asesor-check-${cedula}" style="margin-right: 8px;">
                                        <span>${nombre} - ${cedula}</span>
                                    </label>
                                </div>
                            `;
                        }).join('');
                    }
                    console.log('openModalAccesoBase: Lista de asesores sin acceso creada');
                } else {
                    console.error('openModalAccesoBase: Elemento asesores-acceso-list no encontrado');
                }
            } else {
                console.error('openModalAccesoBase: Error al obtener asesores:', data);
                if (asesoresList) {
                    asesoresList.innerHTML = '<div class="error">Error al cargar asesores: ' + (data.error || data.message || 'Error desconocido') + '</div>';
                }
            }
        })
        .catch(error => {
            console.error('openModalAccesoBase: Error al cargar asesores sin acceso:', error);
            if (asesoresList) {
                asesoresList.innerHTML = '<div class="error">Error de conexión al cargar asesores</div>';
            }
        });
}

function cargarAsesoresConAcceso(baseId) {
    console.log('cargarAsesoresConAcceso: Cargando asesores con acceso para base:', baseId);
    
    if (!baseId) {
        console.warn('cargarAsesoresConAcceso: Base ID no proporcionado');
        return;
    }
    
    fetch(`index.php?action=obtener_asesores_acceso_base&base_id=${baseId}`)
        .then(response => {
            console.log('cargarAsesoresConAcceso: Respuesta recibida. Status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('cargarAsesoresConAcceso: Datos recibidos:', data);
            
            if (data.success && data.asesores) {
                const asesoresIds = data.asesores.map(a => a.asesor_cedula || a.cedula);
                console.log('cargarAsesoresConAcceso: Asesores con acceso:', asesoresIds);
                
                const checkboxes = document.querySelectorAll('.asesor-checkbox');
                console.log('cargarAsesoresConAcceso: Checkboxes encontrados:', checkboxes.length);
                
                checkboxes.forEach(cb => {
                    if (asesoresIds.includes(cb.value)) {
                        cb.checked = true;
                        console.log('cargarAsesoresConAcceso: Checkbox marcado para asesor:', cb.value);
                    }
                });
            } else {
                console.log('cargarAsesoresConAcceso: No hay asesores con acceso o error:', data);
            }
        })
        .catch(error => {
            console.error('cargarAsesoresConAcceso: Error al cargar asesores con acceso:', error);
        });
}

function guardarAccesoBase() {
    console.log('guardarAccesoBase: Iniciando proceso...');
    
    // Obtener base ID
    const baseIdElement = document.getElementById('modal-acceso-base-id');
    if (!baseIdElement) {
        console.error('guardarAccesoBase: Elemento modal-acceso-base-id no encontrado');
        mostrarError('Error interno: Campo de ID no encontrado');
        return;
    }
    
    const baseId = baseIdElement.value;
    console.log('guardarAccesoBase: Base ID obtenido:', baseId);
    
    if (!baseId || baseId.trim() === '') {
        console.error('guardarAccesoBase: Base ID vacío');
        mostrarError('ID de base no encontrado. Por favor, cierre y vuelva a abrir el modal.');
        return;
    }
    
    // Obtener checkboxes seleccionados
    const checkboxes = document.querySelectorAll('.asesor-checkbox:checked');
    console.log('guardarAccesoBase: Checkboxes encontrados:', checkboxes.length);
    
    if (checkboxes.length === 0) {
        console.warn('guardarAccesoBase: No hay asesores seleccionados');
        mostrarError('Por favor, seleccione al menos un asesor');
        return;
    }
    
    const asesoresIds = Array.from(checkboxes).map(cb => cb.value);
    console.log('guardarAccesoBase: Asesores seleccionados:', asesoresIds);
    
    // Crear FormData
    const formData = new FormData();
    formData.append('base_id', baseId);
    formData.append('asesores', JSON.stringify(asesoresIds));
    
    console.log('guardarAccesoBase: FormData creado. Base ID:', baseId, 'Asesores:', asesoresIds);
    
    // Obtener botón y deshabilitar
    const btnGuardar = document.getElementById('btn-guardar-acceso-base');
    if (!btnGuardar) {
        console.error('guardarAccesoBase: Botón btn-guardar-acceso-base no encontrado');
        mostrarError('Error interno: Botón no encontrado');
        return;
    }
    
    const btnTextOriginal = btnGuardar.innerHTML;
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    console.log('guardarAccesoBase: Enviando petición a index.php?action=guardar_acceso_base');
    
    // Enviar petición
    fetch('index.php?action=guardar_acceso_base', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('guardarAccesoBase: Respuesta recibida. Status:', response.status);
        console.log('guardarAccesoBase: Content-Type:', response.headers.get('content-type'));
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('guardarAccesoBase: Respuesta raw:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('guardarAccesoBase: Error al parsear JSON:', e);
                console.error('guardarAccesoBase: Texto recibido:', text);
                throw new Error('Respuesta no es JSON válido: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('guardarAccesoBase: Datos parseados:', data);
        
        if (data.success) {
            console.log('guardarAccesoBase: Éxito - Acceso guardado correctamente');
            mostrarNotificacion('success', `Acceso otorgado a ${asesoresIds.length} asesor(es) exitosamente`);
            
            // Recargar bases para reflejar cambios
            if (typeof cargarBases === 'function') {
                cargarBases();
            }
            
            // Cerrar modal
            closeModalAccesoBase();
        } else {
            console.error('guardarAccesoBase: Error del servidor:', data);
            const errorMsg = data.message || data.error || 'Error desconocido al guardar acceso';
            mostrarError('Error al guardar acceso: ' + errorMsg);
            
            // Si hay debug info, mostrarla en consola
            if (data.debug) {
                console.error('guardarAccesoBase: Debug info:', data.debug);
            }
        }
    })
    .catch(error => {
        console.error('guardarAccesoBase: Error en petición:', error);
        console.error('guardarAccesoBase: Stack:', error.stack);
        mostrarError('Error de conexión al guardar acceso: ' + error.message);
    })
    .finally(() => {
        console.log('guardarAccesoBase: Finalizando...');
        if (btnGuardar) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = btnTextOriginal;
        }
    });
}

function closeModalAccesoBase() {
    document.getElementById('modal-acceso-base').style.display = 'none';
}

function openModalVerClientes(baseId, baseNombre) {
    console.log('openModalVerClientes: Abriendo modal. Base ID:', baseId, 'Nombre:', baseNombre);
    
    const modal = document.getElementById('modal-ver-clientes');
    if (!modal) {
        console.error('openModalVerClientes: Modal no encontrado');
        return;
    }
    
    // Establecer nombre
    const nombreElement = document.getElementById('modal-ver-clientes-nombre');
    if (nombreElement) {
        nombreElement.textContent = baseNombre;
    }
    
    // Mostrar modal
    modal.style.display = 'block';
    console.log('openModalVerClientes: Modal mostrado');
    
    // Asegurarse de que el tbody existe antes de continuar
    const tbody = document.getElementById('modal-clientes-tbody');
    if (!tbody) {
        console.error('openModalVerClientes: Tbody no encontrado después de abrir modal');
    } else {
        console.log('openModalVerClientes: Tbody encontrado correctamente');
    }
}

function closeModalVerClientes() {
    document.getElementById('modal-ver-clientes').style.display = 'none';
}

// Función para ver asesores con acceso a una base
function verAsesoresAccesoBase(baseId, baseNombre) {
    console.log(`verAsesoresAccesoBase: Ver asesores con acceso. Base ID: ${baseId}, Nombre: ${baseNombre}`);
    
    // Verificar que el modal existe
    const modal = document.getElementById('modal-ver-asesores-acceso');
    if (!modal) {
        console.error('verAsesoresAccesoBase: Modal modal-ver-asesores-acceso no encontrado');
        mostrarError('Error: Modal no encontrado');
        return;
    }
    
    // Establecer valores en el modal
    const nombreElement = document.getElementById('modal-ver-asesores-base-nombre');
    const idElement = document.getElementById('modal-ver-asesores-base-id');
    const tbody = document.getElementById('modal-ver-asesores-tbody');
    
    if (!nombreElement || !idElement || !tbody) {
        console.error('verAsesoresAccesoBase: Elementos del modal no encontrados');
        mostrarError('Error: Elementos del modal no encontrados');
        return;
    }
    
    // Establecer valores
    nombreElement.textContent = baseNombre;
    idElement.value = baseId;
    
    // Mostrar modal
    modal.style.display = 'block';
    
    // Mostrar loading
    tbody.innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando asesores...</td></tr>';
    
    // Cargar asesores con acceso
    fetch(`index.php?action=obtener_asesores_con_acceso&base_id=${baseId}`)
        .then(response => response.json())
        .then(data => {
            console.log('verAsesoresAccesoBase: Datos recibidos:', data);
            
            if (data.success && data.asesores) {
                mostrarAsesoresEnModal(data.asesores, baseId, baseNombre);
            } else {
                const errorMsg = data.message || data.error || 'Error desconocido al cargar asesores';
                tbody.innerHTML = `<tr><td colspan="4" class="text-center alert alert-danger">Error: ${errorMsg}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('verAsesoresAccesoBase: Error al cargar asesores:', error);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center alert alert-danger">Error de conexión al cargar asesores</td></tr>';
        });
}

// Función para mostrar asesores en el modal
function mostrarAsesoresEnModal(asesores, baseId, baseNombre) {
    const tbody = document.getElementById('modal-ver-asesores-tbody');
    const totalElement = document.getElementById('modal-ver-asesores-total');
    
    if (!tbody) {
        console.error('mostrarAsesoresEnModal: Tbody no encontrado');
        return;
    }
    
    // Actualizar contador
    if (totalElement) {
        totalElement.textContent = asesores.length;
    }
    
    if (!asesores || asesores.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center">
                    <i class="fas fa-info-circle"></i>
                    <p>No hay asesores con acceso a esta base</p>
                    <small>Para dar acceso, use el botón "Dar Acceso" en la tabla de bases</small>
                </td>
            </tr>
        `;
        return;
    }
    
    // Crear filas de asesores
    tbody.innerHTML = asesores.map(asesor => `
        <tr data-asesor-cedula="${asesor.asesor_cedula}">
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #007bff); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                        ${(asesor.nombre_completo || 'A').charAt(0).toUpperCase()}
                    </div>
                    <strong>${asesor.nombre_completo || '-'}</strong>
                </div>
            </td>
            <td>${asesor.usuario || '-'}</td>
            <td>${asesor.asesor_cedula || '-'}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="liberarAccesoAsesor(${baseId}, '${asesor.asesor_cedula}', '${(asesor.nombre_completo || '').replace(/'/g, "\\'")}')" title="Liberar Acceso">
                    <i class="fas fa-unlock"></i> Liberar
                </button>
            </td>
        </tr>
    `).join('');
}

// Función para liberar acceso de un asesor
function liberarAccesoAsesor(baseId, asesorCedula, asesorNombre) {
    const confirmacion = confirm(`¿Está seguro que desea quitarle el acceso a "${asesorNombre}"?\n\nEste asesor ya no podrá acceder a los clientes de esta base.`);
    
    if (!confirmacion) {
        return;
    }
    
    // Mostrar loading en el botón
    const botones = document.querySelectorAll(`[onclick*="liberarAccesoAsesor(${baseId}, '${asesorCedula}'"]`);
    botones.forEach(btn => {
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        
        // Liberar acceso
        fetch('index.php?action=liberar_acceso_base', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `base_id=${baseId}&asesor_cedula=${asesorCedula}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('success', `Acceso liberado para ${asesorNombre}`);
                // Recargar la lista de asesores
                const nombreElement = document.getElementById('modal-ver-asesores-base-nombre');
                const baseNombre = nombreElement ? nombreElement.textContent : '';
                verAsesoresAccesoBase(baseId, baseNombre);
                // Recargar bases para actualizar contadores
                if (typeof cargarBases === 'function') {
                    cargarBases();
                }
            } else {
                mostrarError('Error al liberar acceso: ' + (data.message || data.error));
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error al liberar acceso:', error);
            mostrarError('Error de conexión al liberar acceso');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    });
}

// Función para cerrar el modal de ver asesores
function cerrarModalVerAsesores() {
    document.getElementById('modal-ver-asesores-acceso').style.display = 'none';
}

function mostrarClientesEnModal(clientes, baseNombre) {
    console.log('mostrarClientesEnModal: Mostrando clientes. Total:', clientes?.length || 0);
    
    // Verificar que el tbody existe
    const tbody = document.getElementById('modal-clientes-tbody');
    if (!tbody) {
        console.error('mostrarClientesEnModal: Elemento modal-clientes-tbody no encontrado');
        const modalBody = document.getElementById('modal-ver-clientes-body');
        if (modalBody) {
            modalBody.innerHTML = '<div class="alert alert-danger">Error: Elemento de tabla no encontrado</div>';
        }
        return;
    }
    
    console.log('mostrarClientesEnModal: Tbody encontrado, actualizando contenido...');
    
    if (!clientes || clientes.length === 0) {
        console.log('mostrarClientesEnModal: No hay clientes para mostrar');
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">No hay clientes en esta base</td>
            </tr>
        `;
        
        // Actualizar contador
        const totalElement = document.getElementById('modal-clientes-total');
        if (totalElement) {
            totalElement.textContent = '0';
        }
        return;
    }
    
    // Crear filas de clientes
    console.log('mostrarClientesEnModal: Creando filas para', clientes.length, 'clientes');
    tbody.innerHTML = clientes.map(cliente => `
        <tr>
            <td>${cliente.IDENTIFICACION || cliente.cc || '-'}</td>
            <td>${cliente['NOMBRE CONTRATANTE'] || cliente.nombre || '-'}</td>
            <td>${cliente.CELULAR || cliente.cel1 || '-'}</td>
        </tr>
    `).join('');
    
    // Actualizar contador
    const totalElement = document.getElementById('modal-clientes-total');
    if (totalElement) {
        totalElement.textContent = clientes.length;
        console.log('mostrarClientesEnModal: Contador actualizado a', clientes.length);
    } else {
        console.warn('mostrarClientesEnModal: Elemento modal-clientes-total no encontrado');
    }
    
    console.log('mostrarClientesEnModal: Proceso completado');
}

// Funciones verDetalleTarea y completarTarea eliminadas - ya no se usan en la nueva estructura

// ========================================
// FUNCIÓN DE VERIFICACIÓN DE TABLAS
// ========================================

function verificarTablas() {
    console.log('Coord_gestion.js: Verificando tablas...');
    
    fetch('index.php?action=verificar_tablas')
        .then(response => response.json())
        .then(data => {
            console.log('Coord_gestion.js: Resultado verificación:', data);
            
            if (data.success) {
                console.log(`Coord_gestion.js: Tablas OK - Comercios: ${data.total_comercios}, Facturas: ${data.total_facturas}`);
                // Cargar datos iniciales
                cargarBases();
            } else {
                console.error('Coord_gestion.js: Error en verificación:', data.error);
                mostrarErrorTablas(data);
            }
        })
        .catch(error => {
            console.error('Coord_gestion.js: Error al verificar tablas:', error);
            mostrarError('Error de conexión al verificar tablas');
        });
}

function mostrarErrorTablas(data) {
    const mensaje = `
        <div class="alert alert-danger">
            <h4><i class="fas fa-exclamation-triangle"></i> Error de Configuración</h4>
            <p><strong>Problema:</strong> ${data.error}</p>
            <p><strong>Instrucciones:</strong> ${data.instrucciones}</p>
            <hr>
            <p><strong>Pasos a seguir:</strong></p>
            <ol>
                <li>Abra la terminal/consola</li>
                <li>Ejecute el comando: <code>mysql -u root -p crediBanco < database/crear_tablas_simple.sql</code></li>
                <li>Ingrese la contraseña de MySQL cuando se solicite</li>
                <li>Recargue esta página</li>
            </ol>
        </div>
    `;
    
    // Mostrar en la pestaña de bases
    const basesTbody = document.getElementById('bases-tbody');
    if (basesTbody) {
        basesTbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    ${mensaje}
                </td>
            </tr>
        `;
    }
}

// ========================================
// INICIALIZACIÓN
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Coord_gestion.js: Inicializando página...');
    
    // Verificar tablas antes de cargar datos
    verificarTablas();
    
    // Cargar bases existentes para el select de carga existente
    cargarBasesExistentes();
    
    console.log('Coord_gestion.js: Página inicializada');
});
