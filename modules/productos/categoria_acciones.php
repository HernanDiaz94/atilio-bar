<?php
/**
 * modules/productos/categoria_acciones.php
 * -----------------------------------------------------------------
 * Activar, desactivar o eliminar categorías. Solo administradores.
 *
 * Una categoría con productos NO se puede eliminar: borrarla dejaría
 * productos sin categoría, y el foráneo de la base lo impide. En
 * ese caso se desactiva, que es lo que hace falta en la práctica:
 * deja de ofrecerse como opción y sus productos quedan fuera de la
 * carta, pero sin perder nada.
 * -----------------------------------------------------------------
*/

$accion      = post('accion');
$idCategoria = (int) post('id', '0');

if ($idCategoria <= 0) {
    flash_error('No se indicó qué categoría modificar.');
    redirigir('categorias');
}

$categoria = DB::one(
    'SELECT id, nombre, activo FROM categorias WHERE id = :id',
    ['id' => $idCategoria]
);

if ($categoria === null) {
    flash_error('Esa categoría no existe.');
    redirigir('categorias');
}

$nombre = (string) $categoria['nombre'];

switch ($accion) {
    // ------------------------------------------------------------
    case 'activar':
        DB::actualizar('categorias', $idCategoria, ['activo' => 1]);

        Auditoria::registrar('activar_categoria', 'categorias', $idCategoria,
            ['activo' => 0], ['activo' => 1]);

        flash_exito('Categoría "' . $nombre . '" activada.');
        break;

    // ------------------------------------------------------------
    case 'desactivar':
        DB::actualizar('categorias', $idCategoria, ['activo' => 0]);

        Auditoria::registrar('desactivar_categoria', 'categorias', $idCategoria,
            ['activo' => 1], ['activo' => 0]);

        flash_exito(
            'Categoría "' . $nombre . '" desactivada. '
            . 'No se pueden agregar productos nuevos a esta categoría.'
        );
        break;

    // ------------------------------------------------------------
    case 'eliminar':
        $productos = (int) DB::valor(
            'SELECT COUNT(*) FROM productos WHERE categoria_id = :id',
            ['id' => $idCategoria]
        );

        if ($productos > 0) {
            flash_error(
                'No se puede eliminar "' . $nombre . '" porque tiene ' . $productos . ' producto(s). '
                . 'Desactivala en su lugar.'
            );
            redirigir('categorias');
        }

        Auditoria::registrar('eliminar_categoria', 'categorias', $idCategoria, $categoria, null);

        DB::eliminar('categorias', $idCategoria);

        flash_exito('Categoría "' . $nombre . '" eliminada.');
        break;

    // ------------------------------------------------------------
    default:
        flash_error('La acción solicitada no existe.');
        break;
}

redirigir('categorias');