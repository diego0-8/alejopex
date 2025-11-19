<?php
require_once 'config.php';

class Tiempo {
    private $conn;
    private $table_name = "tiempos";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear una nueva sesión de tiempo
     * @param string $asesor_cedula
     * @param string $tipo_pausa
     * @return array
     */
    public function crearSesion($asesor_cedula, $tipo_pausa = null) {
        try {
            // Verificar si hay una sesión activa
            $sesion_activa = $this->obtenerSesionActiva($asesor_cedula);
            
            if ($sesion_activa) {
                return [
                    'success' => true,
                    'sesion_id' => $sesion_activa['id'],
                    'message' => 'Sesión ya iniciada'
                ];
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                     (asesor_cedula, fecha, hora_inicio_sesion, estado, tipo_pausa) 
                     VALUES (?, CURDATE(), CURTIME(), 'activa', ?)";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$asesor_cedula, $tipo_pausa]);
            
            if ($result) {
                return [
                    'success' => true,
                    'sesion_id' => $this->conn->lastInsertId(),
                    'message' => 'Sesión creada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear la sesión'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error al crear sesión: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener sesión activa de un asesor
     * @param string $asesor_cedula
     * @return array|null
     */
    public function obtenerSesionActiva($asesor_cedula) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE asesor_cedula = ? 
                     AND estado IN ('activa', 'pausada')
                     ORDER BY created_at DESC 
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error al obtener sesión activa: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualizar tiempo de sesión
     * @param int $sesion_id
     * @param int $tiempo_total
     * @param int $tiempo_pausas
     * @param string $estado
     * @return bool
     */
    public function actualizarTiempo($sesion_id, $tiempo_total, $tiempo_pausas, $estado = 'activa') {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET tiempo_total_sesion = ?,
                         tiempo_pausas = ?,
                         tiempo_activo = ? - ?,
                         estado = ?,
                         ultima_actualizacion = NOW()
                     WHERE id = ?";
            
            $tiempo_activo = $tiempo_total - $tiempo_pausas;
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$tiempo_total, $tiempo_pausas, $tiempo_activo, $tiempo_pausas, $estado, $sesion_id]);
            
        } catch (PDOException $e) {
            error_log("Error al actualizar tiempo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Iniciar pausa
     * @param int $sesion_id
     * @param string $tipo_pausa
     * @return bool
     */
    public function iniciarPausa($sesion_id, $tipo_pausa) {
        try {
            // Actualizar estado de la sesión
            $query = "UPDATE " . $this->table_name . " 
                     SET hora_inicio_pausa = CURTIME(),
                         tipo_pausa = ?,
                         estado = 'pausada'
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$tipo_pausa, $sesion_id]);
            
            // Crear registro en la tabla de pausas
            $query_pausa = "INSERT INTO pausas (sesion_id, tipo_pausa, hora_inicio, estado) 
                           VALUES (?, ?, NOW(), 'activa')";
            
            $stmt_pausa = $this->conn->prepare($query_pausa);
            return $stmt_pausa->execute([$sesion_id, $tipo_pausa]);
            
        } catch (PDOException $e) {
            error_log("Error al iniciar pausa: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finalizar pausa
     * @param int $sesion_id
     * @return bool
     */
    public function finalizarPausa($sesion_id) {
        try {
            // Actualizar estado de la sesión
            $query = "UPDATE " . $this->table_name . " 
                     SET hora_fin_pausa = CURTIME(),
                         estado = 'activa'
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sesion_id]);
            
            // Obtener la pausa activa para esta sesión
            $query_get_pausa = "SELECT id, hora_inicio FROM pausas 
                               WHERE sesion_id = ? AND estado = 'activa' 
                               ORDER BY hora_inicio DESC LIMIT 1";
            
            $stmt_get = $this->conn->prepare($query_get_pausa);
            $stmt_get->execute([$sesion_id]);
            $pausa = $stmt_get->fetch(PDO::FETCH_ASSOC);
            
            if ($pausa) {
                // Calcular duración
                $inicio = new DateTime($pausa['hora_inicio']);
                $fin = new DateTime();
                $duracion = $fin->getTimestamp() - $inicio->getTimestamp();
                
                // Actualizar pausa con hora_fin y duración
                $query_update = "UPDATE pausas 
                                SET hora_fin = NOW(), 
                                    duracion_segundos = ?, 
                                    estado = 'finalizada'
                                WHERE id = ?";
                
                $stmt_update = $this->conn->prepare($query_update);
                return $stmt_update->execute([$duracion, $pausa['id']]);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error al finalizar pausa: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finalizar sesión
     * @param int $sesion_id
     * @return bool
     */
    public function finalizarSesion($sesion_id) {
        try {
            // Obtener datos completos de la sesión
            $query_select = "SELECT 
                                tiempo_total_sesion, 
                                tiempo_pausas, 
                                hora_inicio_sesion, 
                                fecha,
                                hora_fin_sesion
                            FROM " . $this->table_name . " 
                            WHERE id = ?";
            $stmt_select = $this->conn->prepare($query_select);
            $stmt_select->execute([$sesion_id]);
            $sesion = $stmt_select->fetch(PDO::FETCH_ASSOC);
            
            if (!$sesion) {
                error_log("Sesión no encontrada para finalizar: $sesion_id");
                return false;
            }
            
            $tiempo_total = $sesion['tiempo_total_sesion'] ?? 0;
            $tiempo_pausas = $sesion['tiempo_pausas'] ?? 0;
            
            // CORRECCIÓN: Si tiempo_total es 0 o NULL, calcularlo desde hora_inicio hasta hora_fin (o NOW si no hay hora_fin)
            if ($tiempo_total == 0 || $tiempo_total === null) {
                if (!empty($sesion['hora_inicio_sesion']) && !empty($sesion['fecha'])) {
                    // Si ya hay hora_fin, usar esa; si no, usar NOW()
                    if (!empty($sesion['hora_fin_sesion'])) {
                        // Calcular desde hora_inicio hasta hora_fin
                        $query_calc = "SELECT TIMESTAMPDIFF(SECOND, 
                                    CONCAT(?, ' ', ?), 
                                    CONCAT(?, ' ', ?)) as tiempo_calculado";
                        $stmt_calc = $this->conn->prepare($query_calc);
                        $stmt_calc->execute([
                            $sesion['fecha'], 
                            $sesion['hora_inicio_sesion'],
                            $sesion['fecha'], 
                            $sesion['hora_fin_sesion']
                        ]);
                        $resultado = $stmt_calc->fetch(PDO::FETCH_ASSOC);
                        $tiempo_total = $resultado['tiempo_calculado'] ?? 0;
                    } else {
                        // Calcular desde hora_inicio hasta NOW()
                        $query_calc = "SELECT TIMESTAMPDIFF(SECOND, 
                                    CONCAT(?, ' ', ?), 
                                    NOW()) as tiempo_calculado";
                        $stmt_calc = $this->conn->prepare($query_calc);
                        $stmt_calc->execute([$sesion['fecha'], $sesion['hora_inicio_sesion']]);
                        $resultado = $stmt_calc->fetch(PDO::FETCH_ASSOC);
                        $tiempo_total = $resultado['tiempo_calculado'] ?? 0;
                    }
                    
                    if ($tiempo_total > 0) {
                        error_log("Tiempo calculado para sesión {$sesion_id}: {$tiempo_total} segundos");
                    }
                }
            }
            
            $tiempo_activo = max(0, $tiempo_total - $tiempo_pausas);
            
            // Actualizar la sesión con el tiempo total, pausas, tiempo activo y estado finalizada
            $query = "UPDATE " . $this->table_name . " 
                     SET hora_fin_sesion = COALESCE(hora_fin_sesion, CURTIME()),
                         tiempo_total_sesion = ?,
                         tiempo_pausas = ?,
                         tiempo_activo = ?,
                         estado = 'finalizada'
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$tiempo_total, $tiempo_pausas, $tiempo_activo, $sesion_id]);
            
        } catch (PDOException $e) {
            error_log("Error al finalizar sesión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de tiempo de un asesor
     * @param string $asesor_cedula
     * @param string $fecha_inicio
     * @param string $fecha_fin
     * @return array
     */
    public function obtenerEstadisticas($asesor_cedula, $fecha_inicio = null, $fecha_fin = null) {
        try {
            $where = "WHERE asesor_cedula = ?";
            $params = [$asesor_cedula];
            
            if ($fecha_inicio) {
                $where .= " AND fecha >= ?";
                $params[] = $fecha_inicio;
            }
            
            if ($fecha_fin) {
                $where .= " AND fecha <= ?";
                $params[] = $fecha_fin;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_sesiones,
                        SUM(tiempo_activo) as tiempo_activo_total,
                        SUM(tiempo_pausas) as tiempo_pausas_total,
                        AVG(tiempo_activo) as promedio_activo,
                        AVG(tiempo_pausas) as promedio_pausas
                     FROM " . $this->table_name . " 
                     " . $where . "
                     AND estado = 'finalizada'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
            return [
                'total_sesiones' => 0,
                'tiempo_activo_total' => 0,
                'tiempo_pausas_total' => 0,
                'promedio_activo' => 0,
                'promedio_pausas' => 0
            ];
        }
    }
    
    /**
     * Iniciar tiempo de gestión de un cliente
     * @param int $sesion_id
     * @param int $cliente_id
     * @return bool
     */
    public function iniciarGestion($sesion_id, $cliente_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET cliente_id = ?,
                         hora_inicio_gestion = NOW()
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$cliente_id, $sesion_id]);
            
        } catch (PDOException $e) {
            error_log("Error al iniciar gestión: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Finalizar tiempo de gestión de un cliente
     * @param int $sesion_id
     * @param int $tiempo_gestion_en_segundos
     * @return bool
     */
    public function finalizarGestion($sesion_id, $tiempo_gestion_en_segundos) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET hora_fin_gestion = NOW(),
                         tiempo_gestion = ?
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$tiempo_gestion_en_segundos, $sesion_id]);
            
        } catch (PDOException $e) {
            error_log("Error al finalizar gestión: " . $e->getMessage());
            return false;
        }
    }
}

?>

