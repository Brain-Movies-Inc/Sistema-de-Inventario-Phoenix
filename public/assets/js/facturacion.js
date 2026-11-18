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
    var preciosInput = root.querySelector('[data-precios]');
    var contador = root.querySelector('[data-contador]');
    var emitir = root.querySelector('[data-emitir]');
    var igtfCheck = root.querySelector('[data-igtf]');
    var igtfWrap = root.querySelector('[data-igtf-wrap]');
    var metodo = root.querySelector('[data-metodo]');
    var pagoMixto = root.querySelector('[data-pago-mixto]');
    var pagoDivisa = root.querySelector('[data-pago-divisa]');
    var pagoBolivares = root.querySelector('[data-pago-bolivares]');
    var pagoMixtoInfo = root.querySelector('[data-pago-mixto-info]');
    var buscar = root.querySelector('[data-pos-buscar]');

    var URL_PRODUCTOS = root.getAttribute('data-url-productos') || '';
    var URL_CLIENTES = root.getAttribute('data-url-clientes') || '';
    var productosCuerpo = root.querySelector('[data-productos-cuerpo]');
    var paginaAnterior = root.querySelector('[data-pagina-anterior]');
    var paginaSiguiente = root.querySelector('[data-pagina-siguiente]');
    var paginaInfo = root.querySelector('[data-pagina-info]');
    var clienteBuscar = root.querySelector('[data-cliente-buscar]');
    var clienteSelect = root.querySelector('[data-cliente-select]');

    var productosPagina = 1;
    var productosPaginas = parseInt(root.getAttribute('data-productos-paginas') || '1', 10) || 1;
    var productosTotal = parseInt(root.getAttribute('data-productos-total') || '0', 10) || 0;
    var productosPorPagina = parseInt(root.getAttribute('data-productos-por-pagina') || '60', 10) || 60;
    var busquedaProductos = '';
    var temporizadorProductos = null;
    var temporizadorClientes = null;
    var peticionProductos = null;
    var peticionClientes = null;

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

    // Valor para un <input type="number">: punto decimal y dos decimales.
    function valorInput(valor) {
        var numero = Math.max(0, Number(valor) || 0);
        return numero.toFixed(2);
    }

    // Convierte un texto formateado en es-VE ("1.234,56") a número.
    function numeroTexto(texto) {
        var limpio = String(texto || '').replace(/[^0-9.,-]/g, '');

        // En es-VE el punto separa miles y la coma los decimales.
        limpio = limpio.replace(/\./g, '').replace(',', '.');

        var numero = parseFloat(limpio);

        return isFinite(numero) ? numero : 0;
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
        var bienes = round3(subtotalGeneral + ivaTotal);
        var esMixto = !!metodo && metodo.value === 'MIXTO';
        var igtf = 0;
        var totalVes = 0;
        var divisaVes = 0;
        var bolivaresVes = 0;

        if (esMixto) {
            var divisaUsd = pagoDivisa
                ? Math.max(0, parseFloat(pagoDivisa.value || '0') || 0)
                : 0;

            divisaVes = round3(divisaUsd * TASA);

            // La porción en divisa no puede superar el valor de los bienes.
            if (divisaVes > bienes + 0.01 && TASA > 0) {
                divisaVes = bienes;

                if (pagoDivisa) {
                    pagoDivisa.value = valorInput(bienes / TASA);
                }
            }

            igtf = round3(divisaVes * 0.03);
            totalVes = round3(bienes + igtf);
            bolivaresVes = round3(totalVes - divisaVes);

            if (pagoBolivares) {
                pagoBolivares.value = valorInput(bolivaresVes);
            }
        } else {
            igtf = igtfCheck && igtfCheck.checked
                ? round3(bienes * 0.03)
                : 0;
            totalVes = round3(bienes + igtf);
        }

        var totalUsd = TASA > 0 ? round3(totalVes / TASA) : 0;

        setTexto('[data-resumen-exento]', 'Bs ' + formato(exento));
        setTexto('[data-resumen-base]', 'Bs ' + formato(base));
        setTexto('[data-resumen-iva]', 'Bs ' + formato(ivaTotal));
        setTexto('[data-resumen-igtf]', 'Bs ' + formato(igtf));
        setTexto('[data-resumen-total-ves]', 'Bs ' + formato(totalVes));
        setTexto('[data-resumen-total-usd]', '$ ' + formato(totalUsd));

        if (pagoMixto) {
            pagoMixto.hidden = !esMixto;
        }

        if (igtfWrap) {
            igtfWrap.hidden = esMixto;
        }

        if (pagoMixtoInfo) {
            pagoMixtoInfo.textContent = esMixto
                ? 'Divisa: Bs ' + formato(divisaVes) +
                  ' · IGTF: Bs ' + formato(igtf) +
                  ' · Resto en Bs: ' + formato(bolivaresVes)
                : '';
        }

        var igtfRow = root.querySelector('[data-resumen-igtf-row]');
        if (igtfRow) {
            igtfRow.hidden = esMixto ? igtf <= 0 : !(igtfCheck && igtfCheck.checked);
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

        // Precios con los que se calcularon los totales en pantalla. El
        // servidor los compara con los vigentes antes de emitir (F-09).
        if (preciosInput) {
            var precios = {};
            carrito.forEach(function (item) {
                precios[item.id] = item.precio;
            });
            preciosInput.value = JSON.stringify(precios);
        }
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
            if (!item || typeof item !== 'object' || Array.isArray(item)) {
                return;
            }

            var idRaw = item.id_producto;
            var cantidadRaw = item.cantidad;

            if (typeof idRaw !== 'number' && typeof idRaw !== 'string') {
                return;
            }

            if (typeof cantidadRaw !== 'number' && typeof cantidadRaw !== 'string') {
                return;
            }

            var idNumero = Number(idRaw);
            var cantidadNumero = Number(cantidadRaw);

            if (!Number.isFinite(idNumero) || idNumero <= 0
                || Math.floor(idNumero) !== idNumero) {
                return;
            }

            if (!Number.isFinite(cantidadNumero) || cantidadNumero <= 0) {
                return;
            }

            var id = String(idNumero);
            var fila = filaProducto(id);
            if (!fila) {
                return;
            }

            var stock = parseFloat(fila.getAttribute('data-stock') || '0');
            var cantidad = Math.min(cantidadNumero, stock);
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

    function crearFilaProducto(producto) {
        var stock = Number(producto.stock_actual) || 0;
        var minimo = Number(producto.stock_minimo) || 0;
        var claseStock = stock <= 0 ? 'badge-danger' : (stock <= minimo ? 'badge-warn' : 'badge-ok');

        var tr = document.createElement('tr');
        tr.setAttribute('data-producto', '');
        tr.setAttribute('data-id', String(producto.id_producto));
        tr.setAttribute('data-nombre', String(producto.nombre_producto));
        tr.setAttribute('data-precio', String(producto.precio_venta_usd));
        tr.setAttribute('data-iva', String(producto.alicuota_iva));
        tr.setAttribute('data-unidad', String(producto.unidad_medida));
        tr.setAttribute('data-stock', String(stock));

        var tdNombre = document.createElement('td');
        var nombre = document.createElement('strong');
        nombre.textContent = String(producto.nombre_producto);
        var detalle = document.createElement('small');
        detalle.className = 'muted';
        detalle.textContent = String(producto.codigo_qr_barras) + ' · ' + String(producto.unidad_medida);
        tdNombre.appendChild(nombre);
        tdNombre.appendChild(detalle);

        var tdPrecio = document.createElement('td');
        tdPrecio.className = 'num';
        tdPrecio.appendChild(document.createTextNode('$ ' + formato(producto.precio_venta_usd)));
        var precioBs = document.createElement('small');
        precioBs.className = 'muted';
        precioBs.textContent = 'Bs ' + formato(round3(Number(producto.precio_venta_usd) * TASA));
        tdPrecio.appendChild(precioBs);

        var tdStock = document.createElement('td');
        tdStock.className = 'num';
        var badge = document.createElement('span');
        badge.className = 'badge ' + claseStock;
        badge.textContent = formatoCantidad(stock);
        tdStock.appendChild(badge);

        var tdAccion = document.createElement('td');
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-primary';
        boton.setAttribute('data-agregar', '');
        boton.textContent = 'Agregar';
        boton.disabled = stock <= 0;
        tdAccion.appendChild(boton);

        tr.appendChild(tdNombre);
        tr.appendChild(tdPrecio);
        tr.appendChild(tdStock);
        tr.appendChild(tdAccion);

        return tr;
    }

    function renderProductos(productos) {
        if (!productosCuerpo) {
            return;
        }

        productosCuerpo.innerHTML = '';

        if (!productos.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'muted';
            td.textContent = 'No se encontraron productos.';
            tr.appendChild(td);
            productosCuerpo.appendChild(tr);

            return;
        }

        productos.forEach(function (producto) {
            productosCuerpo.appendChild(crearFilaProducto(producto));
        });
    }

    function actualizarPaginacion(datos) {
        productosPagina = Math.max(1, parseInt(datos.pagina, 10) || 1);
        productosPaginas = Math.max(1, parseInt(datos.paginas, 10) || 1);
        productosTotal = Math.max(0, parseInt(datos.total, 10) || 0);

        if (paginaAnterior) {
            paginaAnterior.disabled = productosPagina <= 1;
        }

        if (paginaSiguiente) {
            paginaSiguiente.disabled = productosPagina >= productosPaginas;
        }

        if (!paginaInfo) {
            return;
        }

        if (productosTotal === 0) {
            paginaInfo.textContent = 'No se encontraron productos.';
            return;
        }

        var desde = (productosPagina - 1) * productosPorPagina + 1;
        var hasta = Math.min(productosTotal, productosPagina * productosPorPagina);
        paginaInfo.textContent = 'Mostrando ' + desde + '-' + hasta + ' de ' + productosTotal;
    }

    function cargarProductos(pagina) {
        if (!URL_PRODUCTOS || !productosCuerpo) {
            return;
        }

        if (peticionProductos && typeof peticionProductos.abort === 'function') {
            peticionProductos.abort();
        }

        var controlador = typeof window.AbortController === 'function'
            ? new window.AbortController()
            : null;
        peticionProductos = controlador;

        var url = URL_PRODUCTOS
            + '?q=' + encodeURIComponent(busquedaProductos)
            + '&pagina=' + encodeURIComponent(String(pagina));
        var opciones = {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        };

        if (controlador) {
            opciones.signal = controlador.signal;
        }

        window.fetch(url, opciones)
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('HTTP ' + respuesta.status);
                }

                return respuesta.json();
            })
            .then(function (datos) {
                if (!datos || !Array.isArray(datos.productos)) {
                    throw new Error('Respuesta inválida');
                }

                renderProductos(datos.productos);
                actualizarPaginacion(datos);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                if (paginaInfo) {
                    paginaInfo.textContent = 'No se pudo cargar el catálogo. Intenta de nuevo.';
                }
            })
            .finally(function () {
                if (peticionProductos === controlador) {
                    peticionProductos = null;
                }
            });
    }

    function seleccionClienteActual() {
        if (!clienteSelect) {
            return null;
        }

        var opcion = clienteSelect.options[clienteSelect.selectedIndex];

        if (!opcion || opcion.value === '') {
            return null;
        }

        return { id: opcion.value, texto: opcion.textContent };
    }

    function renderClientes(clientes) {
        if (!clienteSelect) {
            return;
        }

        var conservar = seleccionClienteActual();
        var incluido = false;

        clienteSelect.innerHTML = '';

        var vacio = document.createElement('option');
        vacio.value = '';
        vacio.textContent = '-- Selecciona --';
        clienteSelect.appendChild(vacio);

        clientes.forEach(function (cliente) {
            var opcion = document.createElement('option');
            opcion.value = String(cliente.id_cliente);
            opcion.textContent = String(cliente.rif_cedula) + ' · ' + String(cliente.nombre_razon_social);

            if (conservar && conservar.id === opcion.value) {
                opcion.selected = true;
                incluido = true;
            }

            clienteSelect.appendChild(opcion);
        });

        // Si el cliente elegido no está en los resultados de la búsqueda, se
        // conserva la selección para no perderla al filtrar.
        if (conservar && !incluido) {
            var opcionActual = document.createElement('option');
            opcionActual.value = conservar.id;
            opcionActual.textContent = conservar.texto;
            opcionActual.selected = true;
            clienteSelect.insertBefore(opcionActual, clienteSelect.options[1] || null);
        }
    }

    function cargarClientes() {
        if (!URL_CLIENTES || !clienteSelect) {
            return;
        }

        if (peticionClientes && typeof peticionClientes.abort === 'function') {
            peticionClientes.abort();
        }

        var controlador = typeof window.AbortController === 'function'
            ? new window.AbortController()
            : null;
        peticionClientes = controlador;

        var q = clienteBuscar ? clienteBuscar.value.trim() : '';
        var url = URL_CLIENTES + '?q=' + encodeURIComponent(q);
        var opciones = {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        };

        if (controlador) {
            opciones.signal = controlador.signal;
        }

        window.fetch(url, opciones)
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('HTTP ' + respuesta.status);
                }

                return respuesta.json();
            })
            .then(function (datos) {
                if (!datos || !Array.isArray(datos.clientes)) {
                    throw new Error('Respuesta inválida');
                }

                renderClientes(datos.clientes);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            })
            .finally(function () {
                if (peticionClientes === controlador) {
                    peticionClientes = null;
                }
            });
    }

    // Delegación: sirve tanto para las filas iniciales como para las que se
    // pintan al buscar o paginar.
    if (productosCuerpo) {
        productosCuerpo.addEventListener('click', function (event) {
            var objetivo = event.target;
            var boton = objetivo && typeof objetivo.closest === 'function'
                ? objetivo.closest('[data-agregar]')
                : null;

            if (!boton || !productosCuerpo.contains(boton)) {
                return;
            }

            agregarDesdeBoton(boton);
        });
    }

    if (buscar) {
        buscar.addEventListener('input', function () {
            busquedaProductos = buscar.value.trim();
            window.clearTimeout(temporizadorProductos);
            temporizadorProductos = window.setTimeout(function () {
                cargarProductos(1);
            }, 300);
        });
    }

    if (paginaAnterior) {
        paginaAnterior.addEventListener('click', function () {
            if (productosPagina > 1) {
                cargarProductos(productosPagina - 1);
            }
        });
    }

    if (paginaSiguiente) {
        paginaSiguiente.addEventListener('click', function () {
            if (productosPagina < productosPaginas) {
                cargarProductos(productosPagina + 1);
            }
        });
    }

    if (clienteBuscar) {
        clienteBuscar.addEventListener('input', function () {
            window.clearTimeout(temporizadorClientes);
            temporizadorClientes = window.setTimeout(cargarClientes, 300);
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

    if (pagoDivisa) {
        pagoDivisa.addEventListener('input', render);
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

            // El servidor vuelve a validar la suma; aquí se avisa antes de
            // enviar para no perder el carrito por un error evitable (F-21).
            if (metodo && metodo.value === 'MIXTO') {
                var totalVes = numeroTexto(
                    (root.querySelector('[data-resumen-total-ves]') || {}).textContent
                );
                var divisa = pagoDivisa
                    ? Math.max(0, parseFloat(pagoDivisa.value || '0') || 0)
                    : 0;
                var bolivares = pagoBolivares
                    ? Math.max(0, parseFloat(pagoBolivares.value || '0') || 0)
                    : 0;
                var pagado = round3(divisa * TASA + bolivares);

                if (Math.abs(pagado - totalVes) > 0.02) {
                    event.preventDefault();
                    window.alert(
                        'Los pagos no coinciden con el total.\n' +
                        'Divisa: Bs ' + formato(divisa * TASA) + '\n' +
                        'Bolívares: Bs ' + formato(bolivares) + '\n' +
                        'Total: Bs ' + formato(totalVes)
                    );
                    return;
                }
            }

            emitir.disabled = true;
            emitir.textContent = 'Emitiendo...';
        });
    }

    cargarCarritoInicial();
    render();
})();
