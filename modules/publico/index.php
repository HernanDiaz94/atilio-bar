<?php
/**
 * modules/publico/index.php
 * -----------------------------------------------------------------
 * La página pública: horarios, contacto y carta.
 *
 * ES LO ÚNICO QUE SE ABRE SIN SESIÓN
 *
 * La gente que busca el bar en Google no tiene usuario ni clave, así
 * que esta pantalla tiene que poder cargarse sola. Por eso:
 *
 *   - La consulta de los productos NO pide la columna `costo`. No es
 *     que se esconda: no se pide. No hay forma de que el precio de
 *     compra del fernet llegue al HTML, ni por error ni por descuido.
 *   - Solo se muestran los productos activos.
 *   - Lo que se imprime pasa por htmlspecialchars, como en el resto
 *     del sistema.
 *
 * LO QUE NO HAY ACÁ
 *
 * Ningún dato de venta: ni mesas, ni caja, ni comandas, ni usuarios.
 * La página no consulta esas tablas, así que no hay nada que filtrar.
 *
 * PARA GOOGLE
 *
 * La página lleva los datos estructurados de tipo bar/restaurante
 * con el horario de atención. Es lo que Google usa para mostrar
 * "Abierto · Cierra 02:00" en los resultados.
 * -----------------------------------------------------------------
 */

// --- El horario ---
$semana = Horarios::semana();
$estado = Horarios::estado();

$mostrarHorarios = config_bool('publico_mostrar_horarios', true);

// --- Los datos del bar ---
$nombre    = (string) config_valor('publico_nombre', APP_NOMBRE);
$telefono  = trim((string) config_valor('publico_telefono', ''));
$direccion = trim((string) config_valor('publico_direccion', ''));
$texto     = trim((string) config_valor('publico_texto', ''));

$mostrarCarta = config_bool('publico_mostrar_carta', true);
$mostrarMapa  = config_bool('publico_mostrar_mapa', true) && $direccion !== '';

// --- La carta: SÓLO productos activos y SÓLO columnas públicas ---
//
// Ni `costo`, ni `stock_actual`, ni `stock_minimo`, ni `categoria_id`
// crudo. Lo que se necesita y nada más.
$productos = [];

if ($mostrarCarta) {
    $productos = DB::all(
        'SELECT p.id,
                p.nombre,
                p.descripcion,
                p.precio_venta,
                p.unidad,
                p.formas_venta,
                c.nombre AS categoria,
                c.orden  AS categoria_orden
           FROM productos p
           JOIN categorias c ON c.id = p.categoria_id
          WHERE p.activo = 1
            AND c.activo = 1
          ORDER BY c.orden ASC, c.nombre ASC, p.nombre ASC'
    );
}

// --- Agrupar por categoría, para que no sea una lista interminable ---
$porCategoria = [];

foreach ($productos as $producto) {
    $categoria = (string) $producto['categoria'];

    if (!isset($porCategoria[$categoria])) {
        $porCategoria[$categoria] = [];
    }

    $porCategoria[$categoria][] = $producto;
}

// --- Los próximos días cerrados, para avisar sin que pregunten ---
$proximosCierres = [];

foreach (Horarios::cierres(date('Y-m-d'), date('Y-m-d', strtotime('+60 days'))) as $cierre) {
    $proximosCierres[] = $cierre;
}

// --- Para Google ---
$horariosJsonLd = Horarios::schemaJsonLd();

// El título y la descripción que van en el <title> y en el buscador.
$tituloPagina = $estado['abierto']
    ? $nombre . ' · Abierto ahora'
    : $nombre;

// La descripción es lo que Google muestra debajo del resultado. Va
// con dos frases y sin):= con frases completas.
//
// Antes armaba el nombre pegado a la frase de horarios, y no
// es una frase: le colgaba el "en" de la dirección adelante de una
// frase que ya empezaba con "Abrimos".
$donde  = $direccion !== '' ? ' en ' . $direccion : '';
$estadoTexto = $estado['abierto']
    ? 'Abierto ahora, hasta las ' . ($estado['cierra']?->format('H:i') ?? '') . '.'
    : Horarios::textoProxima($estado['ahora']) . '.';

$descripcion = trim($nombre . $donde . '. ' . $estadoTexto);

require APP_ROOT . '/modules/publico/vistas/index.php';