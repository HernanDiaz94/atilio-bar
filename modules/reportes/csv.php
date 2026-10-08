<?php
/**
 * modules/reportes/csv.php
 * -----------------------------------------------------------------
 * La descarga del reporte.
 *
 * POR QUÉ NO ES LO MISMO QUE LA TABLA
 *
 * El CSV se arma con los mismos datos y las mismas columnas que se
 * ven en pantalla, pero en el formato que el Excel en español
 * entiende. Si el archivo se hiciera a mano, un día se cambiaría una
 * columna en la tabla y el archivo seguiría bajando la vieja.
 *
 * TRES COSAS QUE EL EXCEL EN ESPAÑOL EXIGE
 *
 *   - punto y coma como separador: con coma, el Excel lo lee como
 *     una sola columna gigante;
 *   - coma decimal y punto de miles (1.234,56);
 *   - el BOM al principio del archivo: sin él, "Café" se abre con la
 *     A acentuada y la e con un signo arriba.
 *
 * CUIDADO CON EL CACHE
 *
 * Se manda "no-store": el navegador no tiene por qué guardar un
 * archivo de ventas de ayer y mostrárselo mañana a un reporte que ya
 * debería traer los números de hoy.
 * -----------------------------------------------------------------
 */

$vistas = Reportes::vistas();

$vista = get('vista', 'dia');

if (!isset($vistas[$vista])) {
    $vista = 'dia';
}

$periodo = get('periodo', '');
$desde   = get('desde', '');
$hasta   = get('hasta', '');

$rango = Reportes::rango(
    $desde !== '' ? $desde : null,
    $hasta !== '' ? $hasta : null,
    $periodo
);

$datos = Reportes::paraCsv($vista, $rango);

// --- Una fila arriba con de qué período es -----------------------------
// Sin esto, un archivo que secalled "Productos mas vendidos.csv" no
// dice si es de julio o de todo el año, y a la semana ya no se sabe.
$encabezado = 'Bar Atilio - ' . $vistas[$vista]['csv'] . ' - '
    . Reportes::etiquetaRango($rango);

// --- Nombre del archivo -------------------------------------------------
// Sin tildes ni espacios raros, porque el nombre viaja en la URL y no
// todos los navegadores lo manejan igual.
$archivo = strtolower(str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', ' '],
    ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n', '-'],
    $vistas[$vista]['csv']
));

$archivo .= '-' . $rango['desde'] . '_' . $rango['hasta'] . '.csv';

$csv = Reportes::csv($datos['columnas'], $datos['filas'], $encabezado);

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');

    // Un reporte de "hoy" de ayer se veria igual si el navegador lo
    // guardara en caché: el dueño vería los números viejos sin
    // darse cuenta.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Para que el navegador no intente interpretar el archivo como
    // HTML si algo se sale de lugar.
    header('X-Content-Type-Options: nosniff');

    header('Content-Length: ' . strlen($csv));
}

echo $csv;

exit;