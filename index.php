<?php
/**
 * Archivo principal del sistema IPS
 * Maneja las rutas y controladores
 */

// Incluir configuración principal
require_once 'config.php';
require_once 'includes/csv_helper.php';

// Incluir controladores
require_once 'controller/AuthController.php';
require_once 'controller/AdminController.php';
require_once 'controller/CoordinadorController.php';
require_once 'controller/AsesorController.php';
require_once 'controller/BaseDatosController.php';

/**
 * Función helper para cargar modelos
 */
function cargarModelos($modelos) {
    foreach ($modelos as $modelo) {
        require_once "models/{$modelo}.php";
    }
}

// Obtener la acción solicitada
$action = $_GET['action'] ?? 'login';

// Crear instancia del controlador de autenticación
$authController = new AuthController();

// Manejar las diferentes acciones
switch ($action) {
    case 'login':
        $authController->login();
        break;
        
    case 'logout':
        $authController->logout();
        break;
        
    case 'admin_dashboard':
    case 'dashboard':
        // Verificar autenticación y rol de administrador
        $authController->requerirRol('administrador');
        
        // Obtener datos del usuario actual
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Crear instancia del controlador de administrador
        $adminController = new AdminController();
        
        // Obtener datos usando el controlador
        $usuarios = $adminController->obtenerUsuarios();
        $estadisticas = $adminController->obtenerEstadisticas();
        $coordinadores = $adminController->obtenerCoordinadores();
        $asignaciones = $adminController->obtenerAsignaciones();
        
        // Incluir la vista del dashboard
        include 'views/admin_dashboard.php';
        break;
        
    case 'coordinador_dashboard':
        $authController->requerirRol('coordinador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Crear instancia del controlador de coordinador
        $coordinadorController = new CoordinadorController();
        
        // Obtener datos usando el controlador
        $asesores = $coordinadorController->obtenerAsesores($usuario_actual['cedula']);
        $estadisticas = $coordinadorController->obtenerEstadisticas($usuario_actual['cedula']);
        
        // Asegurar que $asesores sea siempre un array
        if (!is_array($asesores)) {
            $asesores = [];
        }
        
        // Incluir la vista del dashboard de coordinador
        include 'views/Coord_dashboard.php';
        break;
        
    case 'coordinador_gestion':
        $authController->requerirRol('coordinador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Crear instancia del controlador de coordinador
        $coordinadorController = new CoordinadorController();
        
        // Obtener datos para gestión
        $asesores = $coordinadorController->obtenerAsesores($usuario_actual['cedula']);
        
        // Incluir la vista de gestión
        include 'views/Coord_gestion.php';
        break;
        
    case 'coordinador_tareas':
        $authController->requerirRol('coordinador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Crear instancia del controlador de coordinador
        $coordinadorController = new CoordinadorController();
        
        // Obtener tareas
        $tareas = $coordinadorController->obtenerTareas($usuario_actual['cedula']);
        
        echo "<h1>Tareas del Coordinador</h1>";
        echo "<p>Bienvenido, " . htmlspecialchars($usuario_actual['nombre_completo']) . "</p>";
        echo "<p>Tareas asignadas: " . count($tareas) . "</p>";
        echo "<a href='index.php?action=coordinador_dashboard'>Volver al Dashboard</a>";
        break;
        
    case 'coordinador_exporte':
        $authController->requerirRol('coordinador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Crear instancia del controlador de coordinador
        $coordinadorController = new CoordinadorController();
        
        // Obtener estadísticas para la vista de exporte
        $estadisticas = $coordinadorController->obtenerEstadisticas($usuario_actual['cedula']);
        
        // Incluir la vista de exporte
        include 'views/coord_export.php';
        break;
        
    case 'generar_reporte':
        $authController->requerirRol('coordinador');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST']);
            exit();
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $tipo = $input['tipo'] ?? '';
        $fecha_inicio = $input['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin = $input['fecha_fin'] ?? date('Y-m-d');
        if ($tipo !== 'tmo') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tipo de reporte inválido']);
            exit();
        }
        try {
            // PASO 1: Limpiar cualquier output previo y desactivar buffering
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $conn = getDBConnection();
            
            // Función auxiliar para convertir segundos a formato H:MM:SS
            $formatearTiempo = function($segundos) {
                if (!$segundos || $segundos <= 0) return '0:00:00';
                $horas = floor($segundos / 3600);
                $minutos = floor(($segundos % 3600) / 60);
                $seg = $segundos % 60;
                return sprintf('%d:%02d:%02d', $horas, $minutos, $seg);
            };
            
            // Consultar sesiones con nombre del asesor
            $sql_tiempos = "SELECT 
                                t.id as sesion_id,
                                t.asesor_cedula, 
                                u.nombre_completo as asesor_nombre,
                                t.fecha,
                                t.hora_inicio_sesion,
                                t.hora_fin_sesion,
                                t.tiempo_total_sesion,
                                t.tiempo_activo,
                                t.estado
                            FROM tiempos t
                            LEFT JOIN usuarios u ON CAST(t.asesor_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                            WHERE t.fecha BETWEEN ? AND ?
                            ORDER BY t.fecha, t.hora_inicio_sesion";
            $stmt_t = $conn->prepare($sql_tiempos);
            $stmt_t->execute([$fecha_inicio, $fecha_fin]);
            $sesiones = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

            // Consultar todas las pausas relacionadas a esas sesiones
            $sesion_ids = array_column($sesiones, 'sesion_id');
            $pausas_por_sesion = [];
            if (!empty($sesion_ids)) {
                $placeholders = implode(',', array_fill(0, count($sesion_ids), '?'));
                $sql_pausas = "SELECT 
                                    sesion_id,
                                    tipo_pausa,
                                    hora_inicio,
                                    hora_fin,
                                    duracion_segundos,
                                    estado
                                FROM pausas
                                WHERE sesion_id IN ($placeholders)
                                ORDER BY hora_inicio";
                $stmt_p = $conn->prepare($sql_pausas);
                $stmt_p->execute($sesion_ids);
                $todas_pausas = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
                
                // Agrupar pausas por sesion_id
                foreach ($todas_pausas as $pausa) {
                    $pausas_por_sesion[$pausa['sesion_id']][] = $pausa;
                }
            }
            
            // Calcular tiempo total de pausas por sesión para validar tiempo_activo
            $tiempo_pausas_por_sesion = [];
            foreach ($pausas_por_sesion as $sesion_id => $pausas) {
                $tiempo_pausas_por_sesion[$sesion_id] = array_sum(array_column($pausas, 'duracion_segundos'));
            }

            // PASO 2: Configurar headers para descarga ANTES de cualquier output
            $filename = 'reporte_tmo_' . $fecha_inicio . '_a_' . $fecha_fin . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            
            // Abrir output stream
            $output = fopen('php://output', 'w');
            
            // PASO 3: Escribir BOM UTF-8 para Excel (sin espacios ni saltos de línea antes)
            fwrite($output, pack('C*', 0xEF, 0xBB, 0xBF));
            
            // Encabezados del CSV
            $encabezados = [
                'Fecha',
                'Hora Inicio Sesion',
                'Hora Fin Sesion',
                'Asesor',
                'Tiempo Sesion',
                'Hora',
                'Motivo',
                'Tiempo (seg)',
                'Tiempo'
            ];
            
            // PASO 4: Escribir encabezados usando fputcsv con delimitador punto y coma para Excel en español
            fputcsv($output, $encabezados, ';', '"', '\\');
            
            // Procesar cada sesión y sus pausas
            foreach ($sesiones as $sesion) {
                $sesion_id = $sesion['sesion_id'];
                $pausas = $pausas_por_sesion[$sesion_id] ?? [];
                
                // CORRECCIÓN: Tiempo de Sesión es el tiempo total transcurrido desde inicio hasta fin (cronómetro)
                // NO se restan las pausas - es simplemente: hora_fin - hora_inicio (o NOW - hora_inicio si está activa)
                // Es un cronómetro que se inicia al abrir sesión y se detiene al cerrarla
                $es_sesion_activa = empty($sesion['hora_fin_sesion']);
                $tiempo_sesion = 0;
                
                // SIEMPRE calcular tiempo de sesión desde hora_inicio hasta hora_fin (o NOW si está activa)
                if (!empty($sesion['hora_inicio_sesion']) && !empty($sesion['fecha'])) {
                    if (!empty($sesion['hora_fin_sesion'])) {
                        // Sesión finalizada: calcular desde inicio hasta fin
                        $fecha_inicio = $sesion['fecha'] . ' ' . $sesion['hora_inicio_sesion'];
                        $fecha_fin = $sesion['fecha'] . ' ' . $sesion['hora_fin_sesion'];
                        $inicio_timestamp = strtotime($fecha_inicio);
                        $fin_timestamp = strtotime($fecha_fin);
                        if ($inicio_timestamp && $fin_timestamp && $fin_timestamp > $inicio_timestamp) {
                            $tiempo_sesion = $fin_timestamp - $inicio_timestamp;
                        }
                    } else {
                        // Sesión activa: SIEMPRE calcular desde inicio hasta ahora (time() del servidor)
                        // Esto asegura que el tiempo sea preciso en tiempo real
                        $fecha_inicio = $sesion['fecha'] . ' ' . $sesion['hora_inicio_sesion'];
                        $inicio_timestamp = strtotime($fecha_inicio);
                        if ($inicio_timestamp) {
                            $tiempo_sesion = time() - $inicio_timestamp;
                        }
                    }
                }
                
                // Si no se pudo calcular, usar el valor de BD como fallback
                if ($tiempo_sesion == 0) {
                    $tiempo_sesion = $sesion['tiempo_total_sesion'] ?? 0;
                }
                
                // El tiempo de sesión es simplemente el tiempo transcurrido (sin restar pausas)
                // Es como un cronómetro que cuenta desde inicio hasta fin
                
                // Si tiene pausas, crear una fila por cada pausa
                if (!empty($pausas)) {
                    foreach ($pausas as $pausa) {
                        // Extraer solo la hora (HH:MM:SS) de datetime
                        $hora_pausa = $pausa['hora_inicio'] ? date('H:i:s', strtotime($pausa['hora_inicio'])) : '';
                        $tiempo_formato = $formatearTiempo($pausa['duracion_segundos'] ?? 0);
                        
                        // Mapear tipo_pausa a texto legible
                        $motivo = '';
                        $tipo = strtolower($pausa['tipo_pausa'] ?? '');
                        switch($tipo) {
                            case 'break': $motivo = 'Break'; break;
                            case 'almuerzo': $motivo = 'Almuerzo'; break;
                            case 'bano': $motivo = 'Bano'; break;
                            case 'mantenimiento': $motivo = 'Mantenimiento'; break;
                            case 'pausa_activa': $motivo = 'Pausa Activa'; break;
                            case 'actividad_extra': $motivo = 'Actividad Extra'; break;
                            case 'personal': $motivo = 'Personal'; break;
                            default: $motivo = $tipo;
                        }
                        
                        // PASO 5: Preparar fila con codificación correcta para campos de texto
                        $fila_pausa = [
                            (string)date('d/m/Y', strtotime($sesion['fecha'])),
                            (string)($sesion['hora_inicio_sesion'] ?? ''),
                            (string)($sesion['hora_fin_sesion'] ?? ''),
                            mb_convert_encoding($sesion['asesor_nombre'] ?? $sesion['asesor_cedula'] ?? '', 'UTF-8', 'UTF-8'),
                            (string)$formatearTiempo($tiempo_sesion),
                            (string)$hora_pausa,
                            mb_convert_encoding($motivo, 'UTF-8', 'UTF-8'),
                            (string)($pausa['duracion_segundos'] ?? 0),
                            (string)$tiempo_formato
                        ];
                        
                        // PASO 4: Escribir fila usando fputcsv con delimitador punto y coma para Excel en español
                        fputcsv($output, $fila_pausa, ';', '"', '\\');
                    }
                } else {
                    // Si no tiene pausas, mostrar solo la sesión
                    $fila_sesion = [
                        (string)date('d/m/Y', strtotime($sesion['fecha'])),
                        (string)($sesion['hora_inicio_sesion'] ?? ''),
                        (string)($sesion['hora_fin_sesion'] ?? ''),
                        mb_convert_encoding($sesion['asesor_nombre'] ?? $sesion['asesor_cedula'] ?? '', 'UTF-8', 'UTF-8'),
                        (string)$formatearTiempo($tiempo_sesion),
                        '',
                        '',
                        '',
                        ''
                    ];
                    
                    // PASO 4: Escribir fila usando fputcsv con delimitador punto y coma para Excel en español
                    fputcsv($output, $fila_sesion, ';', '"', '\\');
                }
            }
            
            fclose($output);
            exit();
        } catch (Throwable $e) {
            // PASO 6: Manejo de errores mejorado
            error_log("Error generando reporte TMO: " . $e->getMessage());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => 'Error generando reporte: ' . $e->getMessage()]);
            exit();
        }
        break;

    case 'generar_reporte_gestiones':
        $authController->requerirRol('coordinador');
        
        try {
            // PASO 1: Limpiar cualquier output previo y desactivar buffering
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
            $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
            
            $conn = getDBConnection();
            
            // Función auxiliar para convertir segundos a formato H:MM:SS
            $formatearTiempo = function($segundos) {
                if (!$segundos || $segundos <= 0) return '0:00:00';
                $horas = floor($segundos / 3600);
                $minutos = floor(($segundos % 3600) / 60);
                $seg = $segundos % 60;
                return sprintf('%d:%02d:%02d', $horas, $minutos, $seg);
            };
            
            // Función para mapear nivel 1 a texto
            $mapearNivel1 = function($nivel1) {
                if (empty($nivel1) || trim($nivel1) === '') return 'Sin tipificar';
                $nivel1 = trim($nivel1);
                $mapa = [
                    // Nueva estructura
                    'llamada_saliente' => 'LLAMADA SALIENTE',
                    'whatsapp' => 'WHATSAPP',
                    'email' => 'EMAIL',
                    'recibir_llamada' => 'RECIBIR LLAMADA',
                    // Valores antiguos (compatibilidad)
                    'hacer_llamada' => 'HACER LLAMADA',
                    'interaccion' => 'INTERACCION',
                    'INTERACCION' => 'INTERACCION',
                    'HACER LLAMADA' => 'HACER LLAMADA',
                    '1' => 'CONTACTADO',
                    '2' => 'NO CONTACTADO'
                ];
                return $mapa[$nivel1] ?? ($nivel1 ?: 'Sin tipificar');
            };
            
            // Función para mapear nivel 2 a texto
            $mapearNivel2 = function($nivel2, $nivel1) {
                $mapa = [
                    // LLAMADA SALIENTE
                    '1.0' => 'YA PAGO',
                    '1.1' => 'ACUERDO DE PAGO',
                    '1.2' => 'RECORDATORIO',
                    '1.3' => 'VOLUNTAD DE PAGO',
                    '2.0' => 'LOCALIZADO SIN ACUERDO',
                    '3.0' => 'FALLECIDO',
                    '4.0' => 'NO CONTACTO',
                    // WHATSAPP
                    'ws_1.0' => 'YA PAGO',
                    'ws_1.1' => 'ACUERDO DE PAGO',
                    'ws_1.2' => 'RECORDATORIO',
                    'ws_1.3' => 'VOLUNTAD DE PAGO',
                    'ws_2.0' => 'LOCALIZADO SIN ACUERDO',
                    'ws_3.0' => 'FALLECIDO',
                    'ws_4.0' => 'NO CONTACTO',
                    // EMAIL
                    'em_1.0' => 'NO ENTREGADO',
                    'em_1.1' => 'ENTREGADO',
                    'em_1.2' => 'ENVIO DE MENSAJE A TITULAR',
                    // RECIBIR LLAMADA
                    'rc_1.0' => 'YA PAGO',
                    'rc_1.1' => 'ACUERDO DE PAGO',
                    'rc_1.2' => 'VOLUNTAD DE PAGO',
                    'rc_2.0' => 'LOCALIZADO SIN ACUERDO',
                    'rc_3.0' => 'FALLECIDO',
                    'rc_4.0' => 'NO CONTACTO',
                    // Valores antiguos (compatibilidad)
                    '2.1' => ($nivel1 === 'interaccion') ? 'INGRESO A PLATAFORMA / CONSULTA OFERTA' : 'Llamada no contestada',
                    '2.2' => ($nivel1 === 'interaccion') ? 'CONFIRMA QUE SI A MSG OBJETIVO' : 'Mensaje con tercero',
                    '2.3' => ($nivel1 === 'interaccion') ? 'CONFIRMA QUE NO A MSG OBJETIVO' : 'Buzón de voz',
                    '3.1' => 'RECLAMO / RENUENTE',
                    '5.0' => 'BUZON DE MENSAJE',
                    '6.0' => 'DESERTO / COLGO / NO ESCUCHA / NO ENTIENDE',
                    '6.1' => 'NO CONTESTA',
                    '7.0' => 'AQUI NO VIVE / TRABAJA / EQUIVOCADO',
                    '7.1' => 'TELEFONO DAÑADO / ERRADO',
                    '8.0' => 'FALLECIDO / OTROS'
                ];
                return $mapa[$nivel2] ?? ($nivel2 ?? '');
            };
            
            // Función para mapear nivel 3 a texto
            $mapearNivel3 = function($nivel3) {
                $mapa = [
                    // Nueva estructura
                    'pago_total' => 'PAGO TOTAL',
                    'pago_cuota' => 'PAGO CUOTA',
                    'acuerdo_pago_total' => 'ACUERDO PAGO TOTAL',
                    'acuerdo_largo_plazo' => 'ACUERDO A LARGO PLAZO',
                    'acuerdo_aprobado' => 'ACUERDO APROBADO COMITÉ',
                    'seguimiento' => 'SEGUIMIENTO NEGOCIACIÓN VIGENTE',
                    'volver_llamar' => 'VOLVER A LLAMAR',
                    'propuesta_estudio' => 'PROPUESTA EN ESTUDIO',
                    'posible_negociacion' => 'POSIBLE NEGOCIACION',
                    'no_reconoce' => 'NO RECONOCE LA OBLIGACIÓN',
                    'dificultad_pago' => 'DIFICULTAD DE PAGO',
                    'reclamacion' => 'RECLAMACIÓN',
                    'renuente' => 'RENUENTE',
                    'contesta_cuelga' => 'CONTESTA Y CUELGA',
                    'contacto_tercero' => 'CONTACTO CON TERCERO',
                    'fallecido' => 'FALLECIDO',
                    'no_contesta' => 'NO CONTESTA',
                    'buzon_mensaje' => 'BUZÓN DE MENSAJE',
                    'fuera_servicio' => 'FUERA DE SERVICIO',
                    'numero_equivocado' => 'NUMERO EQUIVOCADO',
                    'telefono_apagado' => 'TELÉFONO APAGADO',
                    'telefono_danado' => 'TELÉFONO DAÑADO',
                    'ilocalizado' => 'ILOCALIZADO',
                    'no_entregado' => 'NO ENTREGADO',
                    'entregado' => 'ENTREGADO',
                    'envio_mensaje' => 'ENVIO DE MENSAJE A TITULAR',
                    // Valores antiguos (compatibilidad)
                    '1' => 'TITULAR / ENCARGADO',
                    '2' => 'TERCERO VALIDO',
                    '3' => 'NO CONTACTO',
                    '4' => 'ILOCALIZADO'
                ];
                return $mapa[$nivel3] ?? ($nivel3 ?? '');
            };
            
            // Consultar gestiones
            $sql_gestiones = "SELECT 
                                g.id,
                                g.fecha_creacion,
                                u.nombre_completo as asesor_nombre,
                                COALESCE(c.nombre, 'N/A') as cliente_nombre,
                                COALESCE(c.cc, 'N/A') as cliente_cc,
                                g.canal_contacto,
                                g.contrato_id,
                                g.nivel1_tipo,
                                g.nivel2_clasificacion,
                                g.nivel3_detalle,
                                g.observaciones,
                                g.llamada_telefonica,
                                g.whatsapp,
                                g.correo_electronico,
                                g.sms,
                                g.correo_fisico,
                                g.mensajeria_aplicacion,
                                g.duracion_segundos,
                                g.fecha_pago,
                                g.valor_pago
                            FROM gestiones g
                            LEFT JOIN usuarios u ON CAST(g.asesor_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                            LEFT JOIN clientes c ON g.cliente_id = c.id
                            WHERE g.fecha_creacion >= ? AND g.fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)
                            ORDER BY g.fecha_creacion DESC, u.nombre_completo
                            LIMIT 50000";
            
            $stmt_gestiones = $conn->prepare($sql_gestiones);
            $stmt_gestiones->execute([$fecha_inicio, $fecha_fin]);
            $gestiones = $stmt_gestiones->fetchAll(PDO::FETCH_ASSOC);
            
            // PASO 2: Configurar headers para descarga ANTES de cualquier output
            $filename = 'reporte_gestiones_' . $fecha_inicio . '_a_' . $fecha_fin . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            
            // Abrir output stream
            $output = fopen('php://output', 'w');
            
            // PASO 3: Escribir BOM UTF-8 para Excel (sin espacios ni saltos de línea antes)
            fwrite($output, pack('C*', 0xEF, 0xBB, 0xBF));
            
            // Encabezados del CSV
            $encabezados = [
                'Fecha y Hora',
                'Asesor',
                'Cliente',
                'CC',
                'Canal de Contacto',
                'Obligación',
                'Nivel 1 - Tipo',
                'Nivel 2 - Clasificación',
                'Nivel 3 - Detalle',
                'Fecha Pago',
                'Valor Pago',
                'Canales Autorizados',
                'Duración Gestión',
                'Observaciones'
            ];
            
            // PASO 4: Escribir encabezados usando fputcsv con delimitador punto y coma para Excel en español
            fputcsv($output, $encabezados, ';', '"', '\\');
            
            // Procesar cada gestión
            foreach ($gestiones as $gestion) {
                // Mapear niveles de tipificación
                $nivel1_texto = $mapearNivel1($gestion['nivel1_tipo']);
                $nivel2_texto = $mapearNivel2($gestion['nivel2_clasificacion'], $gestion['nivel1_tipo']);
                $nivel3_texto = $mapearNivel3($gestion['nivel3_detalle']);
                
                // Construir lista de canales autorizados
                $canales = [];
                if ($gestion['llamada_telefonica'] === 'si') $canales[] = 'Llamada';
                if ($gestion['whatsapp'] === 'si') $canales[] = 'WhatsApp';
                if ($gestion['correo_electronico'] === 'si') $canales[] = 'Email';
                if ($gestion['sms'] === 'si') $canales[] = 'SMS';
                if ($gestion['correo_fisico'] === 'si') $canales[] = 'Correo Fisico';
                if ($gestion['mensajeria_aplicacion'] === 'si') $canales[] = 'Mensajeria';
                $canales_texto = !empty($canales) ? implode(' - ', $canales) : 'Ninguno';
                
                // Formatear factura/contrato
                $factura_texto = $gestion['contrato_id'];
                if ($factura_texto === 'ninguna' || empty($factura_texto)) {
                    $factura_texto = 'Ninguna';
                }
                
                // Formatear fecha de pago (formato simple sin espacios)
                $fecha_pago_texto = $gestion['fecha_pago'] ? date('d/m/Y', strtotime($gestion['fecha_pago'])) : '';
                
                // Formatear valor de pago (usar punto como separador decimal)
                $valor_pago_texto = $gestion['valor_pago'] ? number_format($gestion['valor_pago'], 2, '.', '') : '';
                
                // Formatear fecha y hora (separar fecha y hora para evitar problemas)
                $fecha_hora = '';
                if ($gestion['fecha_creacion']) {
                    $timestamp = strtotime($gestion['fecha_creacion']);
                    $fecha_hora = date('d/m/Y H:i:s', $timestamp);
                }
                
                // Formatear tiempo de duración (asegurar formato consistente)
                $duracion_texto = $formatearTiempo($gestion['duracion_segundos'] ?? 0);
                
                // PASO 5: Preparar fila con codificación correcta para campos de texto
                $fila = [
                    (string)$fecha_hora,
                    mb_convert_encoding($gestion['asesor_nombre'] ?? 'N/A', 'UTF-8', 'UTF-8'),
                    mb_convert_encoding($gestion['cliente_nombre'] ?? 'N/A', 'UTF-8', 'UTF-8'),
                    (string)($gestion['cliente_cc'] ?? 'N/A'),
                    mb_convert_encoding(ucfirst($gestion['canal_contacto'] ?? 'N/A'), 'UTF-8', 'UTF-8'),
                    mb_convert_encoding($factura_texto, 'UTF-8', 'UTF-8'),
                    mb_convert_encoding($nivel1_texto, 'UTF-8', 'UTF-8'),
                    mb_convert_encoding($nivel2_texto, 'UTF-8', 'UTF-8'),
                    mb_convert_encoding($nivel3_texto, 'UTF-8', 'UTF-8'),
                    (string)$fecha_pago_texto,
                    (string)$valor_pago_texto,
                    mb_convert_encoding($canales_texto, 'UTF-8', 'UTF-8'),
                    (string)$duracion_texto,
                    mb_convert_encoding($gestion['observaciones'] ?? '', 'UTF-8', 'UTF-8')
                ];
                
                // PASO 4: Escribir fila usando fputcsv con delimitador punto y coma para Excel en español
                fputcsv($output, $fila, ';', '"', '\\');
            }
            
            fclose($output);
            exit();
            
        } catch (Exception $e) {
            // PASO 6: Manejo de errores mejorado
            error_log("Error generando reporte de gestiones: " . $e->getMessage());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage()
            ]);
            exit();
        }
        break;
        
    case 'generar_reporte_tmo':
        $authController->requerirRol('coordinador');
        
        // Método no implementado - usar generarReporte
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Método no disponible. Use generar_reporte'
        ]);
        exit();
        break;
        
    case 'crear_tarea':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            // Recopilar datos del formulario
            $datos_tarea = [
                'coordinador_cedula' => $_SESSION['usuario_id'] ?? '',
                'asesor_cedula' => $_POST['asesor_cedula'] ?? '',
                'titulo' => $_POST['titulo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'tipo_tarea' => $_POST['tipo_tarea'] ?? 'llamada',
                'prioridad' => $_POST['prioridad'] ?? 'media',
                'fecha_limite' => $_POST['fecha_limite'] ?? null,
                'cliente_id' => $_POST['cliente_id'] ?? null,
                'base_id' => $_POST['base_id'] ?? null,
                'observaciones' => $_POST['observaciones'] ?? '',
                'valor_objetivo' => $_POST['valor_objetivo'] ?? 0.00,
                'tiempo_estimado' => $_POST['tiempo_estimado'] ?? 0
            ];
            
            // Crear la tarea
            $response = $coordinadorController->crearTarea($datos_tarea);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    case 'actualizar_tarea':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            $tarea_id = $_POST['tarea_id'] ?? '';
            $datos_actualizacion = $_POST;
            unset($datos_actualizacion['tarea_id']);
            
            // Actualizar la tarea
            $response = $coordinadorController->actualizarTarea($tarea_id, $datos_actualizacion);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    case 'eliminar_tarea':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            $tarea_id = $_POST['tarea_id'] ?? '';
            
            // Eliminar la tarea
            $response = $coordinadorController->eliminarTarea($tarea_id);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    case 'cambiar_estado_tarea':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            $tarea_id = $_POST['tarea_id'] ?? '';
            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
            $resultado = $_POST['resultado'] ?? null;
            
            // Cambiar estado de la tarea
            $response = $coordinadorController->cambiarEstadoTarea($tarea_id, $nuevo_estado, $resultado);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    case 'completar_asignacion':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            $asignacion_id = $_POST['asignacion_id'] ?? '';
            
            // Completar la asignación
            $response = $coordinadorController->completarAsignacion($asignacion_id);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    case 'obtener_tarea':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $coordinadorController = new CoordinadorController();
            
            $tarea_id = $_GET['id'] ?? '';
            
            // Obtener la tarea
            $tarea = $coordinadorController->obtenerTareaPorId($tarea_id);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($tarea);
            exit();
        }
        break;
    
    case 'obtener_detalles_asesor_coord':
        $authController->requerirRol('coordinador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $asesor_cedula = $_GET['asesor_cedula'] ?? null;
        $coordinador_cedula = $usuario_actual['cedula'] ?? null;
        
        if (!$asesor_cedula || !$coordinador_cedula) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cédula de asesor o coordinador no proporcionada']);
            exit();
        }
        
        $coordinadorController = new CoordinadorController();
        $resultado = $coordinadorController->obtenerDetallesAsesor($asesor_cedula, $coordinador_cedula);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;
        
    case 'obtener_asesores_con_acceso':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $coordinadorController = new CoordinadorController();
            
            $base_id = $_GET['base_id'] ?? '';
            
            if (empty($base_id)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de base requerido']);
                exit();
            }
            
            // Obtener asesores con acceso a la base
            $resultado = $coordinadorController->obtenerAsesoresConAccesoBase($base_id);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'liberar_acceso_base':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $coordinadorController = new CoordinadorController();
            
            $asesor_cedula = $_POST['asesor_cedula'] ?? '';
            $base_id = $_POST['base_id'] ?? '';
            
            if (empty($asesor_cedula) || empty($base_id)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Datos requeridos: asesor_cedula y base_id']);
                exit();
            }
            
            // Eliminar acceso del asesor a la base usando guardarAccesoBase con array vacío
            try {
                $conn = getDBConnection();
                
                // Usar siempre asignaciones_base_comercios (la tabla asignaciones_base fue eliminada)
                $tabla_asignaciones = 'asignaciones_base_comercios';
                
                // Eliminar acceso
                $query = "DELETE FROM {$tabla_asignaciones} WHERE base_id = ? AND asesor_cedula = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$base_id, $asesor_cedula]);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Acceso liberado exitosamente'
                ]);
            } catch (Exception $e) {
                error_log("Error al liberar acceso base: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al liberar acceso: ' . $e->getMessage()
                ]);
            }
            exit();
        }
        break;
        
    case 'obtener_tareas_coordinador':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $coordinadorController = new CoordinadorController();
            
            $coordinador_cedula = $_SESSION['usuario_id'] ?? '';
            
            if (empty($coordinador_cedula)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
                exit();
            }
            
            // Obtener tareas del coordinador
            $resultado = $coordinadorController->obtenerTareasCoordinador($coordinador_cedula);
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'asesor_app':
        // Nueva acción para cargar el layout principal con iframe
        $authController->requerirRol('asesor');
        // Cargar directamente el layout principal
        include 'views/layout_asesor.php';
        exit();
        break;
        
    case 'asesor_dashboard':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Si no está en modo iframe, redirigir al layout principal
        if (!isset($_GET['iframe']) || $_GET['iframe'] != '1') {
            header('Location: index.php?action=asesor_app');
            exit();
        }
        
        // Crear instancia del controlador de asesor
        $asesorController = new AsesorController();
        
        // Obtener datos usando el controlador
        $estadisticas = $asesorController->obtenerEstadisticas($usuario_actual['cedula']);
        $clientes = $asesorController->obtenerClientes($usuario_actual['cedula'], 'solo_tareas'); // Solo tareas específicas para la pestaña CLIENTES
        $tareas = $asesorController->obtenerTareas($usuario_actual['cedula']);
        
        // Incluir la vista del dashboard de asesor
        include 'views/asesor_dashboard.php';
        break;
        
    case 'asesor_gestionar':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Si no está en modo iframe, redirigir al layout principal con el cliente
        if (!isset($_GET['iframe']) || $_GET['iframe'] != '1') {
            $cliente_id = $_GET['cliente_id'] ?? null;
            if ($cliente_id) {
                header('Location: index.php?action=asesor_app&gestionar_action=asesor_gestionar&cliente_id=' . urlencode($cliente_id));
            } else {
                header('Location: index.php?action=asesor_app');
            }
            exit();
        }
        
        // Verificar que la sesión tenga los datos necesarios
        if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_cedula'])) {
            error_log("ERROR asesor_gestionar: Sesión sin usuario_id ni usuario_cedula");
            header('Location: index.php?action=login');
            exit();
        }
        
        // Obtener ID del cliente desde la URL
        $cliente_id = $_GET['cliente_id'] ?? null;
        
        if (!$cliente_id) {
            // Redirigir al dashboard si no hay ID de cliente
            header('Location: index.php?action=asesor_dashboard&iframe=1');
            exit();
        }
        
        // Crear instancia del controlador de asesor
        $asesorController = new AsesorController();
        
        // Obtener datos del cliente específico
        $cliente_data = $asesorController->obtenerDatosCliente($cliente_id, $usuario_actual['cedula']);
        $contrato_data = $asesorController->obtenerDatosContrato($cliente_id);
        $historial_data = $asesorController->obtenerHistorialCliente($cliente_id);
        
        // Incluir la vista de gestión de cliente
        include 'views/asesor_gestionar.php';
        break;
        
    case 'obtener_datos_cliente':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $cliente_id = $_GET['cliente_id'] ?? null;
        
        if (!$cliente_id) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
            exit();
        }
        
        $asesorController = new AsesorController();
        $resultado = $asesorController->obtenerDatosCliente($cliente_id, $usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        
    case 'obtener_contratos_cliente':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $cliente_id = $_GET['cliente_id'] ?? null;
        
        if (!$cliente_id) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
            exit();
        }
        
        $asesorController = new AsesorController();
        $resultado = $asesorController->obtenerContratosCliente($cliente_id, $usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        
    case 'actualizar_info_cliente':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $cliente_id = $input['cliente_id'] ?? null;
            $datos = $input['datos'] ?? [];
            
            if (!$cliente_id) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
                exit();
            }
            
            $asesorController = new AsesorController();
            $resultado = $asesorController->actualizarInformacionCliente(
                $cliente_id, 
                $usuario_actual['cedula'], 
                $datos
            );
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'guardar_gestion':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $cliente_id = $input['cliente_id'] ?? null;
            $datos = $input['datos'] ?? [];
            
            if (!$cliente_id) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
                exit();
            }
            
            $asesorController = new AsesorController();
            $resultado = $asesorController->guardarGestion(
                $usuario_actual['cedula'], 
                $cliente_id, 
                $datos
            );
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'obtener_historial_gestiones':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $cliente_id = $_GET['cliente_id'] ?? null;
        
        if (!$cliente_id) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
            exit();
        }
        
        $asesorController = new AsesorController();
        $resultado = $asesorController->obtenerHistorialGestiones($cliente_id, $usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        
    case 'obtener_bases_acceso':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $asesorController = new AsesorController();
        $resultado = $asesorController->obtenerBasesAcceso($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        
    case 'buscar_cliente_asesor':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $termino = $input['termino'] ?? '';
            $criterio = $input['criterio'] ?? 'auto';
            
            if (empty($termino)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Término de búsqueda requerido'
                ]);
                exit();
            }
            
            $asesorController = new AsesorController();
            $resultados = $asesorController->buscarCliente($usuario_actual['cedula'], $criterio, $termino);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'clientes' => $resultados
            ]);
            exit();
        }
        break;
        
        
    case 'admin_usuarios':
        $authController->requerirRol('administrador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        // Incluir la vista de gestión de usuarios
        include 'views/admin_crear_usuario.php';
        break;
        
    case 'admin_asignaciones':
        $authController->requerirRol('administrador');
        $usuario_actual = $authController->obtenerUsuarioActual();
        // Incluir la vista de asignaciones
        include 'views/admin_asignar_personal.php';
        break;
        
    case 'create_usuario':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            // Recopilar datos del formulario
            $datos_usuario = [
                'cedula' => $_POST['cedula'] ?? '',
                'nombre_completo' => $_POST['nombre_completo'] ?? '',
                'usuario' => $_POST['usuario'] ?? '',
                'contrasena' => $_POST['contrasena'] ?? '',
                'estado' => $_POST['estado'] ?? 'activo',
                'rol' => $_POST['rol'] ?? ''
            ];
            
            // Crear el usuario usando el controlador
            $response = $adminController->crearUsuario($datos_usuario);
            
            // Responder con JSON si es AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'asignar_personal':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            // Recopilar datos del formulario
            $datos_asignacion = [
                'asesor_cedula' => $_POST['asesor_cedula'] ?? '',
                'coordinador_cedula' => $_POST['coordinador_cedula'] ?? '',
                'notas' => '', // Campo removido del formulario
                'creado_por' => $_SESSION['usuario_id'] ?? ''
            ];
            
            // Crear la asignación usando el controlador
            $response = $adminController->asignarPersonal($datos_asignacion);
            
            // Responder con JSON si es AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'liberar_asignacion':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            // Obtener ID de la asignación
            $asignacion_id = $_POST['id'] ?? '';
            
            if (empty($asignacion_id)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de asignación requerido']);
                exit();
            }
            
            // Liberar la asignación
            $resultado = $adminController->liberarAsignacion($asignacion_id);
            
            // Devolver respuesta JSON
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'cambiar_estado_usuario':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            $cedula = $_POST['cedula'] ?? '';
            $nuevo_estado = $_POST['estado'] ?? '';
            
            // Cambiar estado del usuario usando el controlador
            $response = $adminController->cambiarEstadoUsuario($cedula, $nuevo_estado);
            
            // Responder con JSON si es AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'eliminar_usuario':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            $cedula = $_POST['cedula'] ?? '';
            
            // Verificar que no sea el usuario actual
            if ($cedula === $_SESSION['usuario_id']) {
                $response = ['success' => false, 'message' => 'No puede eliminar su propio usuario'];
            } else {
                // Eliminar usuario usando el controlador
                $response = $adminController->eliminarUsuario($cedula);
            }
            
            // Responder con JSON si es AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'editar_usuario':
        $authController->requerirRol('administrador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $adminController = new AdminController();
            
            // Recopilar datos del formulario
            $datos_usuario = [
                'cedula' => $_POST['cedula'] ?? '',
                'nombre_completo' => $_POST['nombre_completo'] ?? '',
                'usuario' => $_POST['usuario'] ?? '',
                'contrasena' => $_POST['contrasena'] ?? '',
                'confirmar_contrasena' => $_POST['confirmar_contrasena'] ?? '',
                'rol' => $_POST['rol'] ?? '',
                'estado' => $_POST['estado'] ?? ''
            ];
            
            // Validar contraseñas si se proporcionaron
            if (!empty($datos_usuario['contrasena'])) {
                if ($datos_usuario['contrasena'] !== $datos_usuario['confirmar_contrasena']) {
                    $response = ['success' => false, 'message' => 'Las contraseñas no coinciden'];
                } elseif (strlen($datos_usuario['contrasena']) < 6) {
                    $response = ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres'];
                } else {
                    // Actualizar usuario con nueva contraseña
                    $response = $adminController->actualizarUsuario($datos_usuario);
                }
            } else {
                // Actualizar usuario sin cambiar contraseña
                unset($datos_usuario['contrasena']);
                unset($datos_usuario['confirmar_contrasena']);
                $response = $adminController->actualizarUsuario($datos_usuario);
            }
            
            // Responder con JSON si es AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'obtener_bases':
        $authController->requerirRol('coordinador');
        
        try {
            $coordinadorController = new CoordinadorController();
            $bases = $coordinadorController->obtenerBases($_SESSION['usuario_id'] ?? '');
            $estadisticas = $coordinadorController->obtenerEstadisticasBases();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $bases,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en obtener_bases: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener bases: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'obtener_tareas':
        $authController->requerirRol('coordinador');
        
        try {
            $coordinadorController = new CoordinadorController();
            $tareas = $coordinadorController->obtenerTareas($_SESSION['usuario_id'] ?? '');
            $estadisticas = $coordinadorController->obtenerEstadisticasTareasCompletas($_SESSION['usuario_id'] ?? '');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $tareas,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en obtener_tareas: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener tareas: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'obtener_asignaciones_pendientes':
        $authController->requerirRol('coordinador');
        
        try {
            $coordinador_cedula = $_SESSION['usuario_id'] ?? '';
            
            if (empty($coordinador_cedula)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $asignaciones = $coordinadorController->obtenerTareas($coordinador_cedula);
            
            // Filtrar solo asignaciones pendientes o en progreso
            $asignaciones_pendientes = array_filter($asignaciones, function($asignacion) {
                return in_array($asignacion['estado'] ?? '', ['pendiente', 'en_progreso']);
            });
            
            // Obtener información adicional (nombres de base y asesor)
            $conn = getDBConnection();
            $asignaciones_con_info = [];
            
            foreach ($asignaciones_pendientes as $asignacion) {
                // Obtener nombre de la base
                $base_nombre = '-';
                if (isset($asignacion['base_id'])) {
                    $stmt_base = $conn->prepare("SELECT nombre FROM bases_clientes WHERE id = ?");
                    $stmt_base->execute([$asignacion['base_id']]);
                    $base = $stmt_base->fetch(PDO::FETCH_ASSOC);
                    if ($base) {
                        $base_nombre = $base['nombre'];
                    }
                }
                
                // Obtener nombre del asesor
                $asesor_nombre = '-';
                if (isset($asignacion['asesor_cedula'])) {
                    $stmt_asesor = $conn->prepare("SELECT nombre_completo FROM usuarios WHERE cedula = ?");
                    $stmt_asesor->execute([$asignacion['asesor_cedula']]);
                    $asesor = $stmt_asesor->fetch(PDO::FETCH_ASSOC);
                    if ($asesor) {
                        $asesor_nombre = $asesor['nombre_completo'];
                    }
                }
                
                $asignacion['base_nombre'] = $base_nombre;
                $asignacion['asesor_nombre'] = $asesor_nombre;
                $asignaciones_con_info[] = $asignacion;
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'asignaciones' => array_values($asignaciones_con_info)
            ]);
        } catch (Exception $e) {
            error_log("Error en obtener_asignaciones_pendientes: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener asignaciones: ' . $e->getMessage(),
                'asignaciones' => []
            ]);
        }
        exit();
        break;
        
    case 'obtener_asesores_coordinador':
        $authController->requerirRol('coordinador');
        
        try {
            $coordinadorController = new CoordinadorController();
            $asesores = $coordinadorController->obtenerAsesores($_SESSION['usuario_id'] ?? '');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'asesores' => $asesores
            ]);
        } catch (Exception $e) {
            error_log("Error en obtener_asesores_coordinador: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener asesores: ' . $e->getMessage(),
                'asesores' => []
            ]);
        }
        exit();
        break;
        
    case 'obtener_clientes_disponibles':
        $authController->requerirRol('coordinador');
        
        try {
            $base_id = $_GET['base_id'] ?? null;
            
            if (!$base_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de base no proporcionado'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $clientes = $coordinadorController->obtenerClientesDisponibles($base_id);
            
            header('Content-Type: application/json');
            echo json_encode($clientes);
        } catch (Exception $e) {
            error_log("Error en obtener_clientes_disponibles: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener clientes disponibles: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'crear_asignacion_clientes':
        $authController->requerirRol('coordinador');
        
        try {
            $base_id = $_POST['base_id'] ?? null;
            $asesor_cedula = $_POST['asesor_cedula'] ?? null;
            $cantidad_clientes = $_POST['cantidad_clientes'] ?? null;
            $coordinador_cedula = $_SESSION['usuario_id'] ?? null;
            
            if (!$base_id || !$asesor_cedula || !$cantidad_clientes || !$coordinador_cedula) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Datos incompletos para la asignación'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->crearAsignacionClientes([
                'base_id' => $base_id,
                'asesor_cedula' => $asesor_cedula,
                'coordinador_cedula' => $coordinador_cedula,
                'cantidad_clientes' => $cantidad_clientes
            ]);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en crear_asignacion_clientes: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al crear asignación: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'obtener_clientes_base':
        $authController->requerirRol('coordinador');
        
        try {
            $base_id = $_GET['base_id'] ?? null;
            
            if (!$base_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de base no proporcionado'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->obtenerClientesPorBase($base_id);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en obtener_clientes_base: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener clientes: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'guardar_acceso_base':
        $authController->requerirRol('coordinador');
        
        try {
            // Manejar datos de diferentes formas (FormData, JSON, POST directo)
            $base_id = null;
            $asesores = [];
            
            // Intentar obtener datos de JSON (si Content-Type es application/json)
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input && isset($input['base_id'])) {
                $base_id = $input['base_id'];
                $asesores = $input['asesores'] ?? [];
            } else {
                // Si no viene como JSON, usar $_POST
                $base_id = $_POST['base_id'] ?? null;
                $asesores_raw = $_POST['asesores'] ?? null;
                
                // Si asesores viene como JSON string, decodificar
                if (is_string($asesores_raw)) {
                    $asesores = json_decode($asesores_raw, true);
                    // Si json_decode falla, intentar como array simple
                    if ($asesores === null && json_last_error() !== JSON_ERROR_NONE) {
                        $asesores = [$asesores_raw];
                    }
                } elseif (is_array($asesores_raw)) {
                    $asesores = $asesores_raw;
                } else {
                    $asesores = [];
                }
            }
            
            // Validar base_id
            if (!$base_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de base no proporcionado',
                    'debug' => [
                        'post_data' => $_POST,
                        'input_data' => $input ?? null,
                        'base_id' => $base_id
                    ]
                ]);
                exit();
            }
            
            // Asegurar que asesores sea un array
            if (!is_array($asesores)) {
                if (is_string($asesores)) {
                    $asesores = json_decode($asesores, true) ?? [$asesores];
                } else {
                    $asesores = [];
                }
            }
            
            error_log("guardar_acceso_base - Base ID: {$base_id}, Asesores: " . json_encode($asesores));
            
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->guardarAccesoBase([
                'base_id' => $base_id,
                'asesores' => $asesores
            ]);
            
            error_log("guardar_acceso_base - Resultado: " . json_encode($resultado));
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en guardar_acceso_base: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al guardar acceso: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'obtener_asesores_acceso_base':
        $authController->requerirRol('coordinador');
        
        try {
            $base_id = $_GET['base_id'] ?? null;
            
            if (!$base_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de base no proporcionado'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->obtenerAsesoresConAccesoBase($base_id);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en obtener_asesores_acceso_base: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener asesores: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'eliminar_base':
        $authController->requerirRol('coordinador');
        
        try {
            $base_id = $_POST['base_id'] ?? null;
            
            if (!$base_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de base no proporcionado'
                ]);
                exit();
            }
            
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->eliminarBase($base_id);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en eliminar_base: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al eliminar base: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'obtener_historial':
        $authController->requerirRol('coordinador');
        
        try {
            $baseDatosController = new BaseDatosController();
            $resultado = $baseDatosController->obtenerHistorial();
            
            // Normalizar la respuesta para que siempre tenga 'data' con el array de historial
            if ($resultado['success']) {
                $historial_array = $resultado['historial'] ?? [];
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $historial_array,
                    'total' => count($historial_array)
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $resultado['message'] ?? 'Error al obtener historial',
                    'data' => []
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en obtener_historial: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
                'data' => []
            ]);
        }
        exit();
        break;
        
    case 'verificar_tablas':
        $authController->requerirRol('coordinador');
        
        try {
            $conn = getDBConnection();
            
            // Verificar si existen las tablas
            $tablas_existen = true;
            $errores = [];
            
            // Verificar tabla clientes
            $stmt = $conn->query("SHOW TABLES LIKE 'clientes'");
            if ($stmt->rowCount() == 0) {
                $tablas_existen = false;
                $errores[] = 'Tabla clientes no existe';
            }
            
            // Verificar tabla obligaciones
            $stmt = $conn->query("SHOW TABLES LIKE 'obligaciones'");
            if ($stmt->rowCount() == 0) {
                $tablas_existen = false;
                $errores[] = 'Tabla obligaciones no existe';
            }
            
            if (!$tablas_existen) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Las tablas no existen. Ejecute el script SQL primero.',
                        'errores' => $errores,
                        'instrucciones' => 'Ejecute: mysql -u root -p apex < database/crear_tabla_clientes.sql && mysql -u root -p apex < database/crear_tabla_obligaciones.sql'
                    ]);
                exit();
            }
            
            // Verificar si hay datos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes");
            $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM obligaciones");
            $total_obligaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'tablas_existen' => true,
                'total_clientes' => $total_clientes,
                'total_obligaciones' => $total_obligaciones,
                'mensaje' => 'Tablas verificadas correctamente'
            ]);
            
        } catch (Exception $e) {
            error_log("Error en verificar_tablas: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error al verificar tablas: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'test_clientes_obligaciones':
        $authController->requerirRol('coordinador');
        
        try {
            $coordinadorController = new CoordinadorController();
            $resultado = [
                'success' => true,
                'mensaje' => 'Sistema de clientes y obligaciones funcionando correctamente',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit();
        break;
        
    case 'cargar_csv':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Aumentar límites para archivos grandes
            ini_set('memory_limit', '1024M');
            set_time_limit(7200); // 2 horas
            
            $coordinadorController = new CoordinadorController();
            
            // Obtener parámetros de carga
            $tipo_carga = $_POST['tipo_carga'] ?? 'nueva';
            $nombre_archivo = $_POST['nombre_archivo'] ?? '';
            $base_datos_id = $_POST['base_datos_id'] ?? null;
            
            error_log("index.php: Procesando carga CSV de comercios y facturas - Tipo: {$tipo_carga}, Nombre: {$nombre_archivo}");
            
            // Verificar si se subió un archivo
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                
                // Crear directorio si no existe
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $_FILES['csv_file']['name'];
                $file_tmp = $_FILES['csv_file']['tmp_name'];
                $file_size = $_FILES['csv_file']['size'];
                
                // Verificar tamaño del archivo (500MB máximo)
                $max_size = 500 * 1024 * 1024; // 500MB
                if ($file_size > $max_size) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'El archivo es demasiado grande. Máximo permitido: 500MB',
                        'file_size' => $file_size,
                        'max_size' => $max_size
                    ]);
                    exit();
                }
                
                $file_path = $upload_dir . uniqid() . '_' . $file_name;
                
                // Mover archivo subido
                if (move_uploaded_file($file_tmp, $file_path)) {
                    error_log("index.php: Archivo subido correctamente: {$file_path} ({$file_size} bytes)");
                    
                    // Procesar archivo CSV usando CoordinadorController con batch processing
                    $batch_size = $file_size > 100 * 1024 * 1024 ? 2000 : 1000; // Lotes más grandes para archivos grandes
                    $base_datos_id = $_POST['base_datos_id'] ?? null;
                    $resultado = $coordinadorController->procesarCSVClientesObligaciones($file_path, $tipo_carga, $nombre_archivo, $batch_size, $base_datos_id);
                    
                    // Eliminar archivo temporal
                    @unlink($file_path);
                    
                    error_log("index.php: Resultado del procesamiento: " . json_encode($resultado));
                    
                    // Responder con JSON
                    header('Content-Type: application/json');
                    echo json_encode($resultado);
                    exit();
                } else {
                    error_log("index.php: Error al mover archivo subido");
                    $response = ['success' => false, 'message' => 'Error al subir el archivo'];
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }
            } else {
                error_log("index.php: No se seleccionó archivo o error en subida");
                $response = ['success' => false, 'message' => 'No se seleccionó ningún archivo o hubo un error en la subida'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
        break;
        
    case 'descargar_plantilla':
        $authController->requerirRol('coordinador');
        
        // Crear plantilla CSV
        $headers = [
            'CONTRATO', 'TIPO DOCUMENTO', 'IDENTIFICACION', 'NOMBRE CONTRATANTE', 
            'CIUDAD', 'TEL1', 'TEL2', 'TEL3', 'TEL4', 'EMAIL CONTRATANTE',
            'MODALIDAD DE PAGO', 'FRANJA', 'DIAS EN MORA', 'EDAD MORA', 'TOTAL CARTERA'
        ];
        
        // Datos de ejemplo
        $ejemplo = [
            '112237960000', 'CC', '66762486', 'MONICA PATRICIA COLLAZOS AMAYA', 'CALI',
            '3186597515', '6605470', '6653127', '', 'monica@email.com',
            'MENSUAL', '13 dias', '14', '0-30 DIAS', '96726'
        ];
        
        // Generar CSV
        $csv_content = implode(',', $headers) . "\n";
        $csv_content .= implode(',', $ejemplo) . "\n";
        
        // Enviar archivo
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="plantilla_clientes.csv"');
        echo $csv_content;
        exit();
        break;
        
        
        
    case 'obtener_estadisticas_bases':
        $authController->requerirRol('coordinador');
        
        $coordinadorController = new CoordinadorController();
        $estadisticas = $coordinadorController->obtenerEstadisticasBases();
        
        header('Content-Type: application/json');
        echo json_encode($estadisticas);
        exit();
        break;
        
    case 'obtener_clientes_no_asignados':
        $authController->requerirRol('coordinador');
        
        $base_id = $_GET['base_id'] ?? '';
        if (empty($base_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Base ID requerido']);
            exit();
        }
        
        // Usar obtenerClientesDisponibles que existe en CoordinadorController
        $coordinadorController = new CoordinadorController();
        $clientes_disponibles = $coordinadorController->obtenerClientesDisponibles($base_id);
        
        header('Content-Type: application/json');
        echo json_encode($clientes_disponibles);
        exit();
        break;
        
    case 'obtener_siguiente_cliente':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $asesorController = new AsesorController();
        $siguiente_cliente = $asesorController->obtenerSiguienteCliente($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($siguiente_cliente);
        exit();
        break;
        
        
    case 'limpiar_historial':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $baseDatosController = new BaseDatosController();
            $resultado = $baseDatosController->limpiarHistorial();
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'eliminar_base':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $base_id = $_POST['base_id'] ?? '';
            
            if (empty($base_id)) {
                $response = ['success' => false, 'message' => 'ID de base requerido'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            
            $baseDatosController = new BaseDatosController();
            $resultado = $baseDatosController->eliminarBase($base_id);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'exportar_historial':
        $authController->requerirRol('coordinador');
        
        $baseDatosController = new BaseDatosController();
        $resultado = $baseDatosController->exportarHistorial();
        
        if ($resultado['success']) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="historial_actividades.csv"');
            echo $resultado['csv_content'];
        } else {
            header('Content-Type: application/json');
            echo json_encode($resultado);
        }
        exit();
        break;
        
    case 'check_updates':
        $authController->requerirAutenticacion();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Respuesta simple sin dependencias complejas
            $resultado = [
                'success' => true,
                'has_updates' => false,
                'updates' => [],
                'timestamp' => time() * 1000
            ];
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'obtener_estadisticas_asesor':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $asesorController = new AsesorController();
        $estadisticas = $asesorController->obtenerEstadisticas($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($estadisticas);
        exit();
        break;
        
    case 'obtener_resumen_tareas':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        $asesorController = new AsesorController();
        $resumen_tareas = $asesorController->obtenerResumenTareas($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resumen_tareas);
        exit();
        break;
        
    case 'obtener_clientes_filtrados':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        // Obtener filtros de la petición
        $filtros = [
            'gestionado' => $_GET['gestionado'] ?? '',
            'contactado' => $_GET['contactado'] ?? '',
            'fecha' => $_GET['fecha'] ?? ''
        ];
        
        $asesorController = new AsesorController();
        $clientes = $asesorController->obtenerClientes($usuario_actual['cedula'], 'solo_tareas', $filtros);
        
        header('Content-Type: application/json');
        echo json_encode($clientes);
        exit();
        break;
        
    case 'obtener_detalles_base':
        $authController->requerirRol('coordinador');
        
        $fecha_carga = $_GET['fecha'] ?? '';
        $baseDatosController = new BaseDatosController();
        $resultado = $baseDatosController->obtenerDetallesBase($fecha_carga);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;
        
    case 'obtener_asesores':
        $authController->requerirRol('coordinador');
        
        $adminController = new AdminController();
        $resultado = $adminController->obtenerAsesores();
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;
        
    case 'obtener_asesores_sin_acceso':
        $authController->requerirRol('coordinador');
        
        $coordinadorController = new CoordinadorController();
        $base_id = $_GET['base_id'] ?? '';
        $usuario_actual = $authController->obtenerUsuarioActual();
        $coordinador_cedula = $usuario_actual['cedula'] ?? '';
        
        if (empty($base_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID de base requerido', 'asesores' => []]);
            exit();
        }
        
        try {
            // Obtener todos los asesores del coordinador
            $todos_asesores = $coordinadorController->obtenerAsesores($coordinador_cedula);
            
            // Obtener asesores con acceso a esta base
            $asesores_con_acceso = $coordinadorController->obtenerAsesoresConAccesoBase($base_id);
            // El resultado trae la columna 'asesor_cedula', no 'cedula'
            $cedulas_con_acceso = array_column($asesores_con_acceso['asesores'] ?? [], 'asesor_cedula');
            
            // Filtrar asesores sin acceso
            $asesores_sin_acceso = array_filter($todos_asesores, function($asesor) use ($cedulas_con_acceso) {
                return !in_array($asesor['cedula'], $cedulas_con_acceso);
            });
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'asesores' => array_values($asesores_sin_acceso)
            ]);
        } catch (Exception $e) {
            error_log("Error en obtener_asesores_sin_acceso: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener asesores: ' . $e->getMessage(),
                'asesores' => []
            ]);
        }
        exit();
        break;
        
    case 'obtener_asesores_con_acceso':
        $authController->requerirRol('coordinador');
        
        $base_id = $_GET['base_id'] ?? '';
        if (empty($base_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID de base requerido']);
            exit();
        }
        
        $coordinadorController = new CoordinadorController();
        $resultado = $coordinadorController->obtenerAsesoresConAccesoBase($base_id);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;

    case 'guardar_asignaciones_base':
        $authController->requerirRol('coordinador');
        
        // Obtener datos del POST
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['base_id']) || !isset($input['asesor_ids'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit();
        }
        
        $baseId = $input['base_id'];
        $asesorIds = $input['asesor_ids'];
        
        // Usar guardarAccesoBase que existe en CoordinadorController
        $coordinadorController = new CoordinadorController();
        $resultado = $coordinadorController->guardarAccesoBase([
            'base_id' => $baseId,
            'asesores' => $asesorIds
        ]);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;
        
    case 'guardar_acceso_base':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['base_id']) || !isset($input['asesores'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit();
            }
            
            $baseId = $input['base_id'];
            $asesores = $input['asesores'];
            
            // Usar guardarAccesoBase que existe en CoordinadorController
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->guardarAccesoBase([
                'base_id' => $baseId,
                'asesores' => $asesores
            ]);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'asignar_clientes':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $baseId = $input['base_id'] ?? '';
            $asesorCedula = $input['asesor_cedula'] ?? '';
            $clientesAsignar = intval($input['clientes_asignar'] ?? 0);
            $coordinadorCedula = $_SESSION['usuario_id'] ?? '';
            
            if (!$baseId || !$asesorCedula || !$clientesAsignar || !$coordinadorCedula) {
                $response = ['success' => false, 'message' => 'Datos incompletos'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            
            // Obtener los IDs reales de los clientes de la base seleccionada
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE base_id = ? ORDER BY id");
            $stmt->execute([$baseId]);
            $todos_clientes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $clientes_reales = array_slice($todos_clientes, 0, $clientesAsignar);
            
            if (empty($clientes_reales)) {
                $response = ['success' => false, 'message' => 'No hay clientes disponibles en la base seleccionada'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            
            // Crear datos para la asignación con IDs reales
            $datosAsignacion = [
                'coordinador_cedula' => $coordinadorCedula,
                'asesor_cedula' => $asesorCedula,
                'estado' => 'pendiente',
                'clientes_asignados' => json_encode(['clientes' => $clientes_reales]),
                'base_id' => $baseId
            ];
            
            // Usar el controlador para crear la asignación
            $coordinadorController = new CoordinadorController();
            $resultado = $coordinadorController->crearTarea($datosAsignacion);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
        break;
        
    case 'obtener_asignaciones':
        $authController->requerirRol('coordinador');
        
        // Simular datos de asignaciones (aquí se implementaría la consulta real)
        $asignaciones = [
            [
                'id' => 1,
                'nombre_base' => 'Base del 23/10/2025',
                'nombre_asesor' => 'Fernanda Cortez',
                'clientes_asignados' => 25,
                'fecha_asignacion' => '2025-01-23 10:30:00'
            ],
            [
                'id' => 2,
                'nombre_base' => 'Base del 22/10/2025',
                'nombre_asesor' => 'Fernanda Cortez',
                'clientes_asignados' => 15,
                'fecha_asignacion' => '2025-01-22 14:15:00'
            ]
        ];
        
        $response = [
            'success' => true,
            'asignaciones' => $asignaciones
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
        break;
        
    case 'eliminar_asignacion':
        $authController->requerirRol('coordinador');
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            
            if (!$id) {
                $response = ['success' => false, 'message' => 'ID de asignación requerido'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            
            // Simular eliminación exitosa (aquí se implementaría la lógica real)
            $response = [
                'success' => true,
                'message' => 'Asignación eliminada exitosamente'
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        break;
        
    // =====================================================
    // RUTAS PARA SISTEMA DE MEDICIÓN DE TIEMPO (ASESORES)
    // =====================================================
    
    case 'crear_sesion_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        require_once 'models/Tiempo.php';
        $tiempo_model = new Tiempo();
        
        $resultado = $tiempo_model->crearSesion($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit();
        break;
        
    case 'obtener_sesion_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        require_once 'models/Tiempo.php';
        $tiempo_model = new Tiempo();
        
        $sesion = $tiempo_model->obtenerSesionActiva($usuario_actual['cedula']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $sesion !== null,
            'sesion' => $sesion
        ]);
        exit();
        break;
        
    case 'actualizar_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            $resultado = $tiempo_model->actualizarTiempo(
                $input['sesion_id'],
                $input['tiempo_total'],
                $input['tiempo_pausas'],
                $input['estado']
            );
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado]);
            exit();
        }
        break;
        
    case 'iniciar_pausa':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            $resultado = $tiempo_model->iniciarPausa($input['sesion_id'], $input['tipo_pausa']);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado]);
            exit();
        }
        break;
        
    case 'finalizar_pausa':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            $resultado = $tiempo_model->finalizarPausa($input['sesion_id']);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado]);
            exit();
        }
        break;
        
    case 'finalizar_sesion_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            $resultado = $tiempo_model->finalizarSesion($input['sesion_id']);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado]);
            exit();
        }
        break;
        
    case 'guardar_actividad_extra':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            try {
                $conn = getDBConnection();
                
                // Insertar actividad extra en la tabla pausas
                $stmt = $conn->prepare("INSERT INTO pausas (sesion_id, tipo_pausa, hora_inicio, hora_fin, duracion_segundos, estado)
                                        VALUES (?, 'personal', NOW(), NOW(), ?, 'finalizada')");
                
                $resultado = $stmt->execute([
                    $input['sesion_id'],
                    $input['duracion_segundos']
                ]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => $resultado]);
                exit();
                
            } catch (Exception $e) {
                error_log("Error al guardar actividad extra: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
        }
        break;
        
    case 'verificar_contrasena':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            try {
                $conn = getDBConnection();
                
                // Obtener contraseña del usuario actual
                $stmt = $conn->prepare("SELECT contrasena FROM usuarios WHERE cedula = ?");
                $stmt->execute([$usuario_actual['cedula']]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    // Verificar contraseña
                    $verificado = password_verify($input['contrasena'], $usuario['contrasena']);
                    
                    if ($verificado) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit();
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
                exit();
                
            } catch (Exception $e) {
                error_log("Error al verificar contraseña: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al verificar']);
                exit();
            }
        }
        break;
        
    case 'bloquear_asesor':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            try {
                $conn = getDBConnection();
                
                // Verificar si el asesor ya está bloqueado
                $stmt = $conn->prepare("SELECT id FROM bloqueos WHERE asesor_cedula = ? AND estado = 'activo'");
                $stmt->execute([$usuario_actual['cedula']]);
                $bloqueoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bloqueoExistente) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'bloqueo_id' => $bloqueoExistente['id']]);
                    exit();
                }
                
                // Crear nuevo bloqueo
                $stmt = $conn->prepare("INSERT INTO bloqueos (asesor_cedula, sesion_id, tipo_pausa, tiempo_pausa_estimado, tiempo_excedido, hora_inicio_pausa, hora_bloqueo, estado)
                                        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 'activo')");
                
                $resultado = $stmt->execute([
                    $usuario_actual['cedula'],
                    $input['sesion_id'],
                    $input['tipo_pausa'],
                    $input['tiempo_pausa_estimado'],
                    $input['tiempo_excedido']
                ]);
                
                if ($resultado) {
                    $bloqueoId = $conn->lastInsertId();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'bloqueo_id' => $bloqueoId]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error al crear bloqueo']);
                }
                exit();
                
            } catch (Exception $e) {
                error_log("Error al bloquear asesor: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
        }
        break;
        
    case 'verificar_estado_bloqueo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        try {
            $conn = getDBConnection();
            
            // Verificar si el asesor tiene un bloqueo activo
            $stmt = $conn->prepare("SELECT id FROM bloqueos WHERE asesor_cedula = ? AND estado = 'activo'");
            $stmt->execute([$usuario_actual['cedula']]);
            $bloqueo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['desbloqueado' => !$bloqueo]);
            exit();
            
        } catch (Exception $e) {
            error_log("Error al verificar estado de bloqueo: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['desbloqueado' => false]);
            exit();
        }
        break;
        
    case 'obtener_bloqueos_activos':
        $authController->requerirRol(['coordinador', 'superadmin']);
        $coordinador_actual = $authController->obtenerUsuarioActual();
        
        try {
            $conn = getDBConnection();
            
            // Obtener todos los bloqueos activos con información del asesor
            $stmt = $conn->prepare("
                SELECT 
                    b.id,
                    b.asesor_cedula,
                    u.nombre_completo as nombre_asesor,
                    b.sesion_id,
                    b.tipo_pausa,
                    b.tiempo_pausa_estimado,
                    b.tiempo_excedido,
                    b.hora_inicio_pausa,
                    b.hora_bloqueo,
                    b.estado
                FROM bloqueos b
                INNER JOIN usuarios u ON b.asesor_cedula = u.cedula
                WHERE b.estado = 'activo'
                ORDER BY b.hora_bloqueo DESC
            ");
            
            $stmt->execute();
            $bloqueos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'bloqueos' => $bloqueos]);
            exit();
            
        } catch (Exception $e) {
            error_log("Error al obtener bloqueos activos: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'bloqueos' => []]);
            exit();
        }
        break;
        
    case 'desbloquear_asesor':
        $authController->requerirRol(['coordinador', 'superadmin']);
        $coordinador_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            try {
                $conn = getDBConnection();
                
                // Actualizar bloqueo a desbloqueado
                $stmt = $conn->prepare("
                    UPDATE bloqueos 
                    SET estado = 'desbloqueado',
                        coordinador_cedula = ?,
                        motivo_desbloqueo = ?,
                        hora_desbloqueo = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $coordinador_actual['cedula'],
                    $input['motivo'],
                    $input['bloqueo_id']
                ]);
                
                // Registrar log de auditoría
                $stmt = $conn->prepare("
                    INSERT INTO logs_desbloqueos (bloqueo_id, coordinador_cedula, asesor_cedula, motivo_desbloqueo, hora_desbloqueo)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $input['bloqueo_id'],
                    $coordinador_actual['cedula'],
                    $input['asesor_cedula'],
                    $input['motivo']
                ]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
                
            } catch (Exception $e) {
                error_log("Error al desbloquear asesor: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
        }
        break;
        
    case 'iniciar_gestion_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            // Obtener sesión activa
            $sesion = $tiempo_model->obtenerSesionActiva($usuario_actual['cedula']);
            
            if ($sesion) {
                $resultado = $tiempo_model->iniciarGestion($sesion['id'], $input['cliente_id']);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => $resultado, 'sesion_id' => $sesion['id']]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
            }
            exit();
        }
        break;
        
    case 'finalizar_gestion_tiempo':
        $authController->requerirRol('asesor');
        $usuario_actual = $authController->obtenerUsuarioActual();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            require_once 'models/Tiempo.php';
            $tiempo_model = new Tiempo();
            
            // Calcular tiempo de gestión en segundos
            $inicio = new DateTime($input['hora_inicio']);
            $fin = new DateTime($input['hora_fin']);
            $diferencia = $fin->diff($inicio);
            $tiempo_en_segundos = ($diferencia->h * 3600) + ($diferencia->i * 60) + $diferencia->s;
            
            $resultado = $tiempo_model->finalizarGestion($input['sesion_id'], $tiempo_en_segundos);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado, 'tiempo_gestion' => $tiempo_en_segundos]);
            exit();
        }
        break;
        
    default:
        // Redirigir al login si la acción no es válida
        header('Location: index.php?action=login');
        exit();
}
?>

