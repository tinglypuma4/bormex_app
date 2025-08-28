/**
 * Dashboard BORMEX - JavaScript Principal
 * Sistema de gestión de notas y clientes
 */

// ===================
// VARIABLES GLOBALES
// ===================
let currentPage = 'dashboard';
let sidebarOpen = false;
let currentDropdown = null;

// ===================
// INICIALIZACIÓN
// ===================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando Dashboard BORMEX...');
    
    // Inicializar componentes
    initializeNavigation();
    initializeSearch();
    initializeKeyboardShortcuts();
    initializeResponsive();
    initializeNotifications();
    
    // Actualizar timer de sesión cada minuto
    updateSessionTimer();
    setInterval(updateSessionTimer, 60000);
    
    console.log('Dashboard BORMEX inicializado correctamente');
});

// ===================
// NAVEGACIÓN
// ===================

/**
 * Mostrar página específica
 * @param {string} pageId - ID de la página a mostrar
 */
function showPage(pageId) {
    // Validar si es una navegación externa
    if (pageId === 'notas-nueva') {
        goToNewNote();
        return;
    }
    
    // Ocultar todas las páginas
    document.querySelectorAll('.page-content').forEach(page => {
        page.classList.remove('active');
    });
    
    // Mostrar página seleccionada
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
        currentPage = pageId;
        
        // Actualizar URL sin recargar página
        const newUrl = `${window.location.pathname}?page=${pageId}`;
        window.history.pushState({page: pageId}, '', newUrl);
        
        // Actualizar navegación
        setActiveNavItem(pageId);
        
        // Cerrar sidebar en móvil
        if (window.innerWidth <= 992) {
            closeSidebar();
        }
        
        // Ejecutar callbacks específicos de página
        onPageLoad(pageId);
    } else {
        console.warn(`Página ${pageId} no encontrada`);
        showNotification('Página no encontrada', 'error');
    }
}

/**
 * Establecer elemento activo en navegación
 * @param {string} pageId - ID de la página activa
 */
function setActiveNavItem(pageId) {
    // Remover clase active de todos los links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Agregar clase active al link correspondiente
    const activeLink = document.querySelector(`.nav-link[data-page="${pageId}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}

/**
 * Inicializar navegación y estado inicial
 */
function initializeNavigation() {
    // Verificar URL para página inicial
    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = urlParams.get('page') || 'dashboard';
    
    if (initialPage !== 'dashboard') {
        showPage(initialPage);
    }
    
    // Manejar botón atrás del navegador
    window.addEventListener('popstate', function(event) {
        const page = event.state?.page || 'dashboard';
        showPage(page);
    });
}

/**
 * Callbacks específicos cuando se carga una página
 * @param {string} pageId - ID de la página cargada
 */
function onPageLoad(pageId) {
    switch (pageId) {
        case 'dashboard':
            // Recargar estadísticas del dashboard
            loadDashboardStats();
            break;
        case 'notas-historial':
            // Cargar historial de notas
            loadNotesHistory();
            break;
        case 'clientes':
            // Cargar lista de clientes
            loadClientsList();
            break;
        // Agregar más casos según sea necesario
    }
}

/**
 * Navegar a nueva nota - RUTA CORREGIDA CON BASE_URL
 */
function goToNewNote() {
    // Usar la BASE_URL definida en config.php
    window.location.href = '/bormex_app/public_html/pages/notas/nueva.php';
}

// ===================
// SIDEBAR
// ===================

/**
 * Toggle del sidebar
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar) {
        sidebar.classList.toggle('open');
        sidebarOpen = sidebar.classList.contains('open');
        
        // Mostrar/ocultar overlay en móvil
        if (overlay) {
            overlay.style.display = sidebarOpen ? 'block' : 'none';
        }
        
        // Agregar clase al body para animaciones
        document.body.classList.toggle('sidebar-open', sidebarOpen);
    }
}

/**
 * Cerrar sidebar
 */
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar) {
        sidebar.classList.remove('open');
        sidebarOpen = false;
        
        if (overlay) {
            overlay.style.display = 'none';
        }
        
        document.body.classList.remove('sidebar-open');
    }
}

// ===================
// DROPDOWNS
// ===================

/**
 * Toggle notificaciones
 */
function toggleNotifications() {
    toggleDropdown('notificationMenu');
}

/**
 * Toggle menú de usuario
 */
function toggleUserMenu() {
    toggleDropdown('userMenu');
}

/**
 * Toggle dropdown genérico
 * @param {string} dropdownId - ID del dropdown
 */
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    const isOpen = dropdown.classList.contains('show');
    
    // Cerrar todos los dropdowns
    closeAllDropdowns();
    
    // Abrir el dropdown seleccionado si no estaba abierto
    if (!isOpen) {
        dropdown.classList.add('show');
        currentDropdown = dropdownId;
    }
}

/**
 * Cerrar todos los dropdowns
 */
function closeAllDropdowns() {
    document.querySelectorAll('.notification-menu, .user-menu').forEach(menu => {
        menu.classList.remove('show');
    });
    currentDropdown = null;
}

// ===================
// BÚSQUEDA
// ===================

/**
 * Inicializar búsqueda global
 */
function initializeSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Limpiar timeout anterior
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Buscar después de 500ms de inactividad
        searchTimeout = setTimeout(() => {
            if (query.length >= 2) {
                performGlobalSearch(query);
            } else {
                clearSearchResults();
            }
        }, 500);
    });
    
    // Manejar Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = e.target.value.trim();
            if (query.length >= 2) {
                performGlobalSearch(query);
            }
        }
    });
}

/**
 * Realizar búsqueda global
 * @param {string} query - Término de búsqueda
 */
function performGlobalSearch(query) {
    console.log('Buscando:', query);
    
    // Mostrar indicador de carga
    showSearchLoading();
    
    // Realizar búsqueda mediante AJAX
    fetch('api/search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => response.json())
    .then(data => {
        displaySearchResults(data);
    })
    .catch(error => {
        console.error('Error en búsqueda:', error);
        showNotification('Error al realizar la búsqueda', 'error');
    })
    .finally(() => {
        hideSearchLoading();
    });
}

/**
 * Mostrar indicador de carga en búsqueda
 */
function showSearchLoading() {
    // Implementar indicador de carga
}

/**
 * Ocultar indicador de carga
 */
function hideSearchLoading() {
    // Ocultar indicador de carga
}

/**
 * Mostrar resultados de búsqueda
 * @param {Object} results - Resultados de búsqueda
 */
function displaySearchResults(results) {
    // Implementar mostrar resultados
    console.log('Resultados de búsqueda:', results);
}

/**
 * Limpiar resultados de búsqueda
 */
function clearSearchResults() {
    // Limpiar resultados mostrados
}

// ===================
// ATAJOS DE TECLADO
// ===================

/**
 * Inicializar atajos de teclado
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Solo procesar atajos si no estamos en un campo de entrada
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'n':
                    e.preventDefault();
                    goToNewNote();
                    break;
                case 'h':
                    e.preventDefault();
                    showPage('notas-historial');
                    break;
                case 'k':
                    e.preventDefault();
                    focusSearch();
                    break;
                case '/':
                    e.preventDefault();
                    focusSearch();
                    break;
            }
        }
        
        // Tecla Escape
        if (e.key === 'Escape') {
            closeAllDropdowns();
            if (sidebarOpen && window.innerWidth <= 992) {
                closeSidebar();
            }
        }
    });
}

/**
 * Enfocar campo de búsqueda
 */
function focusSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    }
}

// ===================
// RESPONSIVE
// ===================

/**
 * Inicializar comportamiento responsive
 */
function initializeResponsive() {
    // Cerrar sidebar al hacer clic fuera (móvil)
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992 && sidebarOpen) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            
            if (sidebar && !sidebar.contains(event.target) && 
                toggle && !toggle.contains(event.target)) {
                closeSidebar();
            }
        }
        
        // Cerrar dropdowns si se hace clic fuera
        if (currentDropdown && !event.target.closest('.notification-dropdown') && 
            !event.target.closest('.user-dropdown')) {
            closeAllDropdowns();
        }
    });
    
    // Manejar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            closeSidebar();
        }
    });
}

// ===================
// NOTIFICACIONES
// ===================

/**
 * Inicializar sistema de notificaciones
 */
function initializeNotifications() {
    // Cargar notificaciones iniciales
    loadNotifications();
    
    // Actualizar notificaciones cada 30 segundos
    setInterval(loadNotifications, 30000);
}

/**
 * Mostrar notificación toast
 * @param {string} message - Mensaje a mostrar
 * @param {string} type - Tipo de notificación (success, error, warning, info)
 * @param {number} duration - Duración en ms (por defecto 5000)
 */
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="closeNotification(this)">&times;</button>
        </div>
    `;
    
    // Agregar al container
    container.appendChild(notification);
    
    // Auto-cerrar después de la duración especificada
    setTimeout(() => {
        closeNotification(notification.querySelector('.notification-close'));
    }, duration);
}

/**
 * Cerrar notificación
 * @param {Element} closeBtn - Botón de cerrar
 */
function closeNotification(closeBtn) {
    const notification = closeBtn.closest('.notification');
    if (notification) {
        notification.style.animation = 'slideOut 0.3s ease-in-out';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

/**
 * Cargar notificaciones del servidor
 */
function loadNotifications() {
    fetch('api/notifications.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.unread_count || 0);
            updateNotificationList(data.notifications || []);
        })
        .catch(error => {
            console.error('Error cargando notificaciones:', error);
        });
}

/**
 * Actualizar badge de notificaciones
 * @param {number} count - Número de notificaciones no leídas
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

/**
 * Actualizar lista de notificaciones
 * @param {Array} notifications - Lista de notificaciones
 */
function updateNotificationList(notifications) {
    const list = document.getElementById('notificationList');
    if (!list || !notifications.length) return;
    
    list.innerHTML = notifications.map(notification => `
        <div class="notification-item ${notification.read ? '' : 'unread'}" 
             onclick="markNotificationRead(${notification.id})">
            <div class="notification-content">
                <h4>${notification.title}</h4>
                <p>${notification.message}</p>
                <span class="notification-time">${formatNotificationTime(notification.created_at)}</span>
            </div>
        </div>
    `).join('');
}

/**
 * Marcar notificación como leída
 * @param {number} notificationId - ID de la notificación
 */
function markNotificationRead(notificationId) {
    fetch('api/notifications.php', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: notificationId, action: 'mark_read' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications(); // Recargar notificaciones
        }
    })
    .catch(error => {
        console.error('Error marcando notificación:', error);
    });
}

/**
 * Marcar todas las notificaciones como leídas
 */
function markAllNotificationsRead() {
    fetch('api/notifications.php', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_all_read' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            showNotification('Todas las notificaciones marcadas como leídas', 'success');
        }
    })
    .catch(error => {
        console.error('Error marcando notificaciones:', error);
    });
}

/**
 * Formatear tiempo de notificación
 * @param {string} timestamp - Timestamp de la notificación
 * @returns {string} Tiempo formateado
 */
function formatNotificationTime(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = now - time;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 60) {
        return `hace ${minutes} min`;
    } else if (hours < 24) {
        return `hace ${hours} hora${hours > 1 ? 's' : ''}`;
    } else {
        return `hace ${days} día${days > 1 ? 's' : ''}`;
    }
}

// ===================
// ESTADÍSTICAS Y DATOS
// ===================

/**
 * Cargar estadísticas del dashboard
 */
function loadDashboardStats() {
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => {
            console.error('Error cargando estadísticas:', error);
        });
}

/**
 * Actualizar estadísticas en el dashboard
 * @param {Object} stats - Objeto con las estadísticas
 */
function updateDashboardStats(stats) {
    // Actualizar estadísticas en cards
    const statsCards = document.querySelectorAll('.stats-card h3');
    if (statsCards.length >= 4) {
        statsCards[0].textContent = stats.notas_total || 0;
        statsCards[1].textContent = stats.notas_entregadas || 0;
        statsCards[2].textContent = stats.notas_pendientes || 0;
        statsCards[3].textContent = `$${(stats.total_adeudado || 0).toLocaleString()}`;
    }
    
    // Actualizar estadísticas en sidebar
    const sidebarStats = document.querySelectorAll('.quick-stats .stat-number');
    if (sidebarStats.length >= 4) {
        sidebarStats[0].textContent = stats.notas_total || 0;
        sidebarStats[1].textContent = stats.notas_entregadas || 0;
        sidebarStats[2].textContent = stats.notas_pendientes || 0;
        sidebarStats[3].textContent = `$${(stats.total_adeudado || 0).toLocaleString()}`;
    }
}

/**
 * Cargar historial de notas
 */
function loadNotesHistory() {
    console.log('Cargando historial de notas...');
    // Implementar carga de historial
}

/**
 * Cargar lista de clientes
 */
function loadClientsList() {
    console.log('Cargando lista de clientes...');
    // Implementar carga de clientes
}

// ===================
// UTILIDADES
// ===================

/**
 * Actualizar timer de sesión
 */
function updateSessionTimer() {
    const timer = document.getElementById('sessionTimer');
    if (!timer) return;
    
    // Calcular tiempo restante basado en timeout de sesión (1 hora)
    const sessionStart = sessionStorage.getItem('session_start');
    if (!sessionStart) {
        sessionStorage.setItem('session_start', Date.now().toString());
        return;
    }
    
    const elapsed = Date.now() - parseInt(sessionStart);
    const sessionTimeout = 3600000; // 1 hora en ms
    const remaining = sessionTimeout - elapsed;
    
    if (remaining <= 0) {
        timer.textContent = 'Expirada';
        showNotification('Su sesión ha expirado. Por favor, inicie sesión nuevamente.', 'warning');
        return;
    }
    
    const minutes = Math.floor(remaining / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);
    timer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

/**
 * Confirmar logout
 * @returns {boolean} Confirmación del usuario
 */
function confirmLogout() {
    return confirm('¿Está seguro que desea cerrar sesión?');
}

/**
 * Formatear número como moneda
 * @param {number} amount - Cantidad a formatear
 * @returns {string} Cantidad formateada
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

/**
 * Formatear fecha
 * @param {string} dateString - Fecha en string
 * @returns {string} Fecha formateada
 */
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// ===================
// ANIMACIONES CSS
// ===================

// Agregar estilos para animaciones de notificaciones
const notificationStyles = `
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;

// Inyectar estilos si no existen
if (!document.getElementById('dashboard-animations')) {
    const style = document.createElement('style');
    style.id = 'dashboard-animations';
    style.textContent = notificationStyles;
    document.head.appendChild(style);
}

// ===================
// EXPORTAR FUNCIONES GLOBALES
// ===================

// Hacer funciones disponibles globalmente para onclick en HTML
window.showPage = showPage;
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.toggleNotifications = toggleNotifications;
window.toggleUserMenu = toggleUserMenu;
window.goToNewNote = goToNewNote;
window.markAllNotificationsRead = markAllNotificationsRead;
window.confirmLogout = confirmLogout;
window.closeNotification = closeNotification;