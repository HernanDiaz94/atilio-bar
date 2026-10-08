<?php
/**
 * modules/caja/clave.php
 * -----------------------------------------------------------------
 * La clave de caja: la tiene que escribir el vendedor para abrir o
 * cerrar el turno.
 *
 * Es la unica pantalla del sistema que escribe algo que no se puede
 * volver a leer: la clave se guarda con hash y no se muestra jamas.
 * Ni siquiera el administrador puede ver cual es. Si se le olvida, la
 * cambia.
 * -----------------------------------------------------------------
 *
 * Solo el administrador. Es su clave: si ladefiniera el vendedor,
 * seria el vendedor el que decide cuando se la puede pasar a otro.
 */

$accion = post('accion', '');

$errores = [];
$erroresGuardados = tomar_errores();
$errores = array_merge($errores, $erroresGuardados['campos']);

// ------------------------------------------------------------
// Quitar la clave
//
// Por que existe: el administrador la puede perder, y sin forma de
// recuperarla el bar queda sin poder abrir la caja. Es una puerta de
// salida, no algo para usar a diario.
// ------------------------------------------------------------
if ($accion === 'quitar') {
    Caja::clave_quitar();

    Auditoria::registrar('quitar_clave_caja', 'configuracion', null, null, [
        'clave' => Caja::CLAVE_CONFIG,
    ]);

    flash_exito('Se quitó la clave de caja. Ahora cualquiera del turno puede abrir y cerrar.');
    redirigir('caja/clave');
}

// ------------------------------------------------------------
// Guardar la clave
// ------------------------------------------------------------
if ($accion === 'guardar' && es_post()) {
    $clave       = post_crudo('clave');
    $confirmacion = post_crudo('clave_confirmacion');

    if (mb_strlen($clave) < 4) {
        $errores['clave'] = 'Poné una clave de al menos 4 caracteres.';
    }

    if ($clave !== $confirmacion) {
        $errores['clave_confirmacion'] = 'Las dos claves no coinciden.';
    }

    // Con errores NO se guarda, aunque las dos claves coincidan.
    // Antes la condición era solo "coinciden y no está vacía", así que
    // una clave de dos caracteres se guardaba: el error se acumulaba
    // pero no impedía el INSERT.
    if (empty($errores)) {
        Caja::clave_guardar($clave);

        Auditoria::registrar('definir_clave_caja', 'configuracion', null, null, [
            'clave' => Caja::CLAVE_CONFIG,
        ]);

        flash_exito('Clave de caja guardada. A partir de ahora el vendedor la necesita para abrir y cerrar.');
        redirigir('caja/clave');
    }

    guardar_errores($errores);
    flash_error('Revisá los campos marcados.');
    redirigir('caja/clave');
}

// ------------------------------------------------------------
// La pantalla
// ------------------------------------------------------------
$configurada = Caja::clave_configurada();

$titulo    = 'Clave de caja';
$subtitulo = $configurada
    ? 'Ya está puesta. El vendedor la necesita para abrir y cerrar el turno.'
    : 'Todavía no está puesta. Sin clave, el vendedor no puede abrir ni cerrar.';

$accionesCabecera = '<a href="' . e(url('caja')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a la caja</a>';

require APP_ROOT . '/modules/caja/vistas/clave.php';