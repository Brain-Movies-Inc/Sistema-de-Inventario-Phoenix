(function () {
    'use strict';

    var sidebar = document.querySelector('[data-sidebar]');
    var toggle = document.querySelector('[data-menu-toggle]');

    if (sidebar && toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('abierto');
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            var mensaje = element.getAttribute('data-confirm') || '¿Continuar?';
            if (!window.confirm(mensaje)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-alert-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var alerta = button.closest('.alert');
            if (alerta) {
                alerta.remove();
            }
        });
    });

    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    window.setTimeout(function () {
        // Solo los avisos flash desaparecen; los avisos permanentes (por
        // ejemplo, la tasa BCV desactualizada) deben seguir visibles.
        document.querySelectorAll('.alerts .alert').forEach(function (alerta) {
            alerta.classList.add('oculto');
        });
    }, 6000);
})();
