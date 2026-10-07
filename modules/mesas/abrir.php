<?php
/**
 * modules/mesas/abrir.php
 * -----------------------------------------------------------------
 * Abre una mesa. Lo usan los dos roles.
 * -----------------------------------------------------------------
*/

$mesaId     = (int) post('mesa_id', '0');
$comensales = (int) post('comensales', '1');
$observacion = post('observacion');

$errores = [];

if ($mesaId <= 0) {
    flash_error('No se indicó qué mesa abrir.');
    redirigir('mesas');
}

if ($comensales < 1 || $comensales > 50) {
    $errores['comensales'] = 'La cantidad de comensales no es válida.';
}

// --- Abrir -------------------------------------------------------
if (empty($errores)) {
    $resultado = Comanda::abrir($mesaId, $comensales, $observacion);

    if ($resultado['ok']) {
        flash_exito('Mesa abierta.');

        if ($observacion !== '') {
            flash_info('Nota guardada: ' . $observacion);
        }

        redirigir('mesas/ver&mesa=' . $mesaId);
    }

    $errores['comensales'] = $resultado['mensaje'];
}

guardar_errores($errores);
flash_error('No se pudo abrir la mesa.');

if (!headers_sent()) {
    header('Location: ' . url('mesas/ver') . '&mesa=' . $mesaId);
    exit;
}

exit;