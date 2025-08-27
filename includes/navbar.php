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
                    <!-- Las notificaciones se cargarán dinámicamente -->
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
                    <div class="notification-item">
                        <div class="notification-content">
                            <h4>Recordatorio</h4>
                            <p>Entrega programada para hoy</p>
                            <span class="notification-time">hace 2 horas</span>
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