<?php
require_once('../../../includes/config.php');

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('../../../index.php');
}

// Procesar acciones AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance()->getConnection();
        
        switch ($_POST['action']) {
            case 'get_notes':
                // Obtener notas con filtros
                $search = cleanInput($_POST['search'] ?? '');
                $status_filter = $_POST['status_filter'] ?? '';
                
                $where_conditions = [];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(n.folio LIKE ? OR n.client_name LIKE ? OR n.client_phone LIKE ?)";
                    $search_param = "%{$search}%";
                    $params[] = $search_param;
                    $params[] = $search_param;
                    $params[] = $search_param;
                }
                
                if (!empty($status_filter)) {
                    $where_conditions[] = "n.status = ?";
                    $params[] = $status_filter;
                }
                
                $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                $stmt = $db->prepare("
                    SELECT n.*, u.first_name, u.last_name 
                    FROM notes n 
                    LEFT JOIN users u ON n.created_by = u.id 
                    {$where_clause}
                    ORDER BY n.created_at DESC
                ");
                $stmt->execute($params);
                $notes = $stmt->fetchAll();
                
                // Calcular KPIs
                $kpis = [
                    'total' => 0,
                    'entregadas' => 0,
                    'pendientes' => 0,
                    'anticipo' => 0,
                    'adeudado' => 0
                ];
                
                foreach ($notes as $note) {
                    $kpis['total']++;
                    
                    switch ($note['status']) {
                        case 'pagada_y_entregada':
                            $kpis['entregadas']++;
                            break;
                        case 'liquidada_pendiente_entrega':
                            $kpis['pendientes']++;
                            $kpis['adeudado'] += $note['current_total'];
                            break;
                        case 'con_anticipo_trabajandose':
                            $kpis['anticipo']++;
                            $kpis['adeudado'] += $note['current_total'];
                            break;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'notes' => $notes,
                    'kpis' => $kpis
                ]);
                exit;
                
            case 'get_note_details':
                // Obtener detalles completos de una nota
                $note_id = intval($_POST['note_id']);
                
                $stmt = $db->prepare("SELECT * FROM notes WHERE id = ?");
                $stmt->execute([$note_id]);
                $note = $stmt->fetch();
                
                if (!$note) {
                    throw new Exception('Nota no encontrada');
                }
                
                // Obtener items
                $stmt = $db->prepare("SELECT * FROM note_items WHERE note_id = ? ORDER BY id");
                $stmt->execute([$note_id]);
                $items = $stmt->fetchAll();
                
                // Obtener datos de facturación si existen
                $invoice_data = null;
                if ($note['requires_invoice']) {
                    $stmt = $db->prepare("SELECT * FROM invoice_data WHERE note_id = ?");
                    $stmt->execute([$note_id]);
                    $invoice_data = $stmt->fetch();
                }
                
                echo json_encode([
                    'success' => true,
                    'note' => $note,
                    'items' => $items,
                    'invoice_data' => $invoice_data
                ]);
                exit;
                
            case 'update_note':
                // Actualizar nota existente
                if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token CSRF inválido');
                }
                
                $note_id = intval($_POST['note_id']);
                $client_name = cleanInput($_POST['client_name']);
                $client_phone = cleanInput($_POST['client_phone'] ?? '');
                $client_email = cleanInput($_POST['client_email'] ?? '');
                $payment_method = $_POST['payment_method'] ?? 'efectivo';
                $advance_payment = floatval($_POST['advance_payment'] ?? 0);
                $discount = floatval($_POST['discount'] ?? 0);
                $requires_invoice = isset($_POST['requires_invoice']) ? 1 : 0;
                $observations = cleanInput($_POST['observations'] ?? '');
                $status = $_POST['status'] ?? 'con_anticipo_trabajandose';
                
                if (empty($client_name)) {
                    throw new Exception('El nombre del cliente es requerido');
                }
                
                $db->beginTransaction();
                
                // Procesar items actualizados
                $items = [];
                $subtotal_total = 0;
                
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $item) {
                        $description = cleanInput($item['description'] ?? '');
                        $quantity = intval($item['quantity'] ?? 0);
                        $unit_price = floatval($item['unit_price'] ?? 0);
                        
                        if (!empty($description) && $quantity > 0 && $unit_price >= 0) {
                            $subtotal_item = $quantity * $unit_price;
                            $items[] = [
                                'description' => $description,
                                'quantity' => $quantity,
                                'unit_price' => $unit_price,
                                'subtotal' => $subtotal_item
                            ];
                            $subtotal_total += $subtotal_item;
                        }
                    }
                }
                
                if (empty($items)) {
                    throw new Exception('Debe tener al menos un producto');
                }
                
                // Calcular totales
                $subtotal = $subtotal_total - $discount;
                $tax_amount = $requires_invoice ? ($subtotal * 0.16) : 0;
                $total = $subtotal + $tax_amount;
                $current_total = max(0, $total - $advance_payment);
                
                // Actualizar nota principal
                $stmt = $db->prepare("
                    UPDATE notes SET 
                        client_name = ?, client_phone = ?, client_email = ?, status = ?,
                        payment_method = ?, advance_payment = ?, discount = ?, requires_invoice = ?,
                        observations = ?, subtotal = ?, tax_amount = ?, total = ?, current_total = ?,
                        updated_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $client_name, $client_phone, $client_email, $status,
                    $payment_method, $advance_payment, $discount, $requires_invoice,
                    $observations, $subtotal, $tax_amount, $total, $current_total,
                    $_SESSION['user_id'], $note_id
                ]);
                
                // Eliminar items anteriores
                $stmt = $db->prepare("DELETE FROM note_items WHERE note_id = ?");
                $stmt->execute([$note_id]);
                
                // Insertar nuevos items
                $stmt = $db->prepare("
                    INSERT INTO note_items (note_id, description, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($items as $item) {
                    $stmt->execute([
                        $note_id, $item['description'], $item['quantity'], 
                        $item['unit_price'], $item['subtotal']
                    ]);
                }
                
                // Manejar datos de facturación
                if ($requires_invoice) {
                    $tax_id = cleanInput($_POST['tax_id'] ?? '');
                    $business_name = cleanInput($_POST['business_name'] ?? '');
                    $address = cleanInput($_POST['invoice_address'] ?? '');
                    $cfdi_use = $_POST['cfdi_use'] ?? 'G03';
                    
                    if (empty($tax_id) || empty($business_name)) {
                        throw new Exception('RFC y Razón Social son requeridos para facturación');
                    }
                    
                    // Verificar si ya existe registro de facturación
                    $stmt = $db->prepare("SELECT id FROM invoice_data WHERE note_id = ?");
                    $stmt->execute([$note_id]);
                    $existing_invoice = $stmt->fetch();
                    
                    if ($existing_invoice) {
                        $stmt = $db->prepare("
                            UPDATE invoice_data SET tax_id = ?, business_name = ?, address = ?, cfdi_use = ?
                            WHERE note_id = ?
                        ");
                        $stmt->execute([$tax_id, $business_name, $address, $cfdi_use, $note_id]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO invoice_data (note_id, tax_id, business_name, address, cfdi_use) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$note_id, $tax_id, $business_name, $address, $cfdi_use]);
                    }
                } else {
                    // Eliminar datos de facturación si ya no se requiere
                    $stmt = $db->prepare("DELETE FROM invoice_data WHERE note_id = ?");
                    $stmt->execute([$note_id]);
                }
                
                $db->commit();
                
                echo json_encode(['success' => true, 'message' => 'Nota actualizada correctamente']);
                exit;
                
            case 'delete_note':
                // Eliminar nota
                if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token CSRF inválido');
                }
                
                $note_id = intval($_POST['note_id']);
                
                // Verificar permisos (solo admin o el creador puede eliminar)
                $stmt = $db->prepare("SELECT folio, created_by FROM notes WHERE id = ?");
                $stmt->execute([$note_id]);
                $note = $stmt->fetch();
                
                if (!$note) {
                    throw new Exception('Nota no encontrada');
                }
                
                if (!hasPermission('admin') && $note['created_by'] != $_SESSION['user_id']) {
                    throw new Exception('No tienes permisos para eliminar esta nota');
                }
                
                // Eliminar nota (CASCADE eliminará items e invoice_data)
                $stmt = $db->prepare("DELETE FROM notes WHERE id = ?");
                $stmt->execute([$note_id]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Nota {$note['folio']} eliminada correctamente"
                ]);
                exit;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

$page_title = "Historial de Notas";
$page_description = "Ver y gestionar todas las notas";
$current_page = "notas-historial";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- CSS CORREGIDO - Usar funciones helper -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboard.css'); ?>">
    
    <!-- DEBUG CSS URLs solo en desarrollo -->
    <?php if (ini_get('display_errors') && !isAjaxRequest()): ?>
    <!-- 
    DEBUG HISTORIAL CSS:
    style.css: <?php echo asset('css/style.css'); ?>
    layout.css: <?php echo asset('css/layout.css'); ?>
    dashboard.css: <?php echo asset('css/dashboard.css'); ?>
    -->
    <?php endif; ?>
    
    <style>
        .historial-page {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding: 24px;
        }
        
        .historial-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
        }
        
        .toolbar {
            display: flex;
            gap: 16px;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .toolbar input,
        .toolbar select {
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .toolbar input:focus,
        .toolbar select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 90, 61, 0.1);
        }
        
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .kpi {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        
        .kpi-label {
            font-size: 14px;
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        
        .table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.success {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: var(--info);
            color: white;
        }
        
        .btn-pdf {
            background: var(--secondary);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--secondary);
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-2xl);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-top: 1px solid var(--border);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-control {
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 90, 61, 0.1);
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .products-table th,
        .products-table td {
            padding: 12px;
            border: 1px solid var(--border);
            text-align: left;
        }
        
        .products-table th {
            background: var(--gray-50);
            font-weight: 600;
        }
        
        .btn-add-product {
            background: var(--success);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
        }
        
        @media (max-width: 768px) {
            .historial-page {
                padding: 16px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar input,
            .toolbar select {
                min-width: auto;
                width: 100%;
            }
            
            .kpis {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 600px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body class="historial-page">
    <div class="historial-container">
        
        <!-- Header -->
        <div class="page-header">
            <h1>Historial de Notas</h1>
            <div style="display: flex; gap: 12px;">
                <a href="<?php echo dashboard_url(); ?>" class="btn-secondary">Dashboard</a>
                <a href="<?php echo nueva_nota_url(); ?>" class="btn-primary">Nueva Nota</a>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <input type="text" id="filterText" placeholder="Buscar por folio, cliente o teléfono...">
            <select id="filterStatus">
                <option value="">Todos los estados</option>
                <option value="con_anticipo_trabajandose">Con Anticipo Trabajándose</option>
                <option value="liquidada_pendiente_entrega">Liquidada Pendiente Entrega</option>
                <option value="pagada_y_entregada">Pagada y Entregada</option>
            </select>
            <button class="btn-secondary" id="btnClearFilters">Limpiar Filtros</button>
        </div>
        
        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <div class="kpi-label">Notas Totales</div>
                <div class="kpi-value" id="kpiTotal">0</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Pagadas y Entregadas</div>
                <div class="kpi-value" id="kpiEntregadas">0</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Liquidadas Pendientes</div>
                <div class="kpi-value" id="kpiPendientes">0</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Con Anticipo</div>
                <div class="kpi-value" id="kpiAnticipo">0</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Total Adeudado</div>
                <div class="kpi-value" id="kpiAdeudado">$0.00</div>
            </div>
        </div>
        
        <!-- Tabla de Notas -->
        <div class="table-container">
            <div class="loading" id="loading">
                <div>Cargando notas...</div>
            </div>
            
            <div class="empty-state" id="emptyState" style="display: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <h3>No hay notas</h3>
                <p>No se encontraron notas que coincidan con los filtros actuales</p>
            </div>
            
            <table class="table" id="notesTable" style="display: none;">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="notesTableBody">
                    <!-- Se llena dinámicamente -->
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal de Edición -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Nota <span id="editModalFolio"></span></h2>
                <button class="btn-secondary" onclick="closeEditModal()">Cerrar</button>
            </div>
            
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editNoteId">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Cliente -->
                    <h3>Información del Cliente</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editClientName">Nombre del Cliente *</label>
                            <input type="text" id="editClientName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editClientPhone">Teléfono</label>
                            <input type="tel" id="editClientPhone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editClientEmail">Email</label>
                            <input type="email" id="editClientEmail" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Estado</label>
                            <select id="editStatus" class="form-control">
                                <option value="con_anticipo_trabajandose">Con Anticipo Trabajándose</option>
                                <option value="liquidada_pendiente_entrega">Liquidada Pendiente Entrega</option>
                                <option value="pagada_y_entregada">Pagada y Entregada</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Productos -->
                    <h3>Productos</h3>
                    <table class="products-table" id="editProductsTable">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="editProductsBody">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                    <button type="button" class="btn-add-product" onclick="addEditProduct()">+ Agregar Producto</button>
                    
                    <!-- Pago -->
                    <h3>Pago y Facturación</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editPaymentMethod">Método de Pago</label>
                            <select id="editPaymentMethod" class="form-control">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editAdvancePayment">Anticipo</label>
                            <input type="number" id="editAdvancePayment" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="editDiscount">Descuento</label>
                            <input type="number" id="editDiscount" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="editRequiresInvoice"> Requiere Factura
                            </label>
                        </div>
                    </div>
                    
                    <!-- Campos de Facturación -->
                    <div id="editInvoiceFields" style="display: none;">
                        <h4>Datos de Facturación</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editTaxId">RFC</label>
                                <input type="text" id="editTaxId" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editBusinessName">Razón Social</label>
                                <input type="text" id="editBusinessName" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editInvoiceAddress">Dirección</label>
                                <input type="text" id="editInvoiceAddress" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editCfdiUse">Uso CFDI</label>
                                <input type="text" id="editCfdiUse" class="form-control" value="G03">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Observaciones -->
                    <div class="form-group">
                        <label for="editObservations">Observaciones</label>
                        <textarea id="editObservations" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <!-- Totales -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Subtotal</label>
                            <input type="text" id="editSubtotal" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>IVA</label>
                            <input type="text" id="editTax" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Total</label>
                            <input type="text" id="editTotal" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Total Actual</label>
                            <input type="text" id="editCurrentTotal" class="form-control" readonly>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="button" class="btn-primary" onclick="saveEditNote()">Guardar Cambios</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentNotes = [];
        let editProductCounter = 0;
        
        // Formatear moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(amount || 0);
        }
        
        // Formatear fecha
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('es-MX');
        }
        
        // Obtener texto del estado
        function getStatusText(status) {
            const statuses = {
                'con_anticipo_trabajandose': 'Con Anticipo',
                'liquidada_pendiente_entrega': 'Pendiente Entrega',
                'pagada_y_entregada': 'Pagada y Entregada'
            };
            return statuses[status] || status;
        }
        
        // Obtener clase del badge de estado
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'pagada_y_entregada':
                    return 'status-badge success';
                case 'liquidada_pendiente_entrega':
                    return 'status-badge warning';
                case 'con_anticipo_trabajandose':
                    return 'status-badge info';
                default:
                    return 'status-badge';
            }
        }
        
        // Cargar notas
        function loadNotes() {
            const search = document.getElementById('filterText').value.trim();
            const statusFilter = document.getElementById('filterStatus').value;
            
            const formData = new FormData();
            formData.append('action', 'get_notes');
            formData.append('search', search);
            formData.append('status_filter', statusFilter);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentNotes = data.notes;
                    updateKPIs(data.kpis);
                    renderNotesTable(data.notes);
                } else {
                    console.error('Error:', data.error);
                    alert('Error al cargar las notas: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
        
        // Actualizar KPIs
        function updateKPIs(kpis) {
            document.getElementById('kpiTotal').textContent = kpis.total;
            document.getElementById('kpiEntregadas').textContent = kpis.entregadas;
            document.getElementById('kpiPendientes').textContent = kpis.pendientes;
            document.getElementById('kpiAnticipo').textContent = kpis.anticipo;
            document.getElementById('kpiAdeudado').textContent = formatCurrency(kpis.adeudado);
        }
        
        // Renderizar tabla de notas
        function renderNotesTable(notes) {
            const loading = document.getElementById('loading');
            const emptyState = document.getElementById('emptyState');
            const table = document.getElementById('notesTable');
            const tbody = document.getElementById('notesTableBody');
            
            loading.style.display = 'none';
            
            if (notes.length === 0) {
                emptyState.style.display = 'block';
                table.style.display = 'none';
                return;
            }
            
            emptyState.style.display = 'none';
            table.style.display = 'table';
            
            tbody.innerHTML = '';
            
            notes.forEach(note => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${note.folio}</strong></td>
                    <td>${formatDate(note.created_at)}</td>
                    <td>${note.client_name}</td>
                    <td>${note.client_phone || '—'}</td>
                    <td>${formatCurrency(note.total)}</td>
                    <td><span class="${getStatusBadgeClass(note.status)}">${getStatusText(note.status)}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-edit" onclick="editNote(${note.id})">Editar</button>
                            <button class="btn-action btn-pdf" onclick="generatePDF(${note.id})">PDF</button>
                            <button class="btn-action btn-delete" onclick="deleteNote(${note.id}, '${note.folio}')">Eliminar</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Editar nota
        function editNote(noteId) {
            const formData = new FormData();
            formData.append('action', 'get_note_details');
            formData.append('note_id', noteId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    openEditModal(data.note, data.items, data.invoice_data);
                } else {
                    alert('Error al cargar la nota: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
        
        // Abrir modal de edición
        function openEditModal(note, items, invoiceData) {
            document.getElementById('editNoteId').value = note.id;
            document.getElementById('editModalFolio').textContent = note.folio;
            
            // Llenar datos del cliente
            document.getElementById('editClientName').value = note.client_name;
            document.getElementById('editClientPhone').value = note.client_phone || '';
            document.getElementById('editClientEmail').value = note.client_email || '';
            document.getElementById('editStatus').value = note.status;
            
            // Llenar datos de pago
            document.getElementById('editPaymentMethod').value = note.payment_method;
            document.getElementById('editAdvancePayment').value = note.advance_payment;
            document.getElementById('editDiscount').value = note.discount;
            document.getElementById('editRequiresInvoice').checked = note.requires_invoice == 1;
            document.getElementById('editObservations').value = note.observations || '';
            
            // Llenar datos de facturación si existen
            if (invoiceData) {
                document.getElementById('editTaxId').value = invoiceData.tax_id;
                document.getElementById('editBusinessName').value = invoiceData.business_name;
                document.getElementById('editInvoiceAddress').value = invoiceData.address;
                document.getElementById('editCfdiUse').value = invoiceData.cfdi_use;
            }
            
            // Mostrar/ocultar campos de facturación
            toggleEditInvoiceFields();
            
            // Cargar productos
            loadEditProducts(items);
            
            // Mostrar modal
            document.getElementById('editModal').classList.add('show');
        }
        
        // Cerrar modal de edición
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        // Toggle campos de facturación en edición
        function toggleEditInvoiceFields() {
            const requiresInvoice = document.getElementById('editRequiresInvoice').checked;
            const invoiceFields = document.getElementById('editInvoiceFields');
            
            invoiceFields.style.display = requiresInvoice ? 'block' : 'none';
            
            calculateEditTotals();
        }
        
        // Cargar productos en el modal de edición
        function loadEditProducts(items) {
            const tbody = document.getElementById('editProductsBody');
            tbody.innerHTML = '';
            editProductCounter = 0;
            
            items.forEach(item => {
                addEditProductRow(item.description, item.quantity, item.unit_price);
            });
            
            if (items.length === 0) {
                addEditProductRow();
            }
            
            calculateEditTotals();
        }
        
        // Agregar fila de producto en edición
        function addEditProduct() {
            addEditProductRow();
        }
        
        function addEditProductRow(description = '', quantity = 1, unitPrice = 0) {
            const tbody = document.getElementById('editProductsBody');
            const id = ++editProductCounter;
            
            const row = document.createElement('tr');
            row.setAttribute('data-id', id);
            row.innerHTML = `
                <td><input type="text" class="form-control product-description" value="${description}" oninput="calculateEditTotals()"></td>
                <td><input type="number" class="form-control product-quantity" value="${quantity}" min="1" oninput="calculateEditTotals()"></td>
                <td><input type="number" class="form-control product-price" value="${unitPrice}" min="0" step="0.01" oninput="calculateEditTotals()"></td>
                <td class="product-subtotal">${formatCurrency(quantity * unitPrice)}</td>
                <td><button type="button" class="btn-delete" onclick="removeEditProduct(${id})">Eliminar</button></td>
            `;
            
            tbody.appendChild(row);
        }
        
        // Eliminar producto en edición
        function removeEditProduct(id) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.remove();
                calculateEditTotals();
            }
        }
        
        // Calcular totales en edición
        function calculateEditTotals() {
            const rows = document.querySelectorAll('#editProductsBody tr');
            let subtotal = 0;
            
            rows.forEach(row => {
                const quantity = parseFloat(row.querySelector('.product-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.product-price').value) || 0;
                const itemSubtotal = quantity * price;
                
                row.querySelector('.product-subtotal').textContent = formatCurrency(itemSubtotal);
                subtotal += itemSubtotal;
            });
            
            const discount = parseFloat(document.getElementById('editDiscount').value) || 0;
            const requiresInvoice = document.getElementById('editRequiresInvoice').checked;
            const advance = parseFloat(document.getElementById('editAdvancePayment').value) || 0;
            
            const subtotalAfterDiscount = Math.max(0, subtotal - discount);
            const tax = requiresInvoice ? (subtotalAfterDiscount * 0.16) : 0;
            const total = subtotalAfterDiscount + tax;
            const currentTotal = Math.max(0, total - advance);
            
            document.getElementById('editSubtotal').value = formatCurrency(subtotal);
            document.getElementById('editTax').value = formatCurrency(tax);
            document.getElementById('editTotal').value = formatCurrency(total);
            document.getElementById('editCurrentTotal').value = formatCurrency(currentTotal);
        }
        
        // Guardar nota editada
        function saveEditNote() {
            const noteId = document.getElementById('editNoteId').value;
            const clientName = document.getElementById('editClientName').value.trim();
            
            if (!clientName) {
                alert('El nombre del cliente es requerido');
                return;
            }
            
            // Recopilar productos
            const items = [];
            const rows = document.querySelectorAll('#editProductsBody tr');
            
            rows.forEach(row => {
                const description = row.querySelector('.product-description').value.trim();
                const quantity = parseInt(row.querySelector('.product-quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.product-price').value) || 0;
                
                if (description && quantity > 0) {
                    items.push({
                        description: description,
                        quantity: quantity,
                        unit_price: unitPrice
                    });
                }
            });
            
            if (items.length === 0) {
                alert('Debe tener al menos un producto');
                return;
            }
            
            // Preparar datos
            const formData = new FormData();
            formData.append('action', 'update_note');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('note_id', noteId);
            formData.append('client_name', clientName);
            formData.append('client_phone', document.getElementById('editClientPhone').value);
            formData.append('client_email', document.getElementById('editClientEmail').value);
            formData.append('status', document.getElementById('editStatus').value);
            formData.append('payment_method', document.getElementById('editPaymentMethod').value);
            formData.append('advance_payment', document.getElementById('editAdvancePayment').value);
            formData.append('discount', document.getElementById('editDiscount').value);
            formData.append('observations', document.getElementById('editObservations').value);
            
            if (document.getElementById('editRequiresInvoice').checked) {
                formData.append('requires_invoice', '1');
                formData.append('tax_id', document.getElementById('editTaxId').value);
                formData.append('business_name', document.getElementById('editBusinessName').value);
                formData.append('invoice_address', document.getElementById('editInvoiceAddress').value);
                formData.append('cfdi_use', document.getElementById('editCfdiUse').value);
            }
            
            // Agregar productos
            items.forEach((item, index) => {
                formData.append(`items[${index}][description]`, item.description);
                formData.append(`items[${index}][quantity]`, item.quantity);
                formData.append(`items[${index}][unit_price]`, item.unit_price);
            });
            
            // Enviar
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditModal();
                    loadNotes();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
        
        // Generar PDF
         function generatePDF(noteId) {
            const note = currentNotes.find(n => n.id == noteId);
            if (!note) {
                alert('Nota no encontrada');
                return;
            }
            
            // Obtener detalles completos de la nota incluyendo productos
            const formData = new FormData();
            formData.append('action', 'get_note_details');
            formData.append('note_id', noteId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Abrir ventana de impresión con todos los datos
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(generatePrintHTML(data.note, data.items, data.invoice_data));
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(() => {
                        printWindow.print();
                    }, 500);
                } else {
                    alert('Error al cargar los detalles de la nota: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al generar PDF');
            });
        }
        
        // Generar HTML para impresión - CON PRODUCTOS COMPLETOS
        function generatePrintHTML(note, items, invoiceData) {
            let itemsHTML = '';
            
            // Generar filas de productos
            if (items && items.length > 0) {
                items.forEach(item => {
                    itemsHTML += `
                        <tr>
                            <td>${item.description}</td>
                            <td>${item.quantity}</td>
                            <td>${formatCurrency(item.unit_price)}</td>
                            <td>${formatCurrency(item.subtotal)}</td>
                        </tr>
                    `;
                });
            } else {
                itemsHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; color: #666;">No hay productos registrados</td>
                    </tr>
                `;
            }
            
            // Generar sección de datos de facturación si existe
            let invoiceSection = '';
            if (note.requires_invoice && invoiceData) {
                invoiceSection = `
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h3>Datos de Facturación</h3>
                        <div><strong>RFC:</strong> ${invoiceData.tax_id}</div>
                        <div><strong>Razón Social:</strong> ${invoiceData.business_name}</div>
                        <div><strong>Dirección Fiscal:</strong> ${invoiceData.address || '—'}</div>
                        <div><strong>Uso CFDI:</strong> ${invoiceData.cfdi_use}</div>
                    </div>
                `;
            }
            
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Nota ${note.folio} - BORMEX</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px; 
                            color: #333; 
                            line-height: 1.4;
                        }
                        .header { 
                            display: flex; 
                            justify-content: space-between; 
                            margin-bottom: 30px;
                            padding-bottom: 15px;
                            border-bottom: 2px solid #333;
                        }
                        .logo { 
                            font-size: 28px; 
                            font-weight: bold; 
                            color: #2d5a3d;
                        }
                        .company-info {
                            font-size: 14px;
                            color: #666;
                            margin-top: 5px;
                        }
                        .note-info { 
                            text-align: right; 
                            font-size: 14px; 
                        }
                        .note-info div {
                            margin: 3px 0;
                        }
                        .client-section { 
                            margin: 30px 0;
                            background: #f8f9fa;
                            padding: 20px;
                            border-radius: 8px;
                        }
                        .client-section h3 {
                            margin-top: 0;
                            color: #2d5a3d;
                            font-size: 18px;
                            border-bottom: 1px solid #ddd;
                            padding-bottom: 10px;
                        }
                        .client-info {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 15px;
                            margin-top: 15px;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin: 30px 0; 
                            font-size: 14px;
                        }
                        th, td { 
                            border: 1px solid #ddd; 
                            padding: 12px 8px; 
                            text-align: left; 
                        }
                        th { 
                            background: #f5f5f5; 
                            font-weight: bold;
                            color: #2d5a3d;
                        }
                        .totals { 
                            margin-top: 30px;
                            border: 2px solid #2d5a3d;
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        .totals-header {
                            background: #2d5a3d;
                            color: white;
                            padding: 12px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .totals-content {
                            padding: 20px;
                            background: #f8f9fa;
                        }
                        .total-row {
                            display: flex;
                            justify-content: space-between;
                            padding: 8px 0;
                            border-bottom: 1px solid #ddd;
                        }
                        .total-row:last-child {
                            border-bottom: none;
                            font-weight: bold;
                            font-size: 16px;
                            margin-top: 10px;
                            padding-top: 15px;
                            border-top: 2px solid #2d5a3d;
                        }
                        .payment-info {
                            margin-top: 30px;
                            padding: 20px;
                            background: #f0f8f4;
                            border-radius: 8px;
                        }
                        .footer { 
                            margin-top: 40px; 
                            font-size: 12px; 
                            color: #666; 
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        .footer .terms {
                            margin-top: 15px;
                            font-style: italic;
                        }
                        .watermark {
                            position: fixed;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%) rotate(-45deg);
                            font-size: 120px;
                            color: rgba(45, 90, 61, 0.05);
                            font-weight: bold;
                            z-index: -1;
                            pointer-events: none;
                        }
                        @media print { 
                            body { margin: 0.5in; } 
                            .no-print { display: none; }
                            .header { page-break-after: avoid; }
                            table { page-break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="watermark">BORMEX</div>
                    
                    <div class="header">
                        <div>
                            <div class="logo">BORMEX</div>
                            <div class="company-info">
                                <div>Bordados y Manualidades Mexicanas</div>
                                <div>San Miguel Sciosla, Puebla</div>
                                <div>Tel: 2211-73-81-50</div>
                            </div>
                        </div>
                        <div class="note-info">
                            <div><strong>Folio: ${note.folio}</strong></div>
                            <div>Fecha: ${formatDate(note.created_at)}</div>
                            <div>Estado: ${getStatusText(note.status)}</div>
                            <div>Método de pago: ${note.payment_method}</div>
                        </div>
                    </div>
                    
                    <div class="client-section">
                        <h3>Información del Cliente</h3>
                        <div class="client-info">
                            <div><strong>Cliente:</strong> ${note.client_name}</div>
                            <div><strong>Teléfono:</strong> ${note.client_phone || '—'}</div>
                            <div><strong>Email:</strong> ${note.client_email || '—'}</div>
                            <div><strong>Dirección:</strong> ${note.client_address || '—'}</div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                    </table>
                    
                    <div class="totals">
                        <div class="totals-header">Resumen de Totales</div>
                        <div class="totals-content">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span>${formatCurrency(note.subtotal + note.discount)}</span>
                            </div>
                            <div class="total-row">
                                <span>Descuento:</span>
                                <span>${formatCurrency(note.discount)}</span>
                            </div>
                            <div class="total-row">
                                <span>IVA (16%):</span>
                                <span>${formatCurrency(note.tax_amount)}</span>
                            </div>
                            <div class="total-row">
                                <span>Total:</span>
                                <span>${formatCurrency(note.total)}</span>
                            </div>
                            <div class="total-row">
                                <span>Anticipo:</span>
                                <span>${formatCurrency(note.advance_payment)}</span>
                            </div>
                            <div class="total-row">
                                <span>TOTAL ACTUAL:</span>
                                <span>${formatCurrency(note.current_total)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-info">
                        <div><strong>Método de pago:</strong> ${note.payment_method}</div>
                        <div><strong>Estado de la nota:</strong> ${getStatusText(note.status)}</div>
                        ${note.observations ? `<div><strong>Observaciones:</strong> ${note.observations}</div>` : ''}
                    </div>
                    
                    ${invoiceSection}
                    
                    <div class="footer">
                        <div><strong>Términos y Condiciones:</strong></div>
                        <div class="terms">
                            <div>• Después de 30 días de la fecha de entrega no se realizan cambios ni devoluciones.</div>
                            <div>• Si el trabajo se hizo conforme a la nota, no nos hacemos responsables de cambios adicionales.</div>
                            <div>• Los precios están sujetos a cambios sin previo aviso.</div>
                            <div>• BORMEX se reserva el derecho de rechazar trabajos que no cumplan con nuestros estándares de calidad.</div>
                        </div>
                    </div>
                    
                    <div class="no-print" style="margin-top: 30px; text-align: center;">
                        <button onclick="window.print()" style="background: #2d5a3d; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; margin-right: 10px;">Imprimir PDF</button>
                        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer;">Cerrar</button>
                    </div>
                </body>
                </html>
            `;
        }
        // Generar HTML para impresión
      
        
        // Eliminar nota
        function deleteNote(noteId, folio) {
            if (!confirm(`¿Está seguro de eliminar la nota ${folio}? Esta acción no se puede deshacer.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_note');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('note_id', noteId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadNotes();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar notas inicialmente
            loadNotes();
            
            // Filtros
            document.getElementById('filterText').addEventListener('input', loadNotes);
            document.getElementById('filterStatus').addEventListener('change', loadNotes);
            document.getElementById('btnClearFilters').addEventListener('click', function() {
                document.getElementById('filterText').value = '';
                document.getElementById('filterStatus').value = '';
                loadNotes();
            });
            
            // Modal de edición
            document.getElementById('editRequiresInvoice').addEventListener('change', toggleEditInvoiceFields);
            document.getElementById('editAdvancePayment').addEventListener('input', calculateEditTotals);
            document.getElementById('editDiscount').addEventListener('input', calculateEditTotals);
        });
        
        console.log('Historial BORMEX inicializado');
        console.log('CSS URLs:', {
            style: '<?php echo asset("css/style.css"); ?>',
            layout: '<?php echo asset("css/layout.css"); ?>',
            dashboard: '<?php echo asset("css/dashboard.css"); ?>'
        });
    </script>
</body>
</html>