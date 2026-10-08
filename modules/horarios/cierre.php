<?php
/**
 * modules/horarios/cierre.php
 * -----------------------------------------------------------------
 * Marcar y sacar fechas cerradas (feriados, vacaciones).
 *
 * Una sola pantalla para las dos cosas: agregar y borrar. Son la
 * misma lista y separarlas sería hacer que el dueño entre a dos
 * lugares para trabajar lo mismo.
 * -----------------------------------------------------------------
 */

$accion = post('accion', 'marcar');

// --- Sacar ---
if ($accion === 'sacar') {
    // El id viene en un campo oculto del formulario, o sea por POST.
    // Con get_id() (que mira el GET) daba siempre 0 y el día quedaba
    // marcado sin poder sacarlo.
    $id = (int) post('id', '0');

    if ($id <= 0) {
        flash_error('No se sabe qué fecha hay que sacar.');

        redirigir('horarios');
    }

    Horarios::sacarCierre($id);

    flash_exito('Fecha sacada. El bar vuelve a abrir ese día.');

    redirigir('horarios');
}

// --- Marcar ---
$fecha  = trim(post('fecha', ''));
$motivo = trim(post('motivo', ''));

$resultado = Horarios::marcarCierre($fecha, $motivo);

if (!$resultado['ok']) {
    guardar_entradas($_POST);
    flash_error($resultado['mensaje']);

    redirigir('horarios');
}

flash_exito($motivo === ''
    ? 'Día marcado como cerrado.'
    : 'Día marcado como cerrado (' . $motivo . ').');

redirigir('horarios');