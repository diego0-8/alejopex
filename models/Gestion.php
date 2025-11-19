<?php
require_once 'config.php';

class Gestion {
    
    /**
     * Crear una nueva gestión
     */
    public static function crear($data) {
        try {
            $conn = getDBConnection();
            
            $sql = "INSERT INTO gestiones (
                asesor_cedula,
                cliente_id,
                canal_contacto,
                contrato_id,
                nivel1_tipo,
                nivel2_clasificacion,
                nivel3_detalle,
                observaciones,
                llamada_telefonica,
                whatsapp,
                correo_electronico,
                sms,
                correo_fisico,
                mensajeria_aplicacion,
                duracion_segundos,
                fecha_pago,
                valor_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $params = [
                $data['asesor_cedula'],
                $data['cliente_id'],
                $data['canal_contacto'] ?? null,
                $data['contrato_id'] ?? null,
                (!empty($data['nivel1_tipo']) && trim($data['nivel1_tipo']) !== '') ? trim($data['nivel1_tipo']) : null,
                (!empty($data['nivel2_clasificacion']) && trim($data['nivel2_clasificacion']) !== '') ? trim($data['nivel2_clasificacion']) : null,
                (!empty($data['nivel3_detalle']) && trim($data['nivel3_detalle']) !== '') ? trim($data['nivel3_detalle']) : null,
                $data['observaciones'] ?? null,
                $data['llamada_telefonica'] ?? 'no',
                $data['whatsapp'] ?? 'no',
                $data['correo_electronico'] ?? 'no',
                $data['sms'] ?? 'no',
                $data['correo_fisico'] ?? 'no',
                $data['mensajeria_aplicacion'] ?? 'no',
                $data['duracion_segundos'] ?? 0,
                $data['fecha_pago'] ?? null,
                $data['valor_pago'] ?? null
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Gestión guardada exitosamente',
                    'id' => $conn->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al guardar la gestión'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener gestiones de un asesor para un cliente
     */
    public static function obtenerGestionesCliente($cliente_id, $asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Optimizado con índices: usa idx_gestiones_cliente y idx_gestiones_asesor_fecha
            $sql = "SELECT * FROM gestiones 
                    WHERE cliente_id = ? AND asesor_cedula = ?
                    ORDER BY fecha_creacion DESC
                    LIMIT 100";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cliente_id, $asesor_cedula]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener historial de gestiones de un cliente
     */
    public static function obtenerHistorial($cliente_id) {
        try {
            $conn = getDBConnection();
            
            // Optimizado: usa idx_gestiones_cliente y limita resultados
            $sql = "SELECT g.*, u.nombre_completo as asesor_nombre 
                    FROM gestiones g
                    INNER JOIN usuarios u ON CAST(g.asesor_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                    WHERE g.cliente_id = ?
                    ORDER BY g.fecha_creacion DESC
                    LIMIT 100";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cliente_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
}

?>
