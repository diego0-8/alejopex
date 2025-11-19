<?php
require_once 'config.php';

class Cliente {
    private $conn;
    private $table_name = "clientes";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear un nuevo cliente
     * @param array $data Datos del cliente
     * @return array Resultado de la operación
     */
    public function crear($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (base_id, `TIPO DE IDENTIFICACION`, `IDENTIFICACION`, `NOMBRE CONTRATANTE`, `CIUDAD`, 
                      `TEL 1`, `TEL 2`, `TEL 3`, `TEL 4`, `EMAIL CONTRATANTE`) 
                     VALUES (:base_id, :tipo_id, :identificacion, :nombre, :ciudad, :tel1, :tel2, :tel3, :tel4, :email)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':base_id', $data['base_id']);
            $stmt->bindParam(':tipo_id', $data['tipo_identificacion']);
            $stmt->bindParam(':identificacion', $data['identificacion']);
            $stmt->bindParam(':nombre', $data['nombre_completo']);
            $stmt->bindParam(':ciudad', $data['ciudad']);
            $stmt->bindParam(':tel1', $data['tel1']);
            $stmt->bindParam(':tel2', $data['tel2']);
            $stmt->bindParam(':tel3', $data['tel3']);
            $stmt->bindParam(':tel4', $data['tel4']);
            $stmt->bindParam(':email', $data['email']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Cliente creado exitosamente',
                    'id' => $this->conn->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear cliente'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener cliente por identificación
     * @param string $identificacion
     * @return array|false
     */
    public function obtenerPorIdentificacion($identificacion) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE `IDENTIFICACION` = :identificacion";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':identificacion', $identificacion);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener cliente: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los clientes
     * @return array
     */
    public function obtenerTodos() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY `FECHA CREACION` DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener clientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de clientes
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            // Obtener total de clientes
            $query_total = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt_total = $this->conn->prepare($query_total);
            $stmt_total->execute();
            $total_clientes = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener clientes gestionados (con al menos una gestión)
            $query_gestionados = "SELECT COUNT(DISTINCT cliente_id) as total FROM gestiones";
            $stmt_gestionados = $this->conn->prepare($query_gestionados);
            $stmt_gestionados->execute();
            $clientes_gestionados = $stmt_gestionados->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Calcular pendientes
            $clientes_pendientes = $total_clientes - $clientes_gestionados;
            
            // Obtener clientes nuevos (últimos 30 días)
            $query_nuevos = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                            WHERE `FECHA CREACION` >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt_nuevos = $this->conn->prepare($query_nuevos);
            $stmt_nuevos->execute();
            $clientes_nuevos = $stmt_nuevos->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'total_clientes' => $total_clientes,
                'clientes_activos' => $total_clientes, // Todos los clientes están activos por defecto
                'clientes_inactivos' => 0,
                'clientes_gestionados' => $clientes_gestionados,
                'clientes_pendientes' => $clientes_pendientes,
                'clientes_nuevos' => $clientes_nuevos
            ];
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
            return [
                'total_clientes' => 0,
                'clientes_activos' => 0,
                'clientes_inactivos' => 0,
                'clientes_gestionados' => 0,
                'clientes_pendientes' => 0,
                'clientes_nuevos' => 0
            ];
        }
    }

    /**
     * Validar número de teléfono
     * @param string $telefono
     * @return bool
     */
    private function validarTelefono($telefono) {
        // Limpiar el teléfono
        $telefono = trim($telefono);
        
        // Si es 0 o vacío, no es válido
        if ($telefono === '0' || empty($telefono)) {
            return false;
        }
        
        // Debe tener más de 4 dígitos
        $digitos = preg_replace('/[^0-9]/', '', $telefono);
        return strlen($digitos) > 4;
    }

    /**
     * Procesar y limpiar datos del CSV
     * @param array $row Fila del CSV
     * @return array Datos procesados
     */
    public function procesarDatosCSV($row) {
        // Mapeo de campos del CSV a la base de datos
        $data = [
            'tipo_identificacion' => trim($row['TIPO DOCUMENTO'] ?? ''),
            'identificacion' => trim($row['IDENTIFICACION'] ?? ''),
            'nombre_completo' => trim($row['NOMBRE CONTRATANTE'] ?? ''),
            'ciudad' => trim($row['CIUDAD'] ?? ''),
            'email' => trim($row['EMAIL CONTRATANTE'] ?? '')
        ];

        // Procesar teléfonos - solo guardar los que tengan más de 4 dígitos
        $telefonos = [
            'tel1' => trim($row['TEL1'] ?? ''),
            'tel2' => trim($row['TEL2'] ?? ''),
            'tel3' => trim($row['TEL3'] ?? ''),
            'tel4' => trim($row['TEL4'] ?? '')
        ];

        $telefonos_validos = [];
        foreach ($telefonos as $key => $telefono) {
            if ($this->validarTelefono($telefono)) {
                $telefonos_validos[$key] = $telefono;
            } else {
                $telefonos_validos[$key] = null;
            }
        }

        $data = array_merge($data, $telefonos_validos);

        return $data;
    }
}
?>
