<?php
/**
 * modules/mesas/mover.php
 * -----------------------------------------------------------------
 * Mover un pedido a otra mesa.
 *
 * GET  muestra la pantalla para elegir la mesa de destino.
 * POST hace el movimiento.
 *
 * Se usa cuando la mesa se mancha, la quieren otra, o llega más
 * gente y hay que juntarlas. Perder el pedido por un cambio de
 * lugar sería una Barbaridad.
 *
 * Lo usan los dos roles.
 * -----------------------------------------------------------------
*/

$comandaId = (int) get('comanda_id', '0');

if ($comandaId <= 0) {
    $comandaId = (int) post('comanda_id', '0');
}

if ($comandaId <= 0) {
    flash_error('No se indicó qué pedido mover.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$mesaActual = Mesas::obtener_simple((int) $comanda['mesa_id']);
$volver     = url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id'];

// Un pedido cerrado no se mueve: ya no se le puede tocar nada.
if ($comanda['estado'] !== 'abierta') {
    flash_error('Este pedido ya no se puede mover.');

    if (!headers_sent()) {
        header('Location: ' . $volver);
        exit;
    }

    exit;
}

// ------------------------------------------------------------
// POST: hacer el movimiento
// ------------------------------------------------------------
if (es_post()) {
    $mesaDestino = (int) post('mesa_destino', '0');

    if ($mesaDestino <= 0) {
        guardar_errores(['mesa_destino' => 'Elegí una mesa de destino.']);
        flash_error('No se eligió mesa de destino.');

        if (!headers_sent()) {
            header('Location: ' . url('mesas/mover') . '&comanda_id=' . $comandaId);
            exit;
        }

        exit;
    }

    $resultado = Comanda::mover($comandaId, $mesaDestino);

    if (!$resultado['ok']) {
        guardar_entradas(['mesa_destino' => $mesaDestino]);
        guardar_errores(['mesa_destino' => $resultado['mensaje']]);
        flash_error($resultado['mensaje']);

        if (!headers_sent()) {
            header('Location: ' . url('mesas/mover') . '&comanda_id=' . $comandaId);
            exit;
        }

        exit;
    }

    $destino = Mesas::obtener_simple($mesaDestino);

    flash_exito('Pedido movido a la mesa ' . ($destino['numero'] ?? '?') . '.');

    if (!headers_sent()) {
        header('Location: ' . $volver);
        exit;
    }

    exit;
}

// ------------------------------------------------------------
// GET: mostrar la pantalla de elección
// ------------------------------------------------------------

// Mesas a las que se puede ir: las libres, más la que ya tiene el
// pedido (para poder volver atrás sin error).
$mesasLibres = DB::all(
    "SELECT m.id, m.numero, m.nombre, m.capacidad, m.zona,
            (SELECT c2.id FROM comandas c2
             WHERE c2.mesa_id = m.id AND c2.estado IN ('abierta','por_cobrar')
             LIMIT 1) AS ocupada_por
     FROM mesas m
     WHERE m.activo = 1
     ORDER BY m.zona ASC, m.orden ASC
     LIMIT 100"
);

$erroresGuardados = tomar_errores();
$entradas         = recuperar_entradas();

require APP_ROOT . '/modules/mesas/vistas/mover.php';