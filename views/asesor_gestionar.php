<?php require_once __DIR__ . '/../config.php'; ?>
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
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/coordinador-dashboard.css">
    <link rel="stylesheet" href="assets/css/asesor_gestionar.css">
</head>

<body data-user-id="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">

    <?php
    // Incluir navbar compartido
    $action = 'asesor_gestionar';
    include __DIR__ . '/Navbar.php';
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
                            <div class="cliente-dato" id="cliente-email-container" style="display: none;">
                                <span class="dato-label"><i class="fas fa-envelope"></i> Email:</span>
                                <span id="cliente-email">-</span>
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
                            <label><i class="fas fa-file-invoice"></i> Obligación a Gestionar: <small
                                    style="color: #666;">(Opcional - Si no selecciona ninguna, se guardará como
                                    "Ninguna")</small></label>
                            <select id="contrato-gestionar">
                                <option value="">Selecciona una factura (opcional)</option>
                                <option value="ninguna">Ninguna (Cliente no quiso pagar ninguna)</option>
                                <!-- Las facturas se cargarán dinámicamente -->
                            </select>
                        </div>
                        <div class="form-group" id="opciones-todas-facturas" style="display: none; margin-top: 10px;">
                            <div
                                style="display: flex; gap: 15px; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
                                <label
                                    style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="gestionar-obligaciones" value="todas" id="radio-todas"
                                        onchange="manejarSeleccionObligaciones('todas')">
                                    <span style="font-weight: 500;"><i class="fas fa-check-double"></i> Tipificar todas
                                        las obligaciones</span>
                                </label>
                                <label
                                    style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="gestionar-obligaciones" value="ninguna" id="radio-ninguna"
                                        onchange="manejarSeleccionObligaciones('ninguna')">
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
                                <input type="date" id="fecha-pago" placeholder="Fecha de pago"
                                    style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" min="">
                                <div style="flex: 1; position: relative;">
                                    <span
                                        style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666; font-weight: 600;">$</span>
                                    <input type="text" id="valor-pago" placeholder="0"
                                        style="width: 100%; padding: 8px 8px 8px 30px; border: 1px solid #ddd; border-radius: 4px;"
                                        inputmode="numeric">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones y Comentarios -->
                <div class="seccion-observaciones">
                    <h3><i class="fas fa-comment-dots"></i> Observaciones y Comentarios</h3>
                    <p class="instrucciones">Documente las interacciones y seguimientos pertinentes</p>
                    <div class="observaciones-detalladas">
                        <label>Observaciones Detalladas:</label>
                        <textarea id="observaciones-texto" rows="10"
                            placeholder="Describe detalladamente el resultado de la gestión, acuerdos, próximos pasos, objeciones del cliente, etc."></textarea>
                    </div>
                </div>
            </div>

            <!-- COLUMNA 3: SOFTPHONE Y CANALES -->
            <div class="columna-tres">
                <!-- Softphone WebRTC - Solo visible para asesores con extensión -->
                <?php
                // Obtener datos del usuario desde la base de datos para verificar extensión
                require_once __DIR__ . '/../models/Usuario.php';
                $usuario_model = new Usuario();

                // Intentar obtener el usuario de múltiples formas
                $usuario_data = false;
                $identificador_usado = '';

                // Método 1: Por cédula desde sesión
                if (!empty($_SESSION['usuario_cedula'])) {
                    $identificador_usado = $_SESSION['usuario_cedula'];
                    $usuario_data = $usuario_model->obtenerPorCedula($identificador_usado);
                    if ($usuario_data && defined('ASTERISK_DEBUG_MODE') && ASTERISK_DEBUG_MODE) {
                        error_log("DEBUG Softphone - Usuario encontrado por usuario_cedula: " . $identificador_usado);
                    }
                }

                // Método 2: Por usuario_id (que también es la cédula según AuthController)
                if (!$usuario_data && !empty($_SESSION['usuario_id'])) {
                    $identificador_usado = $_SESSION['usuario_id'];
                    $usuario_data = $usuario_model->obtenerPorCedula($identificador_usado);
                    if ($usuario_data && defined('ASTERISK_DEBUG_MODE') && ASTERISK_DEBUG_MODE) {
                        error_log("DEBUG Softphone - Usuario encontrado por usuario_id: " . $identificador_usado);
                    }
                }

                // DEBUG: Verificar datos obtenidos
                if (defined('ASTERISK_DEBUG_MODE') && ASTERISK_DEBUG_MODE) {
                    error_log("DEBUG Softphone - Variables de sesión:");
                    error_log("  - usuario_cedula: " . ($_SESSION['usuario_cedula'] ?? 'NO DEFINIDA'));
                    error_log("  - usuario_id: " . ($_SESSION['usuario_id'] ?? 'NO DEFINIDA'));
                    error_log("  - usuario_rol: " . ($_SESSION['usuario_rol'] ?? 'NO DEFINIDO'));

                    if ($usuario_data) {
                        error_log("DEBUG Softphone - Usuario encontrado:");
                        error_log("  - Cédula: " . ($usuario_data['cedula'] ?? 'NO DEFINIDA'));
                        error_log("  - Extension: " . ($usuario_data['extension_telefono'] ?? $usuario_data['extension'] ?? 'NO DEFINIDA'));
                        error_log("  - Clave Extension: " . (!empty($usuario_data['clave_extension'] ?? '') ? 'DEFINIDA (' . strlen($usuario_data['clave_extension']) . ' caracteres)' : 'VACIA'));
                        error_log("  - SIP Password (legacy): " . (!empty($usuario_data['sip_password'] ?? '') ? 'DEFINIDA (' . strlen($usuario_data['sip_password']) . ' caracteres)' : 'VACIA'));
                    } else {
                        error_log("DEBUG Softphone - ERROR: Usuario NO encontrado");
                        error_log("  - Intentó con: " . ($identificador_usado ?: 'NINGUNO'));
                    }
                }

                // Verificar que el usuario sea asesor Y tenga extensión y clave SIP asignadas
                // Prioridad: extension_telefono y clave_extension (nuevos campos), luego extension y sip_password (legacy)
                $extension_telefono = $usuario_data['extension_telefono'] ?? $usuario_data['extension'] ?? '';
                $clave_extension = $usuario_data['clave_extension'] ?? $usuario_data['sip_password'] ?? '';
                
                $mostrar_softphone = (
                    isset($_SESSION['usuario_rol']) &&
                    $_SESSION['usuario_rol'] === 'asesor' &&
                    $usuario_data &&
                    !empty($extension_telefono) &&
                    !empty($clave_extension)
                );

                // DEBUG: Verificar resultado de mostrar_softphone
                if (defined('ASTERISK_DEBUG_MODE') && ASTERISK_DEBUG_MODE) {
                    error_log("DEBUG Softphone - Mostrar softphone: " . ($mostrar_softphone ? 'SI' : 'NO'));
                    error_log("DEBUG Softphone - Rol: " . ($_SESSION['usuario_rol'] ?? 'NO DEFINIDO'));
                }

                if ($mostrar_softphone):
                    ?>
                    <div class="seccion-softphone-wrapper" style="margin-bottom: 20px;">
                        <div id="webrtc-softphone" class="webrtc-softphone-panel inline"></div>
                    </div>
                <?php endif; ?>

                <!-- Canales de Comunicación -->
                <div class="seccion-canales">
                    <h3><i class="fas fa-broadcast-tower"></i> Canales de Comunicación Autorizados</h3>
                    <p class="instrucciones">Seleccione los canales autorizados por la empresa para futuras
                        comunicaciones</p>
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
        <div class="action-buttons" id="action-buttons-container"
            style="display: flex; gap: 15px; justify-content: center; align-items: center; flex-wrap: wrap;">
            <!-- Botones iniciales (antes de guardar) -->
            <div id="botones-iniciales" style="display: flex; gap: 15px; align-items: center;">
                <button type="button" class="btn-action btn-primary" id="btn-guardar-gestion" onclick="guardarGestion()">
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
            <div id="botones-despues-guardar" style="display: none; gap: 15px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn-action btn-primary" id="btn-nueva-gestion" onclick="prepararNuevaGestion()"
                    title="Registrar otra gestión sobre este mismo cliente sin recargar la página">
                    <i class="fas fa-plus-circle"></i> Nueva gestión
                </button>
                <button class="btn-action btn-primary" id="btn-siguiente-cliente" onclick="irSiguienteCliente()"
                    style="display: none;">
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
    <div id="modal-tiempo-sesion"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; min-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #007bff;">
                    <i class="fas fa-clock"></i> Tiempo de Sesión
                </h3>
                <button onclick="toggleTiempoModal()"
                    style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <span style="display: block; margin-bottom: 5px; color: #666; font-size: 13px;">Hora Actual</span>
                    <span id="reloj-activo" style="font-size: 20px; font-weight: 700; color: #007bff;">--:-- --</span>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <span style="display: block; margin-bottom: 5px; color: #666; font-size: 13px;">Tiempo de
                        Sesión</span>
                    <span id="tiempo-sesion" style="font-size: 20px; font-weight: 700; color: #28a745;">00:00:00</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button id="btn-pausa" onclick="iniciarPausaBreak()"
                        style="padding: 12px; background: #ffc107; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-coffee"></i> Break
                    </button>
                    <button id="btn-almuerzo" onclick="iniciarPausaAlmuerzo()"
                        style="padding: 12px; background: #fd7e14; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-utensils"></i> Almuerzo
                    </button>
                    <button id="btn-bano" onclick="iniciarPausaBano()"
                        style="padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-toilet"></i> Baño
                    </button>
                    <button id="btn-mantenimiento" onclick="iniciarPausaMantenimiento()"
                        style="padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-tools"></i> Mantenimiento
                    </button>
                    <button id="btn-pausa-activa" onclick="iniciarPausaActiva()"
                        style="padding: 12px; background: #20c997; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-running"></i> Pausa Activa
                    </button>
                    <button id="btn-actividad-extra" onclick="iniciarActividadExtra()"
                        style="padding: 12px; background: #6610f2; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        <i class="fas fa-stopwatch"></i> Actividad Extra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Pausa (cuando está en pausa) -->
    <div id="modal-pausa"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px;">
            <i class="fas fa-clock" style="font-size: 48px; color: #ffc107; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">En Pausa</h3>
            <p style="margin: 0 0 20px 0; color: #666;" id="tipo-pausa-texto">Break de 30 minutos</p>
            <div style="font-size: 32px; font-weight: 700; color: #007bff; margin-bottom: 20px;">
                <span class="tiempo-pausa">30:00</span>
            </div>
            <button onclick="mostrarModalVerificacion()" class="btn btn-primary"
                style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-play"></i> Continuar Trabajo
            </button>
        </div>
    </div>

    <!-- Modal de Verificación de Contraseña -->
    <div id="modal-verificacion-contrasena"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10002; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <i class="fas fa-lock" style="font-size: 48px; color: #007bff; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">Verificación de Contraseña</h3>
            <p style="margin: 0 0 20px 0; color: #666;">Ingrese su contraseña para reanudar la sesión</p>

            <div style="margin-bottom: 20px; text-align: left;">
                <label for="input-contrasena-verificacion"
                    style="display: block; margin-bottom: 8px; color: #666; font-size: 14px;">Contraseña:</label>
                <input type="password" id="input-contrasena-verificacion" placeholder="Ingrese su contraseña"
                    style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;"
                    onkeypress="if(event.key === 'Enter') verificarContrasena();">
            </div>

            <div id="mensaje-error-verificacion"
                style="display: none; background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;">
                Contraseña incorrecta. Intentos restantes: <span id="intentos-restantes">3</span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="verificarContrasena()" class="btn btn-primary"
                    style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-check"></i> Verificar
                </button>
                <button onclick="cerrarModalVerificacion()" class="btn btn-secondary"
                    style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Actividad Extra (cronómetro) -->
    <div id="modal-actividad-extra"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 400px;">
            <i class="fas fa-stopwatch" style="font-size: 48px; color: #6610f2; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">Actividad Extra</h3>
            <p style="margin: 0 0 20px 0; color: #666;">En progreso...</p>
            <div style="font-size: 32px; font-weight: 700; color: #007bff; margin-bottom: 20px;">
                <span id="tiempo-actividad-extra">00:00:00</span>
            </div>
            <button onclick="finalizarActividadExtra()" class="btn btn-primary"
                style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-stop"></i> Finalizar Actividad
            </button>
        </div>
    </div>

    <!-- Modal de Búsqueda de Cliente -->
    <div id="modal-busqueda-cliente"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10003; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #007bff;">
                    <i class="fas fa-search"></i> Buscar Cliente
                </h3>
                <button onclick="cerrarModalBusqueda()"
                    style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="busqueda-cliente-input"
                    style="display: block; margin-bottom: 8px; color: #666; font-size: 14px;">CC o Celular:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="busqueda-cliente-input" placeholder="Ingrese CC o celular..."
                        style="flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;"
                        onkeypress="if(event.key === 'Enter') buscarClienteDesdeModal();">
                    <button onclick="buscarClienteDesdeModal()"
                        style="padding: 12px 20px; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <!-- Resultados de búsqueda -->
            <div id="resultados-busqueda-cliente"
                style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; background: #f8f9fa;">
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-search"></i>
                    <p>Ingrese CC o celular para buscar</p>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Cache busting para asegurar que el navegador cargue los JS actualizados
    $v_nav = @filemtime(__DIR__ . '/../assets/js/navbar-busqueda-cliente.js') ?: (defined('APP_VERSION') ? APP_VERSION : time());
    $v_gestionar = @filemtime(__DIR__ . '/../assets/js/asesor-gestionar.js') ?: (defined('APP_VERSION') ? APP_VERSION : time());
    $v_tiempos = @filemtime(__DIR__ . '/../assets/js/asesor-tiempos.js') ?: (defined('APP_VERSION') ? APP_VERSION : time());
    ?>
    <script src="assets/js/navbar-busqueda-cliente.js?v=<?php echo urlencode((string)$v_nav); ?>"></script>
    <script src="assets/js/asesor-gestionar.js?v=<?php echo urlencode((string)$v_gestionar); ?>"></script>
    <script src="assets/js/asesor-tiempos.js?v=<?php echo urlencode((string)$v_tiempos); ?>"></script>

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
                        const celular = comercio.CEL || comercio.CELULAR || comercio['TEL 1'] || comercio.cel || 'N/A';
                        const nb = comercio.NOMBRE_BASE || comercio.nombre_base || '';

                        html += `
                            <div style="padding: 15px; border-bottom: 1px solid #dee2e6; cursor: pointer;" 
                                 onclick="gestionarClienteDesdeModal('${comercioId}')">
                                <div style="font-weight: 600; color: #333; margin-bottom: 5px;">
                                    ${nombreCliente}
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    ${nb ? `<div><i class="fas fa-database"></i> Base: <strong style="color:#0056b3;">${nb}</strong></div>` : ''}
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
            // Redirigir a la vista de gestión (recarga la página)
            window.location.href = `index.php?action=asesor_gestionar&cliente_id=${clienteId}`;
        }

        function volverClientes() {
            window.location.href = 'index.php?action=asesor_dashboard#tab-clientes';
        }

        // Función global para ser llamada desde asesor-gestionar.js después de guardar
        window.mostrarBotonesDespuesGuardar = mostrarBotonesDespuesGuardar;
        if (typeof prepararNuevaGestion === 'function') {
            window.prepararNuevaGestion = prepararNuevaGestion;
        }
    </script>

    <!-- WebRTC Softphone Integration -->
    <?php
    if ($mostrar_softphone):
        // Incluir configuración WebRTC
        // IMPORTANTE: Usar require_once para evitar redefiniciones, pero forzar recarga si es necesario
        $config_path = __DIR__ . '/../config/asterisk.php';
        if (file_exists($config_path)) {
            // Limpiar opcache si está habilitado (para desarrollo)
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($config_path, true);
            }
            require_once $config_path;
        } else {
            // Fallback: intentar con ruta relativa
            require_once __DIR__ . '/../config/asterisk.php';
        }
        
        // Verificar que las constantes estén definidas
        if (!defined('ASTERISK_SIP_DOMAIN')) {
            error_log('[SOFTPHONE ERROR] ASTERISK_SIP_DOMAIN no está definido después de incluir config/asterisk.php');
        }
        if (!defined('ASTERISK_WSS_SERVER')) {
            error_log('[SOFTPHONE ERROR] ASTERISK_WSS_SERVER no está definido después de incluir config/asterisk.php');
        }
        
        $webrtc_config = getWebRTCConfig();
        
        // Debug: Verificar valores antes de pasarlos a JavaScript
        error_log('[SOFTPHONE DEBUG] Configuración obtenida:');
        error_log('  - sip_domain: ' . ($webrtc_config['sip_domain'] ?? 'NO DEFINIDO'));
        error_log('  - wss_server: ' . ($webrtc_config['wss_server'] ?? 'NO DEFINIDO'));

        // Usar datos frescos de la base de datos si están disponibles (prioridad sobre sesión)
        // Prioridad: extension_telefono y clave_extension (nuevos campos), luego extension y sip_password (legacy)
        $extension = $usuario_data['extension_telefono'] ?? $usuario_data['extension'] ?? $_SESSION['usuario_extension'] ?? '';
        $sip_password = $usuario_data['clave_extension'] ?? $usuario_data['sip_password'] ?? $_SESSION['usuario_sip_password'] ?? '';
        
        // CRÍTICO: Limpiar la contraseña de espacios en blanco al inicio y final
        $sip_password = trim($sip_password);
        
        // Determinar si estamos en red local (para configuración de ICE)
        // Por defecto, si no hay servidores STUN configurados, asumimos que es LAN
        $is_local_network = empty($webrtc_config['iceServers']) || 
                           (is_array($webrtc_config['iceServers']) && count($webrtc_config['iceServers']) === 0);
        
        // DEBUG: Verificar la contraseña RAW antes de pasarla a JavaScript
        if (defined('ASTERISK_DEBUG_MODE') && ASTERISK_DEBUG_MODE) {
            error_log('[SOFTPHONE DEBUG] ===== VERIFICACIÓN DE CONTRASEÑA SIP =====');
            error_log('  - sip_password RAW (antes de trim): ' . var_export($usuario_data['sip_password'] ?? $_SESSION['usuario_sip_password'] ?? 'NO DEFINIDA', true));
            error_log('  - sip_password DESPUÉS de trim: ' . var_export($sip_password, true));
            error_log('  - Longitud de sip_password: ' . strlen($sip_password));
            error_log('  - sip_password en hex: ' . bin2hex($sip_password));
            error_log('  - sip_password esperada en PBX: Inicio2018');
            error_log('  - ¿Coinciden?: ' . ($sip_password === 'Inicio2018' ? 'SÍ' : 'NO'));
            if ($sip_password !== 'Inicio2018') {
                error_log('  - DIFERENCIA DETECTADA:');
                error_log('    * Longitud esperada: ' . strlen('Inicio2018'));
                error_log('    * Longitud actual: ' . strlen($sip_password));
                // Comparar carácter por carácter
                $esperada = 'Inicio2018';
                for ($i = 0; $i < max(strlen($esperada), strlen($sip_password)); $i++) {
                    $char_esperado = isset($esperada[$i]) ? $esperada[$i] : '[FIN]';
                    $char_actual = isset($sip_password[$i]) ? $sip_password[$i] : '[FIN]';
                    if ($char_esperado !== $char_actual) {
                        error_log("    * Posición $i: Esperado '$char_esperado' (ASCII " . ord($char_esperado) . ") vs Actual '$char_actual' (ASCII " . (isset($sip_password[$i]) ? ord($sip_password[$i]) : 'N/A') . ")");
                    }
                }
            }
            error_log('[SOFTPHONE DEBUG] ===========================================');
        }
        ?>
        <script src="assets/js/sip.min.js"></script>
        <script src="assets/js/softphone-web.js"></script>
        <script>
            // Configuración del softphone
            // DEBUG: Verificar valores desde PHP antes de crear el objeto
            <?php
            // Debug: Verificar valores directamente desde PHP
            error_log('[SOFTPHONE DEBUG] ASTERISK_SIP_DOMAIN definido: ' . (defined('ASTERISK_SIP_DOMAIN') ? ASTERISK_SIP_DOMAIN : 'NO DEFINIDO'));
            error_log('[SOFTPHONE DEBUG] webrtc_config[sip_domain]: ' . ($webrtc_config['sip_domain'] ?? 'NO DEFINIDO'));
            error_log('[SOFTPHONE DEBUG] webrtc_config[wss_server]: ' . ($webrtc_config['wss_server'] ?? 'NO DEFINIDO'));
            ?>
            const webrtcConfig = {
                wss_server: '<?php echo $webrtc_config['wss_server']; ?>',
                sip_domain: '<?php echo $webrtc_config['sip_domain']; ?>',
                extension: '<?php echo htmlspecialchars($extension, ENT_QUOTES, 'UTF-8'); ?>',
                password: '<?php echo addslashes($sip_password); ?>', // Usar addslashes en lugar de htmlspecialchars para contraseñas
                display_name: '<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Asesor', ENT_QUOTES, 'UTF-8'); ?>',
                preferredRtpPort: <?php echo (int) ($webrtc_config['preferred_rtp_port'] ?? 10000); ?>,
                iceServers: <?php
                $iceServers = $webrtc_config['iceServers'] ?? [];
                if (!is_array($iceServers) || empty($iceServers)) {
                    $iceServers = [];
                }
                echo json_encode($iceServers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ?>,
                is_local_network: <?php echo $is_local_network ? 'true' : 'false'; ?>,
                debug_mode: <?php echo $webrtc_config['debug_mode'] ? 'true' : 'false'; ?>
            };

            // Logs de depuración solo con debug_mode (sin exponer contraseña en el navegador)
            if (webrtcConfig.debug_mode) {
                console.log('🔍 [DEBUG] Configuración del softphone:');
                console.log('  - Extension:', webrtcConfig.extension || 'VACIA');
                console.log('  - Password definida:', webrtcConfig.password ? 'sí' : 'no');
                console.log('  - WSS Server:', webrtcConfig.wss_server || 'VACIO');
                console.log('  - SIP Domain:', webrtcConfig.sip_domain || 'VACIO');
            }

            // Verificar que los valores críticos no estén vacíos
            if (!webrtcConfig.extension || webrtcConfig.extension.trim() === '') {
                console.error('❌ [ERROR CRÍTICO] La extensión está vacía. Verifica la base de datos.');
            }
            if (!webrtcConfig.password || webrtcConfig.password.trim() === '') {
                console.error('❌ [ERROR CRÍTICO] La contraseña SIP está vacía. Verifica la base de datos.');
            }
            if (!webrtcConfig.wss_server || webrtcConfig.wss_server.trim() === '') {
                console.error('❌ [ERROR CRÍTICO] El servidor WSS está vacío. Verifica config/asterisk.php');
            }
            if (!webrtcConfig.sip_domain || webrtcConfig.sip_domain.trim() === '') {
                console.error('❌ [ERROR CRÍTICO] El dominio SIP está vacío. Verifica config/asterisk.php');
            }

            // Esperar a SIP.js y softphone-web.js (setTimeout recurrente: menos carga que setInterval cada 100ms)
            function inicializarSoftphoneConVerificacion() {
                let intentos = 0;
                const maxIntentos = 100;
                const delayMs = 50;

                function siguienteIntento() {
                    intentos++;

                    const sipjsListo = typeof SIP !== 'undefined' &&
                        typeof SIP.UserAgent !== 'undefined';

                    const softphoneListo = typeof WebRTCSoftphone !== 'undefined';

                    if (sipjsListo && softphoneListo) {
                        if (webrtcConfig.debug_mode) {
                            console.log('✅ Todos los componentes listos, inicializando softphone...');
                        }

                        try {
                            const container = document.getElementById('webrtc-softphone');
                            if (!container) {
                                console.warn('⚠️ [WebRTC Softphone] Contenedor del softphone no encontrado. El usuario puede no tener extensión asignada.');
                                return;
                            }

                            if (webrtcConfig.debug_mode) {
                                console.log('🔄 [WebRTC Softphone] Inicializando softphone...');
                                console.log('📝 [WebRTC Softphone] Config (sin credenciales):', {
                                    extension: webrtcConfig.extension || 'VACIA',
                                    password_configurada: !!webrtcConfig.password,
                                    wss_server: webrtcConfig.wss_server,
                                    sip_domain: webrtcConfig.sip_domain,
                                    debug_mode: webrtcConfig.debug_mode
                                });
                            }

                            // Validar que la extensión y password no estén vacías
                            if (!webrtcConfig.extension || webrtcConfig.extension.trim() === '') {
                                console.error('❌ [WebRTC Softphone] Error: Extension está vacía');
                                alert('Error: La extensión SIP no está configurada. Contacta al administrador.');
                                return;
                            }

                            if (!webrtcConfig.password || webrtcConfig.password.trim() === '') {
                                console.error('❌ [WebRTC Softphone] Error: Password está vacía');
                                alert('Error: La contraseña SIP no está configurada. Contacta al administrador.');
                                return;
                            }

                            window.webrtcSoftphone = new WebRTCSoftphone(webrtcConfig);
                            if (webrtcConfig.debug_mode) {
                                console.log('✅ [WebRTC Softphone] Inicializado');
                                console.log('📞 [WebRTC Softphone] Extensión:', webrtcConfig.extension);
                                console.log('💡 Tip: verificarEstadoSoftphone() en consola');
                            }

                            window.verificarEstadoSoftphone = function () {
                                if (window.webrtcSoftphone) {
                                    const c = window.webrtcSoftphone.config || {};
                                    console.log('📊 [WebRTC Softphone] Estado:', {
                                        extension: c.extension,
                                        sip_domain: c.sip_domain,
                                        wss_server: c.wss_server,
                                        isRegistered: window.webrtcSoftphone.isRegistered,
                                        isConnected: window.webrtcSoftphone.isConnected,
                                        status: window.webrtcSoftphone.status,
                                        transportState: window.webrtcSoftphone.userAgent?.transport?.state,
                                        registrationState: window.webrtcSoftphone.userAgent?.registration?.state
                                    });
                                } else {
                                    console.warn('⚠️ [WebRTC Softphone] El softphone no está inicializado');
                                }
                            };

                        } catch (error) {
                            console.error('❌ [WebRTC Softphone] Error al inicializar softphone:', error);
                            console.error('❌ [WebRTC Softphone] Stack:', error.stack);
                            if (webrtcConfig.debug_mode) {
                                alert('Error al inicializar el softphone: ' + error.message);
                            }
                        }

                    } else {
                        if (webrtcConfig.debug_mode && intentos % 10 === 0) {
                            console.log(`⏳ Esperando componentes... (${intentos}/${maxIntentos})`);
                            console.log('  SIP.js listo:', sipjsListo);
                            console.log('  WebRTCSoftphone listo:', softphoneListo);
                        }

                        if (intentos >= maxIntentos) {
                            console.error('❌ Timeout esperando componentes del softphone');
                            if (webrtcConfig.debug_mode) {
                                alert('El softphone no se pudo inicializar. Por favor, recarga la página.');
                            }
                        } else {
                            setTimeout(siguienteIntento, delayMs);
                        }
                    }
                }

                siguienteIntento();
            }

            // Iniciar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', inicializarSoftphoneConVerificacion);
            } else {
                inicializarSoftphoneConVerificacion();
            }

            // Función global para llamar desde click-to-call
            function llamarDesdeWebRTC(numero) {
                if (typeof window.webrtcSoftphone !== 'undefined' &&
                    window.webrtcSoftphone !== null &&
                    window.webrtcSoftphone.callNumber) {
                    window.webrtcSoftphone.callNumber(numero);
                } else {
                    console.warn('Softphone no disponible. Por favor, espera a que se inicialice.');
                }
            }
        </script>
        <style>
            /* Estilos básicos para el softphone inline */
            .seccion-softphone-wrapper {
                background: white;
                border-radius: 8px;
                padding: 0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                border: 1px solid #dee2e6;
                margin-bottom: 20px;
                overflow: hidden;
                max-width: 100%;
            }

            .webrtc-softphone-panel.inline {
                position: relative !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
            }

            .webrtc-softphone-panel.inline.hidden {
                display: none !important;
            }

            .webrtc-softphone-panel.inline .softphone-header {
                background: #007bff;
                color: white;
                padding: 10px 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 8px 8px 0 0;
            }

            .webrtc-softphone-panel.inline .softphone-header h3 {
                margin: 0;
                color: white;
                font-size: 16px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .webrtc-softphone-panel.inline .softphone-body {
                padding: 15px;
                background: white;
            }

            .webrtc-softphone-panel.inline .softphone-status {
                margin-bottom: 15px;
                text-align: center;
            }

            .webrtc-softphone-panel.inline .status-indicator {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-size: 13px;
            }

            .webrtc-softphone-panel.inline .status-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                display: inline-block;
            }

            .webrtc-softphone-panel.inline .status-dot.connected {
                background: #28a745;
            }

            .webrtc-softphone-panel.inline .status-dot.disconnected {
                background: #dc3545;
            }

            .webrtc-softphone-panel.inline .status-dot.connecting {
                background: #ffc107;
                animation: pulse 1.5s infinite;
            }

            .webrtc-softphone-panel.inline .status-dot.in-call {
                background: #007bff;
                animation: pulse 1.5s infinite;
            }

            @keyframes pulse {

                0%,
                100% {
                    opacity: 1;
                }

                50% {
                    opacity: 0.5;
                }
            }

            .webrtc-softphone-panel.inline .number-display {
                background: #f8f9fa;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                padding: 12px;
                text-align: center;
                font-size: 18px;
                font-weight: 600;
                color: #333;
                margin-bottom: 15px;
                min-height: 30px;
            }

            .webrtc-softphone-panel.inline .dialpad {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin-bottom: 15px;
            }

            .webrtc-softphone-panel.inline .dialpad-btn {
                background: white;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                padding: 15px 5px;
                font-size: 18px;
                font-weight: 600;
                color: #333;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 50px;
            }

            .webrtc-softphone-panel.inline .dialpad-btn:hover {
                background: #f8f9fa;
                border-color: #007bff;
            }

            .webrtc-softphone-panel.inline .dialpad-btn-letter {
                font-size: 10px;
                color: #666;
                margin-top: 2px;
            }

            .webrtc-softphone-panel.inline .action-buttons {
                display: flex;
                gap: 8px;
                margin-bottom: 15px;
            }

            .webrtc-softphone-panel.inline .action-btn {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                transition: all 0.2s;
            }

            .webrtc-softphone-panel.inline .delete-btn {
                background: #dc3545;
                color: white;
            }

            .webrtc-softphone-panel.inline .delete-btn:hover {
                background: #c82333;
            }

            .webrtc-softphone-panel.inline .call-btn {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
            }

            .webrtc-softphone-panel.inline .call-btn:hover {
                background: linear-gradient(135deg, #218838, #1ea080);
            }

            .webrtc-softphone-panel.inline .hangup-btn {
                background: #dc3545;
                color: white;
            }

            .webrtc-softphone-panel.inline .hangup-btn:hover {
                background: #c82333;
            }

            .webrtc-softphone-panel.inline .call-info {
                background: #f8f9fa;
                border-radius: 6px;
                padding: 12px;
                margin-bottom: 15px;
                text-align: center;
                display: none;
            }

            .webrtc-softphone-panel.inline .call-info.active {
                display: block;
            }

            .webrtc-softphone-panel.inline .call-controls {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .webrtc-softphone-panel.inline .control-btn {
                background: white;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                padding: 10px;
                font-size: 12px;
                font-weight: 600;
                color: #333;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                transition: all 0.2s;
            }

            .webrtc-softphone-panel.inline .control-btn:hover {
                background: #f8f9fa;
                border-color: #007bff;
            }

            .webrtc-softphone-panel.inline .control-btn.active {
                background: #007bff;
                color: white;
                border-color: #007bff;
            }

            .webrtc-softphone-panel.inline .conference-btn {
                background: linear-gradient(135deg, #17a2b8, #138496);
                color: white;
                border-color: #17a2b8;
            }

            .webrtc-softphone-panel.inline .conference-btn:hover {
                background: linear-gradient(135deg, #138496, #117a8b);
                border-color: #138496;
            }

            .webrtc-softphone-panel.inline .transfer-btn {
                background: linear-gradient(135deg, #ffc107, #e0a800);
                color: #333;
                border-color: #ffc107;
            }

            .webrtc-softphone-panel.inline .transfer-btn:hover {
                background: linear-gradient(135deg, #e0a800, #d39e00);
                border-color: #e0a800;
            }

            /* Estilos para modales */
            .softphone-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                align-items: center;
                justify-content: center;
            }

            .softphone-modal .modal-content {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
                max-height: 90vh;
                overflow: auto;
            }

            .softphone-modal .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #dee2e6;
            }

            .softphone-modal .modal-header h4 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #333;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .softphone-modal .modal-close {
                background: none;
                border: none;
                font-size: 24px;
                color: #666;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: all 0.2s;
            }

            .softphone-modal .modal-close:hover {
                background: #f8f9fa;
                color: #333;
            }

            .softphone-modal .modal-body {
                padding: 20px;
            }

            .softphone-modal .modal-body p {
                margin: 0 0 15px 0;
                color: #666;
                font-size: 14px;
            }

            .softphone-modal .modal-input {
                width: 100%;
                padding: 12px;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                font-size: 16px;
                margin-bottom: 20px;
                box-sizing: border-box;
            }

            .softphone-modal .modal-input:focus {
                outline: none;
                border-color: #007bff;
            }

            .softphone-modal .modal-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }

            .softphone-modal .modal-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s;
            }

            .softphone-modal .modal-btn-primary {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
            }

            .softphone-modal .modal-btn-primary:hover {
                background: linear-gradient(135deg, #218838, #1ea080);
            }

            .softphone-modal .modal-btn-secondary {
                background: #6c757d;
                color: white;
            }

            .softphone-modal .modal-btn-secondary:hover {
                background: #5a6268;
            }
        </style>
    <?php endif; ?>

</body>

</html>