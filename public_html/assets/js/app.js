// BORMEX - Aplicación Principal
console.log('BORMEX App inicializado');

// Funciones globales básicas
window.BORMEX = {
    version: '1.0.0',
    init: function() {
        console.log('Inicializando BORMEX Sistema...');
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.BORMEX.init();
});