<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Administrador - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/coordinador-dashboard.css">
</head>
<body>

    <?php
    $action = 'admin_reportes';
    include __DIR__ . '/Navbar.php';
    ?>

    <div class="main-container">
        <?php include __DIR__ . '/Header.php'; ?>

        <section class="current-call-section">
            <div class="call-details">
                <h3>REPORTES DEL ADMINISTRADOR</h3>
                <p class="call-info">Sistema <?php echo APP_NAME; ?></p>
                <p class="call-info">Carga masiva de gestiones por CSV</p>
                <small>Validación por base activa, cliente y asesor con acceso</small>
                <div class="media-controls">
                    <button class="media-button" type="button" onclick="document.getElementById('csv_file').click()">
                        <i class="fas fa-file-upload"></i> Seleccionar CSV
                    </button>
                    <button class="media-button" type="button" onclick="desplazarAFormulario()">
                        <i class="fas fa-cloud-upload-alt"></i> Ir a Carga
                    </button>
                </div>
            </div>

            <div class="call-main-view">
                <div class="client-info">
                    <i class="fas fa-file-import"></i>
                    <div>
                        <span class="client-name">Centro de Carga de Gestiones</span>
                        <span class="client-company"><?php echo APP_NAME; ?> - Importación administrativa</span>
                    </div>
                </div>

                <div class="main-tabs">
                    <span class="active">REPORTES</span>
                </div>

                <div class="content-sections">
                    <div class="tab-content active" id="tab-reportes">
                        <div class="report-container">
                            <div class="report-header">
                                <h3 style="color: #2c3e50; margin-bottom: 10px;">
                                    <i class="fas fa-file-csv"></i> Cargar Reporte de Gestiones
                                </h3>
                                <p style="color: #7f8c8d; margin-bottom: 15px;">
                                    Seleccione una base activa y cargue un CSV para registrar gestiones históricas en la tabla <code>gestiones</code>.
                                </p>
                                <div style="background: #e8f4f8; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                                    <p style="margin: 0 0 8px 0; font-weight: 600; color: #2c3e50; font-size: 14px;">
                                        <i class="fas fa-info-circle"></i> Validaciones del cargue:
                                    </p>
                                    <ul style="margin: 0; padding-left: 20px; color: #495057; font-size: 13px;">
                                        <li>La base se selecciona arriba; el CSV no necesita la columna <strong>Base</strong>.</li>
                                        <li>Se cruza la cédula del cliente contra la base activa seleccionada.</li>
                                        <li>El asesor se cruza por coincidencia exacta con <code>usuarios.nombre_completo</code>.</li>
                                        <li>Solo se inserta si el asesor tiene acceso activo a esa base.</li>
                                        <li>La fecha se toma de la columna <code>fecha_gestion</code> o encabezados equivalentes del archivo.</li>
                                        <li>La columna <code>teléfono de contacto</code> se lee del CSV y se guarda en <code>gestiones.telefono_contacto</code>.</li>
                                        <li>Las filas que fallen se omiten y quedan reportadas al final.</li>
                                    </ul>
                                </div>
                            </div>

                            <div id="admin-reportes-formulario" class="date-range-card" style="background: #fff; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                <h5 style="color: #2c3e50; margin-bottom: 20px; font-size: 16px; font-weight: 600;">
                                    <i class="fas fa-database"></i> Datos de importación
                                </h5>

                                <form id="admin-reportes-form" enctype="multipart/form-data">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 20px;">
                                        <div>
                                            <label for="base_id" style="display: block; margin-bottom: 8px; color: #495057; font-weight: 600; font-size: 14px;">Base activa</label>
                                            <select id="base_id" name="base_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;" required>
                                                <option value="">Seleccione una base</option>
                                                <?php foreach (($bases_activas ?? []) as $base): ?>
                                                    <option value="<?php echo (int) $base['id']; ?>">
                                                        <?php echo htmlspecialchars($base['nombre']); ?> (<?php echo (int) ($base['total_clientes'] ?? 0); ?> clientes)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="csv_file" style="display: block; margin-bottom: 8px; color: #495057; font-weight: 600; font-size: 14px;">Archivo CSV</label>
                                            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: #fff;" required>
                                            <div id="admin-reportes-archivo-info" style="margin-top: 10px; padding: 12px 14px; border: 1px dashed #cfd4da; border-radius: 8px; background: #f8f9fa; color: #495057; font-size: 13px;">
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <i class="fas fa-file-csv" style="color:#28a745;"></i>
                                                    <div style="min-width:0;">
                                                        <div style="font-weight:700; color:#2c3e50; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                            Ningún archivo seleccionado
                                                        </div>
                                                        <div style="color:#6c757d; margin-top:2px;">
                                                            Selecciona un CSV para ver el nombre aquí.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 20px;">
                                        <p style="margin: 0 0 10px 0; font-weight: 600; color: #2c3e50;">
                                            Encabezados esperados del CSV
                                        </p>
                                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                            <span class="status-badge active">fecha_gestion</span>
                                            <span class="status-badge active">asesor</span>
                                            <span class="status-badge active">cc</span>
                                            <span class="status-badge active">canal de contacto</span>
                                            <span class="status-badge active">teléfono de contacto</span>
                                            <span class="status-badge active">obligación</span>
                                            <span class="status-badge active">nivel 1 / 2 / 3</span>
                                            <span class="status-badge active">fecha pago</span>
                                            <span class="status-badge active">valor pago</span>
                                            <span class="status-badge active">canales autorizados</span>
                                            <span class="status-badge active">duración gestión</span>
                                            <span class="status-badge active">observaciones</span>
                                        </div>
                                    </div>

                                    <div style="text-align: center;">
                                        <button id="btn-importar-gestiones" type="submit" style="padding: 15px 40px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 16px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,123,255,0.3);">
                                            <i class="fas fa-cloud-upload-alt"></i> Validar e Importar CSV
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div id="admin-reportes-estado" style="display: none; background: #fff3cd; color: #856404; padding: 15px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeeba;"></div>

                            <div id="admin-reportes-resultado" style="display: none; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                <h4 style="margin: 0 0 20px 0; color: #2c3e50;">
                                    <i class="fas fa-clipboard-check"></i> Resumen de importación
                                </h4>

                                <div id="admin-reportes-resumen" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;"></div>

                                <div id="admin-reportes-errores-wrapper" style="display: none;">
                                    <h5 style="margin: 0 0 12px 0; color: #2c3e50;">Filas omitidas</h5>
                                    <div style="overflow-x: auto;">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="background: #f8f9fa;">
                                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef;">Fila</th>
                                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef;">CC Cliente</th>
                                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef;">Asesor</th>
                                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef;">Motivo</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-reportes-errores"></tbody>
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

    <?php $v_admin_reportes = @filemtime(__DIR__ . '/../assets/js/admin-reportes.js') ?: (defined('APP_VERSION') ? APP_VERSION : time()); ?>
    <script src="assets/js/admin-reportes.js?v=<?php echo urlencode((string) $v_admin_reportes); ?>"></script>
</body>
</html>
