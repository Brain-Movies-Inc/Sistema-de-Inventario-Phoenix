(function () {
    'use strict';

    var root = document.querySelector('[data-pos]');
    if (!root) {
        return;
    }

    var TASA = parseFloat(root.getAttribute('data-tasa') || '0');
    var carrito = new Map();

    var form = root.querySelector('[data-form-venta]');
    var tbody = root.querySelector('[data-carrito]');
    var wrap = root.querySelector('[data-carrito-wrap]');
    var vacio = root.querySelector('[data-vacio]');
    var itemsInput = root.querySelector('[data-items]');
    var contador = root.querySelector('[data-contador]');
    var emitir = root.querySelector('[data-emitir]');
    var igtfCheck = root.querySelector('[data-igtf]');
    var metodo = root.querySelector('[data-metodo]');
    var buscar = root.querySelector('[data-pos-buscar]');

    function round3(valor) {
        return Math.round((Number(valor) + Number.EPSILON) * 1000) / 1000;
    }

    function formato(valor, decimales) {
        var d = typeof decimales === 'number' ? decimales : 2;
        return Number(valor).toLocaleString('es-VE', {
            minimumFractionDigits: d,
            maximumFractionDigits: d
        });
    }

    // Cantidades: sin ceros decimales innecesarios (44 en vez de 44,000).
    function formatoCantidad(valor) {
        return Number(valor).toLocaleString('es-VE', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        });
    }

    function filaProducto(id) {
        return root.querySelector('[data-producto][data-id="' + id + '"]');
    }

    function agregarDesdeBoton(boton) {
        var fila = boton.closest('[data-producto]');
        if (!fila) {
            return;
        }

        var id = fila.getAttribute('data-id');
        var stock = parseFloat(fila.getAttribute('data-stock') || '0');
        var existente = carrito.get(id);

        if (!(stock > 0)) {
            window.alert('No hay stock disponible de ' + (fila.getAttribute('data-nombre') || 'este producto') + '.');
            return;
        }

        if (existente) {
            if (existente.cantidad + 1 > stock) {
                window.alert('No hay stock suficiente de ' + existente.nombre + '.');
                return;
            }
            existente.cantidad = round3(existente.cantidad + 1);
        } else {
            carrito.set(id, {
                id: id,
                nombre: fila.getAttribute('data-nombre') || '',
                precio: parseFloat(fila.getAttribute('data-precio') || '0'),
                iva: parseFloat(fila.getAttribute('data-iva') || '0'),
                unidad: fila.getAttribute('data-unidad') || 'UND',
                stock: stock,
                cantidad: 1
            });
        }

        render();
    }

    function render() {
        tbody.innerHTML = '';

        var exento = 0;
        var base = 0;
        var ivaTotal = 0;

        carrito.forEach(function (item) {
            var precioVes = round3(item.precio * TASA);
            var subtotal = round3(item.cantidad * precioVes);
            var iva = item.iva > 0 ? round3(subtotal * item.iva / 100) : 0;

            if (item.iva > 0) {
                base += subtotal;
                ivaTotal += iva;
            } else {
                exento += subtotal;
            }

            var tr = document.createElement('tr');

            var tdProducto = document.createElement('td');
            var nombre = document.createElement('strong');
            nombre.textContent = item.nombre;
            var detalle = document.createElement('small');
            detalle.className = 'muted';
            detalle.textContent = '$ ' + formato(item.precio) + ' · ' + item.unidad;
            tdProducto.appendChild(nombre);
            tdProducto.appendChild(detalle);

            var tdCantidad = document.createElement('td');
            tdCantidad.className = 'num';
            var inputCantidad = document.createElement('input');
            inputCantidad.type = 'number';
            inputCantidad.min = '0.001';
            inputCantidad.step = '0.001';
            inputCantidad.value = item.cantidad;
            inputCantidad.setAttribute('aria-label', 'Cantidad de ' + item.nombre);
            inputCantidad.addEventListener('change', function () {
                var valor = parseFloat(inputCantidad.value || '0');
                if (Number.isNaN(valor) || valor <= 0) {
                    inputCantidad.value = item.cantidad;
                    return;
                }
                if (valor > item.stock) {
                    window.alert('Solo hay ' + formatoCantidad(item.stock) + ' ' + item.unidad + ' disponibles.');
                    inputCantidad.value = item.stock;
                    item.cantidad = item.stock;
                } else {
                    item.cantidad = round3(valor);
                }
                render();
            });
            tdCantidad.appendChild(inputCantidad);

            var tdSubtotal = document.createElement('td');
            tdSubtotal.className = 'num';
            tdSubtotal.textContent = 'Bs ' + formato(subtotal);

            var tdQuitar = document.createElement('td');
            tdQuitar.className = 'num';
            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'btn btn-sm btn-danger';
            quitar.textContent = '×';
            quitar.setAttribute('aria-label', 'Quitar ' + item.nombre);
            quitar.addEventListener('click', function () {
                carrito.delete(item.id);
                render();
            });
            tdQuitar.appendChild(quitar);

            tr.appendChild(tdProducto);
            tr.appendChild(tdCantidad);
            tr.appendChild(tdSubtotal);
            tr.appendChild(tdQuitar);
            tbody.appendChild(tr);
        });

        var subtotalGeneral = round3(exento + base);
        var igtf = igtfCheck && igtfCheck.checked
            ? round3((subtotalGeneral + ivaTotal) * 0.03)
            : 0;
        var totalVes = round3(subtotalGeneral + ivaTotal + igtf);
        var totalUsd = TASA > 0 ? round3(totalVes / TASA) : 0;

        setTexto('[data-resumen-exento]', 'Bs ' + formato(exento));
        setTexto('[data-resumen-base]', 'Bs ' + formato(base));
        setTexto('[data-resumen-iva]', 'Bs ' + formato(ivaTotal));
        setTexto('[data-resumen-igtf]', 'Bs ' + formato(igtf));
        setTexto('[data-resumen-total-ves]', 'Bs ' + formato(totalVes));
        setTexto('[data-resumen-total-usd]', '$ ' + formato(totalUsd));

        var igtfRow = root.querySelector('[data-resumen-igtf-row]');
        if (igtfRow) {
            igtfRow.hidden = !(igtfCheck && igtfCheck.checked);
        }

        var hay = carrito.size > 0;
        wrap.hidden = !hay;
        vacio.hidden = hay;
        contador.textContent = carrito.size + (carrito.size === 1 ? ' ítem' : ' ítems');
        emitir.disabled = !hay || TASA <= 0;

        itemsInput.value = JSON.stringify(Array.from(carrito.values()).map(function (item) {
            return {
                id_producto: Number(item.id),
                cantidad: item.cantidad
            };
        }));
    }

    function setTexto(selector, texto) {
        var nodo = root.querySelector(selector);
        if (nodo) {
            nodo.textContent = texto;
        }
    }

    function cargarCarritoInicial() {
        var inicial = root.getAttribute('data-carrito-inicial');
        if (!inicial || inicial === '[]') {
            return;
        }

        var items;
        try {
            items = JSON.parse(inicial);
        } catch (error) {
            return;
        }

        if (!Array.isArray(items)) {
            return;
        }

        items.forEach(function (item) {
            var id = String(item.id_producto);
            var fila = filaProducto(id);
            if (!fila) {
                return;
            }

            var stock = parseFloat(fila.getAttribute('data-stock') || '0');
            var cantidad = Math.min(parseFloat(item.cantidad || '0'), stock);
            if (cantidad <= 0) {
                return;
            }

            carrito.set(id, {
                id: id,
                nombre: fila.getAttribute('data-nombre') || '',
                precio: parseFloat(fila.getAttribute('data-precio') || '0'),
                iva: parseFloat(fila.getAttribute('data-iva') || '0'),
                unidad: fila.getAttribute('data-unidad') || 'UND',
                stock: stock,
                cantidad: round3(cantidad)
            });
        });
    }

    root.querySelectorAll('[data-agregar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            agregarDesdeBoton(boton);
        });
    });

    if (buscar) {
        buscar.addEventListener('input', function () {
            var q = buscar.value.trim().toLowerCase();
            root.querySelectorAll('[data-producto]').forEach(function (fila) {
                var texto = (
                    (fila.getAttribute('data-nombre') || '') + ' ' +
                    (fila.getAttribute('data-unidad') || '') + ' ' +
                    (fila.querySelector('small') ? fila.querySelector('small').textContent : '')
                ).toLowerCase();
                fila.hidden = q !== '' && texto.indexOf(q) === -1;
            });
        });
    }

    if (igtfCheck) {
        igtfCheck.addEventListener('change', render);
    }

    if (metodo) {
        metodo.addEventListener('change', function () {
            if (metodo.value === 'ZELLE' && igtfCheck) {
                igtfCheck.checked = true;
            }
            render();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (carrito.size === 0) {
                event.preventDefault();
                window.alert('Agrega al menos un producto al carrito.');
                return;
            }

            if (TASA <= 0) {
                event.preventDefault();
                window.alert('No hay una tasa BCV vigente para calcular la factura.');
                return;
            }

            emitir.disabled = true;
            emitir.textContent = 'Emitiendo...';
        });
    }

    cargarCarritoInicial();
    render();
})();
