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
})();