<?php
/**
 * modules/mesas/administrar.php
 * -----------------------------------------------------------------
 * Alta, edición y baja de mesas. Solo administradores.
 * -----------------------------------------------------------------
*/

$erroresGuardados = tomar_errores();
$entradas         = recuperar_entradas();

$mesas = DB::all(
    "SELECT m.*,
            (SELECT COUNT(*) FROM comandas c
              WHERE c.mesa_id = m.id) AS comandas_total,
            (SELECT COUNT(*) FROM comandas c
              WHERE c.mesa_id = m.id
                AND c.estado IN ('abierta', 'por_cobrar')) AS comandas_abiertas
     FROM mesas m
     ORDER BY m.zona ASC, m.orden ASC, m.numero ASC"
);

$totalMesas   = count($mesas);
$totalActivas = count(
    array_filter($mesas, static fn ($m) => (int) $m['activo'] === 1)
);

require APP_ROOT . '/modules/mesas/vistas/administrar.php';