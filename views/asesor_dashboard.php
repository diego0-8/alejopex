<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Asesor - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/coordinador-dashboard.css">
    <style>
        /* Estilos para la barra de búsqueda desplegable */
        .search-bar {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .search-input-group input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        
        .search-input-group input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .search-btn, .clear-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-btn {
            background: #007bff;
            color: white;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        .clear-btn {
            background: #6c757d;
            color: white;
        }
        
        .clear-btn:hover {
            background: #545b62;
        }
        
        .search-results-quick {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
        }
        
        .search-result-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .search-result-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .search-result-content {
            flex: 1;
            margin-bottom: 12px;
        }
        
        .search-result-actions {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        
        .btn-gestionar-search {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }
        
        .btn-gestionar-search:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        
        .btn-gestionar-search i {
            font-size: 14px;
        }
        
        .search-result-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .search-result-details {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        
        .no-search-results {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        /* Estilos para la barra de búsqueda de clientes */
        .clientes-search-bar {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .clientes-search-bar .search-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .clientes-search-bar .search-input-group input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        
        .clientes-search-bar .search-input-group input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .clientes-search-bar .search-btn, 
        .clientes-search-bar .clear-btn {
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .clientes-search-bar .search-btn {
            background: #28a745;
            color: white;
        }
        
        .clientes-search-bar .search-btn:hover {
            background: #218838;
        }
        
        .clientes-search-bar .clear-btn {
            background: #6c757d;
            color: white;
        }
        
        .clientes-search-bar .clear-btn:hover {
            background: #545b62;
        }
        
        /* Estilos para los botones de acción de la tabla */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }
        
        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-manage {
            background: #007bff;
            color: white;
        }
        
        .btn-manage:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,123,255,0.3);
        }
        
        .btn-history {
            background: #6c757d;
            color: white;
        }
        
        .btn-history:hover {
            background: #545b62;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(108,117,125,0.3);
        }
        
        /* Estilos para la información del cliente */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-details strong {
            display: block;
            color: #333;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .user-details small {
            color: #666;
            font-size: 11px;
        }
        
        .phone-number {
            color: #007bff;
            font-weight: 500;
            font-size: 14px;
        }
    </style>
</head>
<body data-user-id="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">

    <?php 
    // Incluir navbar compartido
    $action = 'asesor_dashboard';
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
                <h3>ESTADÍSTICAS DEL ASESOR</h3>
                <p class="call-info">Sistema <?php echo APP_NAME; ?></p>
                <p class="call-info">Gestión de Clientes</p>
                <small>Resumen de Actividad</small>
                <div class="media-controls">
                    <button class="media-button" onclick="toggleBusqueda()">
                        <i class="fas fa-search"></i> Buscar Cliente
                    </button>
                </div>
                
                <!-- Barra de búsqueda desplegable -->
                <div class="search-bar" id="search-bar" style="display: none; margin-top: 15px;">
                    <div class="search-input-group">
                        <input type="text" id="search-input" placeholder="Buscar por CC o Número de Obligación..." 
                               onkeyup="buscarClienteRapido(this.value)">
                        <button class="search-btn" onclick="ejecutarBusqueda()">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="clear-btn" onclick="limpiarBusqueda()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <!-- Resultados de búsqueda rápida -->
                    <div class="search-results-quick" id="search-results-quick" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Bases de Clientes Disponibles -->
                <div class="bases-acceso" style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px; color: #007bff; font-size: 14px;">
                        <i class="fas fa-database"></i> Bases de Clientes Disponibles
                    </h4>
                    <div id="bases-lista" style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="base-item" style="padding: 10px; background: #f8f9fa; border-left: 3px solid #28a745; border-radius: 4px;">
                            <span style="font-weight: 600; color: #333;">
                                <i class="fas fa-check-circle" style="color: #28a745;"></i> Cargando bases...
                            </span>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="call-main-view">
                <div class="client-info">
                    <i class="fas fa-user-tie"></i>
                    <div>
                        <span class="client-name">Panel de Asesor</span>
                        <span class="client-company"><?php echo APP_NAME; ?> - Gestión de Clientes</span>
                    </div>
                </div>

                <div class="main-tabs">
                    <span class="active" onclick="cambiarTab('estadisticas')">ESTADÍSTICAS</span>
                    <span onclick="cambiarTab('clientes')">CLIENTES</span>
                </div>
                
                <div class="content-sections">
                    <!-- PESTAÑA 1: ESTADÍSTICAS -->
                    <div class="tab-content active" id="tab-estadisticas">
                        <div class="left-content">
                            <!-- Widgets de Estadísticas -->
                            <h4 class="section-title">Resumen Personal</h4>
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Clientes Asignados</label>
                                    <input type="text" id="stat-clientes-asignados" value="<?php echo $estadisticas['clientes_asignados'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Gestionados</label>
                                    <input type="text" id="stat-clientes-gestionados" value="<?php echo $estadisticas['clientes_gestionados'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Clientes Pendientes</label>
                                    <input type="text" id="stat-clientes-pendientes" value="<?php echo $estadisticas['clientes_pendientes'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Tareas Completadas</label>
                                    <input type="text" id="stat-tareas-completadas" value="<?php echo $estadisticas['tareas_completadas'] ?? 0; ?>" readonly>
                                </div>
                            </div>
                            
                            <!-- Segunda fila de estadísticas -->
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Llamadas Realizadas</label>
                                    <input type="text" id="stat-llamadas-realizadas" value="<?php echo $estadisticas['llamadas_realizadas'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Contactos Exitosos</label>
                                    <input type="text" id="stat-contactos-exitosos" value="<?php echo $estadisticas['contactos_exitosos'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Promesas de Pago</label>
                                    <input type="text" id="stat-promesas-pago" value="<?php echo $estadisticas['promesas_pago'] ?? 0; ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Eficiencia (%)</label>
                                    <input type="text" id="stat-eficiencia" value="<?php 
                                        $asignados = $estadisticas['clientes_asignados'] ?? 0;
                                        $gestionados = $estadisticas['clientes_gestionados'] ?? 0;
                                        echo ($asignados > 0) ? round(($gestionados / $asignados) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                            </div>

                            <!-- Tercera fila de estadísticas -->
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Valor Recuperado</label>
                                    <input type="text" value="$<?php echo number_format($estadisticas['valor_recuperado'] ?? 0, 0, ',', '.'); ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Meta Mensual</label>
                                    <input type="text" value="$<?php echo number_format($estadisticas['meta_mensual'] ?? 0, 0, ',', '.'); ?>" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Cumplimiento (%)</label>
                                    <input type="text" value="<?php 
                                        $meta = $estadisticas['meta_mensual'] ?? 0;
                                        $recuperado = $estadisticas['valor_recuperado'] ?? 0;
                                        echo ($meta > 0) ? round(($recuperado / $meta) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Promedio por Cliente</label>
                                    <input type="text" value="$<?php 
                                        $gestionados = $estadisticas['clientes_gestionados'] ?? 0;
                                        $recuperado = $estadisticas['valor_recuperado'] ?? 0;
                                        echo ($gestionados > 0) ? number_format($recuperado / $gestionados, 0, ',', '.') : 0;
                                    ?>" readonly>
                                </div>
                            </div>

                            <!-- Porcentajes de Rendimiento -->
                            <h4>Rendimiento Personal</h4>
                            <div class="form-section">
                                <div class="input-group">
                                    <label>Contactabilidad (%)</label>
                                    <input type="text" value="<?php 
                                        $llamadas = $estadisticas['llamadas_realizadas'] ?? 0;
                                        $contactos = $estadisticas['contactos_exitosos'] ?? 0;
                                        echo ($llamadas > 0) ? round(($contactos / $llamadas) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Efectividad (%)</label>
                                    <input type="text" value="<?php 
                                        $contactos = $estadisticas['contactos_exitosos'] ?? 0;
                                        $promesas = $estadisticas['promesas_pago'] ?? 0;
                                        echo ($contactos > 0) ? round(($promesas / $contactos) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Productividad (%)</label>
                                    <input type="text" value="<?php 
                                        $asignados = $estadisticas['clientes_asignados'] ?? 0;
                                        $gestionados = $estadisticas['clientes_gestionados'] ?? 0;
                                        echo ($asignados > 0) ? round(($gestionados / $asignados) * 100, 1) : 0;
                                    ?>%" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Puntualidad (%)</label>
                                    <input type="text" value="<?php echo $estadisticas['puntualidad'] ?? 0; ?>%" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: CLIENTES -->
                    <div class="tab-content" id="tab-clientes">
                        <div class="left-content">
                            <!-- Filtro -->
                            <div class="filtros-clientes" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                                <h4 style="margin: 0 0 15px 0; color: #007bff; font-size: 16px;">
                                    <i class="fas fa-filter"></i> Filtro
                                </h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                                    <div class="input-group">
                                        <label style="font-size: 13px; color: #666; margin-bottom: 5px;">Estado de Gestión</label>
                                        <select id="filter-gestionado" onchange="manejarCambioGestion(this.value)" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                                            <option value="">Todos</option>
                                            <option value="gestionado">Ya gestionado</option>
                                            <option value="no_gestionado">No gestionado</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label style="font-size: 13px; color: #666; margin-bottom: 5px;">Estado de Contacto</label>
                                        <select id="filter-contactado" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                                            <option value="">Todos</option>
                                            <option value="contactado">Contactado</option>
                                            <option value="no_contactado">No contactado</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label style="font-size: 13px; color: #666; margin-bottom: 5px;">Fecha de Gestión</label>
                                        <input type="date" id="filter-fecha" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                                    </div>
                                    <div class="input-group" style="display: flex; gap: 10px;">
                                        <button onclick="aplicarFiltrosClientes()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                            <i class="fas fa-search"></i> Aplicar
                                        </button>
                                        <button onclick="limpiarFiltrosClientes()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                            <i class="fas fa-times"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="clientes-header">
                            <h4 class="section-title">Lista de Clientes</h4>
                                
                                <!-- Barra de búsqueda específica para la lista de clientes -->
                                <div class="clientes-search-bar">
                                    <div class="search-input-group">
                                        <input type="text" id="clientes-search-input" placeholder="Buscar por CC o Número de Obligación..." 
                                               onkeyup="if(event.key==='Enter') ejecutarBusquedaClientes()">
                                        <button class="search-btn" onclick="ejecutarBusquedaClientes()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button class="clear-btn" onclick="limpiarBusquedaClientes()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contenedor principal con dos columnas -->
                            <div class="clientes-main-container" style="display: flex; gap: 20px;">
                                
                                <!-- Columna izquierda: Lista de clientes -->
                                <div class="clientes-table-container" style="flex: 2;">
                                <div class="table-responsive">
                                    <table class="clientes-table">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Celular</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($clientes) && !empty($clientes) && is_array($clientes)): ?>
                                                <?php foreach ($clientes as $comercio): ?>
                                                    <tr data-comercio-id="<?php echo $comercio['ID_COMERCIO'] ?? $comercio['id'] ?? ''; ?>">
                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($comercio['NOMBRE_COMERCIO'] ?? $comercio['nombre_comercio'] ?? '-'); ?></strong>
                                                                    <small>NIT CXC: <?php echo htmlspecialchars($comercio['NIT_CXC'] ?? $comercio['nit_cxc'] ?? '-'); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="phone-number"><?php echo htmlspecialchars($comercio['CEL'] ?? $comercio['cel'] ?? '-'); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn-action btn-manage" onclick="gestionarCliente('<?php echo $comercio['ID_COMERCIO'] ?? $comercio['id'] ?? ''; ?>')" title="Gestionar">
                                                                    <i class="fas fa-edit"></i> Gestionar
                                                                </button>
                                                                <button class="btn-action btn-history" onclick="verHistorialCliente('<?php echo $comercio['ID_COMERCIO'] ?? $comercio['id'] ?? ''; ?>')" title="Historial">
                                                                    <i class="fas fa-history"></i> Historial
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="no-data">
                                                        <i class="fas fa-users"></i>
                                                        <p>No hay clientes asignados</p>
                                                        <small>Contacte al coordinador para obtener tareas</small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                                
                                <!-- Columna derecha: Resumen de tareas -->
                                <div class="resumen-tareas-container" style="flex: 1;">
                                    <h4 class="section-title">Resumen de Tareas</h4>
                                    <div class="table-responsive">
                                        <table class="clientes-table">
                                            <thead>
                                                <tr>
                                                    <th>Base</th>
                                                    <th>Asignados</th>
                                                    <th>Gestionados</th>
                                                    <th>Pendientes</th>
                                                    <th>Progreso</th>
                                                </tr>
                                            </thead>
                                            <tbody id="resumen-tareas-body">
                                                <tr>
                                                    <td colspan="5" class="no-data">
                                                        <i class="fas fa-spinner fa-spin"></i>
                                                        <p>Cargando resumen...</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </section>
    </div>

    <!-- Modal de Tiempo de Sesión -->
    <div id="modal-tiempo-sesion" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; min-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #007bff;">
                    <i class="fas fa-clock"></i> Tiempo de Sesión
                </h3>
                <button onclick="toggleTiempoModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <span style="display: block; margin-bottom: 5px; color: #666; font-size: 13px;">Hora Actual</span>
                    <span id="reloj-activo" style="font-size: 20px; font-weight: 700; color: #007bff;">--:-- --</span>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <span style="display: block; margin-bottom: 5px; color: #666; font-size: 13px;">Tiempo de Sesión</span>
                    <span id="tiempo-sesion" style="font-size: 20px; font-weight: 700; color: #28a745;">00:00:00</span>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button id="btn-pausa" onclick="iniciarPausaBreak()" style="padding: 12px; background: #ffc107; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-coffee"></i> Break
                    </button>
                    <button id="btn-almuerzo" onclick="iniciarPausaAlmuerzo()" style="padding: 12px; background: #fd7e14; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-utensils"></i> Almuerzo
                    </button>
                    <button id="btn-bano" onclick="iniciarPausaBano()" style="padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-toilet"></i> Baño
                    </button>
                    <button id="btn-mantenimiento" onclick="iniciarPausaMantenimiento()" style="padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-tools"></i> Mantenimiento 
                    </button>
                    <button id="btn-pausa-activa" onclick="iniciarPausaActiva()" style="padding: 12px; background: #20c997; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-running"></i> Pausa Activa
                    </button>
                    <button id="btn-actividad-extra" onclick="iniciarActividadExtra()" style="padding: 12px; background: #6610f2; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-stopwatch"></i> Actividad Extra
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Pausa (cuando está en pausa) -->
    <div id="modal-pausa" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px;">
            <i class="fas fa-clock" style="font-size: 48px; color: #ffc107; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">En Pausa</h3>
            <p style="margin: 0 0 20px 0; color: #666;" id="tipo-pausa-texto">Break de 30 minutos</p>
            <div style="font-size: 32px; font-weight: 700; color: #007bff; margin-bottom: 20px;">
                <span class="tiempo-pausa">30:00</span>
            </div>
            <button onclick="mostrarModalVerificacion()" class="btn btn-primary" style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-play"></i> Continuar Trabajo
            </button>
        </div>
    </div>

    <!-- Modal de Verificación de Contraseña -->
    <div id="modal-verificacion-contrasena" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10002; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <i class="fas fa-lock" style="font-size: 48px; color: #007bff; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">Verificación de Contraseña</h3>
            <p style="margin: 0 0 20px 0; color: #666;">Ingrese su contraseña para reanudar la sesión</p>
            
            <div style="margin-bottom: 20px; text-align: left;">
                <label for="input-contrasena-verificacion" style="display: block; margin-bottom: 8px; color: #666; font-size: 14px;">Contraseña:</label>
                <input type="password" id="input-contrasena-verificacion" placeholder="Ingrese su contraseña" 
                       style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;"
                       onkeypress="if(event.key === 'Enter') verificarContrasena();">
            </div>
            
            <div id="mensaje-error-verificacion" style="display: none; background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;">
                Contraseña incorrecta. Intentos restantes: <span id="intentos-restantes">3</span>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="verificarContrasena()" class="btn btn-primary" style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-check"></i> Verificar
                </button>
                <button onclick="cerrarModalVerificacion()" class="btn btn-secondary" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Actividad Extra (cronómetro) -->
    <div id="modal-actividad-extra" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px;">
            <i class="fas fa-stopwatch" style="font-size: 48px; color: #6610f2; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">Actividad Extra</h3>
            <p style="margin: 0 0 20px 0; color: #666;">En progreso...</p>
            <div style="font-size: 32px; font-weight: 700; color: #007bff; margin-bottom: 20px;">
                <span id="tiempo-actividad-extra">00:00:00</span>
            </div>
            <button onclick="finalizarActividadExtra()" class="btn btn-primary" style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-stop"></i> Finalizar Actividad
            </button>
        </div>
    </div>

    <script src="assets/js/navbar-busqueda-cliente.js"></script>
    <script src="assets/js/asesor-dashboard.js"></script>
    <script src="assets/js/asesor-clientes.js"></script>
    <script src="assets/js/asesor-tiempos.js"></script>
    <script src="assets/js/hybrid-updater.js"></script>
    
    <script>
        // Función para abrir/cerrar modal de tiempo
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
        
        // Funciones globales para los botones de pausa
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
        
        // Variables para la verificación de contraseña
        let intentosVerificacion = 3;
        
        function mostrarModalVerificacion() {
            const modal = document.getElementById('modal-verificacion-contrasena');
            if (modal) {
                modal.style.display = 'flex';
                document.getElementById('input-contrasena-verificacion').value = '';
                document.getElementById('mensaje-error-verificacion').style.display = 'none';
                intentosVerificacion = 3;
                document.getElementById('intentos-restantes').textContent = '3';
            }
        }
        
        function cerrarModalVerificacion() {
            const modal = document.getElementById('modal-verificacion-contrasena');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        async function verificarContrasena() {
            const contrasena = document.getElementById('input-contrasena-verificacion').value;
            const mensajeError = document.getElementById('mensaje-error-verificacion');
            const intentosRestantes = document.getElementById('intentos-restantes');
            
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
                        mensajeError.style.display = 'block';
                        intentosRestantes.textContent = intentosVerificacion;
                        document.getElementById('input-contrasena-verificacion').value = '';
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
        
        function finalizarPausa() {
            // Esta función ahora se llama después de la verificación
            if (window.asesorTiempos) {
                window.asesorTiempos.finalizarPausa();
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
        
        // Función para manejar el cambio en el filtro de gestión
        function manejarCambioGestion(valor) {
            const filterContactado = document.getElementById('filter-contactado');
            const labelContactado = filterContactado.previousElementSibling;
            
            if (valor === 'no_gestionado') {
                // Si no gestionado está seleccionado, deshabilitar y limpiar el filtro de contacto
                filterContactado.disabled = true;
                filterContactado.value = '';
                filterContactado.style.opacity = '0.5';
                labelContactado.style.opacity = '0.5';
            } else {
                // Si gestionado está seleccionado, habilitar el filtro de contacto
                filterContactado.disabled = false;
                filterContactado.style.opacity = '1';
                labelContactado.style.opacity = '1';
            }
        }
        
        // Funciones para los filtros de clientes (solo comercios asignados por tareas)
        async function aplicarFiltrosClientes() {
            const gestionado = document.getElementById('filter-gestionado').value;
            const contactado = document.getElementById('filter-contactado').value;
            const fecha = document.getElementById('filter-fecha').value;
            
            console.log('Aplicando filtros:', { gestionado, contactado, fecha });
            
            try {
                // Construir URL con parámetros de filtro
                const params = new URLSearchParams();
                if (gestionado) params.append('gestionado', gestionado);
                if (contactado) params.append('contactado', contactado);
                if (fecha) params.append('fecha', fecha);
                
                const response = await fetch(`index.php?action=obtener_clientes_filtrados&${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Error al obtener clientes filtrados');
                }
                
                const clientes = await response.json();
                
                // Actualizar la tabla de clientes
                actualizarTablaClientes(clientes);
                
                console.log('Clientes filtrados obtenidos:', clientes);
                
            } catch (error) {
                console.error('Error al aplicar filtros:', error);
                alert('Error al aplicar filtros. Por favor intente nuevamente.');
            }
        }
        
        function limpiarFiltrosClientes() {
            document.getElementById('filter-gestionado').value = '';
            document.getElementById('filter-contactado').value = '';
            document.getElementById('filter-fecha').value = '';
            
            // Habilitar el filtro de contacto si estaba deshabilitado
            const filterContactado = document.getElementById('filter-contactado');
            const labelContactado = filterContactado.previousElementSibling;
            filterContactado.disabled = false;
            filterContactado.style.opacity = '1';
            labelContactado.style.opacity = '1';
            
            console.log('Filtros limpiados');
            
            // Recargar todos los clientes asignados
            aplicarFiltrosClientes();
        }
        
        function actualizarTablaClientes(clientes) {
            const tbody = document.querySelector('#tab-clientes .clientes-table tbody');
            if (!tbody) return;
            
            // Limpiar contenido anterior
            tbody.innerHTML = '';
            
            const lista = Array.isArray(clientes) ? clientes : [];
            if (lista.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="no-data">
                            <i class="fas fa-users"></i>
                            <p>No se encontraron clientes con los filtros aplicados</p>
                            <small>Intente ajustar los criterios de búsqueda</small>
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Generar filas para cada cliente (solo asignados por tareas)
            lista.forEach(comercio => {
                const id = comercio.ID_COMERCIO || comercio.id;
                const nombre = comercio.NOMBRE_COMERCIO || comercio.nombre_comercio || '-';
                const nit = comercio.NIT_CXC || comercio.nit_cxc || '-';
                const cel = comercio.CEL || comercio.cel || '-';
                
                const row = document.createElement('tr');
                row.setAttribute('data-comercio-id', id);
                row.innerHTML = `
                    <td>
                        <div class="user-info">
                            <div class="user-details">
                                <strong>${nombre}</strong>
                                <small>NIT CXC: ${nit}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="phone-number">${cel}</span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-manage" onclick="gestionarCliente('${id}')" title="Gestionar">
                                <i class="fas fa-edit"></i> Gestionar
                            </button>
                            <button class="btn-action btn-history" onclick="verHistorialCliente('${id}')" title="Historial">
                                <i class="fas fa-history"></i> Historial
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Buscar por CC o Número de Obligación dentro de los clientes ASIGNADOS
        async function ejecutarBusquedaClientes() {
            const termino = document.getElementById('clientes-search-input').value.trim();
            const tbody = document.querySelector('#tab-clientes .clientes-table tbody');
            if (!tbody) return;
            if (!termino) { aplicarFiltrosClientes(); return; }
            try {
                const resp = await fetch('index.php?action=buscar_cliente_asesor', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ criterio: 'auto', termino })
                });
                const data = await resp.json();
                const resultados = Array.isArray(data.clientes) ? data.clientes : [];
                // Limitar a IDs que estén actualmente asignados (presentes en el DOM)
                const asignadosIds = Array.from(document.querySelectorAll('#tab-clientes tr[data-comercio-id]')).map(tr => String(tr.getAttribute('data-comercio-id')));
                const filtrados = resultados.filter(c => asignadosIds.includes(String(c.ID_COMERCIO || c.id)));
                
                actualizarTablaClientesAsignados(filtrados);
            } catch (e) {
                console.error('Error en búsqueda de clientes:', e);
            }
        }

        function limpiarBusquedaClientes() {
            const input = document.getElementById('clientes-search-input');
            input.value = '';
            aplicarFiltrosClientes();
        }

        function actualizarTablaClientesAsignados(lista) {
            const tbody = document.querySelector('#tab-clientes .clientes-table tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!lista || lista.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="no-data">
                            <i class="fas fa-search"></i>
                            <p>No se encontraron clientes asignados con el criterio</p>
                        </td>
                    </tr>
                `;
                return;
            }
            lista.forEach(comercio => {
                const id = comercio.ID_COMERCIO || comercio.id;
                const nombre = comercio.NOMBRE_COMERCIO || comercio.nombre_comercio || '-';
                const nit = comercio.NIT_CXC || comercio.nit_cxc || '-';
                const cel = comercio.CEL || comercio.cel || '-';
                const row = document.createElement('tr');
                row.setAttribute('data-comercio-id', id);
                row.innerHTML = `
                    <td>
                        <div class="user-info">
                            <div class="user-details">
                                <strong>${nombre}</strong>
                                <small>NIT CXC: ${nit}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="phone-number">${cel}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-manage" onclick="gestionarCliente('${id}')" title="Gestionar">
                                <i class="fas fa-edit"></i> Gestionar
                            </button>
                            <button class="btn-action btn-history" onclick="verHistorialCliente('${id}')" title="Historial">
                                <i class="fas fa-history"></i> Historial
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Función para actualizar estadísticas automáticamente
        async function actualizarEstadisticas() {
            try {
                const response = await fetch('index.php?action=obtener_estadisticas_asesor', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Error al obtener estadísticas');
                }
                
                const estadisticas = await response.json();
                
                // Actualizar campos de estadísticas
                document.getElementById('stat-clientes-asignados').value = estadisticas.clientes_asignados || 0;
                document.getElementById('stat-clientes-gestionados').value = estadisticas.clientes_gestionados || 0;
                document.getElementById('stat-clientes-pendientes').value = estadisticas.clientes_pendientes || 0;
                document.getElementById('stat-tareas-completadas').value = estadisticas.tareas_completadas || 0;
                document.getElementById('stat-llamadas-realizadas').value = estadisticas.llamadas_realizadas || 0;
                document.getElementById('stat-contactos-exitosos').value = estadisticas.contactos_exitosos || 0;
                document.getElementById('stat-promesas-pago').value = estadisticas.promesas_pago || 0;
                
                // Calcular eficiencia
                const asignados = estadisticas.clientes_asignados || 0;
                const gestionados = estadisticas.clientes_gestionados || 0;
                const eficiencia = asignados > 0 ? Math.round((gestionados / asignados) * 100 * 10) / 10 : 0;
                document.getElementById('stat-eficiencia').value = eficiencia + '%';
                
                console.log('Estadísticas actualizadas:', estadisticas);
                
            } catch (error) {
                console.error('Error al actualizar estadísticas:', error);
            }
        }
        
        // Función para actualizar resumen de tareas
        async function actualizarResumenTareas() {
            try {
                const response = await fetch('index.php?action=obtener_resumen_tareas', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Error al obtener resumen de tareas');
                }
                
                const resumenTareas = await response.json();
                const tbody = document.getElementById('resumen-tareas-body');
                
                if (!tbody) return;
                
                // Limpiar contenido anterior
                tbody.innerHTML = '';
                
                if (resumenTareas.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="no-data">
                                <i class="fas fa-tasks"></i>
                                <p>No hay tareas activas</p>
                                <small>Contacte al coordinador para obtener tareas</small>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                // Generar filas para cada tarea
                resumenTareas.forEach(tarea => {
                    const progresoColor = tarea.porcentaje_progreso >= 80 ? 'green' : 
                                        tarea.porcentaje_progreso >= 50 ? 'orange' : 'red';
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="user-info">
                                <div class="user-details">
                                    <strong>${tarea.base_nombre}</strong>
                                    <small>ID: ${tarea.tarea_id} | ${tarea.fecha_asignacion}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="phone-number">${tarea.total_clientes_asignados}</span>
                        </td>
                        <td>
                            <span style="color: #28a745; font-weight: bold;">${tarea.clientes_gestionados}</span>
                        </td>
                        <td>
                            <span style="color: #dc3545; font-weight: bold;">${tarea.clientes_pendientes}</span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                                    <div style="background: ${progresoColor}; height: 100%; width: ${tarea.porcentaje_progreso}%; transition: width 0.3s ease;"></div>
                                </div>
                                <span style="font-size: 12px; font-weight: bold; color: ${progresoColor};">${tarea.porcentaje_progreso}%</span>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                console.log('Resumen de tareas actualizado:', resumenTareas);
                
            } catch (error) {
                console.error('Error al actualizar resumen de tareas:', error);
                const tbody = document.getElementById('resumen-tareas-body');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="no-data">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Error al cargar resumen</p>
                                <small>Intente recargar la página</small>
                            </td>
                        </tr>
                    `;
                }
            }
        }
        
        // Actualizar estadísticas cada 30 segundos
        setInterval(actualizarEstadisticas, 30000);
        
        // Actualizar resumen de tareas cada 30 segundos
        setInterval(actualizarResumenTareas, 30000);
        
        // Actualizar estadísticas cuando se regresa a la pestaña de estadísticas
        function cambiarTab(tabName) {
            // Ocultar todas las pestañas
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remover clase active de todos los spans
            const tabSpans = document.querySelectorAll('.main-tabs span');
            tabSpans.forEach(span => span.classList.remove('active'));
            
            // Mostrar la pestaña seleccionada
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Activar el span correspondiente
            event.target.classList.add('active');
            
            // Si se cambia a estadísticas, actualizar los datos
            if (tabName === 'estadisticas') {
                actualizarEstadisticas();
            }
            
            // Si se cambia a clientes, actualizar el resumen de tareas
            if (tabName === 'clientes') {
                actualizarResumenTareas();
            }
        }
        
        // Datos iniciales de clientes asignados renderizados por PHP
        const comerciosAsignadosInicial = <?php echo json_encode($clientes ?? [], JSON_UNESCAPED_UNICODE); ?>;

        // Cargar resumen de tareas al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarResumenTareas();
            actualizarEstadisticas();
            // Pintar la tabla con los datos de clientes asignados al cargar
            actualizarTablaClientes(comerciosAsignadosInicial);
        });
        
        // Función global para gestionar cliente (para compatibilidad)
        function gestionarCliente(clienteId) {
            window.location.href = 'index.php?action=asesor_gestionar&cliente_id=' + clienteId;
        }
        
        // Función global para ver historial (para compatibilidad)
        function verHistorialCliente(clienteId) {
            // Implementar funcionalidad de historial si es necesario
            console.log('Ver historial del cliente:', clienteId);
        }
    </script>

</body>
</html>
