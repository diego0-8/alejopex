<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
</head>
<body data-user-id="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">

    <?php 
    // Incluir navbar compartido
    $action = 'dashboard';
    include 'views/Navbar.php'; 
    ?>

    <div class="main-container">
        <?php 
        // Incluir header compartido
        include 'views/Header.php'; 
        ?>

        <!-- Sección Principal del Dashboard -->
        <section class="current-call-section">
            <div class="call-details">
                <h3>ESTADÍSTICAS GENERALES</h3>
                <p class="call-info">Sistema <?php echo APP_NAME; ?></p>
                <p class="call-info">Administración Central</p>
                <small>Acciones Principales</small>
                <div class="media-controls">
                    <button class="media-button" onclick="openModal('crear-usuario')">
                        <i class="fas fa-user-plus"></i> Crear Usuario
                    </button>
                    <button class="media-button" onclick="openModal('asignar-personal')">
                        <i class="fas fa-user-friends"></i> Asignar Personal
                    </button>
                    <button class="media-button" onclick="openModal('cargar-clientes')">
                        <i class="fas fa-upload"></i> Cargar Clientes
                    </button>
                    <button class="media-button" onclick="openModal('generar-reporte')">
                        <i class="fas fa-file-alt"></i> Generar Reporte
                    </button>
                </div>
                
            </div>
            
            <div class="call-main-view">
                <div class="client-info">
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <span class="client-name">Panel de Control</span>
                        <span class="client-company"><?php echo APP_NAME; ?> - Administración</span>
                    </div>
                </div>

                <div class="main-tabs">
                    <span class="active" onclick="cambiarTab('estadisticas')">ESTADÍSTICAS</span>
                    <span onclick="cambiarTab('usuarios')">USUARIOS</span>
                    <span onclick="cambiarTab('asignaciones')">ASIGNACIONES</span>
                    <span onclick="cambiarTab('clientes')">CLIENTES</span>
                    <span onclick="cambiarTab('actividad')">ACTIVIDAD</span>
                </div>
                
                <div class="content-sections">
                    <!-- PESTAÑA 1: ESTADÍSTICAS -->
                    <div class="tab-content active" id="tab-estadisticas">
                        <div class="left-content">
                            <!-- Widgets de Estadísticas -->
                            <h4 style="margin-top: 0;">Resumen de Sistema</h4>
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Total Usuarios</label>
                                    <input type="text" value="<?php echo $estadisticas['total_usuarios'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Usuarios Activos</label>
                                    <input type="text" value="<?php echo $estadisticas['usuarios_activos'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Total Coordinadores</label>
                                    <input type="text" value="<?php echo $estadisticas['total_coordinadores'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Coordinadores Disponibles</label>
                                    <input type="text" value="<?php echo $estadisticas['coordinadores_disponibles'] ?? 0; ?>" readonly>
                                </div>
                            </div>
                            
                            <!-- Segunda fila de estadísticas -->
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Total Asesores</label>
                                    <input type="text" value="<?php echo $estadisticas['total_asesores'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Asesores Asignados</label>
                                    <input type="text" value="<?php echo $estadisticas['asesores_asignados'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Total Clientes</label>
                                    <input type="text" value="<?php echo $estadisticas['total_clientes'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Nuevos</label>
                                    <input type="text" value="<?php echo $estadisticas['clientes_nuevos'] ?? 0; ?>" readonly>
                                </div>
                            </div>
                            
                            <!-- Tercera fila de estadísticas -->
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Total Contratos</label>
                                    <input type="text" value="<?php echo $estadisticas['total_contratos'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Total Cartera</label>
                                    <input type="text" value="$<?php echo number_format($estadisticas['total_cartera'] ?? 0, 0, ',', '.'); ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Gestionados</label>
                                    <input type="text" value="<?php echo $estadisticas['clientes_gestionados'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Pendientes</label>
                                    <input type="text" value="<?php echo $estadisticas['clientes_pendientes'] ?? 0; ?>" readonly>
                                </div>
                            </div>

                            <!-- Porcentajes de Rendimiento -->
                            <h4>Rendimiento del Sistema</h4>
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Usuarios Activos (%)</label>
                                    <input type="text" value="<?php 
                                        $total = $estadisticas['total_usuarios'] ?? 0;
                                        $activos = $estadisticas['usuarios_activos'] ?? 0;
                                        echo ($total > 0) ? round(($activos / $total) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Coordinadores Disponibles (%)</label>
                                    <input type="text" value="<?php 
                                        $total = $estadisticas['total_coordinadores'] ?? 0;
                                        $disponibles = $estadisticas['coordinadores_disponibles'] ?? 0;
                                        echo ($total > 0) ? round(($disponibles / $total) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Asesores Asignados (%)</label>
                                    <input type="text" value="<?php 
                                        $total = $estadisticas['total_asesores'] ?? 0;
                                        $asignados = $estadisticas['asesores_asignados'] ?? 0;
                                        echo ($total > 0) ? round(($asignados / $total) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Nuevos (%)</label>
                                    <input type="text" value="<?php 
                                        $total = $estadisticas['total_clientes'] ?? 0;
                                        $nuevos = $estadisticas['clientes_nuevos'] ?? 0;
                                        echo ($total > 0) ? round(($nuevos / $total) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: USUARIOS -->
                    <div class="tab-content" id="tab-usuarios" style="display: none;">
                        <div class="left-content">
                            <div class="usuarios-header">
                                <h4 style="margin-top: 0;">Gestión de Usuarios</h4>
                                <button class="btn btn-primary" onclick="openModal('crear-usuario')">
                                    <i class="fas fa-user-plus"></i> Crear Nuevo Usuario
                                </button>
                            </div>
                            
                            <!-- Estadísticas rápidas -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <h5>Total Usuarios</h5>
                                    <div class="stat-value"><?php echo $estadisticas['total_usuarios'] ?? 0; ?></div>
                                    <div class="stat-subtitle">En el sistema</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Usuarios Activos</h5>
                                    <div class="stat-value"><?php echo $estadisticas['usuarios_activos'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Estado activo</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Coordinadores</h5>
                                    <div class="stat-value"><?php echo $estadisticas['total_coordinadores'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Total coordinadores</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Asesores</h5>
                                    <div class="stat-value"><?php echo $estadisticas['total_asesores'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Total asesores</div>
                                </div>
                            </div>
                            
                            <!-- Tabla de usuarios -->
                            <div class="usuarios-table-container">
                                <div class="table-header">
                                    <h5>Lista de Usuarios</h5>
                                    <div class="table-actions">
                                        <button class="btn btn-sm btn-secondary" onclick="refreshUsuarios()">
                                            <i class="fas fa-sync-alt"></i> Actualizar
                                </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="usuarios-table">
                                        <thead>
                                            <tr>
                                                <th>Nombre Completo</th>
                                                <th>Usuario</th>
                                                <th>Rol</th>
                                                <th>Estado</th>
                                                <th>Fecha Creación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($usuarios)): ?>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <tr data-usuario-id="<?php echo $usuario['cedula']; ?>">
                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-avatar">
                                                                    <?php echo strtoupper(substr($usuario['nombre_completo'], 0, 1)); ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                                                    <small>Cédula: <?php echo $usuario['cedula']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="username"><?php echo htmlspecialchars($usuario['usuario']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="rol-badge rol-<?php echo $usuario['rol']; ?>">
                                                                <?php echo ucfirst($usuario['rol']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="estado-badge estado-<?php echo $usuario['estado']; ?>">
                                                                <i class="fas fa-circle"></i>
                                                                <?php echo ucfirst($usuario['estado']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fecha-creacion">
                                                                <?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn-action btn-edit" onclick="editarUsuario('<?php echo $usuario['cedula']; ?>')" title="Editar">
                                                                    <i class="fas fa-edit"></i>
                                </button>
                                                                <?php if ($usuario['estado'] === 'activo'): ?>
                                                                    <button class="btn-action btn-disable" onclick="cambiarEstadoUsuario('<?php echo $usuario['cedula']; ?>', 'inactivo')" title="Desactivar">
                                                                        <i class="fas fa-user-times"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn-action btn-enable" onclick="cambiarEstadoUsuario('<?php echo $usuario['cedula']; ?>', 'activo')" title="Activar">
                                                                        <i class="fas fa-user-check"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($usuario['cedula'] !== $_SESSION['usuario_id']): ?>
                                                                    <button class="btn-action btn-delete" onclick="eliminarUsuario('<?php echo $usuario['cedula']; ?>')" title="Eliminar">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="no-data">
                                                        <i class="fas fa-users"></i>
                                                        <p>No hay usuarios registrados</p>
                                                        <button class="btn btn-primary" onclick="openModal('crear-usuario')">
                                                            <i class="fas fa-user-plus"></i> Crear Primer Usuario
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: ASIGNACIONES -->
                    <div class="tab-content" id="tab-asignaciones" style="display: none;">
                        <div class="left-content">
                            <div class="asignaciones-header">
                                <h4 style="margin-top: 0;">Gestión de Asignaciones</h4>
                                <div class="table-actions">
                                    <button class="btn btn-primary" onclick="openModal('asignar-personal')">
                                        <i class="fas fa-user-plus"></i> Nueva Asignación
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="refreshAsignaciones()">
                                        <i class="fas fa-sync-alt"></i> Actualizar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Tabla de asignaciones -->
                            <div class="asignaciones-table-container">
                                <div class="table-responsive">
                                    <table class="asignaciones-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Asesor</th>
                                                <th>Coordinador</th>
                                                <th>Estado</th>
                                                <th>Fecha Asignación</th>
                                                <th>Creado Por</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($asignaciones) && is_array($asignaciones)): ?>
                                                <?php foreach ($asignaciones as $asignacion): ?>
                                                    <tr data-asignacion-id="<?php echo $asignacion['id']; ?>">
                                                        <td><?php echo $asignacion['id']; ?></td>
                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-avatar">
                                                                    <?php echo strtoupper(substr($asignacion['asesor_nombre'], 0, 1)); ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($asignacion['asesor_nombre']); ?></strong>
                                                                    <small>Cédula: <?php echo $asignacion['asesor_cedula']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-avatar">
                                                                    <?php echo strtoupper(substr($asignacion['coordinador_nombre'], 0, 1)); ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($asignacion['coordinador_nombre']); ?></strong>
                                                                    <small>Cédula: <?php echo $asignacion['coordinador_cedula']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="estado-badge estado-<?php echo $asignacion['estado']; ?>">
                                                                <i class="fas fa-circle"></i>
                                                                <?php echo ucfirst($asignacion['estado']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fecha-asignacion">
                                                                <?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_asignacion'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="creado-por">
                                                                <?php echo htmlspecialchars($asignacion['creador_nombre'] ?? 'Sistema'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn-action btn-liberar" onclick="liberarAsignacion(<?php echo $asignacion['id']; ?>)" title="Liberar Asesor">
                                                                    <i class="fas fa-unlink"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="no-data">
                                                        <i class="fas fa-user-friends"></i>
                                                        <p>No hay asignaciones registradas</p>
                                                        <small>Las asignaciones aparecerán aquí una vez que se creen</small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <aside class="right-sidebar">
                            <h4>Resumen de Asignaciones</h4>
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <span class="stat-number"><?php echo count($asignaciones); ?></span>
                                        <span class="stat-label">Total Asignaciones</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-user-check"></i>
                                    <div>
                                        <span class="stat-number"><?php echo count(array_filter($asignaciones, function($a) { return $a['estado'] === 'activa'; })); ?></span>
                                        <span class="stat-label">Activas</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-user-times"></i>
                                    <div>
                                        <span class="stat-number"><?php echo count(array_filter($asignaciones, function($a) { return $a['estado'] === 'inactiva'; })); ?></span>
                                        <span class="stat-label">Inactivas</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="quick-actions">
                                <button class="action-btn" onclick="openModal('asignar-personal')">
                                    <i class="fas fa-user-plus"></i>
                                    Nueva Asignación
                                </button>
                                <button class="action-btn" onclick="exportarAsignaciones()">
                                    <i class="fas fa-download"></i>
                                    Exportar Lista
                                </button>
                                <button class="action-btn" onclick="refreshAsignaciones()">
                                    <i class="fas fa-sync"></i>
                                    Actualizar
                                </button>
                            </div>
                        </aside>
                    </div>

                    <!-- PESTAÑA 4: CLIENTES -->
                    <div class="tab-content" id="tab-clientes" style="display: none;">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Resumen de Clientes</h4>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <h5>Total Clientes</h5>
                                    <div class="stat-value"><?php echo $estadisticas['total_clientes'] ?? 0; ?></div>
                                    <div class="stat-subtitle">En la base de datos</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Gestionados</h5>
                                    <div class="stat-value"><?php echo $estadisticas['clientes_gestionados'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Con al menos una gestión</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Pendientes</h5>
                                    <div class="stat-value"><?php echo $estadisticas['clientes_pendientes'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Sin gestionar</div>
                                </div>
                                <div class="stat-card">
                                    <h5>Nuevos (30 días)</h5>
                                    <div class="stat-value"><?php echo $estadisticas['clientes_nuevos'] ?? 0; ?></div>
                                    <div class="stat-subtitle">Último mes</div>
                                </div>
                            </div>
                            
                            <div class="quick-actions">
                                <button class="btn btn-primary" onclick="openModal('cargar-clientes')">
                                    <i class="fas fa-upload"></i> Cargar Nuevos Clientes
                                </button>
                                <button class="btn btn-secondary" onclick="openModal('generar-reporte')">
                                    <i class="fas fa-file-alt"></i> Generar Reporte
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 4: ACTIVIDAD -->
                    <div class="tab-content" id="tab-actividad" style="display: none;">
                        <div class="left-content">
                            <h4 style="margin-top: 0;">Actividad Reciente del Sistema</h4>
                            <div class="activity-list">
                                <?php if (!empty($estadisticas['actividad_reciente'])): ?>
                                    <?php foreach ($estadisticas['actividad_reciente'] as $actividad): ?>
                                        <div class="history-item">
                                            <div class="activity-icon">
                                                <?php 
                                                $icono = 'fas fa-info-circle';
                                                switch($actividad['tipo']) {
                                                    case 'usuario_creado':
                                                        $icono = 'fas fa-user-plus';
                                                        break;
                                                    case 'carga_excel':
                                                        $icono = 'fas fa-upload';
                                                        break;
                                                    case 'asignacion_asesor':
                                                        $icono = 'fas fa-user-friends';
                                                        break;
                                                    case 'gestion_cliente':
                                                        $icono = 'fas fa-phone';
                                                        break;
                                                }
                                                ?>
                                                <i class="<?php echo $icono; ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <h5><?php echo htmlspecialchars($actividad['descripcion']); ?></h5>
                                                <small>
                                                    <strong><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></strong> 
                                                    (<?php echo ucfirst($actividad['usuario_rol']); ?>) - 
                                                    <?php echo $actividad['tiempo_relativo']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="history-item">
                                        <h5>No hay actividad reciente</h5>
                                        <small>El sistema está esperando actividad</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <aside class="right-sidebar">
                        <h4>Acciones Rápidas</h4>
                        <div class="quick-actions-sidebar">
                            <button class="action-btn-sidebar" onclick="openModal('crear-usuario')">
                                <i class="fas fa-user-plus"></i> Nuevo Usuario
                            </button>
                            <button class="action-btn-sidebar" onclick="openModal('asignar-personal')">
                                <i class="fas fa-user-friends"></i> Asignar
                            </button>
                            <button class="action-btn-sidebar" onclick="openModal('cargar-clientes')">
                                <i class="fas fa-upload"></i> Cargar
                            </button>
                            <button class="action-btn-sidebar" onclick="openModal('generar-reporte')">
                                <i class="fas fa-file-alt"></i> Reporte
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>

    <!-- Modals -->
    <!-- Modal Crear Usuario -->
    <div id="crear-usuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Crear Nuevo Usuario</h3>
                <button class="close-btn" onclick="closeModal('crear-usuario')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-crear-usuario" onsubmit="crearUsuario(event)">
                    <div class="form-group">
                        <label for="cedula">Cédula *</label>
                        <input type="text" id="cedula" name="cedula" required placeholder="Ej: 12345678">
                        <small>Identificación única del usuario</small>
                    </div>
                    <div class="form-group">
                        <label for="nombre_completo">Nombre Completo *</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" required placeholder="Ej: Juan Pérez García">
                        <small>Nombre y apellidos completos</small>
                    </div>
                    <div class="form-group">
                        <label for="usuario">Usuario *</label>
                        <input type="text" id="usuario" name="usuario" required placeholder="Ej: jperez">
                        <small>Nombre único para iniciar sesión</small>
                    </div>
                    <div class="form-group">
                        <label for="contrasena">Contraseña *</label>
                        <input type="password" id="contrasena" name="contrasena" required placeholder="Mínimo 6 caracteres">
                        <small>Contraseña segura para el acceso</small>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_contrasena">Confirmar Contraseña *</label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required placeholder="Repita la contraseña">
                        <small>Debe coincidir con la contraseña anterior</small>
                    </div>
                    <div class="form-group">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" required onchange="toggleCamposAsesor()">
                            <option value="">Seleccionar rol</option>
                            <option value="administrador">Administrador</option>
                            <option value="coordinador">Coordinador</option>
                            <option value="asesor">Asesor</option>
                        </select>
                        <small>Define los permisos del usuario</small>
                    </div>
                    
                    <!-- Campos específicos para asesores (WebRTC Softphone) -->
                    <div id="campos-asesor" style="display: none;">
                        <div class="form-group">
                            <label for="extension">Extensión SIP</label>
                            <input type="text" id="extension" name="extension" placeholder="Ej: 1001">
                            <small>Número de extensión para el softphone WebRTC (opcional)</small>
                        </div>
                        <div class="form-group">
                            <label for="sip_password">Contraseña SIP</label>
                            <input type="password" id="sip_password" name="sip_password" placeholder="Contraseña para autenticación SIP">
                            <small>Contraseña para autenticación en el servidor SIP (opcional)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado *</label>
                        <select id="estado" name="estado" required>
                            <option value="activo" selected>Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                        <small>Estado inicial del usuario</small>
                    </div>
                    
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('crear-usuario')">Cancelar</button>
                        <button type="submit" id="btn-crear-usuario" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Crear Usuario
                        </button>
                    </div>
                </form>
                <div id="alert-container-crear"></div>
            </div>
        </div>
    </div>

    <!-- Modal Asignar Personal -->
    <div id="asignar-personal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Asignar Personal</h3>
                <button class="close-btn" onclick="closeModal('asignar-personal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-asignar-personal" onsubmit="asignarPersonal(event)">
                    <div class="form-group">
                        <label for="asesor_cedula">Asesor *</label>
                        <select id="asesor_cedula" name="asesor_cedula" required>
                            <option value="">Seleccionar asesor</option>
                            <?php foreach ($estadisticas['asesores_sin_coordinador'] ?? [] as $asesor): ?>
                                <option value="<?php echo $asesor['cedula']; ?>"><?php echo $asesor['nombre_completo']; ?> (<?php echo $asesor['usuario']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small>Seleccione un asesor que no tenga coordinador asignado</small>
                    </div>
                    <div class="form-group">
                        <label for="coordinador_cedula">Coordinador *</label>
                        <select id="coordinador_cedula" name="coordinador_cedula" required>
                            <option value="">Seleccionar coordinador</option>
                            <?php foreach ($coordinadores as $coord): ?>
                                <option value="<?php echo $coord['cedula']; ?>"><?php echo $coord['nombre_completo']; ?> (<?php echo $coord['usuario']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small>Seleccione el coordinador que supervisará al asesor</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('asignar-personal')">Cancelar</button>
                        <button type="submit" id="btn-asignar-personal" class="btn btn-primary">
                            <i class="fas fa-user-friends"></i> Asignar
                        </button>
                    </div>
                </form>
                <div id="alert-container-asignar"></div>
            </div>
        </div>
    </div>

    <!-- Modal Cargar Clientes -->
    <div id="cargar-clientes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cargar Clientes desde Excel</h3>
                <button class="close-btn" onclick="closeModal('cargar-clientes')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-cargar-clientes" onsubmit="cargarClientes(event)">
                    <div class="form-group">
                        <label for="archivo">Seleccionar archivo Excel/CSV</label>
                        <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="form-group">
                        <label for="coordinador_id">Asignar a Coordinador</label>
                        <select id="coordinador_id" name="coordinador_id" required>
                            <option value="">Seleccionar coordinador</option>
                            <?php foreach ($coordinadores as $coord): ?>
                                <option value="<?php echo $coord['id']; ?>"><?php echo $coord['nombre_completo']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('cargar-clientes')">Cancelar</button>
                        <button type="submit" id="btn-cargar-clientes" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Cargar Clientes
                        </button>
                    </div>
                </form>
                <div id="alert-container-cargar"></div>
            </div>
        </div>
    </div>

    <!-- Modal Generar Reporte -->
    <div id="generar-reporte" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Generar Reporte</h3>
                <button class="close-btn" onclick="closeModal('generar-reporte')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-generar-reporte" onsubmit="generarReporte(event)">
                    <div class="form-group">
                        <label for="tipo_reporte">Tipo de Reporte</label>
                        <select id="tipo_reporte" name="tipo_reporte" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="usuarios">Reporte de Usuarios</option>
                            <option value="clientes">Reporte de Clientes</option>
                            <option value="gestion">Reporte de Gestión</option>
                            <option value="productividad">Reporte de Productividad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha de Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('generar-reporte')">Cancelar</button>
                        <button type="submit" id="btn-generar-reporte" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> Generar Reporte
                        </button>
                    </div>
                </form>
                <div id="alert-container-reporte"></div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div id="editar-usuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Usuario</h3>
                <button class="close-btn" onclick="closeModal('editar-usuario')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-editar-usuario" onsubmit="editarUsuarioSubmit(event)">
                    <input type="hidden" id="editar_cedula" name="cedula">
                    
                    <div class="form-group">
                        <label for="editar_cedula_display">Cédula</label>
                        <input type="text" id="editar_cedula_display" readonly style="background-color: #f8f9fa; color: #6c757d;">
                        <small>La cédula no se puede modificar</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_nombre_completo">Nombre Completo *</label>
                        <input type="text" id="editar_nombre_completo" name="nombre_completo" required placeholder="Ej: Juan Pérez García">
                        <small>Nombre y apellidos completos</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_usuario">Usuario *</label>
                        <input type="text" id="editar_usuario" name="usuario" required placeholder="Ej: jperez">
                        <small>Nombre único para iniciar sesión</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_contrasena">Nueva Contraseña</label>
                        <input type="password" id="editar_contrasena" name="contrasena" placeholder="Dejar vacío para mantener la actual">
                        <small>Dejar vacío si no desea cambiar la contraseña</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_confirmar_contrasena">Confirmar Nueva Contraseña</label>
                        <input type="password" id="editar_confirmar_contrasena" name="confirmar_contrasena" placeholder="Repita la nueva contraseña">
                        <small>Debe coincidir con la nueva contraseña</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_rol">Rol *</label>
                        <select id="editar_rol" name="rol" required onchange="toggleCamposAsesorEditar()">
                            <option value="">Seleccionar rol</option>
                            <option value="administrador">Administrador</option>
                            <option value="coordinador">Coordinador</option>
                            <option value="asesor">Asesor</option>
                        </select>
                        <small>Define los permisos del usuario</small>
                    </div>
                    
                    <!-- Campos específicos para asesores (WebRTC Softphone) -->
                    <div id="campos-asesor-editar" style="display: none;">
                        <div class="form-group">
                            <label for="editar_extension">Extensión SIP</label>
                            <input type="text" id="editar_extension" name="extension" placeholder="Ej: 1001">
                            <small>Número de extensión para el softphone WebRTC (opcional)</small>
                        </div>
                        <div class="form-group">
                            <label for="editar_sip_password">Contraseña SIP</label>
                            <input type="password" id="editar_sip_password" name="sip_password" placeholder="Contraseña para autenticación SIP">
                            <small>Contraseña para autenticación en el servidor SIP (opcional). Dejar vacío para mantener la actual.</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_estado">Estado *</label>
                        <select id="editar_estado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                        <small>Estado del usuario en el sistema</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editar-usuario')">Cancelar</button>
                        <button type="submit" id="btn-editar-usuario" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
                <div id="alert-container-editar"></div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
    <script src="assets/js/admin.js"></script>
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
        
        // Función para abrir modales
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
            }
        }
        
        // Función para cerrar modales
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                // Si es el modal de crear usuario, resetear y ocultar campos de asesor
                if (modalId === 'crear-usuario') {
                    const form = document.getElementById('form-crear-usuario');
                    if (form) {
                        form.reset();
                        toggleCamposAsesor();
                    }
                }
                // Si es el modal de editar usuario, resetear y ocultar campos de asesor
                if (modalId === 'editar-usuario') {
                    const form = document.getElementById('form-editar-usuario');
                    if (form) {
                        form.reset();
                        toggleCamposAsesorEditar();
                    }
                }
            }
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Función para mostrar/ocultar campos específicos de asesor
        function toggleCamposAsesor() {
            const rolSelect = document.getElementById('rol');
            const camposAsesor = document.getElementById('campos-asesor');
            const extensionInput = document.getElementById('extension');
            const sipPasswordInput = document.getElementById('sip_password');
            
            if (rolSelect && camposAsesor) {
                if (rolSelect.value === 'asesor') {
                    camposAsesor.style.display = 'block';
                    // Hacer los campos opcionales (no requeridos)
                    if (extensionInput) extensionInput.removeAttribute('required');
                    if (sipPasswordInput) sipPasswordInput.removeAttribute('required');
                } else {
                    camposAsesor.style.display = 'none';
                    // Limpiar los campos cuando se ocultan
                    if (extensionInput) {
                        extensionInput.value = '';
                        extensionInput.removeAttribute('required');
                    }
                    if (sipPasswordInput) {
                        sipPasswordInput.value = '';
                        sipPasswordInput.removeAttribute('required');
                    }
                }
            }
        }
        
        // Función para crear usuario (AJAX)
        function crearUsuario(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-crear-usuario');
            const btnCrear = document.getElementById('btn-crear-usuario');
            
            // Validar formulario
            if (!validateForm('form-crear-usuario')) {
                return;
            }
            
            // Validar contraseñas
            const contrasena = document.getElementById('contrasena').value;
            const confirmarContrasena = document.getElementById('confirmar_contrasena').value;
            
            if (contrasena !== confirmarContrasena) {
                mostrarAlerta('Las contraseñas no coinciden', 'error', 'crear-usuario');
                document.getElementById('confirmar_contrasena').classList.add('error');
                return;
            }
            
            if (contrasena.length < 6) {
                mostrarAlerta('La contraseña debe tener al menos 6 caracteres', 'error', 'crear-usuario');
                document.getElementById('contrasena').classList.add('error');
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnCrear.disabled = true;
            btnCrear.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container-crear');
            alertContainer.innerHTML = '';
            
            // Recopilar datos del formulario
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Enviar solicitud AJAX
            fetch('index.php?action=create_usuario', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlerta(result.message, 'success', 'crear-usuario');
                        form.reset();
                        // Ocultar campos de asesor al resetear
                        toggleCamposAsesor();
                        setTimeout(() => {
                            closeModal('crear-usuario');
                            location.reload();
                        }, 2000);
                    } else {
                        mostrarAlerta(result.message, 'error', 'crear-usuario');
                    }
                } catch (e) {
                    mostrarAlerta('Error al procesar la respuesta del servidor', 'error', 'crear-usuario');
                }
            })
            .catch(error => {
                mostrarAlerta('Error de conexión: ' + error.message, 'error', 'crear-usuario');
            })
            .finally(() => {
                // Restaurar botón
                btnCrear.disabled = false;
                btnCrear.innerHTML = '<i class="fas fa-user-plus"></i> Crear Usuario';
            });
        }
        
        // Función para mostrar alertas
        function mostrarAlerta(mensaje, tipo, modalId) {
            const alertContainer = document.getElementById('alert-container-crear');
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
        
        // Función para validar formulario
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                }
            });
            
            return isValid;
        }
        
        // Función para asignar personal (AJAX)
        function asignarPersonal(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-asignar-personal');
            const btnAsignar = document.getElementById('btn-asignar-personal');
            
            // Validar formulario
            if (!validateForm('form-asignar-personal')) {
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnAsignar.disabled = true;
            btnAsignar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asignando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container-asignar');
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
                        mostrarAlertaAsignar(result.message, 'success', 'asignar-personal');
                        form.reset();
                        setTimeout(() => {
                            closeModal('asignar-personal');
                            location.reload();
                        }, 2000);
                    } else {
                        mostrarAlertaAsignar(result.message, 'error', 'asignar-personal');
                    }
                } catch (e) {
                    mostrarAlertaAsignar('Error al procesar la respuesta del servidor', 'error', 'asignar-personal');
                }
            })
            .catch(error => {
                mostrarAlertaAsignar('Error de conexión: ' + error.message, 'error', 'asignar-personal');
            })
            .finally(() => {
                // Restaurar botón
                btnAsignar.disabled = false;
                btnAsignar.innerHTML = '<i class="fas fa-user-friends"></i> Asignar';
            });
        }
        
        // Función para mostrar alertas de asignación
        function mostrarAlertaAsignar(mensaje, tipo, modalId) {
            const alertContainer = document.getElementById('alert-container-asignar');
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
        
        // Función para cargar clientes (AJAX)
        function cargarClientes(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-cargar-clientes');
            const btnCargar = document.getElementById('btn-cargar-clientes');
            const fileInput = document.getElementById('archivo');
            
            // Validar formulario
            if (!validateForm('form-cargar-clientes')) {
                return;
            }
            
            // Validar archivo
            if (!fileInput.files[0]) {
                mostrarAlertaCargar('Por favor seleccione un archivo', 'error', 'cargar-clientes');
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnCargar.disabled = true;
            btnCargar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container-cargar');
            alertContainer.innerHTML = '';
            
            // Recopilar datos del formulario
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Enviar solicitud AJAX
            fetch('index.php?action=cargar_clientes', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlertaCargar(result.message, 'success', 'cargar-clientes');
                        form.reset();
                        setTimeout(() => {
                            closeModal('cargar-clientes');
                            location.reload();
                        }, 2000);
                    } else {
                        mostrarAlertaCargar(result.message, 'error', 'cargar-clientes');
                    }
                } catch (e) {
                    mostrarAlertaCargar('Error al procesar la respuesta del servidor', 'error', 'cargar-clientes');
                }
            })
            .catch(error => {
                mostrarAlertaCargar('Error de conexión: ' + error.message, 'error', 'cargar-clientes');
            })
            .finally(() => {
                // Restaurar botón
                btnCargar.disabled = false;
                btnCargar.innerHTML = '<i class="fas fa-upload"></i> Cargar Clientes';
            });
        }
        
        // Función para mostrar alertas de carga
        function mostrarAlertaCargar(mensaje, tipo, modalId) {
            const alertContainer = document.getElementById('alert-container-cargar');
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
        
        // Función para generar reportes (AJAX)
        function generarReporte(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-generar-reporte');
            const btnGenerar = document.getElementById('btn-generar-reporte');
            
            // Validar formulario
            if (!validateForm('form-generar-reporte')) {
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnGenerar.disabled = true;
            btnGenerar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container-reporte');
            alertContainer.innerHTML = '';
            
            // Recopilar datos del formulario
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Enviar solicitud AJAX
            fetch('index.php?action=generar_reporte', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlertaReporte(result.message, 'success', 'generar-reporte');
                        form.reset();
                        setTimeout(() => {
                            closeModal('generar-reporte');
                        }, 2000);
                    } else {
                        mostrarAlertaReporte(result.message, 'error', 'generar-reporte');
                    }
                } catch (e) {
                    mostrarAlertaReporte('Error al procesar la respuesta del servidor', 'error', 'generar-reporte');
                }
            })
            .catch(error => {
                mostrarAlertaReporte('Error de conexión: ' + error.message, 'error', 'generar-reporte');
            })
            .finally(() => {
                // Restaurar botón
                btnGenerar.disabled = false;
                btnGenerar.innerHTML = '<i class="fas fa-file-alt"></i> Generar Reporte';
            });
        }
        
        // Función para mostrar alertas de reportes
        function mostrarAlertaReporte(mensaje, tipo, modalId) {
            const alertContainer = document.getElementById('alert-container-reporte');
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

        // Funciones para la gestión de usuarios
        function refreshUsuarios() {
            location.reload();
        }

        // Funciones para gestión de asignaciones
        function refreshAsignaciones() {
            location.reload();
        }

        function liberarAsignacion(id) {
            if (confirm('¿Está seguro de que desea liberar este asesor? El asesor quedará disponible para ser asignado a otro coordinador.')) {
                // Mostrar loading
                const btn = event.target.closest('.btn-action');
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                // Enviar solicitud AJAX
                fetch('index.php?action=liberar_asignacion', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&ajax=1`
                })
                .then(response => response.text())
                .then(data => {
                    try {
                        const result = JSON.parse(data);
                        if (result.success) {
                            mostrarAlertaGeneral(result.message, 'success');
                            // Eliminar la fila de la tabla sin recargar la página
                            eliminarFilaAsignacion(id);
                        } else {
                            mostrarAlertaGeneral(result.message, 'error');
                        }
                    } catch (e) {
                        mostrarAlertaGeneral('Error al procesar la respuesta del servidor', 'error');
                    }
                })
                .catch(error => {
                    mostrarAlertaGeneral('Error de conexión: ' + error.message, 'error');
                })
                .finally(() => {
                    // Restaurar botón
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                });
            }
        }

        // Función para eliminar una fila de asignación de la tabla
        function eliminarFilaAsignacion(id) {
            const row = document.querySelector(`tr[data-asignacion-id="${id}"]`);
            if (row) {
                row.remove();
            }
        }

        function exportarAsignaciones() {
            alert('Función de exportar asignaciones en desarrollo');
        }

        function editarUsuario(cedula) {
            // Mostrar loading mientras se obtienen los datos
            const modal = document.getElementById('editar-usuario');
            if (modal) {
                const modalBody = modal.querySelector('.modal-body');
                if (modalBody) {
                    const loadingDiv = document.createElement('div');
                    loadingDiv.id = 'loading-editar-usuario';
                    loadingDiv.style.cssText = 'text-align: center; padding: 40px;';
                    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #007bff;"></i><p style="margin-top: 15px;">Cargando información del usuario...</p>';
                    modalBody.insertBefore(loadingDiv, modalBody.firstChild);
                }
            }
            
            // Obtener TODOS los datos del usuario desde el servidor
            fetch(`index.php?action=obtener_usuario&cedula=${cedula}`)
                .then(response => response.json())
                .then(data => {
                    // Remover loading
                    const loadingDiv = document.getElementById('loading-editar-usuario');
                    if (loadingDiv) {
                        loadingDiv.remove();
                    }
                    
                    if (data.success && data.usuario) {
                        const usuario = data.usuario;
                        
                        // Llenar TODOS los campos del formulario con los datos del servidor
                        document.getElementById('editar_cedula').value = usuario.cedula || cedula;
                        document.getElementById('editar_cedula_display').value = usuario.cedula || cedula;
                        document.getElementById('editar_nombre_completo').value = usuario.nombre_completo || '';
                        document.getElementById('editar_usuario').value = usuario.usuario || '';
                        document.getElementById('editar_rol').value = usuario.rol || '';
                        document.getElementById('editar_estado').value = usuario.estado || 'activo';
                        
                        // Llenar campos de extensión si existe (solo para asesores)
                        if (usuario.extension) {
                            document.getElementById('editar_extension').value = usuario.extension;
                        } else {
                            document.getElementById('editar_extension').value = '';
                        }
                        
                        // No cargar la contraseña SIP por seguridad (siempre dejar vacío)
                        document.getElementById('editar_sip_password').value = '';
                        
                        // Limpiar campos de contraseña (no mostrar contraseñas)
                        document.getElementById('editar_contrasena').value = '';
                        document.getElementById('editar_confirmar_contrasena').value = '';
                        
                        // Mostrar/ocultar campos según el rol
                        toggleCamposAsesorEditar();
                        
                        // Abrir el modal después de cargar los datos
                        openModal('editar-usuario');
                    } else {
                        mostrarAlertaGeneral(data.message || 'Error al obtener datos del usuario', 'error');
                    }
                })
                .catch(error => {
                    // Remover loading en caso de error
                    const loadingDiv = document.getElementById('loading-editar-usuario');
                    if (loadingDiv) {
                        loadingDiv.remove();
                    }
                    
                    console.error('Error al obtener datos del usuario:', error);
                    mostrarAlertaGeneral('Error al cargar los datos del usuario', 'error');
                });
        }
        
        // Función para mostrar/ocultar campos específicos de asesor en edición
        function toggleCamposAsesorEditar() {
            const rolSelect = document.getElementById('editar_rol');
            const camposAsesor = document.getElementById('campos-asesor-editar');
            const extensionInput = document.getElementById('editar_extension');
            const sipPasswordInput = document.getElementById('editar_sip_password');
            
            if (rolSelect && camposAsesor) {
                if (rolSelect.value === 'asesor') {
                    camposAsesor.style.display = 'block';
                    // Hacer los campos opcionales (no requeridos)
                    if (extensionInput) extensionInput.removeAttribute('required');
                    if (sipPasswordInput) sipPasswordInput.removeAttribute('required');
                } else {
                    camposAsesor.style.display = 'none';
                    // Limpiar los campos cuando se ocultan
                    if (extensionInput) {
                        extensionInput.value = '';
                        extensionInput.removeAttribute('required');
                    }
                    if (sipPasswordInput) {
                        sipPasswordInput.value = '';
                        sipPasswordInput.removeAttribute('required');
                    }
                }
            }
        }

        function cambiarEstadoUsuario(cedula, nuevoEstado) {
            const accion = nuevoEstado === 'activo' ? 'activar' : 'desactivar';
            const confirmacion = confirm(`¿Está seguro que desea ${accion} este usuario?`);
            
            if (!confirmacion) return;

            // Mostrar loading
            const btn = event.target.closest('.btn-action');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            // Enviar solicitud AJAX
            fetch('index.php?action=cambiar_estado_usuario', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `cedula=${cedula}&estado=${nuevoEstado}&ajax=1`
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlertaGeneral(result.message, 'success');
                        // Actualizar el estado en la tabla sin recargar la página
                        actualizarEstadoUsuario(cedula, nuevoEstado);
                    } else {
                        mostrarAlertaGeneral(result.message, 'error');
                    }
                } catch (e) {
                    mostrarAlertaGeneral('Error al procesar la respuesta del servidor', 'error');
                }
            })
            .catch(error => {
                mostrarAlertaGeneral('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaurar botón
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }

        function eliminarUsuario(cedula) {
            const confirmacion = confirm('¿Está seguro que desea eliminar este usuario? Esta acción no se puede deshacer.');
            
            if (!confirmacion) return;

            // Mostrar loading
            const btn = event.target.closest('.btn-action');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            // Enviar solicitud AJAX
            fetch('index.php?action=eliminar_usuario', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `cedula=${cedula}&ajax=1`
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlertaGeneral(result.message, 'success');
                        // Eliminar la fila de la tabla sin recargar la página
                        eliminarFilaUsuario(cedula);
                    } else {
                        mostrarAlertaGeneral(result.message, 'error');
                    }
                } catch (e) {
                    mostrarAlertaGeneral('Error al procesar la respuesta del servidor', 'error');
                }
            })
            .catch(error => {
                mostrarAlertaGeneral('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaurar botón
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }

        // Función para mostrar alertas generales
        function mostrarAlertaGeneral(mensaje, tipo) {
            // Crear contenedor de alertas si no existe
            let alertContainer = document.getElementById('alert-container-general');
            if (!alertContainer) {
                alertContainer = document.createElement('div');
                alertContainer.id = 'alert-container-general';
                alertContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
                document.body.appendChild(alertContainer);
            }

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo}`;
            alertDiv.style.cssText = 'margin-bottom: 10px; animation: alertSlideIn 0.3s ease-out;';
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

        // Función para manejar el envío del formulario de edición
        function editarUsuarioSubmit(event) {
            event.preventDefault();
            
            const form = document.getElementById('form-editar-usuario');
            const btnEditar = document.getElementById('btn-editar-usuario');
            
            // Validar formulario básico
            if (!validateForm('form-editar-usuario')) {
                return;
            }
            
            // Validar contraseñas si se proporcionaron
            const contrasena = document.getElementById('editar_contrasena').value;
            const confirmarContrasena = document.getElementById('editar_confirmar_contrasena').value;
            
            if (contrasena && contrasena.length < 6) {
                mostrarAlertaEditar('La contraseña debe tener al menos 6 caracteres', 'error');
                document.getElementById('editar_contrasena').classList.add('error');
                return;
            }
            
            if (contrasena && contrasena !== confirmarContrasena) {
                mostrarAlertaEditar('Las contraseñas no coinciden', 'error');
                document.getElementById('editar_confirmar_contrasena').classList.add('error');
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            btnEditar.disabled = true;
            btnEditar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            // Limpiar alertas anteriores
            const alertContainer = document.getElementById('alert-container-editar');
            alertContainer.innerHTML = '';
            
            // Recopilar datos del formulario
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Enviar solicitud AJAX
            fetch('index.php?action=editar_usuario', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        mostrarAlertaEditar(result.message, 'success');
                        // Obtener cédula del formulario
                        const cedulaUsuario = document.getElementById('editar_cedula').value;
                        // Actualizar la tabla sin recargar la página
                        actualizarFilaUsuario(cedulaUsuario, {
                            nombre_completo: document.getElementById('editar_nombre_completo').value,
                            usuario: document.getElementById('editar_usuario').value,
                            rol: document.getElementById('editar_rol').value,
                            estado: document.getElementById('editar_estado').value
                        });
                        setTimeout(() => {
                            closeModal('editar-usuario');
                        }, 1500);
                    } else {
                        mostrarAlertaEditar(result.message, 'error');
                    }
                } catch (e) {
                    mostrarAlertaEditar('Error al procesar la respuesta del servidor', 'error');
                }
            })
            .catch(error => {
                mostrarAlertaEditar('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaurar botón
                btnEditar.disabled = false;
                btnEditar.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            });
        }

        // Función para mostrar alertas de edición
        function mostrarAlertaEditar(mensaje, tipo) {
            const alertContainer = document.getElementById('alert-container-editar');
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

        // Función para actualizar una fila de usuario en la tabla
        function actualizarFilaUsuario(cedula, datos) {
            const row = document.querySelector(`tr[data-usuario-id="${cedula}"]`);
            if (!row) return;

            // Actualizar nombre completo
            const nombreElement = row.querySelector('.user-details strong');
            if (nombreElement) {
                nombreElement.textContent = datos.nombre_completo;
            }

            // Actualizar usuario
            const usuarioElement = row.querySelector('.username');
            if (usuarioElement) {
                usuarioElement.textContent = datos.usuario;
            }

            // Actualizar rol
            const rolElement = row.querySelector('.rol-badge');
            if (rolElement) {
                rolElement.textContent = datos.rol.charAt(0).toUpperCase() + datos.rol.slice(1);
                rolElement.className = `rol-badge rol-${datos.rol}`;
            }

            // Actualizar estado
            const estadoElement = row.querySelector('.estado-badge');
            if (estadoElement) {
                estadoElement.textContent = datos.estado.charAt(0).toUpperCase() + datos.estado.slice(1);
                estadoElement.className = `estado-badge estado-${datos.estado}`;
            }

            // Actualizar botones de acción según el nuevo estado
            actualizarBotonesAccion(row, datos.estado);
        }

        // Función para actualizar el estado de un usuario en la tabla
        function actualizarEstadoUsuario(cedula, nuevoEstado) {
            const row = document.querySelector(`tr[data-usuario-id="${cedula}"]`);
            if (!row) return;

            // Actualizar estado
            const estadoElement = row.querySelector('.estado-badge');
            if (estadoElement) {
                estadoElement.textContent = nuevoEstado.charAt(0).toUpperCase() + nuevoEstado.slice(1);
                estadoElement.className = `estado-badge estado-${nuevoEstado}`;
            }

            // Actualizar botones de acción
            actualizarBotonesAccion(row, nuevoEstado);
        }

        // Función para actualizar los botones de acción según el estado
        function actualizarBotonesAccion(row, estado) {
            const actionButtons = row.querySelector('.action-buttons');
            if (!actionButtons) return;

            // Encontrar el botón de activar/desactivar
            const enableBtn = actionButtons.querySelector('.btn-enable');
            const disableBtn = actionButtons.querySelector('.btn-disable');

            if (estado === 'activo') {
                // Si está activo, mostrar botón de desactivar
                if (enableBtn) {
                    enableBtn.className = 'btn-action btn-disable';
                    enableBtn.innerHTML = '<i class="fas fa-user-times"></i>';
                    enableBtn.title = 'Desactivar';
                    enableBtn.onclick = function() { cambiarEstadoUsuario(row.dataset.usuarioId, 'inactivo'); };
                }
            } else {
                // Si está inactivo, mostrar botón de activar
                if (disableBtn) {
                    disableBtn.className = 'btn-action btn-enable';
                    disableBtn.innerHTML = '<i class="fas fa-user-check"></i>';
                    disableBtn.title = 'Activar';
                    disableBtn.onclick = function() { cambiarEstadoUsuario(row.dataset.usuarioId, 'activo'); };
                }
            }
        }

        // Función para eliminar una fila de usuario de la tabla
        function eliminarFilaUsuario(cedula) {
            const row = document.querySelector(`tr[data-usuario-id="${cedula}"]`);
            if (row) {
                row.remove();
            }
        }
        
        
        // Sobrescribir openModal si es necesario
        const openModalOriginal = window.openModal;
        window.openModal = function(modalId) {
            if (openModalOriginal) {
                openModalOriginal(modalId);
            } else {
                const modal = document.getElementById(modalId);
                if (modal) modal.style.display = 'block';
            }
        };
    </script>

    <style>
        /* Estilos para el modal de crear usuario */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease-out;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #007bff);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
            max-height: calc(90vh - 120px);
        }

        /* Estilos para el scroll del modal */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Para Firefox */
        .modal-body {
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            font-family: inherit;
            resize: vertical;
        }

        .form-group textarea {
            min-height: 80px;
            line-height: 1.5;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #dc3545;
            background-color: #fff5f5;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* Estilos para campos específicos de asesor */
        #campos-asesor,
        #campos-asesor-editar {
            background: #f0f7ff;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #campos-asesor::before,
        #campos-asesor-editar::before {
            content: "📞 Configuración Softphone WebRTC";
            display: block;
            font-weight: 600;
            color: #007bff;
            margin-bottom: 15px;
            font-size: 0.95rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #cce5ff;
        }
        
        #campos-asesor .form-group,
        #campos-asesor-editar .form-group {
            margin-bottom: 15px;
        }
        
        #campos-asesor .form-group:last-child,
        #campos-asesor-editar .form-group:last-child {
            margin-bottom: 0;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #007bff);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            animation: alertSlideIn 0.3s ease-out;
        }

        @keyframes alertSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
                max-height: 95vh;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-body {
                padding: 20px;
                max-height: calc(95vh - 100px);
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Para pantallas muy pequeñas */
        @media (max-width: 480px) {
            .modal-content {
                margin: 2% auto;
                width: 98%;
                max-height: 98vh;
            }
            
            .modal-body {
                max-height: calc(98vh - 80px);
                padding: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group input,
            .form-group select {
                padding: 10px 12px;
                font-size: 0.95rem;
            }
        }

        /* Estilos para la tabla de usuarios */
        .usuarios-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .usuarios-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .table-header h5 {
            margin: 0;
            color: #495057;
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.875rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .usuarios-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .usuarios-table thead {
            background: #f8f9fa;
        }

        .usuarios-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .usuarios-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .usuarios-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .usuarios-table tbody tr:last-child td {
            border-bottom: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #007bff);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-details strong {
            color: #212529;
            font-size: 0.95rem;
        }

        .user-details small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .username {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #495057;
        }

        .rol-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rol-badge.rol-administrador {
            background: #dc3545;
            color: white;
        }

        .rol-badge.rol-coordinador {
            background: #fd7e14;
            color: white;
        }

        .rol-badge.rol-asesor {
            background: #20c997;
            color: white;
        }

        .estado-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estado-badge.estado-activo {
            background: #d4edda;
            color: #155724;
        }

        .estado-badge.estado-inactivo {
            background: #f8d7da;
            color: #721c24;
        }

        .estado-badge i {
            font-size: 0.7rem;
        }

        .fecha-creacion {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-edit:hover {
            background: #138496;
        }

        .btn-enable {
            background: #28a745;
            color: white;
        }

        .btn-enable:hover {
            background: #218838;
        }

        .btn-disable {
            background: #ffc107;
            color: #212529;
        }

        .btn-disable:hover {
            background: #e0a800;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-liberar {
            background: #ff6b35;
            color: white;
        }

        .btn-liberar:hover {
            background: #e55a2b;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .no-data p {
            margin: 10px 0 20px;
            font-size: 1.1rem;
        }

        /* Responsive para la tabla */
        @media (max-width: 768px) {
            .usuarios-table-container {
                margin: 10px -10px;
                border-radius: 0;
            }

            .table-header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .usuarios-table th,
            .usuarios-table td {
                padding: 10px 15px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .action-buttons {
                flex-wrap: wrap;
                gap: 5px;
            }

            .btn-action {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
        }

        /* Estilos para la tabla de asignaciones */
        .asignaciones-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .asignaciones-header .table-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .asignaciones-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 20px;
        }

        .asignaciones-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .asignaciones-table thead {
            background: #f8f9fa;
        }

        .asignaciones-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .asignaciones-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .asignaciones-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .asignaciones-table tbody tr:last-child td {
            border-bottom: none;
        }

        .fecha-asignacion {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .creado-por {
            color: #495057;
            font-size: 0.9rem;
        }

        .stats-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: row;
            gap: 20px;
            justify-content: space-between;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
            text-align: center;
        }

        .stat-item i {
            font-size: 1.5em;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: 700;
            color: #495057;
        }

        .stat-label {
            font-size: 0.85em;
            color: #6c757d;
        }

        .stat-item i {
            color: #007bff;
            font-size: 1.2em;
        }

        .stat-number {
            font-size: 1.5em;
            font-weight: 700;
            color: #495057;
        }

        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
        }

        .quick-actions {
            display: flex;
            flex-direction: row;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
            align-items: center;
        }

        .action-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85em;
            color: #495057;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: fit-content;
            flex-shrink: 0;
        }

        .action-btn:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }

        .action-btn i {
            color: #007bff;
        }

        /* Responsive para pantallas medianas */
        @media (max-width: 1024px) {
            .quick-actions {
                gap: 12px;
            }

            .action-btn {
                padding: 8px 10px;
                font-size: 0.8em;
            }
        }

        /* Responsive para la tabla de asignaciones */
        @media (max-width: 768px) {
            .asignaciones-table-container {
                margin: 10px -10px;
                border-radius: 0;
            }

            .asignaciones-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .asignaciones-table th,
            .asignaciones-table td {
                padding: 10px 15px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .action-buttons {
                flex-wrap: wrap;
                gap: 5px;
            }

            .btn-action {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .stats-summary {
                flex-direction: column;
                gap: 15px;
            }

            .stat-item {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }

            .quick-actions {
                flex-direction: column;
                gap: 8px;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
                padding: 12px 15px;
                font-size: 0.9em;
            }
        }
    </style>
    
    <!-- Scripts -->
    <script src="assets/js/hybrid-updater.js"></script>
</body>
</html>
