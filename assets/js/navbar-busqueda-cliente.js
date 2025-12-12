/**
 * Script para búsqueda de clientes desde el navbar
 * Funciona en todas las vistas del asesor
 */

// Modal de búsqueda (se crea dinámicamente si no existe)
let modalBusquedaNavbar = null;

/**
 * Abrir modal de búsqueda desde el navbar
 */
function abrirBusquedaClienteNavbar() {
    // Crear modal si no existe
    if (!modalBusquedaNavbar) {
        crearModalBusqueda();
    }
    
    // Mostrar modal
    if (modalBusquedaNavbar) {
        modalBusquedaNavbar.style.display = 'flex';
        const input = modalBusquedaNavbar.querySelector('#navbar-busqueda-input');
        if (input) {
            input.value = '';
            input.focus();
        }
        // Limpiar resultados anteriores
        const resultados = modalBusquedaNavbar.querySelector('#navbar-resultados-busqueda');
        if (resultados) {
            resultados.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-search"></i>
                    <p>Ingrese CC o celular para buscar</p>
                </div>
            `;
        }
    }
}

/**
 * Cerrar modal de búsqueda
 */
function cerrarBusquedaNavbar() {
    if (modalBusquedaNavbar) {
        modalBusquedaNavbar.style.display = 'none';
    }
}

/**
 * Crear el modal de búsqueda dinámicamente
 */
function crearModalBusqueda() {
    // Verificar si ya existe
    const existente = document.getElementById('modal-busqueda-navbar');
    if (existente) {
        modalBusquedaNavbar = existente;
        return;
    }
    
    // Crear modal
    const modal = document.createElement('div');
    modal.id = 'modal-busqueda-navbar';
    modal.style.cssText = `
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 10003;
        justify-content: center;
        align-items: center;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #007bff;">
                    <i class="fas fa-search"></i> Buscar Cliente
                </h3>
                <button onclick="cerrarBusquedaNavbar()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="navbar-busqueda-input" style="display: block; margin-bottom: 8px; color: #666; font-size: 14px;">CC o Celular:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" 
                           id="navbar-busqueda-input" 
                           placeholder="Ingrese CC o celular..." 
                           style="flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;"
                           onkeypress="if(event.key === 'Enter') buscarClienteNavbar();">
                    <button onclick="buscarClienteNavbar()" 
                            style="padding: 12px 20px; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <!-- Resultados de búsqueda -->
            <div id="navbar-resultados-busqueda" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; background: #f8f9fa;">
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-search"></i>
                    <p>Ingrese CC o celular para buscar</p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modalBusquedaNavbar = modal;
    
    // Cerrar al hacer clic fuera del modal
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            cerrarBusquedaNavbar();
        }
    });
}

/**
 * Buscar cliente desde el navbar
 */
async function buscarClienteNavbar() {
    const termino = document.getElementById('navbar-busqueda-input')?.value.trim();
    const resultadosDiv = document.getElementById('navbar-resultados-busqueda');
    
    if (!termino) {
        alert('Por favor ingrese CC o celular');
        return;
    }
    
    if (!resultadosDiv) {
        console.error('No se encontró el contenedor de resultados');
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
                criterio: 'mixto' // Busca en CC, nombre y celular
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.clientes && data.clientes.length > 0) {
            let html = '';
            data.clientes.forEach(cliente => {
                const clienteId = cliente.ID_CLIENTE || cliente.ID_COMERCIO || cliente.id;
                const nombreCliente = cliente['NOMBRE CONTRATANTE'] || cliente.nombre || cliente.NOMBRE_CLIENTE || 'N/A';
                const cc = cliente.IDENTIFICACION || cliente.cc || cliente.ID_CLIENTE || 'N/A';
                const celular = cliente.CELULAR || cliente.CEL || cliente['TEL 1'] || cliente.cel || 'N/A';
                
                html += `
                    <div style="padding: 15px; border-bottom: 1px solid #dee2e6; cursor: pointer; transition: background 0.2s;" 
                         onmouseover="this.style.background='#e9ecef'" 
                         onmouseout="this.style.background='transparent'"
                         onclick="gestionarClienteNavbar('${clienteId}')">
                        <div style="font-weight: 600; color: #333; margin-bottom: 5px;">
                            ${nombreCliente}
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            <div><i class="fas fa-id-card"></i> CC: ${cc}</div>
                            <div><i class="fas fa-phone"></i> Celular: ${celular}</div>
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

/**
 * Redirigir a gestionar cliente desde el navbar
 * Si estamos en la vista de gestión, cambia sin recargar para no perder la llamada
 */
function gestionarClienteNavbar(clienteId) {
    cerrarBusquedaNavbar();
    
    // Verificar si estamos en la vista de gestión del asesor
    const urlParams = new URLSearchParams(window.location.search);
    const currentAction = urlParams.get('action');
    
    // Si estamos en la vista de gestión, usar cambio sin recargar
    if (currentAction === 'asesor_gestionar' && typeof window.cambiarClienteSinRecargar === 'function') {
        console.log('Navbar-busqueda: Cambiando cliente sin recargar para mantener la llamada');
        window.cambiarClienteSinRecargar(clienteId);
    } else {
        // Si no estamos en la vista de gestión, usar redirección normal
        console.log('Navbar-busqueda: Redirigiendo a vista de gestión');
        window.location.href = `index.php?action=asesor_gestionar&cliente_id=${clienteId}`;
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // El modal se creará cuando se necesite
    });
} else {
    // DOM ya está listo
}

