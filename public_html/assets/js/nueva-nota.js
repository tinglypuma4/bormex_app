/**
 * Nueva Nota - JavaScript
 * Funcionalidad completa para el formulario de crear notas
 */

// Variables globales
let currentStep = 1;
let maxStep = 4;
let products = [];
let productCounter = 0;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
    setupEventListeners();
    updateProductsDisplay();
    updateTotals();
    
    console.log('Formulario de Nueva Nota inicializado');
});

/**
 * Inicializar formulario
 */
function initializeForm() {
    // Configurar paso inicial
    showStep(1);
    
    // Limpiar campos de productos
    document.getElementById('product_description').value = '';
    document.getElementById('product_quantity').value = '1';
    document.getElementById('product_price').value = '';
    document.getElementById('product_subtotal').textContent = '$0.00';
    
    // Configurar campos de totales
    updateTotals();
    
    // Enfocar primer campo
    const firstInput = document.getElementById('client_name');
    if (firstInput) {
        firstInput.focus();
    }
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Navegación por pasos
    document.querySelectorAll('.step-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const step = parseInt(this.dataset.step);
            if (step <= getMaxAllowedStep()) {
                showStep(step);
            }
        });
    });
    
    // Campos de productos
    const quantityInput = document.getElementById('product_quantity');
    const priceInput = document.getElementById('product_price');
    
    quantityInput.addEventListener('input', updateProductSubtotal);
    priceInput.addEventListener('input', updateProductSubtotal);
    
    // Campos de totales
    document.getElementById('advance_payment').addEventListener('input', updateTotals);
    document.getElementById('discount').addEventListener('input', updateTotals);
    document.getElementById('requires_invoice').addEventListener('change', function() {
        toggleInvoiceFields();
        updateTotals();
    });
    
    // Teclas de acceso rápido
    document.addEventListener('keydown', function(e) {
        // Enter en campos de producto para agregar
        if (e.key === 'Enter' && e.target.closest('.add-product-form')) {
            e.preventDefault();
            addProduct();
        }
        
        // Escape para limpiar formulario de producto
        if (e.key === 'Escape' && e.target.closest('.add-product-form')) {
            clearProductForm();
        }
    });
    
    // Validación en tiempo real
    document.getElementById('client_name').addEventListener('blur', validateClientName);
    document.getElementById('client_phone').addEventListener('blur', validatePhone);
    document.getElementById('client_email').addEventListener('blur', validateEmail);
    
    // Prevenir envío accidental del formulario
    document.getElementById('notaForm').addEventListener('submit', handleFormSubmit);
}

/**
 * Mostrar paso específico
 */
function showStep(step) {
    if (step < 1 || step > maxStep) return;
    
    // Validar paso actual antes de avanzar
    if (step > currentStep && !validateCurrentStep()) {
        return;
    }
    
    // Ocultar todos los pasos
    document.querySelectorAll('.step').forEach(stepDiv => {
        stepDiv.style.display = 'none';
    });
    
    // Mostrar paso actual
    const targetStep = document.getElementById(`step-${step}`);
    if (targetStep) {
        targetStep.style.display = 'block';
    }
    
    // Actualizar navegación
    updateStepNavigation(step);
    
    // Acciones específicas por paso
    handleStepSpecificActions(step);
    
    currentStep = step;
    
    // Scroll al top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Siguiente paso
 */
function nextStep() {
    if (validateCurrentStep() && currentStep < maxStep) {
        showStep(currentStep + 1);
    }
}

/**
 * Paso anterior
 */
function prevStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

/**
 * Actualizar navegación de pasos
 */
function updateStepNavigation(activeStep) {
    document.querySelectorAll('.step-btn').forEach((btn, index) => {
        const stepNumber = index + 1;
        
        btn.classList.remove('active', 'completed');
        
        if (stepNumber === activeStep) {
            btn.classList.add('active');
        } else if (stepNumber < activeStep) {
            btn.classList.add('completed');
        }
    });
}

/**
 * Obtener paso máximo permitido
 */
function getMaxAllowedStep() {
    if (products.length === 0) return 2;
    return maxStep;
}

/**
 * Validar paso actual
 */
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            return validateStep1();
        case 2:
            return validateStep2();
        case 3:
            return validateStep3();
        case 4:
            return true; // Resumen, siempre válido
        default:
            return true;
    }
}

/**
 * Validar paso 1 - Cliente
 */
function validateStep1() {
    const clientName = document.getElementById('client_name').value.trim();
    
    if (!clientName) {
        showAlert('El nombre del cliente es requerido', 'error');
        document.getElementById('client_name').focus();
        return false;
    }
    
    return validateClientName() && validatePhone() && validateEmail();
}

/**
 * Validar paso 2 - Productos
 */
function validateStep2() {
    if (products.length === 0) {
        showAlert('Debe agregar al menos un producto', 'error');
        document.getElementById('product_description').focus();
        return false;
    }
    
    return true;
}

/**
 * Validar paso 3 - Pago
 */
function validateStep3() {
    const requiresInvoice = document.getElementById('requires_invoice').checked;
    
    if (requiresInvoice) {
        const taxId = document.getElementById('tax_id').value.trim();
        const businessName = document.getElementById('business_name').value.trim();
        
        if (!taxId) {
            showAlert('RFC es requerido para facturación', 'error');
            document.getElementById('tax_id').focus();
            return false;
        }
        
        if (!businessName) {
            showAlert('Razón Social es requerida para facturación', 'error');
            document.getElementById('business_name').focus();
            return false;
        }
    }
    
    return true;
}

/**
 * Validaciones específicas
 */
function validateClientName() {
    const input = document.getElementById('client_name');
    const value = input.value.trim();
    
    if (value.length < 2) {
        input.setCustomValidity('El nombre debe tener al menos 2 caracteres');
        return false;
    }
    
    input.setCustomValidity('');
    return true;
}

function validatePhone() {
    const input = document.getElementById('client_phone');
    const value = input.value.trim();
    
    if (value && !/^\d{10}$/.test(value.replace(/\D/g, ''))) {
        input.setCustomValidity('Ingrese un teléfono válido de 10 dígitos');
        return false;
    }
    
    input.setCustomValidity('');
    return true;
}

function validateEmail() {
    const input = document.getElementById('client_email');
    const value = input.value.trim();
    
    if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        input.setCustomValidity('Ingrese un email válido');
        return false;
    }
    
    input.setCustomValidity('');
    return true;
}

/**
 * Acciones específicas por paso
 */
function handleStepSpecificActions(step) {
    switch (step) {
        case 2:
            // Enfocar campo de descripción
            document.getElementById('product_description').focus();
            break;
        case 4:
            // Generar resumen
            generateSummary();
            break;
    }
}

/**
 * Actualizar subtotal de producto
 */
function updateProductSubtotal() {
    const quantity = parseFloat(document.getElementById('product_quantity').value) || 0;
    const price = parseFloat(document.getElementById('product_price').value) || 0;
    const subtotal = quantity * price;
    
    document.getElementById('product_subtotal').textContent = formatCurrency(subtotal);
}

/**
 * Agregar producto
 */
function addProduct() {
    const description = document.getElementById('product_description').value.trim();
    const quantity = parseFloat(document.getElementById('product_quantity').value) || 0;
    const price = parseFloat(document.getElementById('product_price').value) || 0;
    
    // Validaciones
    if (!description) {
        showAlert('Ingrese una descripción para el producto', 'error');
        document.getElementById('product_description').focus();
        return;
    }
    
    if (quantity <= 0) {
        showAlert('La cantidad debe ser mayor a 0', 'error');
        document.getElementById('product_quantity').focus();
        return;
    }
    
    if (price <= 0) {
        showAlert('El precio debe ser mayor a 0', 'error');
        document.getElementById('product_price').focus();
        return;
    }
    
    // Agregar producto
    const product = {
        id: ++productCounter,
        description: description,
        quantity: quantity,
        unit_price: price,
        subtotal: quantity * price
    };
    
    products.push(product);
    
    // Limpiar formulario
    clearProductForm();
    
    // Actualizar displays
    updateProductsDisplay();
    updateTotals();
    
    // Enfocar descripción para siguiente producto
    document.getElementById('product_description').focus();
    
    showAlert('Producto agregado correctamente', 'success');
}

/**
 * Eliminar producto
 */
function removeProduct(productId) {
    products = products.filter(p => p.id !== productId);
    updateProductsDisplay();
    updateTotals();
    showAlert('Producto eliminado', 'info');
}

/**
 * Limpiar formulario de producto
 */
function clearProductForm() {
    document.getElementById('product_description').value = '';
    document.getElementById('product_quantity').value = '1';
    document.getElementById('product_price').value = '';
    document.getElementById('product_subtotal').textContent = '$0.00';
}

/**
 * Actualizar display de productos
 */
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
        
        // Llenar tabla
        tableBody.innerHTML = '';
        let total = 0;
        
        products.forEach(product => {
            total += product.subtotal;
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="product-description">${escapeHtml(product.description)}</td>
                <td class="product-quantity">${product.quantity}</td>
                <td class="product-price">${formatCurrency(product.unit_price)}</td>
                <td class="product-subtotal">${formatCurrency(product.subtotal)}</td>
                <td class="product-actions">
                    <button type="button" class="btn-remove" onclick="removeProduct(${product.id})" title="Eliminar producto">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3,6 5,6 21,6"></polyline>
                            <path d="M19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"></path>
                        </svg>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
        
        totalDisplay.textContent = formatCurrency(total);
    }
    
    // Crear campos ocultos para envío
    createHiddenProductFields();
}

/**
 * Crear campos ocultos para productos
 */
function createHiddenProductFields() {
    // Remover campos existentes
    document.querySelectorAll('input[name^="items["]').forEach(input => {
        input.remove();
    });
    
    // Crear nuevos campos
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

/**
 * Alternar campos de factura
 */
function toggleInvoiceFields() {
    const requiresInvoice = document.getElementById('requires_invoice').checked;
    const invoiceFields = document.getElementById('invoice_fields');
    
    if (requiresInvoice) {
        invoiceFields.style.display = 'block';
        // Hacer campos requeridos
        document.getElementById('tax_id').required = true;
        document.getElementById('business_name').required = true;
    } else {
        invoiceFields.style.display = 'none';
        // Quitar required
        document.getElementById('tax_id').required = false;
        document.getElementById('business_name').required = false;
        // Limpiar valores
        document.getElementById('tax_id').value = '';
        document.getElementById('business_name').value = '';
        document.getElementById('address').value = '';
    }
}

/**
 * Actualizar totales
 */
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
    document.getElementById('display_subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('display_discount').textContent = formatCurrency(discount);
    document.getElementById('display_tax').textContent = formatCurrency(tax);
    document.getElementById('display_total').textContent = formatCurrency(total);
    document.getElementById('display_advance').textContent = formatCurrency(advance);
    document.getElementById('display_current').textContent = formatCurrency(currentTotal);
}

/**
 * Generar resumen
 */
function generateSummary() {
    const clientName = document.getElementById('client_name').value.trim();
    const clientPhone = document.getElementById('client_phone').value.trim();
    const clientEmail = document.getElementById('client_email').value.trim();
    const paymentMethod = document.getElementById('payment_method').value;
    const advance = parseFloat(document.getElementById('advance_payment').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const requiresInvoice = document.getElementById('requires_invoice').checked;
    const observations = document.getElementById('observations').value.trim();
    
    const subtotal = products.reduce((sum, product) => sum + product.subtotal, 0);
    const subtotalAfterDiscount = Math.max(0, subtotal - discount);
    const tax = requiresInvoice ? (subtotalAfterDiscount * 0.16) : 0;
    const total = subtotalAfterDiscount + tax;
    const currentTotal = Math.max(0, total - advance);
    
    const folio = document.getElementById('currentFolio').textContent;
    
    let html = `
        <div class="summary-card">
            <h4>Información del Cliente</h4>
            <div class="summary-row">
                <span>Nombre:</span>
                <strong>${escapeHtml(clientName)}</strong>
            </div>
            ${clientPhone ? `<div class="summary-row">
                <span>Teléfono:</span>
                <span>${escapeHtml(clientPhone)}</span>
            </div>` : ''}
            ${clientEmail ? `<div class="summary-row">
                <span>Email:</span>
                <span>${escapeHtml(clientEmail)}</span>
            </div>` : ''}
            <div class="summary-row">
                <span>Folio:</span>
                <strong>${escapeHtml(folio)}</strong>
            </div>
        </div>
        
        <div class="summary-card">
            <h4>Productos y Servicios</h4>
            <div class="summary-products">
    `;
    
    products.forEach(product => {
        html += `
            <div class="summary-product">
                <div>
                    <strong>${escapeHtml(product.description)}</strong><br>
                    <small>${product.quantity} × ${formatCurrency(product.unit_price)}</small>
                </div>
                <div>${formatCurrency(product.subtotal)}</div>
            </div>
        `;
    });
    
    html += `
            </div>
        </div>
        
        <div class="summary-card">
            <h4>Información de Pago</h4>
            <div class="summary-row">
                <span>Método de pago:</span>
                <span>${getPaymentMethodText(paymentMethod)}</span>
            </div>
            ${advance > 0 ? `<div class="summary-row">
                <span>Anticipo:</span>
                <span>${formatCurrency(advance)}</span>
            </div>` : ''}
            ${discount > 0 ? `<div class="summary-row">
                <span>Descuento:</span>
                <span>${formatCurrency(discount)}</span>
            </div>` : ''}
            <div class="summary-row">
                <span>¿Requiere factura?</span>
                <span>${requiresInvoice ? 'Sí' : 'No'}</span>
            </div>
        </div>
        
        <div class="summary-card">
            <h4>Totales</h4>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>${formatCurrency(subtotal)}</span>
            </div>
            ${discount > 0 ? `<div class="summary-row">
                <span>Descuento:</span>
                <span>-${formatCurrency(discount)}</span>
            </div>` : ''}
            ${tax > 0 ? `<div class="summary-row">
                <span>IVA (16%):</span>
                <span>${formatCurrency(tax)}</span>
            </div>` : ''}
            <div class="summary-row">
                <span><strong>Total:</strong></span>
                <span><strong>${formatCurrency(total)}</strong></span>
            </div>
            ${advance > 0 ? `<div class="summary-row">
                <span>Anticipo:</span>
                <span>-${formatCurrency(advance)}</span>
            </div>` : ''}
            <div class="summary-row">
                <span><strong>Total Actual:</strong></span>
                <span><strong>${formatCurrency(currentTotal)}</strong></span>
            </div>
        </div>
        
        ${observations ? `<div class="summary-card">
            <h4>Observaciones</h4>
            <p>${escapeHtml(observations)}</p>
        </div>` : ''}
    `;
    
    document.getElementById('summaryContent').innerHTML = html;
}

/**
 * Manejar envío del formulario
 */
function handleFormSubmit(e) {
    if (currentStep !== 4) {
        e.preventDefault();
        showAlert('Complete todos los pasos antes de guardar', 'error');
        return false;
    }
    
    if (products.length === 0) {
        e.preventDefault();
        showAlert('Debe agregar al menos un producto', 'error');
        showStep(2);
        return false;
    }
    
    // Deshabilitar botón de envío para evitar doble envío
    const submitBtns = document.querySelectorAll('button[type="submit"]');
    submitBtns.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline></svg> Guardando...';
    });
    
    return true;
}

/**
 * Limpiar formulario completo
 */
function resetForm() {
    if (confirm('¿Está seguro de que desea limpiar todo el formulario?')) {
        document.getElementById('notaForm').reset();
        products = [];
        productCounter = 0;
        updateProductsDisplay();
        updateTotals();
        showStep(1);
        document.getElementById('invoice_fields').style.display = 'none';
        showAlert('Formulario limpiado', 'info');
    }
}

/**
 * Utilidades
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount || 0);
}

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

function showAlert(message, type = 'info') {
    // Crear elemento de alerta
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '10000';
    alert.style.maxWidth = '400px';
    alert.innerHTML = `
        <span>${message}</span>
        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;margin-left:12px;cursor:pointer;">&times;</button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

// Exportar funciones globales
window.nextStep = nextStep;
window.prevStep = prevStep;
window.showStep = showStep;
window.addProduct = addProduct;
window.removeProduct = removeProduct;
window.updateTotals = updateTotals;
window.toggleInvoiceFields = toggleInvoiceFields;
window.resetForm = resetForm;