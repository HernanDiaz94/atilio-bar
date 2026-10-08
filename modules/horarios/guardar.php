<?php
/**
 * modules/horarios/guardar.php
 * -----------------------------------------------------------------
 * Guarda el horario de la semana.
 *
 * LO QUE HACE ESTE CONTROLADOR Y POR QUÉ
 *
 * Recibe siete renglones de formulario y los valida uno por uno. Un
 * horario mal guardado no rompe nada, pero manda gente a un bar
 * cerrado, así que cada campo se chequea antes de guardar:
 *
 *   - Si un día viene sin hora de apertura, queda cerrado. Es la
 *     forma natural de decir "el lunes no abro".
 *   - Si viene apertura pero no cierre, se avisa y no se guarda ese
 *     día: un bar que abre y nunca cierra está siempre abierto.
 *   - "Cierra al día siguiente" se deduce SOLO cuando la hora de
 *     cierre es más temprano que la de apertura, que es el caso
 *     obvio (20:00 a 02:00). Si el dueño desmarca la casilla a
 *     propósito, se respeta: puede tener un horario rareza y él
 *     sabe.
 * -----------------------------------------------------------------
 */

// La validación vive en Horarios::validarSemana(): es una regla del
// negocio y se prueba sin servidor ni sesiones.
$resultado = Horarios::validarSemana($_POST);
$errores  = $resultado['errores'];
$guardado = $resultado['guardado'];

if ($errores !== []) {
    // Se devuelven también lo que escribió, para que no tenga que
    // volver a cargarlo.
    guardar_entradas($_POST);
    guardar_errores($errores);

    // Ojo con el mensaje: no se guarda NADA, ni siquiera los días que
    // estaban bien. Sin decirlo, el dueño arregla el día marcado y
    // cree que los otros sí se guardaron.
    flash_error('No se guardó nada del horario. Revisá los campos marcados '
        . 'en rojo y volvé a guardar.');

    redirigir('horarios');
}

// --- Guardar ---
Horarios::guardarSemana($guardado);

// --- Aviso si el bar queda cerrado todos los días ---
$todos = true;

foreach (Horarios::semana() as $dia) {
    if (!$dia['cerrado']) {
        $todos = false;
        break;
    }
}

if ($todos) {
    flash_info('El horario quedó con todos los días cerrados. '
        . 'Si fue a propósito, la página pública va a decir que no hay horarios.');
} else {
    flash_exito('Horario guardado. La página pública ya muestra lo nuevo.');
}

redirigir('horarios');