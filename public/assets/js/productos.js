(function () {
    'use strict';

    var form = document.querySelector('[data-productos-form]');
    if (!form) {
        return;
    }

    var tasa = parseFloat(form.getAttribute('data-tasa') || '0');
    if (!isFinite(tasa) || tasa < 0) {
        tasa = 0;
    }

    var formato = new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function calcular(tipo) {
        var usd = form.querySelector('[data-usd="' + tipo + '"]');
        var bs = form.querySelector('[data-bs="' + tipo + '"]');

        if (!usd || !bs) {
            return;
        }

        var valor = parseFloat(usd.value);

        if (!isFinite(valor) || valor < 0) {
            valor = 0;
        }

        bs.value = tasa > 0
            ? 'Bs ' + formato.format(valor * tasa)
            : 'Sin tasa BCV';
    }

    form.querySelectorAll('[data-usd]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            calcular(campo.getAttribute('data-usd'));
        });
    });

    ['costo', 'venta'].forEach(calcular);
})();
