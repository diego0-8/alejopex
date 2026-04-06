document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('admin-reportes-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', manejarImportacionGestiones);

    const fileInput = document.getElementById('csv_file');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
            renderizarArchivoSeleccionado(file);
        });
        const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
        renderizarArchivoSeleccionado(file);
    }
});

function desplazarAFormulario() {
    const formulario = document.getElementById('admin-reportes-formulario');
    if (formulario) {
        formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderizarArchivoSeleccionado(file) {
    const container = document.getElementById('admin-reportes-archivo-info');
    if (!container) {
        return;
    }

    if (!file) {
        container.innerHTML = `
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
        `;
        return;
    }

    const sizeKb = Math.max(1, Math.round(file.size / 1024));
    container.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
            <i class="fas fa-file-csv" style="color:#28a745;"></i>
            <div style="min-width:0;">
                <div title="${escapeHtml(file.name)}" style="font-weight:700; color:#2c3e50; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    ${escapeHtml(file.name)}
                </div>
                <div style="color:#6c757d; margin-top:2px;">
                    ${(escapeHtml(file.type || 'text/csv'))} · ${escapeHtml(String(sizeKb))} KB
                </div>
            </div>
        </div>
    `;
}

async function manejarImportacionGestiones(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const btn = document.getElementById('btn-importar-gestiones');
    const estado = document.getElementById('admin-reportes-estado');
    const resultado = document.getElementById('admin-reportes-resultado');
    const baseId = document.getElementById('base_id')?.value || '';
    const fileInput = document.getElementById('csv_file');

    if (!baseId) {
        mostrarEstado('Debe seleccionar una base activa antes de importar.', 'warning');
        return;
    }

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        mostrarEstado('Debe seleccionar un archivo CSV.', 'warning');
        return;
    }

    const formData = new FormData(form);
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
    resultado.style.display = 'none';
    mostrarEstado('Validando archivo y cruzando registros contra la base seleccionada...', 'info');

    try {
        const response = await fetch('index.php?action=admin_importar_gestiones_csv', {
            method: 'POST',
            body: formData
        });

        const raw = await response.text();
        let data = null;
        try {
            data = raw ? JSON.parse(raw) : null;
        } catch (parseError) {
            console.error('Respuesta no-JSON del servidor', {
                status: response.status,
                statusText: response.statusText,
                redirected: response.redirected,
                url: response.url,
                preview: raw ? raw.slice(0, 400) : ''
            });
            throw new Error(`El servidor no devolvió un JSON válido (HTTP ${response.status}).`);
        }

        if (!data) {
            console.error('Respuesta vacía del servidor', {
                status: response.status,
                statusText: response.statusText,
                redirected: response.redirected,
                url: response.url
            });
            throw new Error(`El servidor devolvió una respuesta vacía (HTTP ${response.status}).`);
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo completar la importación.');
        }

        renderizarResultadoImportacion(data);
        mostrarEstado(data.message || 'Importación completada.', 'success');
    } catch (error) {
        console.error('admin-reportes.js: Error importando CSV de gestiones', error);
        mostrarEstado(error.message || 'Error inesperado al importar el CSV.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Validar e Importar CSV';
    }
}

function mostrarEstado(message, type) {
    const estado = document.getElementById('admin-reportes-estado');
    if (!estado) {
        return;
    }

    const styles = {
        info: {
            background: '#e8f4f8',
            color: '#0c5460',
            border: '#bee5eb'
        },
        success: {
            background: '#d4edda',
            color: '#155724',
            border: '#c3e6cb'
        },
        warning: {
            background: '#fff3cd',
            color: '#856404',
            border: '#ffeeba'
        },
        error: {
            background: '#f8d7da',
            color: '#721c24',
            border: '#f5c6cb'
        }
    };

    const style = styles[type] || styles.info;
    estado.style.display = 'block';
    estado.style.background = style.background;
    estado.style.color = style.color;
    estado.style.border = `1px solid ${style.border}`;
    estado.textContent = message;
}

function renderizarResultadoImportacion(data) {
    const resultado = document.getElementById('admin-reportes-resultado');
    const resumen = document.getElementById('admin-reportes-resumen');
    const erroresWrapper = document.getElementById('admin-reportes-errores-wrapper');
    const erroresBody = document.getElementById('admin-reportes-errores');

    if (!resultado || !resumen || !erroresWrapper || !erroresBody) {
        return;
    }

    const cards = [
        { label: 'Base', value: data.base?.nombre || 'N/A', color: '#007bff' },
        { label: 'Filas leídas', value: data.total_filas ?? 0, color: '#6f42c1' },
        { label: 'Insertadas', value: data.insertadas ?? 0, color: '#28a745' },
        { label: 'Omitidas', value: data.omitidas ?? 0, color: '#dc3545' },
        { label: 'Vacías', value: data.filas_vacias ?? 0, color: '#fd7e14' }
    ];

    resumen.innerHTML = cards.map(card => `
        <div style="padding: 18px; border-radius: 10px; background: #f8f9fa; border-left: 5px solid ${card.color};">
            <div style="font-size: 13px; color: #6c757d; margin-bottom: 6px;">${escapeHtml(card.label)}</div>
            <div style="font-size: 20px; font-weight: 700; color: #2c3e50;">${escapeHtml(String(card.value))}</div>
        </div>
    `).join('');

    const errores = Array.isArray(data.errores) ? data.errores : [];
    if (errores.length > 0) {
        erroresWrapper.style.display = 'block';
        erroresBody.innerHTML = errores.map(error => `
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #f1f3f5;">${escapeHtml(String(error.fila ?? ''))}</td>
                <td style="padding: 12px; border-bottom: 1px solid #f1f3f5;">${escapeHtml(String(error.cedula_cliente ?? ''))}</td>
                <td style="padding: 12px; border-bottom: 1px solid #f1f3f5;">${escapeHtml(String(error.asesor ?? ''))}</td>
                <td style="padding: 12px; border-bottom: 1px solid #f1f3f5;">${escapeHtml(String(error.motivo ?? ''))}</td>
            </tr>
        `).join('');
    } else {
        erroresWrapper.style.display = 'none';
        erroresBody.innerHTML = '';
    }

    resultado.style.display = 'block';
    resultado.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
