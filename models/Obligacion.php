<?php
require_once 'config.php';

class Obligacion {
    private $conn;
    private $table_name = 'obligaciones';

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear una nueva obligación
     */
    public function crear($datos) {
        try {
            $query = "INSERT INTO {$this->table_name} (
                numero_obligacion, cliente_id, dias_mora, franja, valor_obligacion, estado
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([
                $datos['numero_obligacion'],
                $datos['cliente_id'],
                $datos['dias_mora'] ?? 0,
                $datos['franja'],
                $datos['valor_obligacion'] ?? 0.00,
                $datos['estado'] ?? 'vigente'
            ]);

            if ($resultado) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error al crear obligación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener obligación por número
     */
    public function obtenerPorNumero($numero_obligacion) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente, 
                     c.`TEL 1` as tel, c.`EMAIL CONTRATANTE` as email 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.numero_obligacion = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$numero_obligacion]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligación por número: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener obligación por ID
     */
    public function obtenerPorId($id) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente, 
                     c.`TEL 1` as tel, c.`EMAIL CONTRATANTE` as email 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligación por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las obligaciones
     */
    public function obtenerTodos($estado = null, $limite = null, $offset = 0) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id";
            $params = [];

            if ($estado) {
                $query .= " WHERE o.estado = ?";
                $params[] = $estado;
            }

            $query .= " ORDER BY o.fecha_creacion DESC";

            if ($limite) {
                $query .= " LIMIT ?";
                $params[] = $limite;
                
                if ($offset > 0) {
                    $query .= " OFFSET ?";
                    $params[] = $offset;
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener todas las obligaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener obligaciones por cliente
     */
    public function obtenerPorCliente($cliente_id, $estado = null, $limite = null, $offset = 0) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.cliente_id = ?";
            $params = [$cliente_id];

            if ($estado) {
                $query .= " AND o.estado = ?";
                $params[] = $estado;
            }

            $query .= " ORDER BY o.dias_mora DESC";

            if ($limite) {
                $query .= " LIMIT ? OFFSET ?";
                $params[] = $limite;
                $params[] = $offset;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligaciones por cliente: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las obligaciones con filtros
     */
    public function obtenerTodas($filtros = [], $limite = null, $offset = 0) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente, 
                     c.`TEL 1` as tel, c.`EMAIL CONTRATANTE` as email 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id";
            $params = [];
            $condiciones = [];

            // Aplicar filtros
            if (!empty($filtros['estado'])) {
                $condiciones[] = "o.estado = ?";
                $params[] = $filtros['estado'];
            }

            if (!empty($filtros['franja'])) {
                $condiciones[] = "o.franja = ?";
                $params[] = $filtros['franja'];
            }

            if (!empty($filtros['dias_mora_min'])) {
                $condiciones[] = "o.dias_mora >= ?";
                $params[] = $filtros['dias_mora_min'];
            }

            if (!empty($filtros['dias_mora_max'])) {
                $condiciones[] = "o.dias_mora <= ?";
                $params[] = $filtros['dias_mora_max'];
            }

            if (!empty($filtros['cliente_id'])) {
                $condiciones[] = "o.cliente_id = ?";
                $params[] = $filtros['cliente_id'];
            }

            if (!empty($filtros['buscar'])) {
                $condiciones[] = "(o.numero_obligacion LIKE ? OR c.`NOMBRE CONTRATANTE` LIKE ? OR c.IDENTIFICACION LIKE ?)";
                $buscar = "%{$filtros['buscar']}%";
                $params[] = $buscar;
                $params[] = $buscar;
                $params[] = $buscar;
            }

            if (!empty($condiciones)) {
                $query .= " WHERE " . implode(' AND ', $condiciones);
            }

            $query .= " ORDER BY o.dias_mora DESC";

            if ($limite) {
                $query .= " LIMIT ? OFFSET ?";
                $params[] = $limite;
                $params[] = $offset;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligaciones: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar obligación
     */
    public function actualizar($id, $datos) {
        try {
            $campos = [];
            $params = [];

            foreach ($datos as $campo => $valor) {
                if (in_array($campo, ['dias_mora', 'franja', 'valor_obligacion', 'estado'])) {
                    $campos[] = "{$campo} = ?";
                    $params[] = $valor;
                }
            }

            if (empty($campos)) {
                return false;
            }

            $params[] = $id;
            $query = "UPDATE {$this->table_name} SET " . implode(', ', $campos) . " WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error al actualizar obligación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cambiar estado de obligación
     */
    public function cambiarEstado($id, $nuevo_estado) {
        try {
            $query = "UPDATE {$this->table_name} SET estado = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$nuevo_estado, $id]);
        } catch (PDOException $e) {
            error_log("Error al cambiar estado de obligación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar obligación (soft delete)
     */
    public function eliminar($id) {
        try {
            $query = "UPDATE {$this->table_name} SET estado = 'cancelada' WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error al eliminar obligación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de obligaciones
     */
    public function obtenerEstadisticas($cliente_id = null) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_obligaciones,
                        SUM(CASE WHEN estado = 'vigente' THEN 1 ELSE 0 END) as obligaciones_vigentes,
                        SUM(CASE WHEN estado = 'vencida' THEN 1 ELSE 0 END) as obligaciones_vencidas,
                        SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as obligaciones_pagadas,
                        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as obligaciones_canceladas,
                        SUM(valor_obligacion) as valor_total_obligaciones,
                        SUM(CASE WHEN dias_mora > 0 THEN valor_obligacion ELSE 0 END) as valor_en_mora,
                        AVG(dias_mora) as promedio_dias_mora,
                        MAX(dias_mora) as max_dias_mora,
                        MIN(dias_mora) as min_dias_mora
                     FROM {$this->table_name}";
            
            $params = [];
            if ($cliente_id) {
                $query .= " WHERE cliente_id = ?";
                $params[] = $cliente_id;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de obligaciones: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener obligaciones por franja de mora
     */
    public function obtenerPorFranja($franja, $limite = null) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.franja = ? AND o.estado != 'pagada' AND o.estado != 'cancelada'
                     ORDER BY o.valor_obligacion DESC";
            
            if ($limite) {
                $query .= " LIMIT ?";
            }
            
            $stmt = $this->conn->prepare($query);
            
            if ($limite) {
                $stmt->execute([$franja, $limite]);
            } else {
                $stmt->execute([$franja]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligaciones por franja: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener obligaciones con mayor mora
     */
    public function obtenerMayorMora($limite = 10) {
        try {
            $query = "SELECT o.*, c.IDENTIFICACION as nit_cxc, c.`NOMBRE CONTRATANTE` as nombre_cliente 
                     FROM {$this->table_name} o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.dias_mora > 0 AND o.estado != 'pagada' AND o.estado != 'cancelada'
                     ORDER BY o.dias_mora DESC, o.valor_obligacion DESC
                     LIMIT ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener obligaciones con mayor mora: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar estado de obligaciones basado en días de mora
     */
    public function actualizarEstadosPorMora() {
        try {
            $query = "UPDATE {$this->table_name} 
                     SET estado = CASE 
                         WHEN dias_mora < 0 THEN 'vigente'
                         WHEN dias_mora = 0 THEN 'vigente'
                         WHEN dias_mora > 0 THEN 'vencida'
                         ELSE estado
                     END,
                     fecha_actualizacion = CURRENT_TIMESTAMP
                     WHERE estado != 'pagada' AND estado != 'cancelada'";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar estados por mora: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar datos de la obligación
     */
    public function validarDatos($datos) {
        $errores = [];

        if (empty($datos['numero_obligacion'])) {
            $errores[] = 'El número de obligación es obligatorio';
        } elseif (strlen($datos['numero_obligacion']) < 5) {
            $errores[] = 'El número de obligación debe tener al menos 5 caracteres';
        }

        if (empty($datos['cliente_id'])) {
            $errores[] = 'El cliente es obligatorio';
        }

        if (!isset($datos['dias_mora']) || !is_numeric($datos['dias_mora'])) {
            $errores[] = 'Los días de mora deben ser un número válido';
        }

        if (empty($datos['franja'])) {
            $errores[] = 'La franja es obligatoria';
        }

        if (!isset($datos['valor_obligacion']) || !is_numeric($datos['valor_obligacion']) || $datos['valor_obligacion'] < 0) {
            $errores[] = 'El valor de la obligación debe ser un número positivo';
        }

        return $errores;
    }

    /**
     * Verificar si existe una obligación con el mismo número
     */
    public function existeNumero($numero_obligacion, $excluir_id = null) {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE numero_obligacion = ?";
            $params = [$numero_obligacion];

            if ($excluir_id) {
                $query .= " AND id != ?";
                $params[] = $excluir_id;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar número de obligación existente: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Procesar datos desde CSV
     */
    public function procesarDesdeCSV($datos_csv) {
        try {
            $this->conn->beginTransaction();
            $obligaciones_creadas = 0;
            $errores = [];

            foreach ($datos_csv as $fila) {
                // Buscar o crear cliente
                $cliente_model = new Cliente();
                $cliente = $cliente_model->obtenerPorIdentificacion($fila['identificacion']);
                
                if (!$cliente) {
                    $cliente_data = [
                        'base_id' => $fila['base_id'] ?? null,
                        'tipo_identificacion' => $fila['tipo_documento'] ?? '',
                        'identificacion' => $fila['identificacion'],
                        'nombre_completo' => $fila['nombre_cliente'],
                        'ciudad' => $fila['ciudad'] ?? '',
                        'tel1' => $fila['tel1'] ?? null,
                        'tel2' => $fila['tel2'] ?? null,
                        'tel3' => $fila['tel3'] ?? null,
                        'tel4' => $fila['tel4'] ?? null,
                        'email' => $fila['email'] ?? null
                    ];
                    $resultado_cliente = $cliente_model->crear($cliente_data);
                    
                    if (!$resultado_cliente['success']) {
                        $errores[] = "Error al crear cliente: {$fila['identificacion']}";
                        continue;
                    }
                    $cliente_id = $resultado_cliente['id'];
                } else {
                    $cliente_id = $cliente['id'];
                }

                // Crear obligación
                $obligacion_id = $this->crear([
                    'numero_obligacion' => $fila['numero_obligacion'],
                    'cliente_id' => $cliente_id,
                    'dias_mora' => $fila['dias_mora'],
                    'franja' => $fila['franja'],
                    'valor_obligacion' => $fila['valor_obligacion']
                ]);

                if ($obligacion_id) {
                    $obligaciones_creadas++;
                } else {
                    $errores[] = "Error al crear obligación: {$fila['numero_obligacion']}";
                }
            }

            $this->conn->commit();
            return [
                'success' => true,
                'obligaciones_creadas' => $obligaciones_creadas,
                'errores' => $errores
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al procesar CSV: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

