<?php
/**
 * Funciones auxiliares para generación de CSV
 * Evita duplicación de código entre diferentes reportes
 */

/**
 * Función para limpiar y convertir texto a UTF-8
 * @param mixed $texto Texto a limpiar
 * @return string Texto limpio en UTF-8
 */
function limpiarUTF8($texto) {
    if ($texto === null || $texto === '') return '';
    
    // Convertir a string si no lo es
    $texto = (string)$texto;
    
    // Verificar y convertir encoding si es necesario
    if (!mb_check_encoding($texto, 'UTF-8')) {
        // Intentar detectar y convertir desde varios encodings comunes
        $detected = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected && $detected !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $detected);
        } else {
            // Si no se puede detectar, forzar conversión
            $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
        }
    }
    
    // Normalizar caracteres Unicode (NFC) si está disponible
    if (function_exists('normalizer_normalize')) {
        $texto = @normalizer_normalize($texto, Normalizer::FORM_C) ?: $texto;
    }
    
    // Limpiar caracteres de control excepto saltos de línea y tabuladores
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
    
    // Limpiar espacios en blanco al inicio y final
    $texto = trim($texto);
    
    return $texto;
}

/**
 * Función para escribir una fila CSV correctamente formateada
 * @param array $fila Array de valores para la fila
 * @return string Fila CSV formateada con salto de línea
 */
function escribirCSV($fila) {
    $valores = [];
    foreach ($fila as $valor) {
        // Limpiar UTF-8
        $valor = limpiarUTF8($valor);
        
        // Siempre encerrar en comillas si:
        // - Contiene comas, comillas, saltos de línea
        // - Contiene paréntesis (puede confundir a algunos parsers)
        // - Tiene espacios al inicio o final
        // - Es una cadena larga o compleja
        $necesita_comillas = (
            strpos($valor, ',') !== false || 
            strpos($valor, '"') !== false || 
            strpos($valor, "\n") !== false || 
            strpos($valor, "\r") !== false ||
            strpos($valor, '(') !== false ||
            strpos($valor, ')') !== false ||
            trim($valor) !== $valor ||
            strlen($valor) > 30
        );
        
        if ($necesita_comillas) {
            // Escapar comillas dobles duplicándolas (estándar CSV)
            $valor = str_replace('"', '""', $valor);
            $valor = '"' . $valor . '"';
        }
        $valores[] = $valor;
    }
    return implode(',', $valores) . "\r\n";
}

/**
 * Configurar headers HTTP para CSV con encoding UTF-8
 * @param string $filename Nombre del archivo
 */
function configurarHeadersCSV($filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
}

/**
 * Escribir BOM UTF-8 para Excel y otros programas
 */
function escribirBOMUTF8() {
    echo "\xEF\xBB\xBF";
}

/**
 * Generar CSV completo con encabezados y datos
 * @param array $encabezados Array de encabezados
 * @param array $datos Array de arrays con datos de cada fila
 * @param string $filename Nombre del archivo
 */
function generarCSV($encabezados, $datos, $filename) {
    // Configurar headers
    configurarHeadersCSV($filename);
    
    // Escribir BOM UTF-8 primero
    escribirBOMUTF8();
    
    // Limpiar y escribir encabezados
    $encabezados_limpios = array_map('limpiarUTF8', $encabezados);
    echo escribirCSV($encabezados_limpios);
    
    // Escribir datos
    foreach ($datos as $fila) {
        echo escribirCSV($fila);
    }
    
    exit();
}


