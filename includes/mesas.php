<?php
/**
 * includes/mesas.php
 * -----------------------------------------------------------------
 * Servicio de mesas y comandas (Etapa 3).
 *
 * DOS DECISIONES IMPORTANTES:
 *
 * 1) EL ESTADO DE LA MESA SE CALCULA, NO SE GUARDA.
 *    No hay una tabla "mesas_estado". El estado sale de las
 *    comandas abiertas y de las reservas de hoy. Si hubiera una
 *    tabla de estado, cualquier camino que se olvide de
 *    actualizarla (un error, un cierre a mitad de camino) deja la
 *    grilla mintiendo. Calculándolo, no hay forma de que se
 *    desincronice del pedido real.
 *
 * 2) EL STOCK SE DESCUENTA AL AGREGAR EL ÍTEM, NO AL COBRAR.
 *    En un bar con meseros atendidos, lo importante es saber en el
 *    momento si quedan botellas, no a la noche cuando se cierra
 *    la caja. Si el vendedor carga una cerveza de más, se anula
 *    el ítem y el stock vuelve, con los dos movimientos
 *    asentados.
 * -----------------------------------------------------------------
 */

/**
 * Clase Mesas: la grilla y el estado de cada mesa.
 */
final class Mesas
{
    /** Zonas del bar, con su etiqueta. */
    public const ZONAS = [
        'salon'  => 'Salón',
        'vereda' => 'Vereda',
        'barra'  => 'Barra',
    ];

    /**
     * Devuelve todas las mesas con su estado actual, agrupables
     * por zona.
     *
     * La consulta del estado es una sola, con subconsultas
     * correlacionadas: se evita traer comandas por mesa en un
     * bucle, que es lo que hace lenta una grilla con 40 mesas.
     *
     * @return array Lista de mesas con: id, numero, nombre,
     *                  capacidad, zona, orden, estado,
     *                  comanda_id, comensales, minutos_abierta,
     *                  total_comanda, hora_reserva, nombre_reserva
     */
    public static function grilla(bool $soloActivas = true): array
    {
        $filtro = $soloActivas ? 'WHERE m.activo = 1' : '';

        $sql = "SELECT m.id, m.numero, m.nombre, m.capacidad, m.zona, m.orden, m.activo,

                       -- Comanda abierta de la mesa, si la hay
                       c.id            AS comanda_id,
                       c.estado        AS comanda_estado,
                       c.comensales    AS comensales,
                       c.abierta_en    AS abierta_en,
                       u.usuario       AS vendedor,
                       COALESCE(c.observacion, '') AS observacion,

                       -- Total del pedido (solo ítems pendientes)
                       COALESCE((
                           SELECT SUM(ci.importe)
                           FROM comanda_items ci
                           WHERE ci.comanda_id = c.id AND ci.estado = 'pendiente'
                       ), 0) AS total_comanda,

                       -- Cantidad de ítems distintos
                       COALESCE((
                           SELECT COUNT(*)
                           FROM comanda_items ci
                           WHERE ci.comanda_id = c.id AND ci.estado = 'pendiente'
                       ), 0) AS items_comanda,

                       -- Reserva de hoy, a partir de media hora antes
                       -- de la hora actual (para avisar con tiempo)
                       r.id       AS reserva_id,
                       r.hora     AS reserva_hora,
                       r.nombre   AS reserva_nombre,
                       r.comensales AS reserva_comensales

                FROM mesas m

                LEFT JOIN comandas c
                       ON c.mesa_id = m.id
                      AND c.estado IN ('abierta', 'por_cobrar')

                LEFT JOIN usuarios u ON u.id = c.vendedor_id

                LEFT JOIN reservas r
                       ON r.mesa_id = m.id
                      AND r.estado IN ('pendiente', 'confirmada')
                      AND r.fecha = CURDATE()
                      AND r.hora >= DATE_SUB(CURTIME(), INTERVAL 30 MINUTE)

                {$filtro}

                ORDER BY m.zona ASC, m.orden ASC, m.numero ASC";

        $mesas = DB::all($sql);

        // Se calcula el estado en PHP, no en SQL: es una regla de
        // negocio, no una consulta, y queda más legible acá.
        foreach ($mesas as &$mesa) {
            $mesa['estado'] = self::calcular_estado($mesa);
            $mesa['minutos_abierta'] = $mesa['abierta_en'] !== null
                ? (int) floor((time() - strtotime((string) $mesa['abierta_en'])) / 60)
                : 0;
            $mesa['hora_reserva'] = $mesa['reserva_hora'] !== null
                ? substr((string) $mesa['reserva_hora'], 0, 5)
                : null;
        }

        unset($mesa);

        return $mesas;
    }

    /**
     * Regla de estado de una mesa.
     *
     * El orden importa: si una mesa tiene mesa comanda abierta y
     * además una reserva más tarde, está OCUPADA. La reserva se
     * muestra recién cuando la mesa está libre.
     */
    private static function calcular_estado(array $mesa): string
    {
        // 1) Hay un pedido esperando el cobro: lo primero que hay
        //    que ver.
        if (($mesa['comanda_estado'] ?? null) === 'por_cobrar') {
            return 'por_cobrar';
        }

        // 2) Hay gente sentada consumiendo.
        if (($mesa['comanda_estado'] ?? null) === 'abierta') {
            return 'ocupada';
        }

        // 3) Está libre pero tiene reserva hoy.
        if (!empty($mesa['reserva_id'])) {
            return 'reservada';
        }

        return 'libre';
    }

    /**
     * Agrupa la grilla por zona, en el orden del Enum.
     */
    public static function agrupar_por_zona(array $mesas): array
    {
        $grupos = [];

        foreach (self::ZONAS as $clave => $etiqueta) {
            $grupos[$clave] = ['etiqueta' => $etiqueta, 'mesas' => []];

            foreach ($mesas as $mesa) {
                if ($mesa['zona'] === $clave) {
                    $grupos[$clave]['mesas'][] = $mesa;
                }
            }
        }

        return $grupos;
    }

    /**
     * Resumen para la barra superior de la grilla.
     */
    public static function resumen(array $mesas): array
    {
        $resumen = [
            'libre'      => 0,
            'ocupada'    => 0,
            'por_cobrar' => 0,
            'reservada'  => 0,
            'total'      => count($mesas),
        ];

        foreach ($mesas as $mesa) {
            $resumen[$mesa['estado']]++;
        }

        return $resumen;
    }

    /**
     * Datos de una mesa, con su comanda abierta si tiene.
     */
    public static function obtener_simple(int $id): ?array
    {
        $mesa = DB::one(
            'SELECT * FROM mesas WHERE id = :id',
            ['id' => $id]
        );

        if ($mesa === null) {
            return null;
        }

        // Comanda abierta, si hay
        $mesa['comanda'] = DB::one(
            "SELECT c.*, u.usuario AS vendedor
             FROM comandas c
             LEFT JOIN usuarios u ON u.id = c.vendedor_id
             WHERE c.mesa_id = :mesa AND c.estado IN ('abierta', 'por_cobrar')
             ORDER BY c.id DESC
             LIMIT 1",
            ['mesa' => $id]
        );

        return $mesa;
    }
}


/**
 * Clase Comanda: el pedido de una mesa.
 */
final class Comanda
{
    /**
     * Abre una mesa: crea la comanda y la deja con el vendedor
     * actual asignado.
     *
     * @return array{ok: bool, comanda_id: int, mensaje: string}
     */
    public static function abrir(int $mesaId, int $comensales, string $observacion = ''): array
    {
        $mesa = Mesas::obtener_simple($mesaId);

        if ($mesa === null) {
            return ['ok' => false, 'comanda_id' => 0, 'mensaje' => 'Esa mesa no existe.'];
        }

        if ((int) $mesa['activo'] !== 1) {
            return ['ok' => false, 'comanda_id' => 0, 'mensaje' => 'Esa mesa está dada de baja.'];
        }

        // Una mesa no puede tener dos comandas abiertas.
        if ($mesa['comanda'] !== null) {
            return [
                'ok'          => false,
                'comanda_id'  => (int) $mesa['comanda']['id'],
                'mensaje'     => 'Esa mesa ya está abierta.',
            ];
        }

        $comensales = max(1, $comensales);

        try {
            $comandaId = DB::insertar('comandas', [
                'numero'      => self::siguiente_numero(),
                'mesa_id'     => $mesaId,
                'estado'      => 'abierta',
                'comensales'  => $comensales,
                'vendedor_id' => Auth::id(),
                'observacion' => mb_substr($observacion, 0, 255),
                'abierta_en'  => ahora(),
                'creado_en'   => ahora(),
            ]);

            Auditoria::registrar('abrir_mesa', 'comandas', $comandaId, null, [
                'mesa'      => (int) $mesa['numero'],
                'comensales' => $comensales,
            ]);

            return ['ok' => true, 'comanda_id' => $comandaId, 'mensaje' => ''];
        } catch (Throwable $e) {
            error_log('Error abriendo mesa: ' . $e->getMessage());

            return ['ok' => false, 'comanda_id' => 0, 'mensaje' => 'No se pudo abrir la mesa.'];
        }
    }

    /**
     * Genera el número de comanda: C-000001, C-000002...
     *
     * Es correlativo global y no se reinicia por día: es más
     * simple y evita el problema de dos comandas con el mismo
     * número el mismo día.
     */
    private static function siguiente_numero(): string
    {
        $ultimo = DB::valor('SELECT numero FROM comandas ORDER BY id DESC LIMIT 1');
        $siguiente = 1;

        if ($ultimo !== null && preg_match('/(\d+)$/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        }

        return 'C-' . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Anota cuánto stock consume una línea, producto por producto.
     *
     * Se guarda POR UNIDAD y no el total: si después el mozo cambia
     * la cantidad de la línea, el ajuste de stock sale solo de acá
     * y no hay que volver a leer la receta (que para entonces
     * puede haber cambiado).
     */
    private static function anotar_stock(int $itemId, int $comandaId, int $productoId, float $porUnidad): void
    {
        DB::insertar('comanda_item_stock', [
            'item_id'             => $itemId,
            'comanda_id'          => $comandaId,
            'producto_id'         => $productoId,
            'cantidad_por_unidad' => round($porUnidad, 4),
            'devuelto'            => 0,
            'creado_en'           => ahora(),
        ]);
    }

    /**
     * Devuelve al depósito todo el stock que consumió una línea.
     *
     * Multiplica por la cantidad ACTUAL de la línea: si el mozo
     * cargó 3 cervezas, cambió a 5 y después a 2, hay que devolver
     * 2, que es lo que realmente salió del depósito. Usar el valor
     * con el que se creó la línea dejaría el stock desfasado.
     *
     * @return array<int, float> Producto_id => cantidad devuelta
     */
    private static function reponer_stock_item(int $itemId, string $motivo): array
    {
        $devuelto = [];

        $cantidadLinea = (float) DB::valor(
            'SELECT cantidad FROM comanda_items WHERE id = :id',
            ['id' => $itemId]
        );

        if ($cantidadLinea <= 0) {
            return $devuelto;
        }

        $filas = DB::all(
            'SELECT id, producto_id, cantidad_por_unidad
             FROM comanda_item_stock
             WHERE item_id = :item AND devuelto = 0',
            ['item' => $itemId]
        );

        foreach ($filas as $fila) {
            $cantidad = round((float) $fila['cantidad_por_unidad'] * $cantidadLinea, 4);

            if ($cantidad <= 0) {
                continue;
            }

            Stock::aplicar((int) $fila['producto_id'], 'anulacion_item', $cantidad, [
                'motivo'          => $motivo,
                'referencia_tipo' => 'comanda_item',
                'referencia_id'   => $itemId,
            ]);

            // Se marca la fila y no el producto: si una línea
            // tocara dos veces el mismo producto (poco probable,
            // pero posible), cada fila se repone una sola vez.
            DB::actualizar('comanda_item_stock', (int) $fila['id'], ['devuelto' => 1]);

            $devuelto[(int) $fila['producto_id']] = $cantidad;
        }

        return $devuelto;
    }

    /**
     * Agrega un producto al pedido.
     *
     * El precio y el nombre se COPIAN a la comanda. Si mañana subís
     * el precio de la cerveza, esta comanda tiene que seguir
     * mostrando lo que se cobró ese día.
     *
     * @return array{ok: bool, item_id: int, mensaje: string, stock_bajo: ?array}
     */
    public static function agregar_item(int $comandaId, int $productoId, float $cantidad): array
    {
        $error = ['ok' => false, 'item_id' => 0, 'mensaje' => '', 'stock_bajo' => null];

        if ($cantidad <= 0) {
            return $error + ['mensaje' => 'La cantidad tiene que ser mayor a cero.'];
        }

        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] !== 'abierta') {
            return $error + [
                'mensaje' => $comanda['estado'] === 'por_cobrar'
                    ? 'Este pedido ya pidió la cuenta. No se le pueden agregar cosas.'
                    : 'Este pedido ya está cerrado.',
            ];
        }

        // Se pide "costo" aunque el vendedor no lo pueda ver nunca.
        // La consulta corre en el servidor y el valor se guarda en
        // la linea de la comanda: de ahi salen las ganancias de los
        // reportes (Etapa 5). Si no se pidiera acá, quedaria en
        // NULL y ningun reporte podria calcular la ganancia.
        $producto = DB::one(
            'SELECT id, nombre, precio_venta, costo, controla_stock, stock_actual,
                    stock_minimo, unidad, es_combo, activo
             FROM productos
             WHERE id = :id',
            ['id' => $productoId]
        );

        if ($producto === null) {
            return $error + ['mensaje' => 'Ese producto no existe.'];
        }

        if ((int) $producto['activo'] !== 1) {
            return $error + ['mensaje' => 'Ese producto ya no está en la carta.'];
        }

        $importe = round((float) $producto['precio_venta'] * $cantidad, 2);
        $avisos  = [];

        try {
            $itemId = DB::enTransaccion(function () use ($comandaId, $producto, $cantidad, $importe, &$avisos) {
                // --- Stock ---
                // Se descuenta acá, al agregar, no al cobrar. En un
                // bar importa saber en el momento si quedan
                // botellas, no a la noche.
                //
                // Cada descuento se anota en comanda_item_stock con
                // lo que consume POR UNIDAD. Así, al anular, se
                // devuelve exactamente lo que se sacó, incluso si
                // la receta del combo cambió al día siguiente.
                if ((int) $producto['controla_stock'] === 1) {
                    $movimiento = Stock::aplicar((int) $producto['id'], 'mesa_consumo', -$cantidad, [
                        'motivo'          => 'Consumo en mesa',
                        'referencia_tipo' => 'comanda_item',
                        'usuario_id'      => Auth::id(),
                    ]);

                    if (!$movimiento['ok']) {
                        throw new RuntimeException($movimiento['mensaje']);
                    }

                    // Aviso: el stock está por debajo del mínimo.
                    // No es un error: el bar puede vender igual, pero
                    // hay que saberlo.
                    if ($movimiento['stock_resultante'] <= (float) $producto['stock_minimo']) {
                        $avisos[] = 'Quedan ' . cantidad($movimiento['stock_resultante']) . ' '
                            . plural((string) $producto['unidad'], $movimiento['stock_resultante'])
                            . ' de ' . $producto['nombre'] . '.';
                    }

                    if ($movimiento['mensaje'] !== '') {
                        $avisos[] = $movimiento['mensaje'];
                    }
                }

                // --- Insumos del combo ---
                // Un gin tonic descuenta tónica y gin. Si el combo
                // no tiene receta, no descuenta nada.
                $porUnidad = [];

                if ((int) $producto['controla_stock'] === 1) {
                    $porUnidad[(int) $producto['id']] = 1.0;
                }

                if ((int) $producto['es_combo'] === 1) {
                    foreach (Stock::receta((int) $producto['id']) as $insumo) {
                        $aDescontar = round((float) $insumo['cantidad'] * $cantidad, 4);

                        Stock::aplicar((int) $insumo['componente_id'], 'mesa_consumo', -$aDescontar, [
                            'motivo'          => 'Consumo en mesa (insumo de ' . $producto['nombre'] . ')',
                            'referencia_tipo' => 'comanda_item',
                            'usuario_id'      => Auth::id(),
                        ]);

                        $porUnidad[(int) $insumo['componente_id']] = (float) $insumo['cantidad'];
                    }
                }

                // --- La línea ---
                $nuevoItemId = DB::insertar('comanda_items', [
                    'comanda_id'      => $comandaId,
                    'producto_id'     => $producto['id'],
                    'producto_nombre' => $producto['nombre'],
                    'precio_unitario' => $producto['precio_venta'],

                    // El costo tambien se guarda, y no se busca
                    // cuando se arma el reporte (Etapa 5). Si manana
                    // el costo del fernet sube, el reporte de hoy tiene
                    // que seguir hablando de hoy.
                    //
                    // Puede quedar en NULL a proposito: NULL quiere
                    // decir "no se sabe el costo", que es distinto de
                    // cero. El reporte avisa en vez de mostrar una
                    // ganancia inflada.
                    'costo_unitario'  => $producto['costo'],
                    'cantidad'        => $cantidad,
                    'importe'         => $importe,
                    'estado'          => 'pendiente',
                    'usuario_id'      => Auth::id(),
                    'creado_en'       => ahora(),
                ]);

                // --- Qué stock se tocó, y cuánto por unidad ---
                foreach ($porUnidad as $productoId => $porCadaUna) {
                    self::anotar_stock($nuevoItemId, $comandaId, $productoId, $porCadaUna);
                }

                return $nuevoItemId;
            });
        } catch (Throwable $e) {
            error_log('Error agregando ítem a la comanda: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo agregar el producto: ' . $e->getMessage()];
        }

        return [
            'ok'         => true,
            'item_id'    => $itemId,
            'mensaje'    => '',
            'stock_bajo' => $avisos,
        ];
    }

    /**
     * Anula una línea del pedido y repone el stock que había
     * descontado.
     *
     * La reposición NO es borrar la línea: la línea queda marcada
     * como anulada, con su motivo. Así se puede ver después qué se
     * pidió, qué se sacó y quién lo hizo.
     *
     * Solo se puede con el pedido ABIERTO. Si ya pidió la cuenta,
     * primero hay que reabrirlo (Comanda::reabrir), así queda
     * asentado que se volvió atrás.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public static function anular_item(int $comandaId, int $itemId, string $motivo = ''): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] !== 'abierta') {
            return $error + [
                'mensaje' => $comanda['estado'] === 'por_cobrar'
                    ? 'Este pedido ya pidió la cuenta. Reabrilo si hay que corregir algo.'
                    : 'Este pedido ya está cerrado.',
            ];
        }

        $item = DB::one(
            'SELECT * FROM comanda_items WHERE id = :id AND comanda_id = :comanda',
            ['id' => $itemId, 'comanda' => $comandaId]
        );

        if ($item === null) {
            return $error + ['mensaje' => 'Ese producto no está en este pedido.'];
        }

        if ($item['estado'] === 'anulado') {
            return $error + ['mensaje' => 'Ese producto ya estaba anulado.'];
        }

        try {
            DB::enTransaccion(function () use ($item, $motivo) {
                // Reponer TODO lo que esta línea sacó, producto por
                // producto (un combo toca varios).
                self::reponer_stock_item(
                    (int) $item['id'],
                    'Se anuló ' . $item['producto_nombre']
                        . ($motivo !== '' ? ' (' . $motivo . ')' : '')
                );

                DB::actualizar('comanda_items', (int) $item['id'], [
                    'estado'           => 'anulado',
                    'motivo_anulacion' => mb_substr($motivo, 0, 255),
                ]);
            });
        } catch (Throwable $e) {
            error_log('Error anulando ítem: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo anular el producto.'];
        }

        Auditoria::registrar('anular_item_comanda', 'comandas', $comandaId, null, [
            'producto' => (string) $item['producto_nombre'],
            'motivo'   => $motivo,
        ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Cambia la cantidad de una línea.
     *
     * El stock sigue a la cantidad: si sube, se descuenta la
     * diferencia; si baja, se repone. El ajuste sale de lo que la
     * línea consume por unidad (tabla comanda_item_stock), así que
     * un combo ajusta todos sus insumos, no solo el producto.
     */
    public static function cambiar_cantidad(int $comandaId, int $itemId, float $cantidadNueva): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        if ($cantidadNueva <= 0) {
            return $error + ['mensaje' => 'Para sacar un producto del pedido hay que anularlo.'];
        }

        $item = DB::one(
            'SELECT ci.*, c.estado AS comanda_estado
             FROM comanda_items ci
             INNER JOIN comandas c ON c.id = ci.comanda_id
             WHERE ci.id = :id AND ci.comanda_id = :comanda',
            ['id' => $itemId, 'comanda' => $comandaId]
        );

        if ($item === null) {
            return $error + ['mensaje' => 'Ese producto no está en este pedido.'];
        }

        if ($item['estado'] === 'anulado') {
            return $error + ['mensaje' => 'Ese producto ya estaba anulado.'];
        }

        if ($item['comanda_estado'] !== 'abierta') {
            return $error + ['mensaje' => 'Este pedido ya no admite cambios.'];
        }

        $cantidadVieja = (float) $item['cantidad'];
        $diferencia    = round($cantidadNueva - $cantidadVieja, 4);

        if (abs($diferencia) < 0.0001) {
            return ['ok' => true, 'mensaje' => ''];
        }

        $explicacion = 'Cambio de cantidad en ' . $item['producto_nombre']
            . ': de ' . cantidad($cantidadVieja) . ' a ' . cantidad($cantidadNueva);

        try {
            DB::enTransaccion(function () use ($item, $diferencia, $cantidadNueva, $explicacion) {
                // El ajuste se hace producto por producto: por cada
                // cosa que consume la línea, la diferencia exacta.
                $filas = DB::all(
                    'SELECT id, producto_id, cantidad_por_unidad
                     FROM comanda_item_stock
                     WHERE item_id = :item',
                    ['item' => (int) $item['id']]
                );

                foreach ($filas as $fila) {
                    $movimiento = round((float) $fila['cantidad_por_unidad'] * $diferencia, 4);

                    if (abs($movimiento) < 0.0001) {
                        continue;
                    }

                    // Positivo = entra stock, negativo = sale.
                    $aplicado = Stock::aplicar(
                        (int) $fila['producto_id'],
                        'ajuste',
                        -$movimiento,
                        [
                            'motivo'          => $explicacion,
                            'referencia_tipo' => 'comanda_item',
                            'referencia_id'   => (int) $item['id'],
                        ]
                    );

                    if (!$aplicado['ok']) {
                        throw new RuntimeException($aplicado['mensaje']);
                    }
                }

                DB::actualizar('comanda_items', (int) $item['id'], [
                    'cantidad' => $cantidadNueva,
                    'importe'  => round((float) $item['precio_unitario'] * $cantidadNueva, 2),
                ]);
            });
        } catch (Throwable $e) {
            error_log('Error cambiando cantidad: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo cambiar la cantidad.'];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Marca el pedido como "por cobrar".
     *
     * A partir de acá el pedido queda CONGELADO: no se le pueden
     * agregar productos ni cambiar cantidades. Si el mozo se da
     * cuenta de que faltaba algo, reabre el pedido con
     * Comanda::reabrir(), que queda asentado en la auditoría.
     */
    public static function marcar_por_cobrar(int $comandaId): array
    {
        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return ['ok' => false, 'mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] === 'por_cobrar') {
            return ['ok' => false, 'mensaje' => 'Este pedido ya estaba marcado como por cobrar.'];
        }

        if ($comanda['estado'] !== 'abierta') {
            return ['ok' => false, 'mensaje' => 'Este pedido no está abierto.'];
        }

        // Un pedido sin nada cargado no se cobra.
        $items = (int) DB::valor(
            "SELECT COUNT(*) FROM comanda_items WHERE comanda_id = :comanda AND estado = 'pendiente'",
            ['comanda' => $comandaId]
        );

        if ($items === 0) {
            return ['ok' => false, 'mensaje' => 'El pedido está vacío: no hay nada que cobrar.'];
        }

        DB::actualizar('comandas', $comandaId, [
            'estado' => 'por_cobrar',
        ]);

        Auditoria::registrar('marcar_por_cobrar', 'comandas', $comandaId, null, null);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Vuelve a abrir un pedido que ya había pedido la cuenta.
     *
     * Se usa cuando el cliente agrega algo después de pedir la
     * cuenta, o cuando el mozo se equivocó al cerrarlo. El stock
     * no se toca: ya estaba descontado cuando se cargó.
     *
     * Queda asentado en la auditoría quién lo reabrió y por qué.
     */
    public static function reabrir(int $comandaId, string $motivo = ''): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] !== 'por_cobrar') {
            return $error + [
                'mensaje' => $comanda['estado'] === 'abierta'
                    ? 'Este pedido ya está abierto.'
                    : 'Este pedido ya está cerrado.',
            ];
        }

        if ($motivo === '') {
            return $error + ['mensaje' => 'Contá por qué se reabre el pedido.'];
        }

        DB::actualizar('comandas', $comandaId, [
            'estado' => 'abierta',
        ]);

        // El descuento se olvida: se estaba cobrando sobre un
        // pedido congelado, y al reabrir se vuelve a empezar.
        descuento_olvidar($comandaId);

        Auditoria::registrar('reabrir_comanda', 'comandas', $comandaId,
            ['estado' => 'por_cobrar'], [
                'estado' => 'abierta',
                'motivo' => $motivo,
            ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Cancela una mesa sin cobrar.
     *
     * SOLO ADMINISTRADOR. Devuelve todo el stock que se había
     * descontado, porque la mesa se va a levantar sin haber
     * consumido nada.
     */
    public static function anular(int $comandaId, string $motivo = ''): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] !== 'abierta' && $comanda['estado'] !== 'por_cobrar') {
            return $error + ['mensaje' => 'Este pedido ya está cerrado.'];
        }

        if ($motivo === '') {
            return $error + ['mensaje' => 'Contá por qué se cancela la mesa.'];
        }

        try {
            self::anular_cuerpo($comandaId, $motivo);
        } catch (Throwable $e) {
            error_log('Error anulando mesa: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo anular la mesa.'];
        }

        Auditoria::registrar('anular_mesa', 'comandas', $comandaId, null, [
            'motivo' => $motivo,
        ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Da de baja una comanda y devuelve todo su stock.
     *
     * Es el cuerpo que comparten anular() (mesa sin cobrar) y
     * anular_tras_anular_venta() (el administrador devolvió una
     * venta). Se separa porque las dos cosas tienen el mismo efecto
     * sobre el stock y la comanda, y no tiene por qué estar
     * duplicado.
     *
     * @throws Throwable Si algo falla, para que la transacción de
     *                   arriba se revierta entera.
     */
    private static function anular_cuerpo(int $comandaId, string $motivo): void
    {
        // Reponer todo lo consumido, línea por línea.
        $items = DB::all(
            "SELECT * FROM comanda_items
             WHERE comanda_id = :comanda AND estado = 'pendiente'",
            ['comanda' => $comandaId]
        );

        foreach ($items as $item) {
            // Reponer todo lo que sacó esta línea: si es un combo,
            // son varios productos.
            self::reponer_stock_item(
                (int) $item['id'],
                'Mesa anulada: ' . $motivo
            );

            DB::actualizar('comanda_items', (int) $item['id'], [
                'estado'           => 'anulado',
                'motivo_anulacion' => mb_substr('Mesa anulada: ' . $motivo, 0, 255),
            ]);
        }

        DB::actualizar('comandas', $comandaId, [
            'estado'            => 'anulada',
            'usuario_cierre_id' => Auth::id(),
            'cerrada_en'        => ahora(),
        ]);

        // La mesa no se va a cobrar, asi que el descuento pendiente
        // se olvida.
        descuento_olvidar($comandaId);
    }

    /**
     * Anula la comanda porque su venta fue anulada.
     *
     * Lo llama Venta::anular(). El stock vuelve porque los productos
     * ya salieron del depósito cuando se cargaron al pedido, y la
     * comanda queda anulada para que no quede un pedido
     * cobrado dando vueltas por la grilla.
     *
     * No pide motivo ni revisa el rol: eso lo hace Venta::anular(),
     * que es el que sabe por qué se está anulando.
     */
    public static function anular_tras_anular_venta(int $comandaId, string $motivo): void
    {
        self::anular_cuerpo($comandaId, $motivo);
    }

    /**
     * Mueve el pedido a otra mesa.
     *
     * Se usa cuando una mesa se mancha, la quieren otra o llega
     * más gente y hay que juntarlas. El stock no se toca: el
     * consumo ya está registrado y no tiene por qué depender de
     * en qué mesa se pidió.
     */
    public static function mover(int $comandaId, int $mesaDestinoId): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        $comanda = self::obtener($comandaId);

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] !== 'abierta') {
            return $error + ['mensaje' => 'Solo se puede mover un pedido abierto.'];
        }

        if ((int) $comanda['mesa_id'] === $mesaDestinoId) {
            return $error + ['mensaje' => 'El pedido ya está en esa mesa.'];
        }

        $destino = Mesas::obtener_simple($mesaDestinoId);

        if ($destino === null || (int) $destino['activo'] !== 1) {
            return $error + ['mensaje' => 'La mesa de destino no está disponible.'];
        }

        // La mesa de destino tiene que estar libre.
        if ($destino['comanda'] !== null) {
            return $error + [
                'mensaje' => 'La mesa ' . $destino['numero'] . ' ya está ocupada.',
            ];
        }

        $origen = Mesas::obtener_simple((int) $comanda['mesa_id']);
        $numeroOrigen = $origen['numero'] ?? '?';
        $numeroDestino = $destino['numero'];

        try {
            DB::actualizar('comandas', $comandaId, ['mesa_id' => $mesaDestinoId]);
        } catch (Throwable $e) {
            error_log('Error moviendo mesa: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo mover el pedido.'];
        }

        Auditoria::registrar('mover_mesa', 'comandas', $comandaId, [
            'mesa' => $numeroOrigen,
        ], [
            'mesa' => $numeroDestino,
        ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Agrega un producto a una mesa en un solo paso: si la mesa no
     * está abierta, la abre.
     *
     * Es el atajo que usa el botón de la grilla: el vendedor
     * elige mesa y producto sin tener que abrir primero.
     */
    public static function agregar_a_mesa(int $mesaId, int $productoId, float $cantidad, int $comensales = 1): array
    {
        $mesa = Mesas::obtener_simple($mesaId);

        if ($mesa === null) {
            return ['ok' => false, 'item_id' => 0, 'mensaje' => 'Esa mesa no existe.'];
        }

        if ($mesa['comanda'] !== null) {
            return self::agregar_item((int) $mesa['comanda']['id'], $productoId, $cantidad);
        }

        // No estaba abierta: se abre y se agrega.
        $apertura = self::abrir($mesaId, $comensales);

        if (!$apertura['ok']) {
            return ['ok' => false, 'item_id' => 0, 'mensaje' => $apertura['mensaje']];
        }

        $resultado = self::agregar_item($apertura['comanda_id'], $productoId, $cantidad);

        return $resultado + ['comanda_id' => $apertura['comanda_id']];
    }

    // ------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------

    /**
     * Datos de una comanda con sus ítems.
     */
    public static function obtener(int $comandaId): ?array
    {
        $comanda = DB::one(
            'SELECT c.*, m.numero AS mesa_numero, m.nombre AS mesa_nombre,
                    u.usuario AS vendedor, uc.usuario AS cerrado_por
             FROM comandas c
             LEFT JOIN mesas m ON m.id = c.mesa_id
             LEFT JOIN usuarios u ON u.id = c.vendedor_id
             LEFT JOIN usuarios uc ON uc.id = c.usuario_cierre_id
             WHERE c.id = :id',
            ['id' => $comandaId]
        );

        if ($comanda === null) {
            return null;
        }

        $comanda['items'] = self::items($comandaId);

        return $comanda;
    }

    /**
     * Las líneas de una comanda.
     */
    public static function items(int $comandaId): array
    {
        return DB::all(
            'SELECT * FROM comanda_items
             WHERE comanda_id = :comanda
             ORDER BY estado ASC, id ASC',
            ['comanda' => $comandaId]
        );
    }

    /**
     * El total de una comanda (solo ítems pendientes).
     */
    public static function total(int $comandaId): float
    {
        return (float) DB::valor(
            "SELECT COALESCE(SUM(importe), 0) FROM comanda_items
             WHERE comanda_id = :comanda AND estado = 'pendiente'",
            ['comanda' => $comandaId]
        );
    }

    /**
     * Comandas abiertas ahora mismo, para el resumen.
     */
    public static function abiertas(?int $limite = null): array
    {
        $sql = "SELECT c.id, c.numero, c.estado, c.comensales, c.abierta_en,
                       m.numero AS mesa_numero, u.usuario AS vendedor,
                       COALESCE((SELECT SUM(importe) FROM comanda_items ci
                                 WHERE ci.comanda_id = c.id AND ci.estado = 'pendiente'), 0) AS total
                FROM comandas c
                LEFT JOIN mesas m ON m.id = c.mesa_id
                LEFT JOIN usuarios u ON u.id = c.vendedor_id
                WHERE c.estado IN ('abierta', 'por_cobrar')
                ORDER BY c.abierta_en ASC";

        if ($limite !== null) {
            $limite = max(1, min(200, $limite));
            $sql .= " LIMIT {$limite}";
        }

        return DB::all($sql);
    }
}


/**
 * Clase Reserva: las reservas futuras de una mesa.
 *
 * La Etapa 6 va a mostrar estas reservas en la página pública,
 * por eso desde ahora se guardan el nombre y el teléfono.
 */
final class Reserva
{
    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'confirmada'  => 'Confirmada',
        'cancelada'   => 'Cancelada',
        'no_show'     => 'No vino',
    ];

    /**
     * Crea una reserva.
     */
    public static function crear(array $datos): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        $mesaId     = (int) ($datos['mesa_id'] ?? 0);
        $comensales = max(1, (int) ($datos['comensales'] ?? 2));
        $nombre     = mb_substr((string) ($datos['nombre'] ?? ''), 0, 120);
        $telefono   = mb_substr((string) ($datos['telefono'] ?? ''), 0, 40);
        $notas      = mb_substr((string) ($datos['notas'] ?? ''), 0, 255);
        $fecha      = (string) ($datos['fecha'] ?? '');
        $hora       = (string) ($datos['hora'] ?? '');

        if ($mesaId <= 0) {
            return $error + ['mensaje' => 'Elegí una mesa.'];
        }

        if ($nombre === '') {
            return $error + ['mensaje' => 'Anotá a nombre de quién queda la reserva.'];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $error + ['mensaje' => 'La fecha no tiene el formato correcto.'];
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            return $error + ['mensaje' => 'La hora no tiene el formato correcto.'];
        }

        $mesa = Mesas::obtener_simple($mesaId);

        if ($mesa === null || (int) $mesa['activo'] !== 1) {
            return $error + ['mensaje' => 'Esa mesa no está disponible.'];
        }

        // No se puede reservar más gente que la mesa banca.
        if ($comensales > (int) $mesa['capacidad']) {
            return $error + [
                'mensaje' => 'La mesa ' . $mesa['numero'] . ' banca '
                    . $mesa['capacidad'] . ' persona(s), y pediste ' . $comensales . '.',
            ];
        }

        // Una mesa no puede tener dos reservas en el mismo horario.
        $ocupada = DB::valor(
            "SELECT id FROM reservas
             WHERE mesa_id = :mesa AND fecha = :fecha AND hora = :hora
               AND estado IN ('pendiente', 'confirmada')",
            ['mesa' => $mesaId, 'fecha' => $fecha, 'hora' => $hora]
        );

        if ($ocupada) {
            return $error + ['mensaje' => 'Esa mesa ya está reservada a esa hora.'];
        }

        try {
            $id = DB::insertar('reservas', [
                'mesa_id'    => $mesaId,
                'fecha'      => $fecha,
                'hora'       => strlen($hora) === 5 ? $hora . ':00' : $hora,
                'comensales' => $comensales,
                'nombre'     => $nombre,
                'telefono'   => $telefono,
                'notas'      => $notas,
                'estado'     => $datos['estado'] ?? 'pendiente',
                'usuario_id' => Auth::id(),
                'creado_en'  => ahora(),
            ]);

            Auditoria::registrar('crear_reserva', 'reservas', $id, null, [
                'mesa' => (string) $mesa['numero'],
                'fecha' => $fecha,
                'hora'  => $hora,
            ]);

            return ['ok' => true, 'mensaje' => '', 'reserva_id' => $id];
        } catch (Throwable $e) {
            error_log('Error creando reserva: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo guardar la reserva.'];
        }
    }

    /**
     * Cambia el estado de una reserva (confirmar, cancelar...).
     */
    public static function cambiar_estado(int $reservaId, string $estado): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        if (!array_key_exists($estado, self::ESTADOS)) {
            return $error + ['mensaje' => 'Ese estado de reserva no existe.'];
        }

        $reserva = DB::one('SELECT id FROM reservas WHERE id = :id', ['id' => $reservaId]);

        if ($reserva === null) {
            return $error + ['mensaje' => 'Esa reserva no existe.'];
        }

        DB::actualizar('reservas', $reservaId, [
            'estado'         => $estado,
            'actualizado_en' => ahora(),
        ]);

        Auditoria::registrar('cambiar_reserva', 'reservas', $reservaId, null, [
            'estado' => $estado,
        ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Reservas de un rango de fechas.
     */
    public static function listar(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT r.*, m.numero AS mesa_numero, m.capacidad, m.zona
             FROM reservas r
             INNER JOIN mesas m ON m.id = r.mesa_id
             WHERE r.fecha BETWEEN :desde AND :hasta
               AND r.estado IN ('pendiente', 'confirmada')
             ORDER BY r.fecha ASC, r.hora ASC",
            ['desde' => $desde, 'hasta' => $hasta]
        );
    }

    /**
     * Reservas de hoy, para la grilla y el resumen.
     */
    public static function hoy(): array
    {
        return DB::all(
            "SELECT r.*, m.numero AS mesa_numero
             FROM reservas r
             INNER JOIN mesas m ON m.id = r.mesa_id
             WHERE r.fecha = CURDATE()
               AND r.estado IN ('pendiente', 'confirmada')
             ORDER BY r.hora ASC",
            []
        );
    }
}