<?php
/**
 * modules/mesas/acciones_mesa.php
 * -----------------------------------------------------------------
 * Activar, dar de baja o eliminar mesas. Solo administradores.
 *
 * Una mesa con comandas NO se borra nunca: se da de baja. Si se
 * eliminara, los pedidos viejos quedarían apuntando a una mesa
 * inexistente y los informes no cuadrarían.
 * -----------------------------------------------------------------
*/

$accion   = post('accion');
$idMesa   = (int) post('id', '0');

if ($idMesa <= 0) {
    flash_error('No se indicó qué mesa modificar.');
    redirigir('mesas/administrar');
}

$mesa = DB::one(
    'SELECT id, numero, activo FROM mesas WHERE id = :id',
    ['id' => $idMesa]
);

if ($mesa === null) {
    flash_error('Esa mesa no existe.');
    redirigir('mesas/administrar');
}

$numero = (string) $mesa['numero'];

switch ($accion) {
    // ------------------------------------------------------------
    case 'activar':
        DB::actualizar('mesas', $idMesa, ['activo' => 1]);

        Auditoria::registrar('activar_mesa', 'mesas', $idMesa,
            ['activo' => 0], ['activo' => 1]);

        flash_exito('Mesa ' . $numero . ' activada. Vuelve a la grilla.');
        break;

    // ------------------------------------------------------------
    case 'desactivar':
        // No se da de baja una mesa con gente sentada.
        $ocupada = (int) DB::valor(
            "SELECT COUNT(*) FROM comandas
             WHERE mesa_id = :mesa AND estado IN ('abierta', 'por_cobrar')",
            ['mesa' => $idMesa]
        );

        if ($ocupada > 0) {
            flash_error(
                'La mesa ' . $numero . ' tiene un pedido abierto. '
                . 'Cerralo o anulalo antes de darla de baja.'
            );
            redirigir('mesas/administrar');
        }

        DB::actualizar('mesas', $idMesa, ['activo' => 0]);

        Auditoria::registrar('desactivar_mesa', 'mesas', $idMesa,
            ['activo' => 1], ['activo' => 0]);

        flash_exito('Mesa ' . $numero . ' dada de baja. Deja de aparecer en la grilla.');
        break;

    // ------------------------------------------------------------
    case 'eliminar':
        $pedidos = (int) DB::valor(
            'SELECT COUNT(*) FROM comandas WHERE mesa_id = :mesa',
            ['mesa' => $idMesa]
        );

        if ($pedidos > 0) {
            flash_error(
                'No se puede eliminar la mesa ' . $numero . ' porque tiene '
                . $pedidos . ' pedido(s) registrados. '
                . 'Dala de baja en su lugar: así los informes siguen cuadrando.'
            );
            redirigir('mesas/administrar');
        }

        Auditoria::registrar('eliminar_mesa', 'mesas', $idMesa, $mesa, null);

        DB::eliminar('mesas', $idMesa);

        flash_exito('Mesa ' . $numero . ' eliminada.');
        break;

    // ------------------------------------------------------------
    default:
        flash_error('La acción solicitada no existe.');
        break;
}

redirigir('mesas/administrar');