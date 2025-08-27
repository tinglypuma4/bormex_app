<?php
// Obtener la página actual para marcar el enlace activo
$current_uri = $_SERVER['REQUEST_URI'];
$current_script = basename($_SERVER['SCRIPT_NAME'], '.php');

// Función para determinar si un enlace está activo
function isActive($page_identifier) {
    global $current_uri, $current_script;
    
    switch($page_identifier) {
        case 'dashboard':
            return $current_script === 'dashboard';
        case 'nueva-nota':
            return strpos($current_uri, '/notas/nueva') !== false;
        case 'historial':
            return strpos($current_uri, '/notas/historial') !== false || strpos($current_uri, 'historial.php') !== false;
        case 'clientes':
            return strpos($current_uri, 'clientes.php') !== false;
        case 'empresa':
            return strpos($current_uri, 'empresa.php') !== false;
        case 'usuarios':
            return strpos($current_uri, 'usuarios.php') !== false;
        default:
            return false;
    }
}

// Función para obtener la ruta relativa correcta
function getBasePath() {
    global $current_uri;
    // Si estamos en una subcarpeta, necesitamos subir niveles
    if (strpos($current_uri, '/pages/') !== false) {
        return '/../../';
    }
    return '';
}

$basePath = getBasePath();
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <!-- Panel de estadísticas mejorado -->
        <div class="quick-stats">
            <div class="stats-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                </svg>
                <h3>Resumen Ejecutivo</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['notas_total'] ?? 0; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon success">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20,6 9,17 4,12"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number text-success"><?php echo $stats['notas_entregadas'] ?? 0; ?></div>
                        <div class="stat-label">Entregadas</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon warning">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number text-warning"><?php echo $stats['notas_pendientes'] ?? 0; ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon danger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number text-danger">$<?php echo number_format($stats['total_adeudado'] ?? 0, 0); ?></div>
                        <div class="stat-label">Adeudado</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación principal mejorada -->
        <nav class="sidebar-nav">
            <!-- Inicio -->
            <div class="nav-section">
                <div class="nav-section-header">
                    <svg class="section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9,22 9,12 15,12 15,22"></polyline>
                    </svg>
                    <h4 class="nav-section-title">Principal</h4>
                </div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?php echo $basePath; ?>dashboard.php" 
                           class="nav-link <?php echo isActive('dashboard') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="14" width="7" height="7"></rect>
                                        <rect x="3" y="14" width="7" height="7"></rect>
                                    </svg>
                                </div>
                                <span class="nav-text">Inicio</span>
                            </div>
                            <div class="nav-indicator"></div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Gestión de Notas -->
            <div class="nav-section">
                <div class="nav-section-header">
                    <svg class="section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                    </svg>
                    <h4 class="nav-section-title">Gestión de Notas</h4>
                </div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?php echo $basePath; ?>public_html/pages/notas/nueva.php" 
                           class="nav-link <?php echo isActive('nueva-nota') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper primary">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </div>
                                <span class="nav-text">Crear Nueva</span>
                            </div>
                            <div class="nav-shortcut">Ctrl+N</div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $basePath; ?>public_html/pages/notas/historial.php" 
                           class="nav-link <?php echo isActive('historial') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14,2 14,8 20,8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                    </svg>
                                </div>
                                <span class="nav-text">Historial</span>
                            </div>
                            <span class="nav-badge"><?php echo $stats['notas_total'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="alert('Próximamente disponible'); return false;" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                                    </svg>
                                </div>
                                <span class="nav-text">Analytics</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Clientes -->
            <div class="nav-section">
                <div class="nav-section-header">
                    <svg class="section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h4 class="nav-section-title">Clientes</h4>
                </div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="#" onclick="alert('Página en desarrollo'); return false;" 
                           class="nav-link <?php echo isActive('clientes') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <span class="nav-text">Directorio</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="alert('Funcionalidad próximamente'); return false;" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                </div>
                                <span class="nav-text">Agregar Cliente</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Reportes -->
            <div class="nav-section">
                <div class="nav-section-header">
                    <svg class="section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"></line>
                        <line x1="18" y1="20" x2="18" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="16"></line>
                    </svg>
                    <h4 class="nav-section-title">Inteligencia de Negocio</h4>
                </div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="#" onclick="alert('Reportes próximamente'); return false;" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="20" x2="12" y2="10"></line>
                                        <line x1="18" y1="20" x2="18" y2="4"></line>
                                        <line x1="6" y1="20" x2="6" y2="16"></line>
                                    </svg>
                                </div>
                                <span class="nav-text">Ventas</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="alert('Funcionalidad en desarrollo'); return false;" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Finanzas</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <?php if (hasPermission('admin')): ?>
            <!-- Configuración (Solo Admin) -->
            <div class="nav-section">
                <div class="nav-section-header">
                    <svg class="section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                    </svg>
                    <h4 class="nav-section-title">Administración</h4>
                </div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="#" onclick="alert('Configuración próximamente'); return false;" 
                           class="nav-link <?php echo isActive('empresa') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18"></path>
                                        <path d="M5 21V7l8-4v18"></path>
                                        <path d="M19 21V11l-6-4"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Empresa</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="alert('Gestión de usuarios próximamente'); return false;" 
                           class="nav-link <?php echo isActive('usuarios') ? 'active' : ''; ?>">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Usuarios</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="alert('Configuración del sistema próximamente'); return false;" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Sistema</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Acciones rápidas mejoradas -->
        <div class="quick-actions">
            <div class="actions-header">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"></polygon>
                </svg>
                <h4>Acciones Rápidas</h4>
            </div>
            <div class="actions-grid">
                <a href="<?php echo $basePath; ?>public_html/pages/notas/nueva.php" class="action-btn primary" title="Crear nueva nota (Ctrl+N)">
                    <div class="action-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                    <span>Nueva Nota</span>
                </a>
                <a href="<?php echo $basePath; ?>public_html/pages/notas/historial.php" class="action-btn secondary" title="Ver historial (Ctrl+H)">
                    <div class="action-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                        </svg>
                    </div>
                    <span>Historial</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer del sidebar mejorado -->
    <div class="sidebar-footer">
        <div class="system-status">
            <div class="status-indicator-wrapper">
                <span class="status-indicator online"></span>
                <span class="status-text">Online</span>
            </div>
            <div class="status-details">
                <span class="version-text">v1.0.0</span>
                <span class="separator">•</span>
                <span class="year-text"><?php echo date('Y'); ?> BORMEX</span>
            </div>
        </div>
    </div>
</aside>