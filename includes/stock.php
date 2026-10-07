<?php
/**
 * includes/stock.php
 * -----------------------------------------------------------------
 * Servicio de stock: el único lugar por donde pasa cualquier cambio
 * de existencias.
 *
 * ¿Por qué-centralizarlo? Porque si cada pantalla restara stock por
 * su cuenta, tarde o temprano una se olvidaría de dejar el registro
 * en stock_movimientos, y el historial dejaría de cuadrar con la
 * realidad. Con este servicio, que el stock actual coincida con el
 * libro de movimientos es una consecuencia, no una promesa.
 *
 * En la Etapa 2 lo usan las compras y los ajustes manuales. En las
 * etapas siguientes se le suma el cobro de mesas y las ventas
 * rápidas, sin cambiar una sola vez la forma de llamarlo.
 * -----------------------------------------------------------------
 */

/**
 * Clase Stock
 */
final class Stock
{
    /**
     * Tipos de movimiento permitidos.
     * Coinciden con el ENUM de la tabla stock_movimientos.
     */
    public const TIPOS = [
        'alta_inicial',
        'compra',
        'ajuste',
        'venta',
        'anulacion_venta',
        'anulacion_item',
        'mesa_consumo',
        'devolucion',
        'baja',
    ];

    // ------------------------------------------------------------
    // Consulta de producto
    // ------------------------------------------------------------

    /**
     * Devuelve el producto con el stock bloqueado para poder
     * modificarlo dentro de una transacción.
     *
     * El bloqueo (FOR UPDATE) evita dos cargas simultáneas que
     * partan del mismo stock y produzcan un resultado que no
     * cuadra con la suma de los movimientos.
     */
    public static function producto_bloqueado(int $productoId): ?array
    {
        return DB::one(
            'SELECT id, nombre, stock_actual, stock_minimo, costo, controla_stock, activo
             FROM productos
             WHERE id = :id
             FOR UPDATE',
            ['id' => $productoId]
        );
    }

    // ------------------------------------------------------------
    // Costo promedio ponderado
    // ------------------------------------------------------------

    /**
     * Calcula el costo promedio ponderado después de una compra.
     *
     * Si tenés 10 botellas a $100 y comprás 10 a $120, el costo
     * promedio pasa a ser $110, no $120. Es el número que sirve
     * para saber si el negocio está ganando.
     *
     * @param float|null $costoActual    Costo vigente del producto
     * @param float      $stockActual    Stock antes de la compra
     * @param float      $cantidad       Cantidad que entra
     * @param float      $costoEntrada   Costo unitario de lo que entra
     */
    public static function costo_promedio(
        ?float $costoActual,
        float $stockActual,
        float $cantidad,
        float $costoEntrada
    ): ?float {
        // Sin costo conocido antes: adoptamos el de esta compra.
        if ($costoActual === null || (float) $costoActual === 0.0) {
            return round($costoEntrada, 2);
        }

        $stockFinal = $stockActual + $cantidad;

        // Si la compra deja el stock en cero (o negativo por un
        // ajuste previo), no hay promedio posible: usamos el nuevo.
        if ($stockFinal <= 0) {
            return round($costoEntrada, 2);
        }

        $valor = ($stockActual * $costoActual) + ($cantidad * $costoEntrada);

        return round($valor / $stockFinal, 2);
    }

    // ------------------------------------------------------------
    // El movimiento
    // ------------------------------------------------------------

    /**
     * Aplica un movimiento de stock.
     *
     * ESTE MÉTODO DEBE USARSE DENTRO DE UNA TRANSACCIÓN: el llamador
     * la abre con DB::enTransaccion(). Así, si algo falla después
     * (por ejemplo, al guardar el detalle de una compra), se
     * revierte el stock junto con el resto.
     *
     * @param int    $productoId
     * @param string $tipo      Uno de self::TIPOS
     * @param float  $cantidad  Positiva suma, negativa resta
     * @param array  $opciones  [
     *     motivo           => string  (obligatorio en ajustes)
     *     usuario_id       => int
     *     referencia_tipo  => string  'compra' | 'producto'
     *     referencia_id    => int
     *     costo_unitario   => float   costo de esta entrada (compras)
     *     actualizar_costo => bool    default true en compras
     * ]
     *
     * @return array{ok: bool, stock_anterior: float, stock_resultante: float, costo: ?float, mensaje: string}
     */
    public static function aplicar(int $productoId, string $tipo, float $cantidad, array $opciones = []): array
    {
        $resultadoVacio = [
            'ok'               => false,
            'stock_anterior'   => 0.0,
            'stock_resultante' => 0.0,
            'costo'            => null,
            'mensaje'          => '',
        ];

        // --- Validación del tipo ---
        if (!in_array($tipo, self::TIPOS, true)) {
            return $resultadoVacio + ['mensaje' => 'Tipo de movimiento desconocido.'];
        }

        // --- Producto ---
        $producto = self::producto_bloqueado($productoId);

        if ($producto === null) {
            return $resultadoVacio + ['mensaje' => 'El producto no existe.'];
        }

        // Un producto que no lleva control de stock no debería
        // moverse nunca. Si alguien lo intenta, es un error de
        // programación, no un dato malo: por eso se corta acá.
        if ((int) $producto['controla_stock'] !== 1) {
            return $resultadoVacio + [
                'mensaje' => 'El producto "' . $producto['nombre'] . '" no lleva control de stock.',
            ];
        }

        if ($cantidad === 0.0) {
            return $resultadoVacio + ['mensaje' => 'La cantidad tiene que ser distinta de cero.'];
        }

        // --- Cálculo ---
        $stockAnterior = (float) $producto['stock_actual'];
        $stockResultante = $stockAnterior + $cantidad;

        // El stock negativo se permite a propósito: en un bar es real
        // que el último trago se sirva antes de que se corrija el
        // conteo. La app lo marca, no lo esconde.
        $costoNuevo = $producto['costo'] === null ? null : (float) $producto['costo'];

        $actualizarCosto = $opciones['actualizar_costo'] ?? false;

        if ($actualizarCosto && isset($opciones['costo_unitario'])) {
            $costoNuevo = self::costo_promedio(
                $producto['costo'] === null ? null : (float) $producto['costo'],
                $stockAnterior,
                $cantidad,
                (float) $opciones['costo_unitario']
            );
        }

        // --- Actualización del producto ---
        $datosProducto = [
            'stock_actual'   => $stockResultante,
            'actualizado_en' => ahora(),
        ];

        if ($costoNuevo !== null) {
            $datosProducto['costo'] = $costoNuevo;
        }

        DB::actualizar('productos', $productoId, $datosProducto);

        // --- Registro en el libro de movimientos ---
        DB::insertar('stock_movimientos', [
            'producto_id'      => $productoId,
            'tipo'             => $tipo,
            'cantidad'         => $cantidad,
            'stock_anterior'   => $stockAnterior,
            'stock_resultante' => $stockResultante,
            'costo_unitario'   => $costoNuevo,
            'motivo'           => mb_substr((string) ($opciones['motivo'] ?? ''), 0, 255),
            'referencia_tipo'  => mb_substr((string) ($opciones['referencia_tipo'] ?? ''), 0, 40),
            'referencia_id'    => $opciones['referencia_id'] ?? null,
            'usuario_id'       => $opciones['usuario_id'] ?? Auth::id(),
            'creado_en'        => ahora(),
        ]);

        return [
            'ok'               => true,
            'stock_anterior'   => $stockAnterior,
            'stock_resultante' => $stockResultante,
            'costo'            => $costoNuevo,
            'mensaje'          => $stockResultante < 0
                ? 'Quedó el stock en negativo (' . cantidad($stockResultante) . '). Conviene corregir el conteo.'
                : '',
        ];
    }

    // ------------------------------------------------------------
    // Alta de un producto con stock inicial
    // ------------------------------------------------------------

    /**
     * Cuando se da de alta un producto con stock, ese stock tiene que
     * quedar asentado en el libro como movimiento 'alta_inicial'.
     * Si no, el historial no cierra.
     */
    public static function registrar_alta_inicial(int $productoId, float $stockInicial): void
    {
        if ($stockInicial === 0.0) {
            return;
        }

        DB::insertar('stock_movimientos', [
            'producto_id'      => $productoId,
            'tipo'             => 'alta_inicial',
            'cantidad'         => $stockInicial,
            'stock_anterior'   => 0.0,
            'stock_resultante' => $stockInicial,
            'costo_unitario'   => null,
            'motivo'           => 'Stock inicial al dar de alta el producto',
            'referencia_tipo'  => 'producto',
            'referencia_id'    => $productoId,
            'usuario_id'       => Auth::id(),
            'creado_en'        => ahora(),
        ]);
    }

    // ------------------------------------------------------------
    // Recetas de los combos
    // ------------------------------------------------------------

    /**
     * Devuelve los insumos de un combo con la cantidad a descontar.
     */
    public static function receta(int $comboId): array
    {
        return DB::all(
            'SELECT cc.id, cc.cantidad, cc.componente_id,
                    p.nombre, p.unidad, p.stock_actual, p.stock_minimo, p.activo
             FROM combo_componentes cc
             INNER JOIN productos p ON p.id = cc.componente_id
             WHERE cc.combo_id = :combo
             ORDER BY p.nombre ASC',
            ['combo' => $comboId]
        );
    }

    /**
     * Guarda la receta completa de un combo, reemplazando la anterior.
     *
     * Se reemplaza entera en vez de agregar y borrar: si algo falla
     * a mitad de camino no queda media receta.
     *
     * @param array $componentes [id del insumo => cantidad]
     */
    public static function guardar_receta(int $comboId, array $componentes): int
    {
        // Se reemplaza la receta entera en vez de agregar y borrar,
        // para que si algo falla no quede media receta.
        DB::query('DELETE FROM combo_componentes WHERE combo_id = :combo', ['combo' => $comboId]);

        $guardados = 0;

        foreach ($componentes as $insumoId => $cantidad) {
            $insumoId = (int) $insumoId;
            $cantidad = (float) $cantidad;

            // Descartamos los que no sirven: el propio combo, o
            // cantidades en cero.
            if ($cantidad <= 0 || $insumoId <= 0 || $insumoId === $comboId) {
                continue;
            }

            DB::insertar('combo_componentes', [
                'combo_id'     => $comboId,
                'componente_id' => $insumoId,
                'cantidad'     => $cantidad,
            ]);

            $guardados++;
        }

        return $guardados;
    }

    /**
     * Chequea que la receta no tenga problemas.
     *
     * @return array Lista de avisos (vacío si todo está bien)
     */
    public static function problemas_receta(int $comboId): array
    {
        $avisos = [];
        $receta = self::receta($comboId);

        if (empty($receta)) {
            $avisos[] = 'El combo no tiene insumos cargados: al venderlo no se va a descontar nada.';

            return $avisos;
        }

        foreach ($receta as $insumo) {
            $requerido = (float) $insumo['cantidad'];

            if ((float) $insumo['stock_actual'] < $requerido) {
                $avisos[] = 'Faltan insumos de "' . $insumo['nombre'] . '": hay '
                    . cantidad($insumo['stock_actual']) . ' ' . $insumo['unidad']
                    . ' y cada ' . $nombrePluralODepende($comboId) . ' necesita ' . cantidad($requerido) . '.';
            }
        }

        return $avisos;
    }

    // ------------------------------------------------------------
    // Alertas para el dashboard
    // ------------------------------------------------------------

    /**
     * Productos con stock por debajo del mínimo.
     */
    public static function stock_bajo(int $limite = 8): array
    {
        $limite = max(1, min(100, $limite));

        return DB::all(
            "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad,
                    c.nombre AS categoria
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1
               AND p.controla_stock = 1
               AND p.stock_actual <= p.stock_minimo
             ORDER BY (p.stock_actual - p.stock_minimo) ASC, p.nombre ASC
             LIMIT {$limite}"
        );
    }

    /**
     * Cuenta cuántos productos están en cero o por debajo del mínimo.
     */
    public static function cantidad_stock_bajo(): int
    {
        return (int) DB::valor(
            'SELECT COUNT(*) FROM productos
             WHERE activo = 1 AND controla_stock = 1 AND stock_actual <= stock_minimo'
        );
    }

    /**
     * Stock en negativo: señal de que hay que contar.
     */
    public static function cantidad_stock_negativo(): int
    {
        return (int) DB::valor(
            'SELECT COUNT(*) FROM productos
             WHERE activo = 1 AND controla_stock = 1 AND stock_actual < 0'
        );
    }

    // ------------------------------------------------------------
    // Historial
    // ------------------------------------------------------------

    /**
     * Movimientos con los datos del producto, para la pantalla de
     * historial. Filtros opcionales.
     */
    public static function movimientos(array $filtros = [], int $limite = 100, int $pagina = 1): array
    {
        $limite = max(1, min(500, $limite));
        $offset = max(0, ($pagina - 1) * $limite);

        $sql = 'SELECT m.*, p.nombre AS producto_nombre, p.unidad,
                       c.nombre AS categoria, u.usuario AS usuario_nombre,
                       co.proveedor AS compra_proveedor, co.numero_comprobante
                FROM stock_movimientos m
                INNER JOIN productos p ON p.id = m.producto_id
                INNER JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                LEFT JOIN compras co ON co.id = m.referencia_id AND m.referencia_tipo = \'compra\'
                WHERE 1 = 1';

        $parametros = [];

        if (!empty($filtros['producto_id'])) {
            $sql .= ' AND m.producto_id = :producto';
            $parametros['producto'] = (int) $filtros['producto_id'];
        }

        if (!empty($filtros['tipo']) && in_array($filtros['tipo'], self::TIPOS, true)) {
            $sql .= ' AND m.tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['desde'])) {
            $sql .= ' AND m.creado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }

        if (!empty($filtros['hasta'])) {
            $sql .= ' AND m.creado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql .= " ORDER BY m.id DESC LIMIT {$limite} OFFSET {$offset}";

        return DB::all($sql, $parametros);
    }

    /**
     * Cuántos movimientos hay en total (para la paginación).
     */
    public static function total_movimientos(array $filtros = []): int
    {
        $sql = 'SELECT COUNT(*)
                FROM stock_movimientos m
                INNER JOIN productos p ON p.id = m.producto_id
                WHERE 1 = 1';

        $parametros = [];

        if (!empty($filtros['producto_id'])) {
            $sql .= ' AND m.producto_id = :producto';
            $parametros['producto'] = (int) $filtros['producto_id'];
        }

        if (!empty($filtros['tipo']) && in_array($filtros['tipo'], self::TIPOS, true)) {
            $sql .= ' AND m.tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['desde'])) {
            $sql .= ' AND m.creado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }

        if (!empty($filtros['hasta'])) {
            $sql .= ' AND m.creado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        return (int) DB::valor($sql, $parametros);
    }

    // ------------------------------------------------------------
    // Compras
    // ------------------------------------------------------------

    /**
     * Guarda una compra completa: cabecera, detalle, movimientos de
     * stock y actualización de costos, todo en una transacción.
     *
     * @param array $lineas [ ['producto_id' => int, 'cantidad' => float, 'costo' => float], ... ]
     *
     * @return array{ok: bool, compra_id: int, mensaje: string, errores: array}
     */
    public static function guardar_compra(array $datos, array $lineas): array
    {
        // --- Validación de las líneas ---
        $lineasValidas = [];
        $errores = [];

        foreach ($lineas as $linea) {
            $productoId = (int) ($linea['producto_id'] ?? 0);
            $cantidad   = (float) ($linea['cantidad'] ?? 0);
            $costo      = (float) ($linea['costo'] ?? 0);

            if ($productoId <= 0) {
                continue;   // línea vacía del formulario
            }

            if ($cantidad <= 0) {
                $errores[] = 'Hay una línea con cantidad cero o negativa.';
                continue;
            }

            if ($costo < 0) {
                $errores[] = 'Hay una línea con costo negativo.';
                continue;
            }

            $producto = DB::one(
                'SELECT id, nombre, controla_stock FROM productos WHERE id = :id',
                ['id' => $productoId]
            );

            if ($producto === null) {
                $errores[] = 'Una de las líneas apunta a un producto que no existe.';
                continue;
            }

            if ((int) $producto['controla_stock'] !== 1) {
                $errores[] = 'El producto "' . $producto['nombre'] . '" no lleva control de stock, no se puede comprar.';
                continue;
            }

            $lineasValidas[] = [
                'producto_id' => $productoId,
                'cantidad'   => $cantidad,
                'costo'      => $costo,
            ];
        }

        if (empty($lineasValidas)) {
            return ['ok' => false, 'compra_id' => 0, 'mensaje' => '', 'errores' => ['Agregá al menos un producto. ' . implode(' ', $errores)]];
        }

        if (!empty($errores)) {
            return ['ok' => false, 'compra_id' => 0, 'mensaje' => '', 'errores' => $errores];
        }

        $total = 0.0;
        foreach ($lineasValidas as $linea) {
            $total += $linea['cantidad'] * $linea['costo'];
        }

        // --- Todo junto o nada ---
        try {
            return DB::enTransaccion(function () use ($datos, $lineasValidas, $total) {
                $fecha = $datos['fecha'] ?? date('Y-m-d');

                // 1) Cabecera
                $compraId = DB::insertar('compras', [
                    'proveedor'          => mb_substr((string) ($datos['proveedor'] ?? ''), 0, 120),
                    'numero_comprobante' => mb_substr((string) ($datos['numero_comprobante'] ?? ''), 0, 60),
                    'fecha'              => $fecha,
                    'total'              => round($total, 2),
                    'observaciones'      => mb_substr((string) ($datos['observaciones'] ?? ''), 0, 255),
                    'cantidad_items'     => count($lineasValidas),
                    'usuario_id'         => Auth::id(),
                    'creado_en'          => ahora(),
                ]);

                // 2) Detalle + stock
                foreach ($lineasValidas as $linea) {
                    // El costo anterior se lee ANTES de aplicar el
                    // movimiento: después ya quedó el nuevo.
                    $costoPrevio = DB::valor(
                        'SELECT costo FROM productos WHERE id = :id',
                        ['id' => $linea['producto_id']]
                    );

                    $movimiento = self::aplicar(
                        $linea['producto_id'],
                        'compra',
                        $linea['cantidad'],
                        [
                            'motivo'          => 'Compra'
                                . (!empty($datos['proveedor']) ? ' a ' . $datos['proveedor'] : '')
                                . (!empty($datos['numero_comprobante']) ? ' (comprobante ' . $datos['numero_comprobante'] . ')' : ''),
                            'referencia_tipo' => 'compra',
                            'referencia_id'   => $compraId,
                            'costo_unitario'  => $linea['costo'],
                            'actualizar_costo' => true,
                        ]
                    );

                    if (!$movimiento['ok']) {
                        // Rompe la transacción entera: mejor no
                        // guardar una compra a medias.
                        throw new RuntimeException($movimiento['mensaje']);
                    }

                    DB::insertar('compras_detalle', [
                        'compra_id'      => $compraId,
                        'producto_id'    => $linea['producto_id'],
                        'cantidad'       => $linea['cantidad'],
                        'costo_unitario' => $linea['costo'],
                        'costo_anterior' => $costoPrevio === null ? null : (float) $costoPrevio,
                        'costo_nuevo'    => $movimiento['costo'],
                        'subtotal'       => round($linea['cantidad'] * $linea['costo'], 2),
                    ]);
                }

                Auditoria::registrar(
                    'registrar_compra',
                    'compras',
                    $compraId,
                    null,
                    [
                        'proveedor' => $datos['proveedor'] ?? '',
                        'items'     => count($lineasValidas),
                        'total'     => round($total, 2),
                    ]
                );

                return [
                    'ok'       => true,
                    'compra_id' => $compraId,
                    'mensaje'  => '',
                    'errores'  => [],
                ];
            });
        } catch (Throwable $e) {
            error_log('Error guardando compra: ' . $e->getMessage());

            return [
                'ok'       => false,
                'compra_id' => 0,
                'mensaje'  => 'No se pudo guardar la compra: ' . $e->getMessage(),
                'errores'  => [],
            ];
        }
    }
}

/**
 * Helper de apoyo para los avisos de receta.
 * Evita un mensaje repetitive con el nombre del combo.
 */
function nombrePluralODepende(int $comboId): string
{
    $combo = DB::one('SELECT nombre FROM productos WHERE id = :id', ['id' => $comboId]);

    return $combo ? ('cada ' . $combo['nombre']) : 'cada unidad';
}