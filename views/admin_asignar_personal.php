<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Personal - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo"><?php echo APP_NAME; ?></div>
        <nav class="sidebar-nav">
            <ul>
                <li onclick="window.location.href='index.php?action=dashboard'"><i class="fas fa-th-large"></i> Dashboard</li>
                <li onclick="window.location.href='index.php?action=admin_usuarios'"><i class="fas fa-users"></i> Usuarios</li>
                <li class="active" onclick="window.location.href='index.php?action=admin_asignaciones'"><i class="fas fa-user-friends"></i> Asignaciones</li>
            </ul>
        </nav>
        
        <!-- Botón de Cerrar Sesión en la parte inferior -->
        <div class="sidebar-footer">
            <a href="index.php?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </div>

    <div class="main-container">
        <!-- Encabezado Superior -->
        <header class="top-header">
            <div class="header-left">
                <i class="fas fa-user-friends"></i>
                <span>Asignar Personal</span>
                <span><?php echo $_SESSION['usuario_nombre'] ?? 'Usuario'; ?></span>
            </div>
            <div class="header-right">
                <span><i class="fas fa-circle-info"></i></span>
                <span><i class="fas fa-bell"></i></span>
                <img src="https://placehold.co/30x30/FFFFFF/000000?text=<?php echo substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1); ?>" style="border-radius:50%;">
                <span><?php echo $_SESSION['usuario_nombre'] ?? 'Admin'; ?> <i class="fas fa-caret-down"></i></span>
            </div>
        </header>

        <!-- Sección Principal -->
        <section class="current-call-section">
            <div class="call-details">
                <h3>GESTIÓN DE ASIGNACIONES</h3>
                <p class="call-info">Sistema <?php echo APP_NAME; ?></p>
                <p class="call-info">Asignación de Personal</p>
                <small>Administre las asignaciones de coordinadores y asesores</small>
                <div class="media-controls">
                    <button class="media-button" onclick="window.location.href='index.php?action=dashboard'">
                        <i class="fas fa-arrow-left"></i> Volver al Dashboard
                    </button>
                    <button class="media-button" onclick="cambiarTab('asignar')">
                        <i class="fas fa-user-plus"></i> Nueva Asignación
                    </button>
                </div>
            </div>
            
            <div class="call-main-view">
                <div class="client-info">
                    <i class="fas fa-user-friends"></i>
                    <div>
                        <span class="client-name">Panel de Asignaciones</span>
                        <span class="client-company"><?php echo APP_NAME; ?> - Administración</span>
                    </div>
                </div>

                <div class="main-tabs">
                    <span class="active" onclick="cambiarTab('asignar')">ASIGNAR</span>
                    <span onclick="cambiarTab('gestionar')">GESTIONAR</span>
                    <span onclick="cambiarTab('estadisticas')">ESTADÍSTICAS</span>
                    <span onclick="cambiarTab('historial')">HISTORIAL</span>
                </div>
                
                <div class="content-sections">
                    <!-- PESTAÑA 1: ASIGNAR -->
                    <div class="tab-content active" id="tab-asignar">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Nueva Asignación de Personal</h4>
                            <form id="form-asignar-personal" onsubmit="asignarPersonal(event)">
                                <div class="form-section">
                                    <div class="input-group">
                                        <label for="asesor_id">Asesor *</label>
                                        <select id="asesor_id" name="asesor_id" required>
                                            <option value="">Seleccionar asesor</option>
                                            <?php 
                                            // Obtener asesores sin coordinador asignado
                                            require_once 'models/Usuario.php';
                                            $usuario_model = new Usuario();
                                            $asesores = array_filter($usuario_model->obtenerTodos(), function($u) { 
                                                return $u['rol'] === 'asesor' && $u['estado'] === 'activo'; 
                                            });
                                            foreach ($asesores as $asesor): 
                                            ?>
                                                <option value="<?php echo $asesor['cedula']; ?>"><?php echo htmlspecialchars($asesor['nombre_completo']); ?> (<?php echo $asesor['usuario']; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small>Seleccione el asesor a asignar</small>
                                    </div>
                                    <div class="input-group">
                                        <label for="coordinador_id">Coordinador *</label>
                                        <select id="coordinador_id" name="coordinador_id" required>
                                            <option value="">Seleccionar coordinador</option>
                                            <?php 
                                            $coordinadores = array_filter($usuario_model->obtenerTodos(), function($u) { 
                                                return $u['rol'] === 'coordinador' && $u['estado'] === 'activo'; 
                                            });
                                            foreach ($coordinadores as $coord): 
                                            ?>
                                                <option value="<?php echo $coord['cedula']; ?>"><?php echo htmlspecialchars($coord['nombre_completo']); ?> (<?php echo $coord['usuario']; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small>Seleccione el coordinador responsable</small>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <div class="input-group">
                                        <label for="fecha_asignacion">Fecha de Asignación</label>
                                        <input type="date" id="fecha_asignacion" name="fecha_asignacion" value="<?php echo date('Y-m-d'); ?>">
                                        <small>Fecha en que se efectúa la asignación</small>
                                    </div>
                                    <div class="input-group">
                                        <label for="notas_asignacion">Notas de Asignación</label>
                                        <textarea id="notas_asignacion" name="notas_asignacion" rows="3" placeholder="Información adicional sobre la asignación..."></textarea>
                                        <small>Comentarios sobre la asignación (opcional)</small>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">
                                        <i class="fas fa-eraser"></i> Limpiar
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="btn-asignar">
                                        <i class="fas fa-user-friends"></i> Asignar Personal
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <aside class="right-sidebar">
                            <h4>Información de Asignación</h4>
                            <div class="info-card">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <h5>Proceso de Asignación</h5>
                                    <p>Los asesores deben ser asignados a un coordinador para poder trabajar en el sistema.</p>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <i class="fas fa-users"></i>
                                <div>
                                    <h5>Disponibilidad</h5>
                                    <p><strong>Asesores:</strong> <?php echo count($asesores); ?> disponibles</p>
                                    <p><strong>Coordinadores:</strong> <?php echo count($coordinadores); ?> disponibles</p>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h5>Permisos</h5>
                                    <p>Los coordinadores pueden gestionar y supervisar a sus asesores asignados.</p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <!-- PESTAÑA 2: GESTIONAR -->
                    <div class="tab-content" id="tab-gestionar" style="display: none;">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Gestionar Asignaciones Existentes</h4>
                            
                            <!-- Filtros -->
                            <div class="filters-section">
                                <div class="filter-group">
                                    <label for="filtro_coordinador">Filtrar por Coordinador</label>
                                    <select id="filtro_coordinador" onchange="filtrarAsignaciones()">
                                        <option value="">Todos los coordinadores</option>
                                        <?php foreach ($coordinadores as $coord): ?>
                                            <option value="<?php echo $coord['cedula']; ?>"><?php echo htmlspecialchars($coord['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="filtro_estado">Filtrar por Estado</label>
                                    <select id="filtro_estado" onchange="filtrarAsignaciones()">
                                        <option value="">Todos los estados</option>
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Lista de asignaciones -->
                            <div class="assignments-list">
                                <div class="assignment-item">
                                    <div class="assignment-header">
                                        <h5>Coordinador: Juan Pérez</h5>
                                        <div class="assignment-actions">
                                            <button class="btn btn-sm btn-primary" onclick="editarAsignacion(1)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarAsignacion(1)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="assignment-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Asesores Asignados:</span>
                                            <span class="detail-value">3</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Estado:</span>
                                            <span class="detail-value status-active">Activo</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Fecha:</span>
                                            <span class="detail-value">15/01/2024</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Ejemplo de asignación vacía -->
                                <div class="assignment-item empty">
                                    <div class="empty-state">
                                        <i class="fas fa-user-friends"></i>
                                        <h5>No hay asignaciones</h5>
                                        <p>Las asignaciones aparecerán aquí una vez que se creen.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <aside class="right-sidebar">
                            <h4>Acciones Rápidas</h4>
                            <div class="quick-actions">
                                <button class="action-btn" onclick="cambiarTab('asignar')">
                                    <i class="fas fa-user-plus"></i>
                                    Nueva Asignación
                                </button>
                                <button class="action-btn" onclick="exportarAsignaciones()">
                                    <i class="fas fa-download"></i>
                                    Exportar Lista
                                </button>
                                <button class="action-btn" onclick="actualizarAsignaciones()">
                                    <i class="fas fa-sync"></i>
                                    Actualizar
                                </button>
                            </div>
                            
                            <div class="info-card">
                                <i class="fas fa-chart-pie"></i>
                                <div>
                                    <h5>Resumen</h5>
                                    <p><strong>Total Asignaciones:</strong> 0</p>
                                    <p><strong>Asesores Asignados:</strong> 0</p>
                                    <p><strong>Coordinadores Activos:</strong> <?php echo count($coordinadores); ?></p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <!-- PESTAÑA 3: ESTADÍSTICAS -->
                    <div class="tab-content" id="tab-estadisticas" style="display: none;">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Estadísticas de Asignaciones</h4>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h5>Total Asesores</h5>
                                        <div class="stat-value"><?php echo count($asesores); ?></div>
                                        <div class="stat-subtitle">En el sistema</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h5>Asesores Asignados</h5>
                                        <div class="stat-value">0</div>
                                        <div class="stat-subtitle">Con coordinador</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h5>Sin Asignar</h5>
                                        <div class="stat-value"><?php echo count($asesores); ?></div>
                                        <div class="stat-subtitle">Pendientes</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h5>Coordinadores</h5>
                                        <div class="stat-value"><?php echo count($coordinadores); ?></div>
                                        <div class="stat-subtitle">Disponibles</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="chart-section">
                                <h5>Distribución de Asignaciones</h5>
                                <div class="chart-placeholder">
                                    <i class="fas fa-chart-pie"></i>
                                    <p>Gráfico de distribución</p>
                                    <small>Se mostrará cuando haya datos</small>
                                </div>
                            </div>
                        </div>
                        
                        <aside class="right-sidebar">
                            <h4>Métricas</h4>
                            <div class="metric-item">
                                <span class="metric-label">Tasa de Asignación</span>
                                <div class="metric-bar">
                                    <div class="metric-fill" style="width: 0%"></div>
                                </div>
                                <span class="metric-value">0%</span>
                            </div>
                            
                            <div class="metric-item">
                                <span class="metric-label">Eficiencia</span>
                                <div class="metric-bar">
                                    <div class="metric-fill" style="width: 75%"></div>
                                </div>
                                <span class="metric-value">75%</span>
                            </div>
                        </aside>
                    </div>

                    <!-- PESTAÑA 4: HISTORIAL -->
                    <div class="tab-content" id="tab-historial" style="display: none;">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Historial de Asignaciones</h4>
                            
                            <div class="history-filters">
                                <div class="filter-group">
                                    <label for="fecha_desde">Desde</label>
                                    <input type="date" id="fecha_desde" onchange="filtrarHistorial()">
                                </div>
                                <div class="filter-group">
                                    <label for="fecha_hasta">Hasta</label>
                                    <input type="date" id="fecha_hasta" onchange="filtrarHistorial()">
                                </div>
                                <div class="filter-group">
                                    <label for="filtro_accion">Acción</label>
                                    <select id="filtro_accion" onchange="filtrarHistorial()">
                                        <option value="">Todas las acciones</option>
                                        <option value="crear">Crear</option>
                                        <option value="editar">Editar</option>
                                        <option value="eliminar">Eliminar</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="history-list">
                                <div class="history-item">
                                    <div class="history-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="history-content">
                                        <h5>Asignación creada</h5>
                                        <p>Asesor: María García asignado a Coordinador: Juan Pérez</p>
                                        <small>15/01/2024 10:30 AM - Admin</small>
                                    </div>
                                </div>
                                
                                <div class="history-item">
                                    <div class="history-icon">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="history-content">
                                        <h5>Asignación modificada</h5>
                                        <p>Cambio de coordinador para Asesor: Carlos López</p>
                                        <small>14/01/2024 15:45 PM - Admin</small>
                                    </div>
                                </div>
                                
                                <!-- Estado vacío -->
                                <div class="history-item empty">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <h5>No hay historial</h5>
                                        <p>Las acciones aparecerán aquí conforme se realicen.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <aside class="right-sidebar">
                            <h4>Resumen de Actividad</h4>
                            <div class="activity-summary">
                                <div class="activity-item">
                                    <i class="fas fa-plus-circle"></i>
                                    <div>
                                        <span class="activity-count">0</span>
                                        <span class="activity-label">Creadas</span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <i class="fas fa-edit"></i>
                                    <div>
                                        <span class="activity-count">0</span>
                                        <span class="activity-label">Modificadas</span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <i class="fas fa-trash"></i>
                                    <div>
                                        <span class="activity-count">0</span>
                                        <span class="activity-label">Eliminadas</span>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Alertas -->
    <div id="alert-container"></div>

    <script>
        // Función para cambiar entre pestañas
        function cambiarTab(tabName) {
            // Ocultar todas las pestañas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover clase active de todas las pestañas
            const tabSpans = document.querySelectorAll('.main-tabs span');
            tabSpans.forEach(span => {
                span.classList.remove('active');
            });
            
            // Mostrar la pestaña seleccionada
            const selectedTab = document.getElementById('tab-' + tabName);
            if (selectedTab) {
                selectedTab.style.display = 'block';
            }
            
            // Marcar la pestaña como activa
            const selectedSpan = document.querySelector(`[onclick="cambiarTab('${tabName}')"]`);
            if (selectedSpan) {
                selectedSpan.classList.add('active');
            }
        }
        
        // Función para asignar personal
        function asignarPersonal(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-asignar-personal');
            const btnAsignar = document.getElementById('btn-asignar');
            
            // Validar formulario
            if (!validateForm()) {
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnAsignar.disabled = true;
            btnAsignar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asignando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = '';
            
            // Recopilar datos del formulario
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Enviar solicitud AJAX
            fetch('index.php?action=asignar_personal', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlerta(result.message, 'success');
                        form.reset();
                        setTimeout(() => {
                            cambiarTab('gestionar');
                        }, 2000);
                    } else {
                        mostrarAlerta(result.message, 'error');
                    }
                } catch (e) {
                    mostrarAlerta('Error al procesar la respuesta del servidor', 'error');
                }
            })
            .catch(error => {
                mostrarAlerta('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaurar botón
                btnAsignar.disabled = false;
                btnAsignar.innerHTML = '<i class="fas fa-user-friends"></i> Asignar Personal';
            });
        }
        
        // Función para validar formulario
        function validateForm() {
            const requiredFields = ['asesor_id', 'coordinador_id'];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });
            
            return isValid;
        }
        
        // Función para limpiar formulario
        function limpiarFormulario() {
            document.getElementById('form-asignar-personal').reset();
            const inputs = document.querySelectorAll('#form-asignar-personal input, #form-asignar-personal select, #form-asignar-personal textarea');
            inputs.forEach(input => input.classList.remove('error'));
        }
        
        // Función para mostrar alertas
        function mostrarAlerta(mensaje, tipo) {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${mensaje}
            `;
            
            alertContainer.appendChild(alertDiv);
            
            // Auto-ocultar después de 5 segundos
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        // Funciones de filtrado
        function filtrarAsignaciones() {
            // Implementar lógica de filtrado
            console.log('Filtrando asignaciones...');
        }
        
        function filtrarHistorial() {
            // Implementar lógica de filtrado de historial
            console.log('Filtrando historial...');
        }
        
        // Funciones de gestión
        function editarAsignacion(id) {
            console.log('Editando asignación:', id);
        }
        
        function eliminarAsignacion(id) {
            if (confirm('¿Está seguro de que desea eliminar esta asignación?')) {
                console.log('Eliminando asignación:', id);
            }
        }
        
        function exportarAsignaciones() {
            console.log('Exportando asignaciones...');
        }
        
        function actualizarAsignaciones() {
            console.log('Actualizando asignaciones...');
        }
        
        // Validación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-asignar-personal');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('error');
                    } else {
                        this.classList.remove('error');
                    }
                });
            });
        });
    </script>

    <style>
        .info-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .info-card i {
            color: #007bff;
            font-size: 1.2em;
            margin-top: 2px;
        }
        
        .info-card h5 {
            margin: 0 0 5px 0;
            color: #495057;
        }
        
        .info-card p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .filters-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .assignments-list {
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        
        .assignment-item {
            border-bottom: 1px solid #e9ecef;
            padding: 20px;
        }
        
        .assignment-item:last-child {
            border-bottom: none;
        }
        
        .assignment-item.empty {
            text-align: center;
            padding: 40px 20px;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .assignment-header h5 {
            margin: 0;
            color: #495057;
        }
        
        .assignment-actions {
            display: flex;
            gap: 8px;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            font-size: 0.8em;
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 0.9em;
            color: #495057;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .empty-state h5 {
            margin: 0 0 10px 0;
            color: #6c757d;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.9em;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #495057;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }
        
        .action-btn i {
            color: #007bff;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: #007bff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
        }
        
        .stat-content h5 {
            margin: 0 0 5px 0;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: 700;
            color: #495057;
            margin: 0;
        }
        
        .stat-subtitle {
            font-size: 0.8em;
            color: #6c757d;
        }
        
        .chart-section {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        
        .chart-placeholder {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .chart-placeholder i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .metric-item {
            margin-bottom: 20px;
        }
        
        .metric-label {
            display: block;
            font-size: 0.9em;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .metric-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .metric-fill {
            background: #007bff;
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .metric-value {
            font-size: 0.9em;
            font-weight: 600;
            color: #495057;
        }
        
        .history-filters {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .history-list {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .history-item {
            border-bottom: 1px solid #e9ecef;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-item.empty {
            justify-content: center;
        }
        
        .history-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
            font-size: 1.2em;
        }
        
        .history-content h5 {
            margin: 0 0 5px 0;
            color: #495057;
        }
        
        .history-content p {
            margin: 0 0 5px 0;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .history-content small {
            color: #adb5bd;
            font-size: 0.8em;
        }
        
        .activity-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .activity-item:last-child {
            margin-bottom: 0;
        }
        
        .activity-item i {
            color: #007bff;
            font-size: 1.2em;
        }
        
        .activity-count {
            font-size: 1.5em;
            font-weight: 700;
            color: #495057;
        }
        
        .activity-label {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 300px;
        }
        
        .alert-success {
            background: #28a745;
        }
        
        .alert-error {
            background: #dc3545;
        }
        
        .input-group input.error,
        .input-group select.error,
        .input-group textarea.error {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</body>
</html>
