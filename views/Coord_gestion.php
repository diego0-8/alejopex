<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Coordinador - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/coordinador-dashboard.css">
</head>
<body data-user-id="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">

    <?php 
    // Incluir navbar compartido
    $action = 'coordinador_gestion';
    include 'views/Navbar.php'; 
    ?>

    <div class="main-container">
        <?php 
        // Incluir header compartido
        include 'views/Header.php'; 
        ?>

        <!-- Sección Principal de Gestión -->
        <section class="current-call-section">
            <div class="call-details">
                <h3>GESTIÓN DE CLIENTES Y OBLIGACIONES</h3>
                <p class="call-info">Sistema <?php echo APP_NAME; ?></p>
                <p class="call-info">Carga y Administración de Datos</p>
                <small>Gestión de Archivos CSV</small>
                <div class="media-controls">
                    <button class="media-button" onclick="abrirPestañaCarga()">
                        <i class="fas fa-upload"></i> Subir CSV
                    </button>
                    <button class="media-button" onclick="descargarPlantilla()">
                        <i class="fas fa-file-download"></i> Descargar Plantilla
                    </button>
                </div>
            </div>
            
            <div class="call-main-view">
                <div class="client-info">
                    <i class="fas fa-database"></i>
                    <div>
                        <span class="client-name">Base de Clientes</span>
                        <span class="client-company"><?php echo APP_NAME; ?> - Gestión de Datos</span>
                    </div>
                </div>

                <div class="main-tabs">
                    <span class="active" onclick="cambiarTab('bases')">BASES</span>
                    <span onclick="cambiarTab('tareas')">TAREAS</span>
                    <span onclick="cambiarTab('carga-archivo')">CARGA DE ARCHIVO</span>
                    <span onclick="cambiarTab('historial')">HISTORIAL</span>
                </div>
                
                <div class="content-sections">
                    <!-- PESTAÑA 1: BASES -->
                    <div class="tab-content active" id="tab-bases">
                        <div class="left-content">
                            <!-- Gestión de Bases de Clientes -->
                            <div class="form-section">
                                <h4 class="section-title" style="margin-bottom: 20px;">Bases de Clientes Creadas</h4>
                                
                                <!-- Tabla de Bases de Clientes -->
                                <div class="bases-table-container">
                                    <table class="bases-table">
                                        <thead>
                                            <tr>
                                                <th>Nombre de la Base</th>
                                                <th>Fecha Creación</th>
                                                <th>Total Clientes</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bases-tbody">
                                            <tr>
                                                <td colspan="5" class="empty-state">
                                                    <i class="fas fa-database"></i>
                                                    <p>Cargando bases de clientes...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="right-content">
                            <!-- Estadísticas de Bases -->
                            <div class="stats-panel">
                                <h4>Estadísticas de Bases</h4>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <span class="stat-label">Total Bases:</span>
                                        <span class="stat-value" id="stat-total-bases">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Clientes Totales:</span>
                                        <span class="stat-value" id="stat-clientes-totales">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Bases Activas:</span>
                                        <span class="stat-value" id="stat-bases-activas">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones Rápidas -->
                            <div class="quick-actions-panel">
                                <h4>Acciones Rápidas</h4>
                                <div class="quick-actions">
                                    <button class="action-btn" onclick="openModal('crear-base')">
                                        <i class="fas fa-plus"></i> Nueva Base
                                    </button>
                                    <button class="action-btn" onclick="openModal('importar-base')">
                                        <i class="fas fa-upload"></i> Importar Base
                                    </button>
                                    <button class="action-btn" onclick="exportarBases()">
                                        <i class="fas fa-download"></i> Exportar Bases
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: TAREAS -->
                    <div class="tab-content" id="tab-tareas">
                        <div class="tareas-container">
                            <div class="tareas-header">
                                <h4>Asignación de Clientes a Asesores</h4>
                                <p class="tareas-description">Seleccione la base de clientes, asigne un asesor y especifique el número de clientes a asignar</p>
                                <div class="tareas-info-note">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Nota:</strong> Los asesores deben tener acceso a la base de clientes (desde la pestaña "BASES" → botón "Dar Acceso") para poder recibir asignaciones específicas de clientes.
                                </div>
                            </div>
                            
                            <div class="tareas-assignment-table">
                                <table class="assignment-table">
                                    <thead>
                                        <tr>
                                            <th class="field-column">Campo Requerido</th>
                                            <th class="value-column">Selección</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="field-cell">
                                                <div class="field-label">
                                                    <i class="fas fa-database"></i>
                                                    <span>Base de Clientes</span>
                                                </div>
                                                <small class="field-description">Seleccione la base de datos de clientes</small>
                                            </td>
                                            <td class="value-cell">
                                                <select id="select-base-clientes" class="form-control assignment-select" onchange="seleccionarBaseParaTarea(this.value)">
                                                    <option value="">Seleccione una base de clientes...</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="field-cell">
                                                <div class="field-label">
                                                    <i class="fas fa-user-tie"></i>
                                                    <span>Asesor a Asignar</span>
                                                </div>
                                                <small class="field-description">Seleccione el asesor responsable</small>
                                            </td>
                                            <td class="value-cell">
                                                <select id="select-asesor" class="form-control assignment-select" onchange="validarAsignacion()">
                                                    <option value="">Seleccione un asesor...</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="field-cell">
                                                <div class="field-label">
                                                    <i class="fas fa-users"></i>
                                                    <span>Clientes a Asignar</span>
                                                </div>
                                                <small class="field-description">Especifique cuántos clientes asignar (solo clientes no asignados por tareas anteriores)</small>
                                            </td>
                                            <td class="value-cell">
                                                <div class="client-assignment-controls">
                                                    <input type="number" id="input-clientes-asignar" class="form-control assignment-input" 
                                                           placeholder="0" min="1" max="0" onchange="validarAsignacion()">
                                                    <span class="client-total-info" id="client-total-info">Total disponible: 0</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="tareas-summary" id="tareas-summary">
                                <div class="summary-card">
                                    <h5>Resumen de Asignación</h5>
                                    <div class="summary-details">
                                        <div class="summary-item">
                                            <span class="summary-label">Base Seleccionada:</span>
                                            <span class="summary-value" id="summary-base">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Asesor Asignado:</span>
                                            <span class="summary-value" id="summary-asesor">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Clientes a Asignar:</span>
                                            <span class="summary-value" id="summary-clientes">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Clientes Restantes:</span>
                                            <span class="summary-value" id="summary-restantes">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tareas-actions">
                                <button class="btn btn-primary" id="btn-asignar" onclick="asignarClientes()" disabled>
                                    <i class="fas fa-user-plus"></i> Asignar Clientes
                                </button>
                                <button class="btn btn-secondary" onclick="limpiarAsignacion()">
                                    <i class="fas fa-undo"></i> Limpiar Selección
                                </button>
                                <button class="btn btn-info" onclick="verAsignacionesExistentes()">
                                    <i class="fas fa-list"></i> Ver Asignaciones
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: CARGA DE ARCHIVO -->
                    <div class="tab-content" id="tab-carga-archivo">
                        <div class="left-content">
                            <!-- Zona de Carga de Archivos -->
                            <div class="form-section">
                                <h4>Subir Archivo CSV</h4>
                                
                                <!-- Selección de Tipo de Carga -->
                                <div class="upload-type-selection">
                                    <h5>Tipo de Carga</h5>
                                    <div class="upload-type-buttons">
                                        <button type="button" class="btn btn-primary upload-type-btn active" id="btn-carga-nueva" onclick="selectUploadType('nueva')">
                                            <i class="fas fa-plus"></i> Carga Nueva
                                        </button>
                                        <button type="button" class="btn btn-secondary upload-type-btn" id="btn-carga-existente" onclick="selectUploadType('existente')">
                                            <i class="fas fa-database"></i> Carga Existente
                                        </button>
                                    </div>
                                </div>

                                <!-- Formulario para Carga Nueva -->
                                <div class="upload-form" id="form-carga-nueva">
                                    <div class="form-group">
                                        <label for="nombre-archivo">Nombre del Archivo</label>
                                        <input type="text" id="nombre-archivo" class="form-control" placeholder="Ingrese un nombre para el archivo" required>
                                        <small class="form-text text-muted">Este nombre identificará la base de datos creada</small>
                                    </div>
                                    
                                    <!-- Zona de Drop para Carga Nueva -->
                                    <div class="upload-zone" id="upload-zone-nueva" ondrop="dropHandler(event, 'nueva');" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);">
                                    <div class="upload-content">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <h3>Arrastra tu archivo CSV aquí</h3>
                                        <p>o haz clic para seleccionar</p>
                                            <form id="csv-upload-form-nueva" enctype="multipart/form-data">
                                                <input type="file" id="csv-file-nueva" name="csv_file" accept=".csv" onchange="handleFileSelect(event, 'nueva')">
                                                <button type="button" class="btn btn-primary" onclick="document.getElementById('csv-file-nueva').click()">
                                            <i class="fas fa-folder-open"></i> Seleccionar Archivo
                                        </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Formulario para Carga Existente -->
                                <div class="upload-form" id="form-carga-existente">
                                    <div class="form-group">
                                        <label for="base-datos-existente">Base de Datos Existente</label>
                                        <select id="base-datos-existente" class="form-control" required>
                                            <option value="">Seleccione una base de datos...</option>
                                        </select>
                                        <small class="form-text text-muted">Seleccione la base de datos a la que desea añadir el archivo</small>
                                    </div>
                                    
                                    <!-- Zona de Drop para Carga Existente -->
                                    <div class="upload-zone" id="upload-zone-existente" ondrop="dropHandler(event, 'existente');" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <h3>Arrastra tu archivo CSV aquí</h3>
                                            <p>o haz clic para seleccionar</p>
                                            <form id="csv-upload-form-existente" enctype="multipart/form-data">
                                                <input type="file" id="csv-file-existente" name="csv_file" accept=".csv" onchange="handleFileSelect(event, 'existente')">
                                                <button type="button" class="btn btn-primary" onclick="document.getElementById('csv-file-existente').click()">
                                                    <i class="fas fa-folder-open"></i> Seleccionar Archivo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información del Archivo Seleccionado -->
                                <div class="file-info" id="file-info">
                                    <div class="file-details">
                                        <i class="fas fa-file-csv"></i>
                                        <div class="file-data">
                                            <h4 id="file-name">archivo.csv</h4>
                                            <p id="file-size">0 KB</p>
                                            <p id="file-type">text/csv</p>
                                        </div>
                                        <button class="btn btn-danger" onclick="removeFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Configuración de Carga -->
                                <div class="upload-config">
                                    <h4>Configuración de Carga</h4>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Separador de Campos</label>
                                            <select id="separator" class="form-control">
                                                <option value=",">Coma (,)</option>
                                                <option value=";">Punto y coma (;)</option>
                                                <option value="\t">Tabulación</option>
                                                <option value="|">Pipe (|)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Codificación</label>
                                            <select id="encoding" class="form-control">
                                                <option value="utf-8">UTF-8</option>
                                                <option value="latin1">Latin-1</option>
                                                <option value="windows-1252">Windows-1252</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="has-header" checked>
                                                El archivo tiene encabezados
                                            </label>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="skip-empty" checked>
                                                Omitir filas vacías
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botón de Acción -->
                                <div class="upload-actions">
                                    <button class="btn btn-primary" onclick="subirArchivo()" id="btn-subir" disabled>
                                        <i class="fas fa-upload"></i> Subir Archivo
                                    </button>
                                    <button class="btn btn-warning" onclick="descargarPlantilla()">
                                        <i class="fas fa-download"></i> Descargar Plantilla
                                    </button>
                                    <button class="btn btn-secondary" onclick="limpiarFormulario()" id="btn-limpiar">
                                        <i class="fas fa-trash"></i> Limpiar
                                    </button>
                                </div>
                                
                                <!-- Mensajes de Resultado -->
                                <div id="resultado-carga" class="resultado-carga">
                                    <div class="resultado-content">
                                        <h4 id="resultado-titulo">Resultado de la Carga</h4>
                                        <div id="resultado-mensaje"></div>
                                        <div id="resultado-detalles"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="right-content">
                            <!-- Panel de Estadísticas de Procesamiento -->
                            <div class="progress-panel">
                                <h4>Estadísticas de Procesamiento</h4>
                                
                                <div class="progress-stats">
                                    <div class="stat-item">
                                        <span class="stat-label">Total Filas Procesadas:</span>
                                        <span class="stat-value" id="rows-processed">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Total Empresas (NIT Únicos):</span>
                                        <span class="stat-value" id="total-empresas">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Total Obligaciones:</span>
                                        <span class="stat-value" id="total-obligaciones">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Clientes Nuevos Creados:</span>
                                        <span class="stat-value" id="clientes-creados">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Obligaciones Nuevas Creadas:</span>
                                        <span class="stat-value" id="obligaciones-creadas">0</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Errores Encontrados:</span>
                                        <span class="stat-value" id="rows-errors">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Log de Errores -->
                            <div class="error-log">
                                <h4>Log de Errores</h4>
                                <div class="log-content" id="error-log">
                                    <p class="log-empty">No hay errores</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 4: HISTORIAL -->
                    <div class="tab-content" id="tab-historial">
                        <div class="historial-container">
                            <div class="historial-header">
                                <h4>Historial de Actividades</h4>
                                <div class="historial-filters">
                                    <select class="form-control" onchange="filtrarHistorial()">
                                        <option value="">Todas las actividades</option>
                                        <option value="carga_csv">Cargas de archivos</option>
                                        <option value="asignacion_tarea">Asignaciones de tareas</option>
                                        <option value="completar_tarea">Tareas completadas</option>
                                        <option value="acceso_base">Accesos a bases</option>
                                        <option value="semana">Última semana</option>
                                        <option value="mes">Último mes</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="historial-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Actividad</th>
                                            <th>Archivo/Tarea</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historial-tbody">
                                        <tr>
                                            <td colspan="4" class="empty-state">
                                                <i class="fas fa-history"></i>
                                                <p>Cargando historial...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="historial-actions">
                                <button class="btn btn-secondary" onclick="limpiarHistorial()">
                                    <i class="fas fa-trash"></i> Limpiar Historial
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- MODALES -->
    
    <!-- Modal de Subir CSV -->
    <div class="modal" id="modal-subir-csv">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Subir Archivo CSV</h3>
                <button class="modal-close" onclick="closeModal('subir-csv')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Selecciona un archivo CSV para cargar a la base de datos de clientes.</p>
                <div class="file-upload-area">
                    <input type="file" id="modal-csv-file" accept=".csv">
                    <label for="modal-csv-file" class="file-upload-label">
                        <i class="fas fa-upload"></i>
                        <span>Seleccionar Archivo CSV</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('subir-csv')">Cancelar</button>
                <button class="btn btn-primary">Subir Archivo</button>
            </div>
        </div>
    </div>

    <!-- Modal de Plantilla CSV -->
    <div class="modal" id="modal-plantilla-csv">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Descargar Plantilla CSV</h3>
                <button class="modal-close" onclick="closeModal('plantilla-csv')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Descarga la plantilla CSV con el formato correcto para cargar clientes.</p>
                <div class="template-info">
                    <h4>Campos Requeridos:</h4>
                    <ul>
                        <li>nombre_completo</li>
                        <li>email</li>
                        <li>telefono</li>
                        <li>fecha_nacimiento</li>
                        <li>direccion</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('plantilla-csv')">Cancelar</button>
                <button class="btn btn-primary" onclick="descargarPlantilla()">Descargar Plantilla</button>
            </div>
        </div>
    </div>

    <!-- Modal de Validar Datos -->
    <div class="modal" id="modal-validar-datos">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Validar Datos</h3>
                <button class="modal-close" onclick="closeModal('validar-datos')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Valida los datos del archivo CSV antes de cargarlos a la base de datos.</p>
                <div class="validation-results" id="validation-results">
                    <div class="validation-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Formato de archivo válido</span>
                    </div>
                    <div class="validation-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Encabezados correctos</span>
                    </div>
                    <div class="validation-item">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <span>3 registros con datos incompletos</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('validar-datos')">Cerrar</button>
                <button class="btn btn-primary">Procesar Archivo</button>
            </div>
        </div>
    </div>

    <!-- Modal de Configuración de Carga -->
    <div class="modal" id="modal-configuracion-carga">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Configuración de Carga</h3>
                <button class="modal-close" onclick="closeModal('configuracion-carga')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="config-form">
                    <div class="form-group">
                        <label>Tamaño máximo de archivo (MB)</label>
                        <input type="number" class="form-control" value="10">
                    </div>
                    <div class="form-group">
                        <label>Separador de campos</label>
                        <select class="form-control">
                            <option value=",">Coma (,)</option>
                            <option value=";">Punto y coma (;)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('configuracion-carga')">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Acceso a Base de Datos -->
    <div class="modal" id="modal-acceso-base" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Dar Acceso a Base de Datos</h3>
                <button class="modal-close" onclick="closeModalAccesoBase()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="base-access-info">
                    <div class="base-info-card">
                        <h4 id="modal-acceso-base-nombre">Base de Clientes</h4>
                        <input type="hidden" id="modal-acceso-base-id" value="">
                        <div class="base-details">
                            <span class="base-detail-item">
                                <i class="fas fa-info-circle"></i>
                                <strong>Nota:</strong> Los asesores seleccionados tendrán acceso completo a esta base para búsqueda y gestión de clientes.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="asesores-access-section">
                    <h4>Seleccionar Asesores</h4>
                    <p class="section-description">Seleccione los asesores que tendrán acceso completo a esta base de datos.</p>
                    
                    <div class="asesores-list">
                        <div id="asesores-acceso-list" class="asesores-checkbox-list">
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Cargando asesores...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModalAccesoBase()">Cancelar</button>
                <button class="btn btn-primary" onclick="guardarAccesoBase()" id="btn-guardar-acceso-base">
                    <i class="fas fa-save"></i> Guardar Acceso
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ver Clientes -->
    <div class="modal" id="modal-ver-clientes" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Clientes de la Base: <span id="modal-ver-clientes-nombre"></span></h3>
                <button class="modal-close" onclick="closeModalVerClientes()">&times;</button>
            </div>
            <div class="modal-body" id="modal-ver-clientes-body">
                <div class="clientes-summary">
                    <p><strong>Total de clientes:</strong> <span id="modal-clientes-total">0</span></p>
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>CC</th>
                                <th>Nombre</th>
                                <th>Celular</th>
                            </tr>
                        </thead>
                        <tbody id="modal-clientes-tbody">
                            <tr>
                                <td colspan="3" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando clientes...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModalVerClientes()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Ver Asesores con Acceso -->
    <div class="modal" id="modal-ver-asesores-acceso" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Asesores con Acceso a la Base</h3>
                <button class="modal-close" onclick="cerrarModalVerAsesores()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="base-access-info">
                    <div class="base-info-card">
                        <h4 id="modal-ver-asesores-base-nombre">Base de Clientes</h4>
                        <input type="hidden" id="modal-ver-asesores-base-id" value="">
                        <div class="base-details">
                            <span class="base-detail-item">
                                <i class="fas fa-info-circle"></i>
                                <strong>Total de asesores con acceso:</strong> <span id="modal-ver-asesores-total">0</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="asesores-access-list">
                    <h4>Lista de Asesores con Acceso</h4>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto; margin-top: 20px;">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Usuario</th>
                                    <th>Cédula</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="modal-ver-asesores-tbody">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Cargando asesores...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalVerAsesores()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Asignaciones de Asesores -->
    <div class="modal" id="modal-asignaciones-asesores">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Dar Acceso a Base de Clientes</h3>
                <button class="modal-close" onclick="cerrarModalAsignaciones()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="base-assignment-info">
                    <div class="base-info-card">
                        <h4 id="modal-assignment-base-name">Base de Clientes</h4>
                        <div class="base-details">
                            <span class="base-detail-item">
                                <i class="fas fa-database"></i>
                                <strong>Base:</strong> <span id="modal-assignment-base-details">-</span>
                            </span>
                            <span class="base-detail-item">
                                <i class="fas fa-users"></i>
                                <strong>Clientes:</strong> <span id="modal-assignment-base-clients">0</span>
                            </span>
                            <span class="base-detail-item">
                                <i class="fas fa-user-check"></i>
                                <strong>Asesores asignados:</strong> <span id="modal-assignment-count">0</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="asesores-assignment-section">
                    <h4>Seleccionar Asesores</h4>
                    <p class="section-description">Seleccione los asesores que tendrán <strong>acceso completo</strong> a esta base de clientes. Los asesores seleccionados podrán buscar y gestionar todos los clientes de esta base. Para asignar clientes específicos, use la pestaña "TAREAS".</p>
                    
                    <div class="asesores-list">
                        <div class="asesores-filters">
                            <div class="filter-group">
                                <label>Buscar asesor:</label>
                                <input type="text" id="search-asesor-assignment" class="form-control" placeholder="Nombre o usuario del asesor..." onkeyup="filtrarAsesoresAsignacion()">
                            </div>
                            <div class="filter-group">
                                <label>Estado:</label>
                                <select id="filter-estado-assignment" class="form-control" onchange="filtrarAsesoresAsignacion()">
                                    <option value="">Todos</option>
                                    <option value="activo">Activos</option>
                                    <option value="inactivo">Inactivos</option>
                                </select>
                            </div>
                        </div>

                        <div class="asesores-checkbox-list" id="asesores-assignment-list">
                            <!-- Los asesores se cargarán dinámicamente aquí -->
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Cargando asesores...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="assignment-summary" id="assignment-summary" style="display: none;">
                    <h4>Resumen de Acceso</h4>
                    <div class="summary-content">
                        <div class="summary-item">
                            <span class="summary-label">Asesores seleccionados:</span>
                            <span class="summary-value" id="selected-assignment-count">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tipo de acceso:</span>
                            <span class="summary-value" id="assignment-access-type">Acceso completo a la base de clientes</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Permisos:</span>
                            <span class="summary-value" id="assignment-permissions">Búsqueda, visualización y gestión de todos los clientes</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalAsignaciones()">Cancelar</button>
                <button class="btn btn-primary" onclick="guardarAsignacionesAsesores()" id="btn-guardar-asignaciones" disabled>
                    <i class="fas fa-key"></i> Otorgar Acceso
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ver Asignaciones -->
    <div class="modal" id="modal-ver-asignaciones" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Asignaciones Pendientes</h3>
                <button class="modal-close" onclick="cerrarModalVerAsignaciones()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="asignaciones-info">
                    <p class="section-description">Lista de todas las asignaciones de clientes a asesores que están pendientes o en progreso. Use el botón "Completar" para finalizar una asignación.</p>
                </div>
                
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto; margin-top: 20px;">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Base</th>
                                <th>Asesor</th>
                                <th>Clientes</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="modal-asignaciones-tbody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando asignaciones...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalVerAsignaciones()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/coord-comercio-factura.js"></script>
    <script src="assets/js/hybrid-updater.js"></script>
    
    <script>
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modals = ['modal-ver-asesores-acceso', 'modal-ver-clientes', 'modal-acceso-base', 'modal-asignaciones-asesores', 'modal-ver-asignaciones'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && event.target === modal) {
                    if (modalId === 'modal-ver-asesores-acceso') {
                        cerrarModalVerAsesores();
                    } else if (modalId === 'modal-ver-clientes') {
                        closeModalVerClientes();
                    } else if (modalId === 'modal-acceso-base') {
                        closeModalAccesoBase();
                    } else if (modalId === 'modal-asignaciones-asesores') {
                        cerrarModalAsignaciones();
                    } else if (modalId === 'modal-ver-asignaciones') {
                        cerrarModalVerAsignaciones();
                    }
                }
            });
        };
        
        // Función para abrir la pestaña de carga de archivo
        function abrirPestañaCarga() {
            // Cambiar a la pestaña "CARGA DE ARCHIVO"
            cambiarTab('carga-archivo');
        }
        
        // Función para descargar la plantilla CSV
        function descargarPlantilla() {
            // Crear contenido CSV con los campos necesarios para clientes y obligaciones
            const camposCSV = [
                'CC',
                'nombre',
                'numero_obligacion',
                'producto',
                'saldo_capital',
                'saldo_total',
                'dias_mora',
                'cel1',
                'cel2',
                'cel3',
                'cel4',
                'cel5',
                'cel6'
            ];
            
            // Crear contenido CSV con una fila de ejemplo
            const contenidoCSV = camposCSV.join(',') + '\n' +
                '1234567890,JUAN PEREZ,OBL-001,CREDITO,5000000,5500000,45,3001234567,3109876543,,,,,';
            
            // Crear blob y descargar
            const blob = new Blob([contenidoCSV], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'plantilla_clientes_obligaciones.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

</body>
</html>
