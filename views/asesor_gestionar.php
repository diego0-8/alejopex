<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Cliente - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/coordinador-dashboard.css">
    <link rel="stylesheet" href="assets/css/asesor_gestionar.css">
    <link rel="stylesheet" href="assets/css/webrtc-softphone.css">
</head>
<body data-user-id="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">

    <?php 
    // Incluir navbar compartido
    $action = 'asesor_gestionar';
    include 'views/Navbar.php'; 
    ?>

    <div class="gestion-container">

        <!-- Contenido principal en tres columnas -->
        <div class="gestion-content-tres-columnas">
            
            <!-- COLUMNA 1: INFORMACIÓN DEL CLIENTE Y CONTRATOS -->
            <div class="columna-uno">
                <!-- Información del Cliente -->
                <div class="seccion-info-cliente">
                    <h3><i class="fas fa-user"></i> Información del Cliente</h3>
                    <div class="cliente-detalles">
                        <h4 id="cliente-nombre-completo">Cargando...</h4>
                        <div class="cliente-datos-lista">
                            <div class="cliente-dato">
                                <span class="dato-label"><i class="fas fa-id-card"></i> CC:</span>
                                <span id="cliente-cedula">Cargando...</span>
                            </div>
                            <div class="cliente-dato">
                                <span class="dato-label"><i class="fas fa-phone"></i> Celulares:</span>
                                <div class="telefonos-cliente" id="telefonos-cliente">
                                    <span>Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Obligaciones -->
                <div class="seccion-contratos">
                    <h3 id="contratos-titulo"><i class="fas fa-file-invoice-dollar"></i> Obligaciones</h3>
                    <div class="contratos-container" id="contratos-container">
                        <div class="cargando-contratos">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Cargando obligaciones...</p>
                        </div>
                    </div>
                </div>

                <!-- Botón agregar información -->
                <button class="btn-agregar-info">
                    <i class="fas fa-plus"></i> Agregar más información
                </button>

            </div>

            <!-- COLUMNA 2: ÁRBOL DE TIPIFICACIÓN -->
            <div class="columna-dos">
                <div class="seccion-tipificacion">
                    <h3><i class="fas fa-sitemap"></i> Perfilación del cliente</h3>
                    <div class="tipificacion-form">
                        <div class="form-group">
                            <label><i class="fas fa-phone-alt"></i> Canal de Contacto:</label>
                            <select id="canal-contacto">
                                <option value="">Selecciona una opción</option>
                                <option value="llamada">Llamada</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Correo Electrónico</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-file-invoice"></i> Obligación a Gestionar: <small style="color: #666;">(Opcional - Si no selecciona ninguna, se guardará como "Ninguna")</small></label>
                            <select id="contrato-gestionar">
                                <option value="">Selecciona una factura (opcional)</option>
                                <option value="ninguna">Ninguna (Cliente no quiso pagar ninguna)</option>
                                <!-- Las facturas se cargarán dinámicamente -->
                            </select>
                        </div>
                        <div class="form-group" id="opciones-todas-facturas" style="display: none; margin-top: 10px;">
                            <div style="display: flex; gap: 15px; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
                                <label style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="gestionar-obligaciones" value="todas" id="radio-todas" onchange="manejarSeleccionObligaciones('todas')">
                                    <span style="font-weight: 500;"><i class="fas fa-check-double"></i> Tipificar todas las obligaciones</span>
                                </label>
                                <label style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="gestionar-obligaciones" value="ninguna" id="radio-ninguna" onchange="manejarSeleccionObligaciones('ninguna')">
                                    <span style="font-weight: 500;"><i class="fas fa-times"></i> Ninguna</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nivel 1 - Tipo de Contacto:</label>
                            <select id="tipo-contacto-nivel1" required>
                                <option value="">Selecciona una opción</option>
                                <option value="llamada_saliente">LLAMADA SALIENTE</option>
                                <option value="whatsapp">WHATSAPP</option>
                                <option value="email">EMAIL</option>
                                <option value="recibir_llamada">RECIBIR LLAMADA</option>
                            </select>
                        </div>
                        <!-- Nivel 2 - Visible solo si hay selección en Nivel 1 -->
                        <div class="form-group" id="nivel2-container" style="display: none;">
                            <label><i class="fas fa-tag"></i> Nivel 2 - Clasificación:</label>
                            <select id="tipo-contacto-nivel2">
                                <option value="">Primero selecciona el Nivel 1</option>
                            </select>
                        </div>
                        <!-- Nivel 3 - Visible solo si hay selección en Nivel 2 -->
                        <div class="form-group" id="nivel3-container" style="display: none;">
                            <label><i class="fas fa-tag"></i> Nivel 3 - Detalle:</label>
                            <select id="tipo-contacto-nivel3">
                                <option value="">Primero selecciona el Nivel 2</option>
                            </select>
                        </div>
                        <!-- Campos adicionales para ACUERDO DE PAGO y COMPROMISO DE PAGO -->
                        <div class="form-group" id="campos-fecha-valor" style="display: none;">
                            <label><i class="fas fa-calendar-alt"></i> Fecha y Valor:</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="date" id="fecha-pago" placeholder="Fecha de pago" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" min="">
                                <div style="flex: 1; position: relative;">
                                    <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666; font-weight: 600;">$</span>
                                    <input type="text" id="valor-pago" placeholder="0" style="width: 100%; padding: 8px 8px 8px 30px; border: 1px solid #ddd; border-radius: 4px;" inputmode="numeric">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Área para Staqueue -->
                        <div class="staqueue-section">
                            <div class="staqueue-banner">
                                <div class="staqueue-texto">
                                    <strong><i class="fas fa-phone-square"></i> Ingrese a Staqueue</strong>
                                    <p>Accede al sistema de marcado automático para realizar llamadas</p>
                                </div>
                                <a href="https://estaqueue.udpsa.com/loginMarcador.html" target="_blank" class="staqueue-link"><i class="fas fa-external-link-alt"></i> Dale aquí</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA 3: OBSERVACIONES Y CANALES -->
            <div class="columna-tres">
                <!-- Observaciones -->
                <div class="seccion-observaciones">
                    <h3><i class="fas fa-comment-dots"></i> Observaciones y Comentarios</h3>
                    <p class="instrucciones">Documente las interacciones y seguimientos pertinentes</p>
                    <div class="observaciones-detalladas">
                        <label>Observaciones Detalladas:</label>
                        <textarea id="observaciones-texto" rows="10" placeholder="Describe detalladamente el resultado de la gestión, acuerdos, próximos pasos, objeciones del cliente, etc."></textarea>
                    </div>
                </div>

                <!-- Canales de Comunicación -->
                <div class="seccion-canales">
                    <h3><i class="fas fa-broadcast-tower"></i> Canales de Comunicación Autorizados</h3>
                    <p class="instrucciones">Seleccione los canales autorizados por la empresa para futuras comunicaciones</p>
                    <div class="canales-lista">
                        <div class="canal-item">
                            <input type="checkbox" id="canal-llamada">
                            <label for="canal-llamada">
                                <i class="fas fa-phone"></i>
                                Llamada Telefónica
                            </label>
                        </div>
                        <div class="canal-item">
                            <input type="checkbox" id="canal-whatsapp">
                            <label for="canal-whatsapp">
                                <i class="fab fa-whatsapp"></i>
                                WhatsApp
                            </label>
                        </div>
                        <div class="canal-item">
                            <input type="checkbox" id="canal-email">
                            <label for="canal-email">
                                <i class="fas fa-envelope"></i>
                                Correo Electrónico
                            </label>
                        </div>
                        <div class="canal-item">
                            <input type="checkbox" id="canal-sms">
                            <label for="canal-sms">
                                <i class="fas fa-sms"></i>
                                SMS
                            </label>
                        </div>
                        <div class="canal-item">
                            <input type="checkbox" id="canal-correo">
                            <label for="canal-correo">
                                <i class="fas fa-mail-bulk"></i>
                                Correo Físico
                            </label>
                        </div>
                        <div class="canal-item">
                            <input type="checkbox" id="canal-mensajeria">
                            <label for="canal-mensajeria">
                                <i class="fas fa-comments"></i>
                                Mensajería por Aplicaciones
                            </label>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Botones de acción principales -->
        <div class="action-buttons" id="action-buttons-container" style="display: flex; gap: 15px; justify-content: center; align-items: center; flex-wrap: wrap;">
            <!-- Botones iniciales (antes de guardar) -->
            <div id="botones-iniciales" style="display: flex; gap: 15px; align-items: center;">
                <button class="btn-action btn-primary" onclick="guardarGestion()">
                    <i class="fas fa-save"></i> Guardar Gestión
                </button>
                <button class="btn-action btn-secondary" onclick="volverTareas()">
                    <i class="fas fa-tasks"></i> Volver a Tareas
                </button>
                <button class="btn-action btn-success" onclick="irDashboard()">
                    <i class="fas fa-home"></i> Ir al Dashboard
                </button>
            </div>
            
            <!-- Botones después de guardar (ocultos inicialmente) -->
            <div id="botones-despues-guardar" style="display: none; gap: 15px; align-items: center;">
                <button class="btn-action btn-primary" id="btn-siguiente-cliente" onclick="irSiguienteCliente()" style="display: none;">
                    <i class="fas fa-arrow-right"></i> Siguiente Cliente
                </button>
                <button class="btn-action btn-info" onclick="mostrarBusquedaCliente()">
                    <i class="fas fa-search"></i> Buscar Cliente
                </button>
                <button class="btn-action btn-secondary" onclick="volverClientes()">
                    <i class="fas fa-users"></i> Volver a Clientes
                </button>
            </div>
        </div>

        <!-- Historial de gestiones (ancho completo) -->
        <div class="seccion-historial-full">
            <h3><i class="fas fa-history"></i> Historial de Gestiones</h3>
            <div id="historial-container">
                <div class="historial-vacio">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Cargando historial...</p>
                </div>
            </div>
        </div>
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

    <!-- Modal de Búsqueda de Cliente -->
    <div id="modal-busqueda-cliente" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10003; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #007bff;">
                    <i class="fas fa-search"></i> Buscar Cliente
                </h3>
                <button onclick="cerrarModalBusqueda()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="busqueda-cliente-input" style="display: block; margin-bottom: 8px; color: #666; font-size: 14px;">CC o Celular:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="busqueda-cliente-input" placeholder="Ingrese CC o celular..." 
                           style="flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;"
                           onkeypress="if(event.key === 'Enter') buscarClienteDesdeModal();">
                    <button onclick="buscarClienteDesdeModal()" style="padding: 12px 20px; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <!-- Resultados de búsqueda -->
            <div id="resultados-busqueda-cliente" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; background: #f8f9fa;">
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-search"></i>
                    <p>Ingrese CC o celular para buscar</p>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/asesor-gestionar.js"></script>
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
        
        function finalizarPausa() {
            if (window.asesorTiempos) {
                window.asesorTiempos.finalizarPausa();
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
        
        // Funciones para los nuevos botones después de guardar gestión
        function mostrarBotonesDespuesGuardar() {
            document.getElementById('botones-iniciales').style.display = 'none';
            document.getElementById('botones-despues-guardar').style.display = 'flex';
            
            // Verificar si hay siguiente cliente disponible
            verificarSiguienteCliente();
        }
        
        async function verificarSiguienteCliente() {
            try {
                const response = await fetch('index.php?action=obtener_siguiente_cliente', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                const btnSiguienteCliente = document.getElementById('btn-siguiente-cliente');
                
                if (data.success && data.cliente) {
                    btnSiguienteCliente.style.display = 'inline-block';
                    btnSiguienteCliente.title = `Siguiente: ${data.cliente['NOMBRE CONTRATANTE']}`;
                } else {
                    btnSiguienteCliente.style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error al verificar siguiente cliente:', error);
                document.getElementById('btn-siguiente-cliente').style.display = 'none';
            }
        }
        
        async function irSiguienteCliente() {
            try {
                const response = await fetch('index.php?action=obtener_siguiente_cliente', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success && data.cliente) {
                    // Redirigir al siguiente cliente
                    window.location.href = `index.php?action=asesor_gestionar&cliente_id=${data.cliente.ID_CLIENTE}`;
                } else {
                    alert('No hay más clientes pendientes por gestionar');
                }
                
            } catch (error) {
                console.error('Error al obtener siguiente cliente:', error);
                alert('Error al obtener el siguiente cliente');
            }
        }
        
        function mostrarBusquedaCliente() {
            const modal = document.getElementById('modal-busqueda-cliente');
            if (modal) {
                modal.style.display = 'flex';
                document.getElementById('busqueda-cliente-input').value = '';
                document.getElementById('resultados-busqueda-cliente').innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #666;">
                        <i class="fas fa-search"></i>
                        <p>Ingrese CC o celular para buscar</p>
                    </div>
                `;
            }
        }
        
        function cerrarModalBusqueda() {
            const modal = document.getElementById('modal-busqueda-cliente');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        async function buscarClienteDesdeModal() {
            const termino = document.getElementById('busqueda-cliente-input').value.trim();
            const resultadosDiv = document.getElementById('resultados-busqueda-cliente');
            
            if (!termino) {
                alert('Por favor ingrese CC o celular');
                return;
            }
            
            // Mostrar loading
            resultadosDiv.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Buscando cliente...</p>
                </div>
            `;
            
            try {
                const response = await fetch('index.php?action=buscar_cliente_asesor', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        termino: termino,
                        criterio: 'mixto'
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.clientes && data.clientes.length > 0) {
                    let html = '';
                    data.clientes.forEach(comercio => {
                        const comercioId = comercio.ID_COMERCIO || comercio.id || comercio.ID_CLIENTE;
                        const nombreCliente = comercio.nombre || comercio['NOMBRE CONTRATANTE'] || comercio.NOMBRE_CLIENTE || 'N/A';
                        const cc = comercio.cc || comercio.IDENTIFICACION || 'N/A';
                        const celular = comercio.CEL || comercio['TEL 1'] || comercio.cel || 'N/A';
                        
                        html += `
                            <div style="padding: 15px; border-bottom: 1px solid #dee2e6; cursor: pointer;" 
                                 onclick="gestionarClienteDesdeModal('${comercioId}')">
                                <div style="font-weight: 600; color: #333; margin-bottom: 5px;">
                                    ${nombreCliente}
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    <div>CC: ${cc}</div>
                                    <div>Celular: ${celular}</div>
                                </div>
                            </div>
                        `;
                    });
                    resultadosDiv.innerHTML = html;
                } else {
                    resultadosDiv.innerHTML = `
                        <div style="padding: 20px; text-align: center; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>No se encontraron clientes</p>
                            <small>Verifique el CC o celular ingresado</small>
                        </div>
                    `;
                }
                
            } catch (error) {
                console.error('Error al buscar cliente:', error);
                resultadosDiv.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error al buscar cliente</p>
                        <small>Intente nuevamente</small>
                    </div>
                `;
            }
        }
        
        function gestionarClienteDesdeModal(clienteId) {
            cerrarModalBusqueda();
            window.location.href = `index.php?action=asesor_gestionar&cliente_id=${clienteId}`;
        }
        
        function volverClientes() {
            window.location.href = 'index.php?action=asesor_dashboard#tab-clientes';
        }
        
        // Función global para ser llamada desde asesor-gestionar.js después de guardar
        window.mostrarBotonesDespuesGuardar = mostrarBotonesDespuesGuardar;
    </script>

    <!-- WebRTC Softphone Integration -->
    <script src="assets/js/sip.min.js"></script>
    <script>
    // Verificación mejorada de carga de SIP.js
    (function verificarSIPjs() {
        let intentos = 0;
        const maxIntentos = 50;
        
        function verificar() {
            intentos++;
            
            // Verificar si SIP.js está completamente cargado
            if (typeof SIP !== 'undefined' && 
                typeof SIP.UserAgent !== 'undefined' && 
                typeof SIP.UserAgent.makeURI === 'function') {
                
                console.log('✅ SIP.js cargado correctamente (intentos: ' + intentos + ')');
                console.log('✅ Versión:', SIP.version || 'Unknown');
                window.sipjsLoaded = true;
                
                // Cargar el softphone ahora que SIP.js está listo
                const scriptSoftphone = document.createElement('script');
                scriptSoftphone.src = 'assets/js/webrtc-softphone.js';
                scriptSoftphone.onload = function() {
                    console.log('✅ webrtc-softphone.js cargado');
                };
                document.head.appendChild(scriptSoftphone);
                
                return;
            }
            
            if (intentos < maxIntentos) {
                if (intentos % 10 === 0) {
                    console.log('⏳ Esperando SIP.js... (' + intentos + '/' + maxIntentos + ')');
                }
                setTimeout(verificar, 100);
            } else {
                console.error('❌ SIP.js no se cargó después de ' + (maxIntentos * 100) + 'ms');
                console.error('Estado actual:', {
                    'typeof SIP': typeof SIP,
                    'SIP.UserAgent': typeof (typeof SIP !== 'undefined' ? SIP.UserAgent : undefined),
                    'SIP.UserAgent.makeURI': typeof (typeof SIP !== 'undefined' && SIP.UserAgent ? SIP.UserAgent.makeURI : undefined)
                });
                
                // Intentar cargar desde CDN como fallback
                console.log('🔄 Intentando cargar desde CDN...');
                const scriptCDN = document.createElement('script');
                scriptCDN.src = 'https://unpkg.com/sip.js@0.21.2/dist/sip.min.js';
                scriptCDN.onload = function() {
                    console.log('✅ SIP.js cargado desde CDN');
                    window.sipjsLoaded = true;
                    
                    // Cargar softphone después de CDN
                    const scriptSoftphone = document.createElement('script');
                    scriptSoftphone.src = 'assets/js/webrtc-softphone.js';
                    document.head.appendChild(scriptSoftphone);
                };
                scriptCDN.onerror = function() {
                    console.error('❌ Error cargando SIP.js desde CDN');
                    alert('Error crítico: No se pudo cargar SIP.js. El softphone no funcionará.');
                };
                document.head.appendChild(scriptCDN);
            }
        }
        
        // Iniciar verificación después de un momento
        setTimeout(verificar, 100);
    })();
    </script>
    <?php
    // Incluir configuración WebRTC
    require_once 'config/asterisk.php';
    $webrtc_config = getWebRTCConfig();

    // Verificar si el usuario tiene extensión asignada
    if (isset($_SESSION['usuario_extension']) && !empty($_SESSION['usuario_extension'])):
    ?>
    <script>
    // Configuración del softphone
    const webrtcConfig = {
        wss_server: '<?php echo $webrtc_config['wss_server']; ?>',
        sip_domain: '<?php echo $webrtc_config['sip_domain']; ?>',
        extension: '<?php echo $_SESSION['usuario_extension'] ?? ''; ?>',
        password: '<?php echo $_SESSION['usuario_sip_password'] ?? ''; ?>',
        display_name: '<?php echo $_SESSION['usuario_nombre'] ?? 'Asesor'; ?>',
        stun_server: '<?php echo $webrtc_config['stun_server']; ?>',
        // Configuración de servidores ICE (STUN/TURN) - OPCIÓN 1
        // CRÍTICO: Debe ser un array válido para que WebRTC funcione correctamente
        iceServers: <?php 
            $iceServers = $webrtc_config['iceServers'] ?? [];
            // Asegurar que sea un array válido
            if (!is_array($iceServers) || empty($iceServers)) {
                // Fallback: servidores STUN públicos
                $iceServers = [
                    ['urls' => 'stun:stun.l.google.com:19302'],
                    ['urls' => 'stun:stun1.l.google.com:19302']
                ];
            }
            echo json_encode($iceServers, JSON_UNESCAPED_SLASHES);
        ?>,
        debug_mode: <?php echo $webrtc_config['debug_mode'] ? 'true' : 'false'; ?>
    };

    // Esperar a que TANTO SIP.js COMO webrtc-softphone.js estén cargados
    function inicializarSoftphoneConVerificacion() {
        let intentos = 0;
        const maxIntentos = 100;
        
        const intervalo = setInterval(function() {
            intentos++;
            
            // Verificar que TODO esté listo
            const sipjsListo = typeof SIP !== 'undefined' && 
                              typeof SIP.UserAgent !== 'undefined' && 
                              typeof SIP.UserAgent.makeURI === 'function';
            
            const softphoneListo = typeof WebRTCSoftphone !== 'undefined';
            
            if (sipjsListo && softphoneListo) {
                clearInterval(intervalo);
                console.log('✅ Todos los componentes listos, inicializando softphone...');
                
                try {
                    window.webrtcSoftphone = new WebRTCSoftphone(webrtcConfig);
                    console.log('✅ Softphone WebRTC inicializado correctamente');
                    console.log('📞 Extensión:', webrtcConfig.extension);
                } catch (error) {
                    console.error('❌ Error al inicializar softphone:', error);
                    console.error('Stack:', error.stack);
                    alert('Error al inicializar el softphone: ' + error.message);
                }
                
            } else {
                if (intentos % 10 === 0) {
                    console.log(`⏳ Esperando componentes... (${intentos}/${maxIntentos})`);
                    console.log('  SIP.js listo:', sipjsListo);
                    console.log('  WebRTCSoftphone listo:', softphoneListo);
                }
                
                if (intentos >= maxIntentos) {
                    clearInterval(intervalo);
                    console.error('❌ Timeout esperando componentes del softphone');
                    alert('El softphone no se pudo inicializar. Por favor, recarga la página.');
                }
            }
        }, 100);
    }

    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarSoftphoneConVerificacion);
    } else {
        inicializarSoftphoneConVerificacion();
    }

    // Función global para toggle
    function toggleSoftphone() {
        if (typeof window.webrtcSoftphone !== 'undefined' && 
            window.webrtcSoftphone !== null) {
            
            if (typeof window.webrtcSoftphone.toggle === 'function') {
                window.webrtcSoftphone.toggle();
            } else if (typeof window.webrtcSoftphone.show === 'function') {
                window.webrtcSoftphone.show();
            }
        } else {
            alert('El softphone aún no está listo. Por favor, espera un momento.');
        }
    }
    
    // Función global para llamar desde click-to-call
    function llamarDesdeWebRTC(numero) {
        if (typeof window.webrtcSoftphone !== 'undefined' && 
            window.webrtcSoftphone !== null && 
            window.webrtcSoftphone.callNumber) {
            window.webrtcSoftphone.callNumber(numero);
        } else {
            alert('Softphone no disponible. Por favor, espera a que se inicialice.');
        }
    }
    </script>
    <?php else: ?>
    <script>
    console.warn('⚠️ Usuario sin extensión WebRTC asignada');
    </script>
    <?php endif; ?>

</body>
</html>
