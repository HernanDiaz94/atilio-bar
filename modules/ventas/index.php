<?php
/**
 * modules/ventas/index.php
 * -----------------------------------------------------------------
 * Historial de ventas. Solo el administrador.
 *
 * Es la pantalla que se usa para saber "cuánto vendimos hoy" y
 * para encontrar una venta concreta cuando hay que anularla o
 * revisar un cobro.
 * -----------------------------------------------------------------
*/

// --- Filtro por fecha ---------------------------------------------
$desde = get('desde');
$hasta = get('hasta');

foreach (['desde', 'hasta'] as $campo) {
    if ($$campo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$campo)) {
        $$campo = '';
    }
}

// Por defecto: hoy.
if ($desde === '' && $hasta === '') {
    $desde = date('Y-m-d');
    $hasta = date('Y-m-d');
}

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$rangoValido = ($desde !== '' && $hasta !== '');

// --- Paginación ---------------------------------------------------
$pagina  = max(1, (int) get('pagina', '1'));
$porPagina = 30;

// --- Datos --------------------------------------------------------
$ventas  = [];
$totales = ['ventas' => 0, 'bruto' => 0.0, 'descuentos' => 0.0, 'neto' => 0.0];

if ($rangoValido) {
    $ventas = Venta::listar($porPagina, $pagina);
    $totales = Venta::totales($desde, $hasta);
}

// Para la barra de paginación.
$totalPaginas = $rangoValido
    ? max(1, (int) ceil($totales['ventas'] / $porPagina))
    : 1;

require APP_ROOT . '/modules/ventas/vistas/index.php';
