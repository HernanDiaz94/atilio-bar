<?php
/**
 * modules/reportes/index.php
 * -----------------------------------------------------------------
 * Los reportes del bar.
 *
 * Solo el administrador entra acá: estos números traen la ganancia
 * y el costo, que no son asunto del vendedor. El permiso está en la
 * ruta (router.php), no acá: es la primera línea que se lee al
 * entrar, no una condición que se pueda saltar.
 * -----------------------------------------------------------------
 */

$vistas = Reportes::vistas();

$vista = get('vista', 'dia');

if (!isset($vistas[$vista])) {
    $vista = 'dia';
}

// --- Período -----------------------------------------------------------
// Se lee el de las fechas escritas a mano; si no hay, el botón que
// se apretó; si no hay ninguno, el mes.
$periodo = get('periodo', '');
$desde   = get('desde', '');
$hasta   = get('hasta', '');

$rango = Reportes::rango(
    $desde !== '' ? $desde : null,
    $hasta !== '' ? $hasta : null,
    $periodo
);

$esCsv = false;   // en esta pantalla no hay CSV

// --- Los datos del reporte elegido -------------------------------------
// Cada reporte trae sus datos y sus columnas: el CSV usa el mismo
// servicio, asi que no pueden quedar desincronizados.
$datos = [];

switch ($vista) {
    case 'dia':
        $datos = Reportes::ventasPorDia($rango);
        break;

    case 'productos':
        $datos = Reportes::productosMasVendidos($rango);
        break;

    case 'mesas':
        $datos = Reportes::porMesa($rango);
        break;

    case 'pagos':
        $datos = Reportes::porMedioPago($rango);
        break;
}

// --- Los totales, para el cartel de arriba -----------------------------
// Se calculan siempre, sin importar el reporte: el dueño quiere ver
// el total del período sin tener que cambiar de pestaña.
$resumen = Reportes::resumen($rango);

// --- Los avisos que hay que dar sí o sí --------------------------------
// Son tres, y ninguno es decorativo:
//
//  1) Hay productos sin costo cargado -> la ganancia que se muestra
//     está subestimada. Decirlo es mejor que mostrar un número que
//     el dueño va a usar para decidir y que no es el real.
//  2) El período empieza antes de que el costo fuera el real ->
//     esas ganancias son aproximadas.
//  3) El período se recortó porque era demasiado largo.
$avisosCosto = [];

if ($resumen['ventas_sin_costo'] > 0) {
    $avisosCosto[] = $resumen['ventas_sin_costo'] . ($resumen['ventas_sin_costo'] === 1
        ? ' venta del período no tiene'
        : ' ventas del período no tienen')
        . ' costo de producto cargado, así que la ganancia que ves está SUBESTIMADA. '
        . 'Cargá los costos faltantes en la carta y el número va a ser el real.';
}

$costoRealDesde = Reportes::costoRealDesde();

if ($costoRealDesde !== null && $rango['desde'] < $costoRealDesde) {
    $avisosCosto[] = 'Las ventas anteriores al ' . fecha_corta($costoRealDesde)
        . ' usan el costo que el producto tiene hoy, no el que tenía entonces. '
        . 'Su ganancia es aproximada: cargá el costo al producto y, a partir de esa fecha, '
        . 'los reportes usan el costo real.';
}

require APP_ROOT . '/modules/reportes/vistas/index.php';