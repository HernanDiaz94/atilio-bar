/* ================================================================
   assets/js/app.js
   -----------------------------------------------------------------
   JavaScript vanilla de la aplicación.

   Solo mejoras de experiencia de uso. Nada de esto es obligatorio
   para que la app funcione: si el navegador no soporta algo, la
   validación del servidor igual está en PHP.
   ================================================================ */

(function () {
    'use strict';

    /**
     * Muestra un mensaje flotante sin recargar la página.
     * Se usa en lugar de window.alert, que es feo y bloquea.
     */
    function avisar(mensaje, tipo) {
        var contenedor = document.getElementById('contenedorAvisos');

        if (!contenedor) {
            // Si no está el contenedor (login, instalador), usamos el alert.
            window.alert(mensaje);
            return;
        }

        var aviso = document.createElement('div');
        aviso.className = 'alert alert-' + (tipo || 'info') + ' alert-dismissible fade show';
        aviso.setAttribute('role', 'alert');
        aviso.textContent = mensaje;

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-close';
        boton.setAttribute('aria-label', 'Cerrar');
        boton.addEventListener('click', function () {
            eliminar(aviso);
        });

        aviso.appendChild(boton);
        contenedor.appendChild(aviso);

        // Los avisos se van solos a los 6 segundos.
        window.setTimeout(function () {
            eliminar(aviso);
        }, 6000);
    }

    function eliminar(elemento) {
        if (!elemento || !elemento.parentNode) {
            return;
        }

        elemento.style.opacity = '0';

        window.setTimeout(function () {
            if (elemento.parentNode) {
                elemento.parentNode.removeChild(elemento);
            }
        }, 250);
    }

    // --------------------------------------------------------
    // Avisos de Bootstrap que se cierran solos
    // --------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var alertas = document.querySelectorAll('.avisos .alert');

        Array.prototype.forEach.call(alertas, function (alerta) {
            window.setTimeout(function () {
                if (window.bootstrap && window.bootstrap.Alert) {
                    window.bootstrap.Alert.getOrCreateInstance(alerta).close();
                }
            }, 7000);
        });
    });

    // --------------------------------------------------------
    // Confirmación antes de acciones destructivas
    // --------------------------------------------------------
    // Uso: <button data-confirmar="¿Seguro que querés anular?">
    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;

        if (!formulario.hasAttribute('data-confirmar')) {
            return;
        }

        var mensaje = formulario.getAttribute('data-confirmar');

        if (!window.confirm(mensaje)) {
            evento.preventDefault();
        }
    });

    // --------------------------------------------------------
    // Evitar doble envío
    // --------------------------------------------------------
    // En un celular, tocar dos veces un botón "Guardar" no puede
    // crear dos registros.
    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;

        if (formulario.hasAttribute('data-sin-doble-envio')) {
            return;
        }

        // Los formularios con acción asíncrona no se bloquean.
        if (formulario.hasAttribute('data-ajax')) {
            return;
        }

        window.setTimeout(function () {
            var botones = formulario.querySelectorAll('button[type="submit"]');

            Array.prototype.forEach.call(botones, function (boton) {
                boton.disabled = true;
                boton.textContent = 'Guardando...';
            });

            // Si algo sale mal y el navegador vuelve atrás con caché,
            // el botón vuelve a estar disponible.
            window.setTimeout(function () {
                Array.prototype.forEach.call(botones, function (boton) {
                    boton.disabled = false;
                });
            }, 8000);
        }, 0);
    });

    // --------------------------------------------------------
    // Validación de formularios en el cliente
    // Complementa la validación del servidor: acá avisamos antes
    // de mandar, sin reemplazar nunca la validación de PHP.
    // --------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var formularios = document.querySelectorAll('form[data-validar]');

        Array.prototype.forEach.call(formularios, function (formulario) {
            formulario.addEventListener('submit', function (evento) {
                var errores = [];

                // Campos requeridos
                var requeridos = formulario.querySelectorAll('[required]');

                Array.prototype.forEach.call(requeridos, function (campo) {
                    if (campo.type === 'hidden') {
                        return;
                    }

                    var valor = (campo.value || '').trim();

                    if (valor === '') {
                        errores.push('El campo "' + (campo.dataset.etiqueta || campo.name) + '" es obligatorio.');
                        marcarError(campo, true);
                    } else {
                        marcarError(campo, false);
                    }
                });

                // Contraseñas: mínimo 8 caracteres y coincidencia
                var password = formulario.querySelector('input[name="password"]');
                var confirmacion = formulario.querySelector('input[name="password_confirmacion"]');

                if (password && password.value.length > 0 && password.value.length < 8) {
                    errores.push('La contraseña tiene que tener al menos 8 caracteres.');
                    marcarError(password, true);
                } else if (password) {
                    marcarError(password, false);
                }

                if (password && confirmacion && confirmacion.value.length > 0) {
                    if (password.value !== confirmacion.value) {
                        errores.push('Las contraseñas no coinciden.');
                        marcarError(confirmacion, true);
                    } else {
                        marcarError(confirmacion, false);
                    }
                }

                // Emails
                var emails = formulario.querySelectorAll('input[type="email"]');

                Array.prototype.forEach.call(emails, function (campo) {
                    if (campo.value === '') {
                        return;
                    }

                    // Valida con una expresión simple, sin regex compleja.
                    var valido = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(campo.value);

                    marcarError(campo, !valido);

                    if (!valido) {
                        errores.push('El email "' + campo.value + '" no tiene un formato válido.');
                    }
                });

                if (errores.length > 0) {
                    evento.preventDefault();
                    avisar(errores[0], 'danger');

                    // Lleva el foco al primer campo con problema.
                    var primero = formulario.querySelector('.campo-error input, .campo-error select');
                    if (primero) {
                        primero.focus();
                    }
                }
            });
        });
    });

    /**
     * Pide confirmación al perder el foco de un campo.
     * Solo en campos marcados con data-confirmar-salida (por ejemplo
     * el motivo de un ajuste de stock).
     */
    function marcarError(campo, esError) {
        var contenedor = campo.closest('.campo') || campo.parentElement;

        if (!contenedor) {
            return;
        }

        if (esError) {
            contenedor.classList.add('campo-error');
        } else {
            contenedor.classList.remove('campo-error');
        }
    }

    // --------------------------------------------------------
    // Mostrar / ocultar contraseña
    // --------------------------------------------------------
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-ver-password]');

        if (!boton) {
            return;
        }

        var campo = document.getElementById(boton.getAttribute('data-ver-password'));

        if (!campo) {
            return;
        }

        var mostrando = campo.type === 'text';
        campo.type = mostrando ? 'password' : 'text';
        boton.innerHTML = mostrando
            ? '<i class="bi bi-eye"></i> Ver'
            : '<i class="bi bi-eye-slash"></i> Ocultar';
    });

    // --------------------------------------------------------
    // Filtro rápido en tablas
    // Uso: <input data-filtrar="#tablaUsuarios">
    // Para las tablas largas (usuarios, productos) sirve un buscador
    // que filtra por lo que se ve en pantalla.
    // --------------------------------------------------------
    document.addEventListener('input', function (evento) {
        var campo = evento.target;

        if (!campo.hasAttribute('data-filtrar')) {
            return;
        }

        var tabla = document.querySelector(campo.getAttribute('data-filtrar'));

        if (!tabla) {
            return;
        }

        var texto = (campo.value || '').toLowerCase().trim();
        var filas = tabla.querySelectorAll('tbody tr');

        Array.prototype.forEach.call(filas, function (fila) {
            var contenido = (fila.textContent || '').toLowerCase();

            if (contenido.indexOf(texto) !== -1) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });
    });

    // --------------------------------------------------------
    // Aviso al intentar salir con cambios sin guardar
    // --------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var formulario = document.querySelector('form[data-aviso-salida]');

        if (!formulario) {
            return;
        }

        var dirty = false;

        formulario.addEventListener('change', function () {
            dirty = true;
        });

        formulario.addEventListener('submit', function () {
            dirty = false;
        });

        window.addEventListener('beforeunload', function (evento) {
            if (!dirty) {
                return undefined;
            }

            evento.preventDefault();
            evento.returnValue = '';

            return '';
        });
    });

    // ========================================================
    // ========================================================
    //  Mesa: cantidad +/- y buscador de productos (Etapa 3)
    // ========================================================

    /**
     * Los productos del pedido tienen botones + y -.
     *
     * Cada producto es un <form> propio, así que mandar el pedido
     * recarga la página. Es lo correcto: el servidor valida,
     * descuenta stock y deja todo asentado. Mandarlo por AJAX
     * haría que un error de red dejara el pedido desactualizado
     * sin que nadie se entere.
     */
    function conectarCantidadProductos() {
        function ajustar(campo, delta) {
            if (!campo) { return; }

            var valor = parseFloat(campo.value || '1');

            if (isNaN(valor)) { valor = 1; }

            // No se baja de 1: para sacar algo del pedido está el
            // botón de anular, que es otra cosa.
            var nuevo = valor + delta;

            if (nuevo < 1) { nuevo = 1; }

            campo.value = nuevo;
        }

        Array.prototype.forEach.call(
            document.querySelectorAll('.btn-suma'),
            function (boton) {
                boton.addEventListener('click', function (evento) {
                    evento.preventDefault();

                    var campo = boton.parentElement.querySelector('.producto-cantidad');

                    ajustar(campo, 1);

                    if (campo) { campo.focus(); }
                });
            }
        );

        Array.prototype.forEach.call(
            document.querySelectorAll('.btn-resta'),
            function (boton) {
                boton.addEventListener('click', function (evento) {
                    evento.preventDefault();

                    var campo = boton.parentElement.querySelector('.producto-cantidad');

                    ajustar(campo, -1);

                    if (campo) { campo.focus(); }
                });
            }
        );

        // La cantidad acepta enteros y medios (medio gin, por ejemplo).
        Array.prototype.forEach.call(
            document.querySelectorAll('.producto-cantidad'),
            function (campo) {
                campo.addEventListener('change', function () {
                    var valor = parseFloat(campo.value);

                    if (isNaN(valor) || valor <= 0) {
                        campo.value = '1';
                    } else {
                        campo.value = valor;
                    }
                });
            }
        );
    }

    /**
     * Buscador de productos del pedido.
     *
     * Filtra por nombre. Cuando no queda nada avisa: sin ese
     * mensaje, en un bar se termina creyendo que el producto
     * no existe.
     */
    function conectarBuscadorProductos() {
        var campo = document.getElementById('buscarProducto');

        if (!campo) { return; }

        var botones = document.querySelectorAll('.producto-boton');
        var titulos = document.querySelectorAll('.categoria-titulo');

        // Mensaje para cuando no hay resultados
        var aviso = document.createElement('div');
        aviso.id = 'avisoBusqueda';
        aviso.className = 'marco-amarillo p-3 mb-3 centrado';
        aviso.style.display = 'none';
        campo.parentElement.appendChild(aviso);

        campo.addEventListener('input', function () {
            var texto = (campo.value || '').trim().toLowerCase();
            var encontrados = 0;

            Array.prototype.forEach.call(botones, function (boton) {
                var nombre = (boton.getAttribute('data-nombre') || '').toLowerCase();

                if (texto === '' || nombre.indexOf(texto) !== -1) {
                    boton.style.display = '';
                    encontrados++;
                } else {
                    boton.style.display = 'none';
                }
            });

            // El título de cada categoría se oculta si debajo no
            // quedó ningún producto.
            Array.prototype.forEach.call(titulos, function (titulo) {
                var tieneAlguno = false;
                var nodo = titulo.nextElementSibling;

                // Se recorre desde el título hasta el próximo título.
                while (nodo && !nodo.classList.contains('categoria-titulo')) {
                    var visibles = nodo.querySelectorAll('.producto-boton');

                    Array.prototype.forEach.call(visibles, function (b) {
                        if (b.style.display !== 'none') { tieneAlguno = true; }
                    });

                    nodo = nodo.nextElementSibling;
                }

                titulo.style.display = tieneAlguno ? '' : 'none';
            });

            if (texto !== '' && encontrados === 0) {
                aviso.innerHTML = '<i class="bi bi-search"></i> Ningún producto del bar se llama "'
                    + escapar(campo.value) + '".';
                aviso.style.display = '';
            } else {
                aviso.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        conectarCantidadProductos();
        conectarBuscadorProductos();
    });

    // ========================================================
    //  Compra: armar la lista de productos (Etapa 2)
    // ========================================================
    // ========================================================

    /**
     * La compra se carga en una tabla que se arma en el navegador:
     * se elige un producto, la cantidad y el costo, y se agrega.
     *
     * El total se calcula en el cliente, pero eso es solo para
     * mostrarlo. El servidor vuelve a calcular todo: los valores que
     * manda el navegador nunca se usan para calcular el total real.
     */
    function conectarFormularioCompra() {
        var cuerpo = document.getElementById('cuerpoLineas');
        var pie = document.getElementById('pieLineas');
        var botonAgregar = document.getElementById('agregarLinea');
        var botonGuardar = document.getElementById('botonGuardar');
        var sinLineas = document.getElementById('sinLineas');
        var aviso = document.getElementById('avisoLinea');
        var selProducto = document.getElementById('nuevoProducto');
        var inpCantidad = document.getElementById('nuevaCantidad');
        var inpCosto = document.getElementById('nuevoCosto');

        if (!cuerpo || !botonAgregar) {
            return;
        }

        // Índice de líneas. Cada una guarda producto, cantidad y costo.
        var lineas = [];

        function datosProductos() {
            var datos = window.ATILIO_COMPRA || { productos: [], lineas: [] };
            return datos.productos || [];
        }

        function buscarProducto(id) {
            var todos = datosProductos();

            for (var i = 0; i < todos.length; i++) {
                if (todos[i].id === id) {
                    return todos[i];
                }
            }

            return null;
        }

        /** "1.234,56" -> 1234.56 */
        function aNumero(texto) {
            if (typeof texto !== 'string') { return 0; }

            texto = texto.trim().replace(/\./g, '').replace(',', '.');

            var n = parseFloat(texto);

            return isNaN(n) ? 0 : n;
        }

        /** 1234.56 -> "1.234,56" */
        function formatear(n) {
            return n.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function pluralizar(unidad, cantidad) {
            if (unidad === 'bidón' || unidad === 'bidones' ||
                unidad === 'caja' || unidad === 'cajas' ||
                unidad === 'docena' || unidad === 'docenas') {
                return unidad;
            }

            if (cantidad === 1) { return unidad; }

            if (unidad.slice(-1) === 'a') {
                return unidad.slice(0, -1) + 'as';
            }

            return unidad + 's';
        }

        function pintar() {
            cuerpo.innerHTML = '';

            if (lineas.length === 0) {
                if (sinLineas) { sinLineas.classList.remove('d-none'); }
                if (pie) { pie.classList.add('d-none'); }
                if (botonGuardar) { botonGuardar.disabled = true; }
                return;
            }

            if (sinLineas) { sinLineas.classList.add('d-none'); }
            if (pie) { pie.classList.remove('d-none'); }
            if (botonGuardar) { botonGuardar.disabled = false; }

            var total = 0;

            lineas.forEach(function (linea, indice) {
                var subtotal = linea.cantidad * linea.costo;
                total += subtotal;

                var fila = document.createElement('tr');

                fila.innerHTML =
                    '<td>' + escapar(linea.nombre) +
                        '<br><small style="color:var(--texto-suave)">' +
                        escapar(linea.categoria) + '</small></td>' +
                    '<td class="text-end">' + escapar(String(linea.stock)) + '</td>' +
                    '<td class="text-end">' + formatear(linea.cantidad) +
                        ' <small>' + escapar(pluralizar(linea.unidad, linea.cantidad)) + '</small></td>' +
                    '<td class="text-end">$ ' + formatear(linea.costo) + '</td>' +
                    '<td class="text-end"><strong>$ ' + formatear(subtotal) + '</strong></td>' +
                    '<td class="text-end">' +
                    '<button type="button" class="btn btn-peligro btn-sm quitar-linea" data-indice="' + indice + '">' +
                    '<i class="bi bi-x-lg"></i></button></td>';

                // Los datos van en atributos hidden para que el
                // servidor reciba lo que el usuario Splash ve.
                fila.innerHTML +=
                    '<input type="hidden" name="lineas[' + indice + '][producto_id]" value="' + linea.id + '">' +
                    '<input type="hidden" name="lineas[' + indice + '][cantidad]" value="' + linea.cantidad + '">' +
                    '<input type="hidden" name="lineas[' + indice + '][costo]" value="' + linea.costo + '">';

                cuerpo.appendChild(fila);
            });

            var celdaTotal = document.getElementById('totalLineas');

            if (celdaTotal) {
                celdaTotal.textContent = '$ ' + formatear(total);
            }
        }

        // Agregar una línea
        botonAgregar.addEventListener('click', function () {
            if (aviso) { aviso.classList.add('d-none'); }

            var id = parseInt(selProducto.value, 10);

            if (!id) {
                avisar('Elegí un producto primero.', 'warning');
                return;
            }

            var producto = buscarProducto(id);

            if (!producto) { return; }

            var cantidad = aNumero(inpCantidad.value);
            var costo = aNumero(inpCosto.value);

            if (cantidad <= 0) {
                avisar('La cantidad tiene que ser mayor a cero.', 'warning');
                return;
            }

            if (costo < 0) {
                avisar('El costo no puede ser negativo.', 'warning');
                return;
            }

            // El mismo producto dos veces se acumula en una sola línea.
            var existente = null;

            for (var i = 0; i < lineas.length; i++) {
                if (lineas[i].id === id) {
                    existente = lineas[i];
                    break;
                }
            }

            if (existente) {
                existente.cantidad += cantidad;
                // El costo se promedia ponderado para no perder el dato.
                var totalCant = existente.cantidad;
                var costoViejo = existente.costo;
                existente.costo = aNumero(
                    ((costoViejo * (totalCant - cantidad)) + (costo * cantidad)) / totalCant
                );
            } else {
                lineas.push({
                    id: id,
                    nombre: producto.nombre,
                    categoria: producto.categoria,
                    unidad: producto.unidad,
                    stock: producto.stock,
                    cantidad: cantidad,
                    costo: costo
                });
            }

            pintar();

            // Se limpia para cargar el siguiente
            selProducto.value = '';
            inpCantidad.value = '1';
            inpCosto.value = '';
            selProducto.focus();
        });

        // Enter en cualquier campo agrega la línea
        [inpCantidad, inpCosto].forEach(function (campo) {
            if (!campo) { return; }

            campo.addEventListener('keydown', function (evento) {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                    botonAgregar.click();
                }
            });
        });

        // Quitar una línea
        cuerpo.addEventListener('click', function (evento) {
            var boton = evento.target.closest('.quitar-linea');

            if (!boton) { return; }

            var indice = parseInt(boton.getAttribute('data-indice'), 10);

            if (!isNaN(indice)) {
                lineas.splice(indice, 1);
                pintar();
            }
        });

        // Si ya venía con líneas de un intento anterior, se pintan
        var datos = window.ATILIO_COMPRA || {};

        if (Array.isArray(datos.lineas) && datos.lineas.length > 0) {
            datos.lineas.forEach(function (precargada) {
                var producto = buscarProducto(parseInt(precargada.producto_id, 10));

                if (!producto) { return; }

                lineas.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    categoria: producto.categoria,
                    unidad: producto.unidad,
                    stock: producto.stock,
                    cantidad: parseFloat(precargada.cantidad) || 0,
                    costo: parseFloat(precargada.costo) || 0
                });
            });

            pintar();
        } else {
            pintar();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        conectarFormularioCompra();
    });

    // ========================================================
    //  Formulario de producto (Etapa 2)
    // ========================================================

    /**
     * Muestra u oculta el bloque de stock según el tipo de producto
     * elegido. Un trago no tiene sentido con un campo de stock.
     */
    function conectarTipoProducto() {
        var bloqueStock = document.getElementById('bloqueStock');
        var radioSi = document.getElementById('cs_si');
        var radioNo = document.getElementById('cs_no');

        if (!bloqueStock || !radioSi || !radioNo) {
            return;
        }

        function aplicar() {
            var llevaStock = radioSi.checked;

            bloqueStock.classList.toggle('oculto', !llevaStock);

            // Si no lleva stock, los campos se vacían para que no
            // se manden valores que el servidor va a ignorar igual.
            var actual = bloqueStock.querySelector('#stock_actual');
            var minimo = bloqueStock.querySelector('#stock_minimo');

            if (!llevaStock) {
                if (actual) { actual.value = '0'; }
                if (minimo) { minimo.value = '0'; }
            }
        }

        radioSi.addEventListener('change', aplicar);
        radioNo.addEventListener('change', aplicar);
        aplicar();
    }

    /**
     * Receta del combo: agrega y quita filas de insumos.
     *
     * Los insumos llegan en window.ATILIO_FORM_PRODUCTO, que el
     * servidor arma con la lista completa. Así se agrega una fila
     * sin recargar la página.
     */
    function conectarRecetaCombo() {
        var checkbox = document.getElementById('es_combo');
        var bloque = document.getElementById('bloqueReceta');
        var lista = document.getElementById('listaComponentes');
        var botonAgregar = document.getElementById('agregarComponente');

        if (!checkbox || !bloque || !lista || !botonAgregar) {
            return;
        }

        var datos = window.ATILIO_FORM_PRODUCTO || { candidatos: [] };
        var candidatos = datos.candidatos || [];

        function aplicar() {
            bloque.classList.toggle('oculto', !checkbox.checked);
        }

        checkbox.addEventListener('change', aplicar);
        aplicar();

        // Quitar una fila
        lista.addEventListener('click', function (evento) {
            var boton = evento.target.closest('.quitar-componente');

            if (!boton) {
                return;
            }

            var fila = boton.closest('.fila-componente');

            if (fila) {
                fila.remove();
            }
        });

        // Agregar una fila
        botonAgregar.addEventListener('click', function () {
            if (candidatos.length === 0) {
                avisar('No hay insumos cargados para usar en un combo.', 'warning');
                return;
            }

            var fila = document.createElement('div');
            fila.className = 'input-group mb-2 fila-componente';

            var opciones = candidatos.map(function (c) {
                var elegido = (c.id === candidatos[0].id) ? ' selected' : '';
                return '<option value="' + c.id + '"' + elegido + '>'
                    + escapar(c.nombre) + '</option>';
            }).join('');

            fila.innerHTML =
                '<span class="input-group-text"><i class="bi bi-box"></i></span>' +
                '<select name="componentes[]" class="form-select componente-insumo" aria-label="Insumo">'
                + opciones + '</select>' +
                '<input type="text" name="componentes_cantidad[]" class="form-control componente-cantidad" ' +
                'value="1,00" inputmode="decimal" aria-label="Cantidad a descontar">' +
                '<button type="button" class="btn btn-peligro quitar-componente">' +
                '<i class="bi bi-x-lg"></i></button>';

            lista.appendChild(fila);
        });
    }

    /**
     * Escapa un texto para insertarlo con innerHTML.
     *
     * Los nombres de producto vienen del servidor, pero escapear
     * igual: si alguien metiera un <b> en el nombre, no queremos
     * que se ejecute.
     */
    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        conectarTipoProducto();
        conectarRecetaCombo();
    });
})();