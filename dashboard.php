<?php
require_once './includes/config.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('index.php');
}

// Obtener estadísticas básicas para dashboard y sidebar
$stats = ['notas_total' => 0, 'notas_entregadas' => 0, 'notas_pendientes' => 0, 'total_adeudado' => 0];

try {
    $db = Database::getInstance()->getConnection();
    
    // Contar notas por status
    $stmt = $db->prepare("SELECT status, COUNT(*) as count, COALESCE(SUM(current_total), 0) as total FROM notes GROUP BY status");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    foreach ($results as $row) {
        $stats['notas_total'] += $row['count'];
        if ($row['status'] === 'pagada_y_entregada') {
            $stats['notas_entregadas'] = $row['count'];
        } else {
            $stats['notas_pendientes'] += $row['count'];
            $stats['total_adeudado'] += $row['total'];
        }
    }
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
}

// Obtener información adicional para el dashboard
$recent_notes = [];
try {
    $stmt = $db->prepare("
        SELECT n.folio, n.client_name, n.status, n.current_total, n.created_at 
        FROM notes n 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_notes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error obteniendo notas recientes: " . $e->getMessage());
}

// Función auxiliar para las clases de estado
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pagada_y_entregada':
            return 'success';
        case 'liquidada_pendiente_entrega':
            return 'warning';
        case 'con_anticipo_trabajandose':
            return 'info';
        default:
            return 'secondary';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'pagada_y_entregada':
            return 'Pagada y Entregada';
        case 'liquidada_pendiente_entrega':
            return 'Liquidada Pendiente Entrega';
        case 'con_anticipo_trabajandose':
            return 'Con Anticipo Trabajándose';
        default:
            return str_replace('_', ' ', $status);
    }
}

$page_title = "Dashboard Principal";
$page_description = "Panel de control principal del sistema BORMEX";
$current_page = "dashboard";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="robots" content="noindex,nofollow">
    
    <!-- CSS Principal - Orden correcto -->
    <link rel="stylesheet" href="public_html/assets/css/style.css">
    <link rel="stylesheet" href="public_html/assets/css/layout.css">
    <link rel="stylesheet" href="public_html/assets/css/dashboard.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="public_html/assets/images/favicon.ico">
</head>
<body class="dashboard-page" data-page="dashboard">

    <!-- Navbar Superior -->
    <nav class="navbar">
        <div class="navbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            
            <a href="dashboard.php" class="navbar-brand">
                <div class="logo">
                    <span class="bor">BOR</span><span class="mex">MEX</span>
                </div>
                <span class="brand-text">Sistema de Gestión</span>
            </a>
        </div>

        <div class="navbar-center">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Buscar notas, clientes..." id="globalSearch">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
            </div>
        </div>

        <div class="navbar-right">
            <!-- Notificaciones -->
            <div class="notification-dropdown">
                <button class="notification-btn" onclick="toggleNotifications()" aria-label="Ver notificaciones">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="notification-badge" id="notificationCount">3</span>
                </button>
                <div class="notification-menu" id="notificationMenu">
                    <div class="notification-header">
                        <h3>Notificaciones</h3>
                        <button class="mark-all-read" onclick="markAllNotificationsRead()">Marcar todas como leídas</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-item unread">
                            <div class="notification-content">
                                <h4>Nueva nota pendiente</h4>
                                <p>BM004 requiere atención</p>
                                <span class="notification-time">hace 5 min</span>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notification-content">
                                <h4>Pago recibido</h4>
                                <p>Cliente Juan Pérez ha pagado BM003</p>
                                <span class="notification-time">hace 1 hora</span>
                            </div>
                        </div>
                    </div>
                    <div class="notification-footer">
                        <a href="#" onclick="showPage('notificaciones')">Ver todas las notificaciones</a>
                    </div>
                </div>
            </div>

            <!-- Usuario -->
            <div class="user-dropdown">
                <button class="user-btn" onclick="toggleUserMenu()" aria-label="Menú de usuario">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?></span>
                    </div>
                    <svg class="user-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6,9 12,15 18,9"></polyline>
                    </svg>
                </button>
                
                <div class="user-menu" id="userMenu">
                    <div class="user-menu-header">
                        <div class="user-avatar-large">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-name-large"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($_SESSION['username']); ?>@bormex.local</div>
                        </div>
                    </div>
                    
                    <div class="user-menu-items">
                        <a href="#" onclick="showPage('perfil')" class="user-menu-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Mi Perfil
                        </a>
                        
                        <a href="#" onclick="showPage('configuracion')" class="user-menu-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                            </svg>
                            Configuración
                        </a>
                        
                        <div class="menu-separator"></div>
                        
                        <a href="index.php?logout=1" class="user-menu-item logout" onclick="return confirmLogout()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16,17 21,12 16,7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

   <!-- Sidebar Mejorado -->
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
                        <button class="nav-link active" onclick="showPage('dashboard')" data-page="dashboard">
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
                        </button>
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
                        <button class="nav-link" onclick="goToNewNote()" data-page="notas-nueva">
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
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('notas-historial')" data-page="notas-historial">
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
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('notas-estadisticas')" data-page="notas-estadisticas">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                                    </svg>
                                </div>
                                <span class="nav-text">Analytics</span>
                            </div>
                        </button>
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
                        <button class="nav-link" onclick="showPage('clientes')" data-page="clientes">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <span class="nav-text">Directorio</span>
                            </div>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('clientes-nuevo')" data-page="clientes-nuevo">
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
                        </button>
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
                        <button class="nav-link" onclick="showPage('reportes-ventas')" data-page="reportes-ventas">
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
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('reportes-financiero')" data-page="reportes-financiero">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Finanzas</span>
                            </div>
                        </button>
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
                        <button class="nav-link" onclick="showPage('empresa')" data-page="empresa">
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
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('usuarios')" data-page="usuarios">
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
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" onclick="showPage('configuracion-sistema')" data-page="configuracion-sistema">
                            <div class="nav-link-content">
                                <div class="nav-icon-wrapper">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                                    </svg>
                                </div>
                                <span class="nav-text">Sistema</span>
                            </div>
                        </button>
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
                <button class="action-btn primary" onclick="goToNewNote()" title="Crear nueva nota (Ctrl+N)">
                    <div class="action-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                    <span>Nueva Nota</span>
                </button>
                <button class="action-btn secondary" onclick="showPage('notas-historial')" title="Ver historial (Ctrl+H)">
                    <div class="action-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                        </svg>
                    </div>
                    <span>Historial</span>
                </button>
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

    <!-- Contenido Principal -->
    <main class="main-content" id="mainContent">
        
        <!-- Contenido específico del dashboard -->
        <div id="dashboard" class="page-content active">
            <div class="content-header">
                <h1>Dashboard Principal</h1>
                <div class="breadcrumb">Inicio / Dashboard</div>
            </div>

            <!-- Tarjetas de Estadísticas Principales -->
            <div class="stats-cards">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-icon primary">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($stats['notas_total']); ?></h3>
                            <p>Notas Totales</p>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-icon success">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20,6 9,17 4,12"></polyline>
                            </svg>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($stats['notas_entregadas']); ?></h3>
                            <p>Entregadas</p>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-icon warning">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12,6 12,12 16,14"></polyline>
                            </svg>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($stats['notas_pendientes']); ?></h3>
                            <p>Pendientes</p>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-icon danger">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="stats-info">
                            <h3>$<?php echo number_format($stats['total_adeudado'], 2); ?></h3>
                            <p>Total Adeudado</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido del Dashboard -->
            <div class="dashboard-grid">
                <!-- Tarjeta de bienvenida -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h3>Bienvenido a <?php echo APP_NAME; ?></h3>
                    </div>
                    <div class="card-content">
                        <p>Hola <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>, bienvenido al sistema de gestión de notas y órdenes de trabajo.</p>
                        
                        <div style="margin-top: 20px;">
                            <a href="pages/notas/nueva.php" class="btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Crear Nueva Nota
                            </a>
                        </div>
                        
                        <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 6px; border-left: 4px solid #3182ce;">
                            <strong>Rol actual:</strong> <?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Notas recientes -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h3>Notas Recientes</h3>
                        <a href="#" onclick="showPage('notas-historial')" class="card-action">Ver todas</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recent_notes)): ?>
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                                <p>No hay notas recientes</p>
                                <button onclick="goToNewNote()" class="btn-primary">Crear Primera Nota</button>
                            </div>
                        <?php else: ?>
                            <div class="recent-notes-list">
                                <?php foreach ($recent_notes as $note): ?>
                                    <div class="recent-note-item">
                                        <div class="note-info">
                                            <div class="note-folio"><?php echo htmlspecialchars($note['folio']); ?></div>
                                            <div class="note-client"><?php echo htmlspecialchars($note['client_name']); ?></div>
                                            <div class="note-date"><?php echo date('d/m/Y', strtotime($note['created_at'])); ?></div>
                                        </div>
                                        <div class="note-status">
                                            <span class="status-badge <?php echo getStatusBadgeClass($note['status']); ?>">
                                                <?php echo getStatusText($note['status']); ?>
                                            </span>
                                            <div class="note-total">$<?php echo number_format($note['current_total'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h3>Acciones Rápidas</h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions-grid">
                            <a href="pages/notas/nueva.php" class="quick-action-item primary">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>Nueva Nota</span>
                                <kbd>Ctrl+N</kbd>
                            </a>
                            
                            <a href="#" onclick="showPage('notas-historial')" class="quick-action-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                </svg>
                                <span>Historial</span>
                                <kbd>Ctrl+H</kbd>
                            </a>
                            
                            <a href="#" onclick="showPage('clientes')" class="quick-action-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span>Clientes</span>
                            </a>
                            
                            <a href="#" onclick="showPage('notas-estadisticas')" class="quick-action-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                                </svg>
                                <span>Estadísticas</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Información del sistema -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h3>Información del Sistema</h3>
                    </div>
                    <div class="card-content">
                        <div class="system-info">
                            <div class="info-row">
                                <span class="info-label">Versión:</span>
                                <span class="info-value">1.0.0</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value">
                                    <span class="status-indicator online"></span>
                                    En línea
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Último acceso:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Sesión expira:</span>
                                <span class="info-value" id="sessionTimer">--:--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Otras páginas de contenido (ocultas inicialmente) -->
        <div id="notas-historial" class="page-content">
            <div class="content-header">
                <h1>Historial de Notas</h1>
                <div class="breadcrumb">Inicio / Notas / Historial</div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3>📄 Próximamente</h3>
                    <p>Esta sección estará disponible pronto</p>
                </div>
            </div>
        </div>

        <div id="notas-estadisticas" class="page-content">
            <div class="content-header">
                <h1>Estadísticas de Notas</h1>
                <div class="breadcrumb">Inicio / Notas / Estadísticas</div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3>📈 Próximamente</h3>
                    <p>Esta sección estará disponible pronto</p>
                </div>
            </div>
        </div>

        <div id="clientes" class="page-content">
            <div class="content-header">
                <h1>Lista de Clientes</h1>
                <div class="breadcrumb">Inicio / Clientes</div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3>👥 Próximamente</h3>
                    <p>Esta sección estará disponible pronto</p>
                </div>
            </div>
        </div>

        <?php if (hasPermission('admin')): ?>
        <div id="empresa" class="page-content">
            <div class="content-header">
                <h1>Configuración de Empresa</h1>
                <div class="breadcrumb">Inicio / Configuración / Empresa</div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3>🏢 Próximamente</h3>
                    <p>Esta sección estará disponible pronto</p>
                </div>
            </div>
        </div>

        <div id="usuarios" class="page-content">
            <div class="content-header">
                <h1>Gestión de Usuarios</h1>
                <div class="breadcrumb">Inicio / Configuración / Usuarios</div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3>👤 Próximamente</h3>
                    <p>Esta sección estará disponible pronto</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Overlay para móvil -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>
    </main>
    
    <!-- Container para notificaciones toast -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- JavaScript Principal -->
    <script src="public_html/assets/js/app.js"></script>
    <script src="public_html/assets/js/dashboard.js"></script>
    
    <!-- JavaScript específico del dashboard -->
    <script>
    // Timer de sesión específico del dashboard
    function updateSessionTimer() {
        const timer = document.getElementById('sessionTimer');
        if (timer) {
            const now = new Date();
            const minutes = 60 - now.getMinutes();
            const seconds = 60 - now.getSeconds();
            timer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }

    // Actualizar cada segundo
    setInterval(updateSessionTimer, 1000);
    updateSessionTimer();

    // Función para ir a nueva nota
    function goToNewNote() {
        window.location.href = 'pages/notas/nueva.php';
    }

    // Confirmar logout
    function confirmLogout() {
        return confirm('¿Está seguro que desea cerrar sesión?');
    }

    console.log('Dashboard BORMEX inicializado correctamente');
    </script>

    <!-- Corrección CSS crítica -->
    <style>
    /* CORRECCIÓN INMEDIATA - Layout Dashboard */
    body {
        margin: 0 !important;
        padding: 0 !important;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
    }

    .main-content {
        margin-left: 280px !important;
        margin-top: 72px !important;
        padding: 24px !important;
        min-height: calc(100vh - 72px) !important;
    }

    .sidebar {
        position: fixed !important;
        top: 72px !important;
        left: 0 !important;
        width: 280px !important;
        height: calc(100vh - 72px) !important;
        background: white !important;
        z-index: 999 !important;
        overflow-y: auto !important;
    }

    .navbar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 72px !important;
        background: white !important;
        z-index: 1000 !important;
    }

    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%) !important;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .sidebar-toggle {
            display: block !important;
        }
    }
    </style>

</body>
</html>