<?php
require_once('../../../includes/config.php');

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('../../../index.php');
}

// Obtener configuración de la empresa para generar folio
$company_settings = null;
$nuevo_folio = '';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM company_settings WHERE id = 1");
    $stmt->execute();
    $company_settings = $stmt->fetch();
    
    if ($company_settings) {
        $next_number = $company_settings['current_folio_number'] + 1;
        $nuevo_folio = $company_settings['folio_prefix'] . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }
} catch (Exception $e) {
    error_log("Error obteniendo configuración: " . $e->getMessage());
    $nuevo_folio = 'BM001'; // Fallback
}

$success = '';
$error = '';

// Procesar formulario cuando se envía
if ($_POST && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        $db->beginTransaction();
        
        // Datos de la nota
        $client_name = cleanInput($_POST['client_name']);
        $client_phone = cleanInput($_POST['client_phone'] ?? '');
        $client_email = cleanInput($_POST['client_email'] ?? '');
        $client_address = cleanInput($_POST['client_address'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'efectivo';
        $advance_payment = floatval($_POST['advance_payment'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $requires_invoice = isset($_POST['requires_invoice']) ? 1 : 0;
        $observations = cleanInput($_POST['observations'] ?? '');
        $status = $_POST['status'] ?? 'con_anticipo_trabajandose';
        
        // Validar datos requeridos
        if (empty($client_name)) {
            throw new Exception('El nombre del cliente es requerido');
        }
        
        // Procesar items
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
            throw new Exception('Debe agregar al menos un producto');
        }
        
        // Calcular totales
        $subtotal = $subtotal_total - $discount;
        $tax_amount = $requires_invoice ? ($subtotal * 0.16) : 0;
        $total = $subtotal + $tax_amount;
        $current_total = $total - $advance_payment;
        
        // Asegurar que current_total no sea negativo
        $current_total = max(0, $current_total);
        
        // Insertar nota
        $stmt = $db->prepare("
            INSERT INTO notes (
                folio, client_name, client_phone, client_email, client_address, status, 
                payment_method, advance_payment, discount, requires_invoice, 
                observations, subtotal, tax_amount, total, current_total, 
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $nuevo_folio, $client_name, $client_phone, $client_email, $client_address, $status,
            $payment_method, $advance_payment, $discount, $requires_invoice,
            $observations, $subtotal, $tax_amount, $total, $current_total,
            $_SESSION['user_id']
        ]);
        
        $note_id = $db->lastInsertId();
        
        // Insertar items
        $stmt = $db->prepare("
            INSERT INTO note_items (note_id, description, quantity, unit_price, subtotal) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $note_id,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['subtotal']
            ]);
        }
        
        // Insertar datos de facturación si es necesario
        if ($requires_invoice) {
            $tax_id = cleanInput($_POST['tax_id'] ?? '');
            $business_name = cleanInput($_POST['business_name'] ?? '');
            $address = cleanInput($_POST['invoice_address'] ?? '');
            $cfdi_use = $_POST['cfdi_use'] ?? 'G03';
            
            if (empty($tax_id) || empty($business_name)) {
                throw new Exception('RFC y Razón Social son requeridos para facturación');
            }
            
            $stmt = $db->prepare("
                INSERT INTO invoice_data (note_id, tax_id, business_name, address, cfdi_use) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$note_id, $tax_id, $business_name, $address, $cfdi_use]);
        }
        
        // Actualizar folio en configuración
        if ($company_settings) {
            $stmt = $db->prepare("UPDATE company_settings SET current_folio_number = ? WHERE id = 1");
            $stmt->execute([$company_settings['current_folio_number'] + 1]);
        }
        
        $db->commit();
        
        // Redireccionar al dashboard con mensaje de éxito
        redirect('../../../dashboard.php?success=nota_creada&folio=' . urlencode($nuevo_folio));
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
        error_log("Error creando nota: " . $e->getMessage());
    }
}

$page_title = "Nueva Nota";
$page_description = "Crear nueva nota de trabajo";
$current_page = "notas-nueva";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- CSS - RUTAS CORREGIDAS -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/nueva-nota.css'); ?>">
    
    <style>
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="nueva-nota-container">
    
    <!-- Header -->
    <div class="header-section no-print">
        <a href="<?php echo dashboard_url(); ?>" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15,18 9,12 15,6"></polyline>
            </svg>
            Volver al Dashboard
        </a>
        
        <div class="folio-preview">
            <small>Próximo folio</small>
            <strong id="currentFolio"><?php echo htmlspecialchars($nuevo_folio); ?></strong>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Card Principal -->
    <div class="card">
        
        <!-- Steps Navigation -->
        <div class="steps-nav no-print">
            <button class="step-btn active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Cliente</div>
            </button>
            <button class="step-btn" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">Productos</div>
            </button>
            <button class="step-btn" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">Pago y Facturación</div>
            </button>
            <button class="step-btn" data-step="4">
                <div class="step-number">4</div>
                <div class="step-label">Vista Previa</div>
            </button>
        </div>

        <!-- Formulario -->
        <form id="notaForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- STEP 1: Cliente -->
            <div class="step active" id="step-1">
                <div class="step-header">
                    <h2>Información del Cliente</h2>
                    <p>Ingresa los datos del cliente para esta nota</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="client_name">Nombre del Cliente *</label>
                        <input type="text" id="client_name" name="client_name" class="form-control" 
                               placeholder="Nombre completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_phone">Teléfono</label>
                        <input type="tel" id="client_phone" name="client_phone" class="form-control" 
                               placeholder="(10 dígitos)">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="client_email">Email (opcional)</label>
                        <input type="email" id="client_email" name="client_email" class="form-control" 
                               placeholder="correo@ejemplo.com">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="client_address">Dirección</label>
                        <input type="text" id="client_address" name="client_address" class="form-control" 
                               placeholder="Dirección completa del cliente">
                    </div>
                </div>

                <div class="step-actions">
                    <a href="<?php echo dashboard_url(); ?>" class="btn-secondary">Cancelar</a>
                    <button type="button" class="btn-primary" onclick="nextStep()">Siguiente</button>
                </div>
            </div>

            <!-- STEP 2: Productos -->
            <div class="step" id="step-2">
                <div class="step-header">
                    <h2>Productos y Servicios</h2>
                    <p>Agrega los productos o servicios para esta nota</p>
                </div>

                <!-- Agregar Producto -->
                <div class="add-product-section">
                    <h3>Agregar Producto</h3>
                    <div class="add-product-form">
                        <div class="form-group">
                            <input type="text" id="product_description" class="form-control" 
                                   placeholder="Descripción del producto">
                        </div>
                        <div class="form-group">
                            <input type="number" id="product_quantity" class="form-control" 
                                   placeholder="Cantidad" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <input type="number" id="product_price" class="form-control" 
                                   placeholder="Precio unitario" min="0" step="0.01">
                        </div>
                        <div class="subtotal-display" id="product_subtotal">$0.00</div>
                        <button type="button" class="btn-add-product" onclick="addProduct()">Agregar</button>
                    </div>
                </div>

                <!-- Lista de Productos -->
                <div class="products-header">
                    <h3>Productos Agregados</h3>
                    <div class="total-display" id="totalProducts">$0.00</div>
                </div>

                <!-- Estado vacío -->
                <div class="products-empty" id="productsEmpty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="7.5,4.21 12,6.81 16.5,4.21"></polyline>
                        <polyline points="7.5,19.79 7.5,14.6 3,12"></polyline>
                        <polyline points="21,12 16.5,14.6 16.5,19.79"></polyline>
                    </svg>
                    <p>No hay productos agregados</p>
                    <small>Usa el formulario de arriba para agregar productos</small>
                </div>

                <!-- Tabla de productos -->
                <div class="products-table" id="productsTable" style="display: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody"></tbody>
                    </table>
                </div>

                <!-- Observaciones -->
                <div class="form-group full-width">
                    <label for="observations">Observaciones/Instrucciones</label>
                    <textarea id="observations" name="observations" class="form-control" rows="3" 
                              placeholder="Instrucciones especiales, colores, tallas, etc."></textarea>
                </div>

                <div class="step-actions">
                    <button type="button" class="btn-secondary" onclick="prevStep()">Anterior</button>
                    <button type="button" class="btn-primary" onclick="nextStep()">Siguiente</button>
                </div>
            </div>

            <!-- STEP 3: Pago y Facturación -->
            <div class="step" id="step-3">
                <div class="step-header">
                    <h2>Pago y Facturación</h2>
                    <p>Configura el método de pago y datos de facturación</p>
                </div>

                <div class="form-sections">
                    <div class="form-section">
                        <h3>Información de Pago</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="payment_method">Método de Pago</label>
                                <select id="payment_method" name="payment_method" class="form-control">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="tarjeta">Tarjeta</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Estado de la Nota</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="con_anticipo_trabajandose">Con Anticipo Trabajándose</option>
                                    <option value="liquidada_pendiente_entrega">Liquidada Pendiente Entrega</option>
                                    <option value="pagada_y_entregada">Pagada y Entregada</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="advance_payment">Anticipo</label>
                                <input type="number" id="advance_payment" name="advance_payment" 
                                       class="form-control" min="0" step="0.01" value="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="discount">Descuento</label>
                                <input type="number" id="discount" name="discount" 
                                       class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        
                        <div class="checkbox-label">
                            <input type="checkbox" id="requires_invoice" name="requires_invoice" value="1">
                            <span>Requiere Factura (se agregará IVA del 16%)</span>
                        </div>
                    </div>

                    <div class="form-section" id="invoice_fields" style="display: none;">
                        <h3>Datos de Facturación</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tax_id">RFC *</label>
                                <input type="text" id="tax_id" name="tax_id" class="form-control" 
                                       placeholder="RFC del cliente">
                            </div>
                            
                            <div class="form-group">
                                <label for="cfdi_use">Uso de CFDI</label>
                                <input type="text" id="cfdi_use" name="cfdi_use" class="form-control" 
                                       placeholder="G03 - Gastos en general" value="G03">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="business_name">Razón Social *</label>
                                <input type="text" id="business_name" name="business_name" class="form-control" 
                                       placeholder="Nombre de la empresa">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="invoice_address">Dirección Fiscal</label>
                                <input type="text" id="invoice_address" name="invoice_address" class="form-control" 
                                       placeholder="Dirección completa">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totales en vivo -->
                <div class="totals-section">
                    <h3>Resumen de Totales</h3>
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="display_subtotal">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Descuento:</span>
                        <span id="display_discount">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>IVA (16%):</span>
                        <span id="display_tax">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Total:</span>
                        <span id="display_total">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Anticipo:</span>
                        <span id="display_advance">$0.00</span>
                    </div>
                    <div class="total-row current">
                        <span>Total Actual:</span>
                        <span id="display_current">$0.00</span>
                    </div>
                </div>

                <div class="step-actions">
                    <button type="button" class="btn-secondary" onclick="prevStep()">Anterior</button>
                    <button type="button" class="btn-primary" onclick="nextStep()">Vista Previa</button>
                </div>
            </div>

            <!-- STEP 4: Vista Previa -->
            <div class="step" id="step-4">
                <div class="step-header">
                    <h2>Vista Previa de la Nota</h2>
                    <p>Revisa toda la información antes de guardar</p>
                </div>

                <!-- Vista previa de impresión -->
                <div class="print-area" id="print-area">
                    <div class="watermark">
                        <div class="logo">BOR<span>MEX</span></div>
                    </div>
                    
                    <div class="print-header">
                        <div>
                            <h2>BORMEX · Nota de Trabajo</h2>
                            <div class="print-meta"><strong>Folio:</strong> <?php echo htmlspecialchars($nuevo_folio); ?></div>
                            <div class="print-meta"><strong>Cliente:</strong> <span id="preview_client"></span></div>
                            <div class="print-meta"><strong>Dirección:</strong> <span id="preview_client_address"></span></div>
                            <div class="print-meta"><strong>Tel:</strong> <span id="preview_phone"></span> · <strong>Email:</strong> <span id="preview_email"></span></div>
                        </div>
                        <div class="print-meta">
                            <div><strong>Dirección:</strong> <?php echo htmlspecialchars($company_settings['address'] ?? 'San Miguel Sciosla, Puebla'); ?></div>
                            <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($company_settings['phone'] ?? '2211-73-81-50'); ?></div>
                            <div><strong>Fecha:</strong> <span id="preview_date"></span></div>
                        </div>
                    </div>

                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="preview_items"></tbody>
                        <tfoot>
                            <tr><th colspan="3">Subtotal</th><td id="preview_subtotal">$0.00</td></tr>
                            <tr><th colspan="3">Descuento</th><td id="preview_discount_amount">$0.00</td></tr>
                            <tr><th colspan="3">IVA (16%)</th><td id="preview_tax">$0.00</td></tr>
                            <tr><th colspan="3">Total</th><td id="preview_total">$0.00</td></tr>
                            <tr><th colspan="3">Anticipo</th><td id="preview_advance">$0.00</td></tr>
                            <tr><th colspan="3"><strong>Total Actual</strong></th><td id="preview_current"><strong>$0.00</strong></td></tr>
                        </tfoot>
                    </table>

                    <div class="print-footer">
                        <div><strong>Método de pago:</strong> <span id="preview_method"></span></div>
                        <div><strong>Estado:</strong> <span id="preview_status"></span></div>
                        <div><strong>Observaciones:</strong> <span id="preview_observations"></span></div>
                        <div class="terms">
                            <p>Después de 30 días de la fecha de entrega no se realizan cambios ni devoluciones.</p>
                            <p>Si el trabajo se hizo conforme a la nota, no nos hacemos responsables de cambios adicionales.</p>
                        </div>
                    </div>
                </div>

                <div class="step-actions no-print">
                    <button type="button" class="btn-secondary" onclick="prevStep()">Anterior</button>
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="btn-secondary" onclick="printNote()">Imprimir</button>
                        <button type="submit" class="btn-primary">Guardar Nota</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Variables globales
        let currentStep = 1;
        let products = [];
        let productCounter = 0;

        // Formatear moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(amount || 0);
        }

        // Navegación de pasos - CON DEBUGGING
        function showStep(step) {
            console.log(`Navegando al paso ${step}`);
            
            // Verificar que los elementos existen
            const stepElement = document.getElementById(`step-${step}`);
            const stepBtn = document.querySelector(`[data-step="${step}"]`);
            
            if (!stepElement) {
                console.error(`Elemento step-${step} no encontrado`);
                return;
            }
            
            if (!stepBtn) {
                console.error(`Botón data-step="${step}" no encontrado`);
                return;
            }
            
            // Ocultar todos los pasos
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step-btn').forEach(s => s.classList.remove('active'));
            
            // Mostrar paso actual
            stepElement.classList.add('active');
            stepBtn.classList.add('active');
            
            currentStep = step;
            console.log(`Paso cambiado a: ${currentStep}`);
            
            // Acciones específicas por paso
            if (step === 4) {
                console.log('Generando vista previa...');
                generatePreview();
            }
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function nextStep() {
            console.log(`nextStep llamado. Paso actual: ${currentStep}`);
            
            if (!validateCurrentStep()) {
                console.log('Validación fallida');
                return;
            }
            
            if (currentStep < 4) {
                showStep(currentStep + 1);
            } else {
                console.log('Ya estás en el último paso');
            }
        }

        function prevStep() {
            console.log(`prevStep llamado. Paso actual: ${currentStep}`);
            
            if (currentStep > 1) {
                showStep(currentStep - 1);
            } else {
                console.log('Ya estás en el primer paso');
            }
        }

        // Validaciones
        function validateCurrentStep() {
            switch (currentStep) {
                case 1:
                    const clientName = document.getElementById('client_name').value.trim();
                    if (!clientName) {
                        alert('El nombre del cliente es requerido');
                        document.getElementById('client_name').focus();
                        return false;
                    }
                    return true;
                    
                case 2:
                    if (products.length === 0) {
                        alert('Debe agregar al menos un producto');
                        return false;
                    }
                    return true;
                    
                case 3:
                    const requiresInvoice = document.getElementById('requires_invoice').checked;
                    if (requiresInvoice) {
                        const taxId = document.getElementById('tax_id').value.trim();
                        const businessName = document.getElementById('business_name').value.trim();
                        
                        if (!taxId || !businessName) {
                            alert('RFC y Razón Social son requeridos para facturación');
                            return false;
                        }
                    }
                    return true;
            }
            return true;
        }

        // Gestión de productos
        function updateProductSubtotal() {
            const quantity = parseFloat(document.getElementById('product_quantity').value) || 0;
            const price = parseFloat(document.getElementById('product_price').value) || 0;
            const subtotal = quantity * price;
            
            document.getElementById('product_subtotal').textContent = formatCurrency(subtotal);
        }

        function addProduct() {
            const description = document.getElementById('product_description').value.trim();
            const quantity = parseFloat(document.getElementById('product_quantity').value) || 0;
            const price = parseFloat(document.getElementById('product_price').value) || 0;
            
            if (!description) {
                alert('Ingrese una descripción para el producto');
                document.getElementById('product_description').focus();
                return;
            }
            
            if (quantity <= 0) {
                alert('La cantidad debe ser mayor a 0');
                document.getElementById('product_quantity').focus();
                return;
            }
            
            if (price < 0) {
                alert('El precio no puede ser negativo');
                document.getElementById('product_price').focus();
                return;
            }
            
            const product = {
                id: ++productCounter,
                description: description,
                quantity: quantity,
                unit_price: price,
                subtotal: quantity * price
            };
            
            products.push(product);
            
            // Limpiar formulario
            document.getElementById('product_description').value = '';
            document.getElementById('product_quantity').value = '1';
            document.getElementById('product_price').value = '';
            document.getElementById('product_subtotal').textContent = '$0.00';
            
            updateProductsDisplay();
            updateTotals();
            
            document.getElementById('product_description').focus();
        }

        function removeProduct(productId) {
            products = products.filter(p => p.id !== productId);
            updateProductsDisplay();
            updateTotals();
        }

        function updateProductsDisplay() {
            const emptyState = document.getElementById('productsEmpty');
            const tableContainer = document.getElementById('productsTable');
            const tableBody = document.getElementById('productsTableBody');
            const totalDisplay = document.getElementById('totalProducts');
            
            if (products.length === 0) {
                emptyState.style.display = 'block';
                tableContainer.style.display = 'none';
                totalDisplay.textContent = '$0.00';
            } else {
                emptyState.style.display = 'none';
                tableContainer.style.display = 'block';
                
                tableBody.innerHTML = '';
                let total = 0;
                
                products.forEach(product => {
                    total += product.subtotal;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(product.description)}</td>
                        <td>${product.quantity}</td>
                        <td>${formatCurrency(product.unit_price)}</td>
                        <td>${formatCurrency(product.subtotal)}</td>
                        <td>
                            <button type="button" class="btn-remove" onclick="removeProduct(${product.id})">
                                Eliminar
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
                
                totalDisplay.textContent = formatCurrency(total);
            }
            
            createHiddenProductFields();
        }

        function createHiddenProductFields() {
            document.querySelectorAll('input[name^="items["]').forEach(input => {
                input.remove();
            });
            
            const form = document.getElementById('notaForm');
            
            products.forEach((product, index) => {
                const fields = [
                    { name: `items[${index}][description]`, value: product.description },
                    { name: `items[${index}][quantity]`, value: product.quantity },
                    { name: `items[${index}][unit_price]`, value: product.unit_price }
                ];
                
                fields.forEach(field => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field.name;
                    input.value = field.value;
                    form.appendChild(input);
                });
            });
        }

        // Cálculo de totales
        function updateTotals() {
            const subtotal = products.reduce((sum, product) => sum + product.subtotal, 0);
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const requiresInvoice = document.getElementById('requires_invoice').checked;
            const advance = parseFloat(document.getElementById('advance_payment').value) || 0;
            
            const subtotalAfterDiscount = Math.max(0, subtotal - discount);
            const tax = requiresInvoice ? (subtotalAfterDiscount * 0.16) : 0;
            const total = subtotalAfterDiscount + tax;
            const currentTotal = Math.max(0, total - advance);
            
            // Actualizar displays
            const subtotalEl = document.getElementById('display_subtotal');
            const discountEl = document.getElementById('display_discount');
            const taxEl = document.getElementById('display_tax');
            const totalEl = document.getElementById('display_total');
            const advanceEl = document.getElementById('display_advance');
            const currentEl = document.getElementById('display_current');
            
            if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
            if (discountEl) discountEl.textContent = formatCurrency(discount);
            if (taxEl) taxEl.textContent = formatCurrency(tax);
            if (totalEl) totalEl.textContent = formatCurrency(total);
            if (advanceEl) advanceEl.textContent = formatCurrency(advance);
            if (currentEl) currentEl.textContent = formatCurrency(currentTotal);
        }

        // Toggle campos de facturación
        function toggleInvoiceFields() {
            const requiresInvoice = document.getElementById('requires_invoice').checked;
            const invoiceFields = document.getElementById('invoice_fields');
            const taxIdField = document.getElementById('tax_id');
            const businessNameField = document.getElementById('business_name');
            
            if (requiresInvoice) {
                invoiceFields.style.display = 'block';
                if (taxIdField) taxIdField.required = true;
                if (businessNameField) businessNameField.required = true;
            } else {
                invoiceFields.style.display = 'none';
                if (taxIdField) taxIdField.required = false;
                if (businessNameField) businessNameField.required = false;
            }
            
            updateTotals();
        }

        // Vista previa
        function generatePreview() {
            const clientName = document.getElementById('client_name').value.trim();
            const clientPhone = document.getElementById('client_phone').value.trim();
            const clientEmail = document.getElementById('client_email').value.trim();
            const clientAddress = document.getElementById('client_address').value.trim();
            const paymentMethod = document.getElementById('payment_method').value;
            const status = document.getElementById('status').value;
            const observations = document.getElementById('observations').value.trim();
            
            // Llenar datos del cliente
            const elements = {
                'preview_client': clientName || '—',
                'preview_phone': clientPhone || '—',
                'preview_email': clientEmail || '—',
                'preview_client_address': clientAddress || '—',
                'preview_date': new Date().toLocaleString('es-MX'),
                'preview_method': getPaymentMethodText(paymentMethod),
                'preview_status': getStatusText(status),
                'preview_observations': observations || '—'
            };
            
            Object.keys(elements).forEach(id => {
                const element = document.getElementById(id);
                if (element) element.textContent = elements[id];
            });
            
            // Llenar tabla de productos
            const tbody = document.getElementById('preview_items');
            if (tbody) {
                tbody.innerHTML = '';
                
                products.forEach(product => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(product.description)}</td>
                        <td>${product.quantity}</td>
                        <td>${formatCurrency(product.unit_price)}</td>
                        <td>${formatCurrency(product.subtotal)}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            // Calcular y mostrar totales
            const subtotal = products.reduce((sum, product) => sum + product.subtotal, 0);
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const requiresInvoice = document.getElementById('requires_invoice').checked;
            const advance = parseFloat(document.getElementById('advance_payment').value) || 0;
            
            const subtotalAfterDiscount = Math.max(0, subtotal - discount);
            const tax = requiresInvoice ? (subtotalAfterDiscount * 0.16) : 0;
            const total = subtotalAfterDiscount + tax;
            const currentTotal = Math.max(0, total - advance);
            
            const previewElements = {
                'preview_subtotal': formatCurrency(subtotal),
                'preview_discount_amount': formatCurrency(discount),
                'preview_tax': formatCurrency(tax),
                'preview_total': formatCurrency(total),
                'preview_advance': formatCurrency(advance),
                'preview_current': formatCurrency(currentTotal)
            };
            
            Object.keys(previewElements).forEach(id => {
                const element = document.getElementById(id);
                if (element) element.textContent = previewElements[id];
            });
        }

        // Utilidades
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function getPaymentMethodText(method) {
            const methods = {
                'efectivo': 'Efectivo',
                'transferencia': 'Transferencia',
                'tarjeta': 'Tarjeta'
            };
            return methods[method] || method;
        }

        function getStatusText(status) {
            const statuses = {
                'con_anticipo_trabajandose': 'Con Anticipo Trabajándose',
                'liquidada_pendiente_entrega': 'Liquidada Pendiente Entrega',
                'pagada_y_entregada': 'Pagada y Entregada'
            };
            return statuses[status] || status;
        }

        function printNote() {
            window.print();
        }

        // Event listeners - CON DEBUGGING MEJORADO
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado correctamente');
            
            // Verificar que todos los elementos críticos existen
            const criticalElements = [
                'product_quantity', 'product_price', 'advance_payment', 
                'discount', 'requires_invoice', 'step-1', 'step-2', 'step-3', 'step-4'
            ];
            
            criticalElements.forEach(id => {
                const element = document.getElementById(id);
                if (!element) {
                    console.error(`Elemento crítico faltante: ${id}`);
                } else {
                    console.log(`Elemento encontrado: ${id}`);
                }
            });
            
            // Verificar botones de pasos
            const stepButtons = document.querySelectorAll('.step-btn');
            console.log(`Botones de pasos encontrados: ${stepButtons.length}`);
            
            // Configurar event listeners
            const quantityInput = document.getElementById('product_quantity');
            const priceInput = document.getElementById('product_price');
            const advanceInput = document.getElementById('advance_payment');
            const discountInput = document.getElementById('discount');
            const invoiceCheckbox = document.getElementById('requires_invoice');
            
            if (quantityInput) {
                quantityInput.addEventListener('input', updateProductSubtotal);
                console.log('Event listener agregado a quantity');
            }
            
            if (priceInput) {
                priceInput.addEventListener('input', updateProductSubtotal);
                console.log('Event listener agregado a price');
            }
            
            if (advanceInput) {
                advanceInput.addEventListener('input', updateTotals);
                console.log('Event listener agregado a advance');
            }
            
            if (discountInput) {
                discountInput.addEventListener('input', updateTotals);
                console.log('Event listener agregado a discount');
            }
            
            if (invoiceCheckbox) {
                invoiceCheckbox.addEventListener('change', toggleInvoiceFields);
                console.log('Event listener agregado a invoice checkbox');
            }
            
            // Inicializar
            updateProductsDisplay();
            updateTotals();
            
            console.log('Inicialización completada');
        });

        // Navigation con botones
        document.querySelectorAll('.step-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const step = parseInt(this.dataset.step);
                if (step <= currentStep || currentStep === 4) {
                    showStep(step);
                }
            });
        });
    </script>
</body>
</html>