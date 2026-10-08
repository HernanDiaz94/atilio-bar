<?php
/**
 * includes/reportes.php
 * -----------------------------------------------------------------
 * Los reportes de la Etapa 5: ventas por día, qué se vende más, por
 * mesa y zona, y por medio de pago.
 *
 * SON SOLO DEL ADMINISTRADOR
 *
 * No por desconfianza sino porque estos números llevan plata: lo que
 * entra, lo que sale y cuánto queda. Un vendedor necesita saber
 * cuánto cobró, y eso lo ve en la caja; el margen y el costo no son
 * suyos. Por eso las cuatro consultas viven en un archivo que solo
 * carga un administrador, y no hay forma de llegar por la URL.
 *
 * LO QUE ESTÁ ADENTRO DE CADA REPORTE, Y POR QUÉ
 *
 * 1) NUNCA CUENTAN LAS VENTAS ANULADAS.
 *
 *    Una venta anulada fue cobrada y devuelta. Sumarla sería mostrar
 *    plata que no está. Todas las consultas filtran
 *    `estado = 'cobrada'`.
 *
 * 2) EL COSTO ES EL DEL MOMENTO DE LA VENTA.
 *
 *    No se va a buscar al producto actual. Si el costo del fernet
 *    sube en marzo, el reporte de febrero tiene que seguir hablando
 *    de febrero. Por eso el costo se lee de `comanda_items`, que lo
 *    guardó cuando el producto se cargó a la mesa.
 *
 * 3) UN COSTO DESCONOCIDO NO ES CERO.
 *
 *    `productos.costo` puede estar vacío (el dueño todavía no lo
 *    cargó). Si eso se tratara como cero, la ganancia saldría
 *    inflada: "vendí $10.000 y me quedaron $10.000", lo cual es
 *    mentira. Acá `NULL` se cuenta aparte y el reporte avisa que la
 *    ganancia que muestra es parcial.
 *
 * 4) SIN TABLAS NUEVAS.
 *
 *    Un reporte es una pregunta sobre datos que ya existen, no un
 *    dato nuevo. `sql/etapa5.sql` solo agrega la columna del costo,
 *    dos índices y dos claves de configuración.
 * -----------------------------------------------------------------
 */

final class Reportes
{
    /**
     * Los cuatro reportes que existen. La vista y el CSV leen de acá,
     * así que no pueden quedar desincronizados.
     *
     * @return array<string, array{etiqueta: string, icono: string, csv: string}>
     */
    public static function vistas(): array
    {
        return [
            'dia' => [
                'etiqueta' => 'Por día',
                'icono'    => 'bi-calendar-week',
                'csv'      => 'Ventas por dia',
            ],
            'productos' => [
                'etiqueta' => 'Qué se vende',
                'icono'    => 'bi-cup-hot',
                'csv'      => 'Productos mas vendidos',
            ],
            'mesas' => [
                'etiqueta' => 'Mesas y zonas',
                'icono'    => 'bi-grid-3x3-gap',
                'csv'      => 'Ventas por mesa',
            ],
            'pagos' => [
                'etiqueta' => 'Medios de pago',
                'icono'    => 'bi-credit-card',
                'csv'      => 'Ventas por medio de pago',
            ],
        ];
    }

    /**
     * Cuántos días se pueden mirar como mucho.
     *
     * ByetHost es gratis y la base está compartida con otros sitios.
     * Un reporte sobre cinco años de ventas puede tardar tanto que el
     * navegador se rinda y el dueño piense que se colgó.
     */
    public static function maxDias(): int
    {
        $crudo = config_valor('reporte_max_dias', '366');

        return max(1, (int) $crudo);
    }

    /**
     * Desde qué fecha el costo guardado es el real.
     *
     * Antes de aplicar la Etapa 5, las líneas no guardaban costo: se
     * rellenaron con el que el producto tiene hoy. El reporte usa
     * esta fecha para decirlo con todas las letras en vez de
     * mostrar una ganancia aproximada como si fuera exacta.
     */
    public static function costoRealDesde(): ?string
    {
        $crudo = trim((string) config_valor('reporte_costo_real_desde', ''));

        if ($crudo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $crudo)) {
            return null;
        }

        return $crudo;
    }

    /**
     * Los períodos con botón.
     *
     * Se calculan con la zona horaria de la aplicación (ya fijada en
     * el arranque), así que "hoy" es el hoy del bar y no el del
     * servidor de MySQL, que puede estar en otra parte del mundo.
     *
     * @return array<string, array{etiqueta: string, desde: string, hasta: string}>
     */
    public static function periodos(): array
    {
        $hoy   = new DateTimeImmutable('today');
        $lunes = $hoy->modify('monday this week');

        // Si hoy es domingo, "monday this week" trae el lunes
        // siguiente. Para una semana que va de lunes a domingo
        // empieza en el lunes que realmente empezó.
        if ($lunes > $hoy) {
            $lunes = $lunes->modify('-7 days');
        }

        $mes        = $hoy->modify('first day of this month');
        $mesPunto   = $mes->modify('-1 month');
        $mesPuntoF  = $mesPunto->modify('first day of this month');
        $finMesPunt = $mesPunto->modify('last day of this month');

        return [
            'hoy' => [
                'etiqueta' => 'Hoy',
                'desde'    => $hoy->format('Y-m-d'),
                'hasta'    => $hoy->format('Y-m-d'),
            ],
            'ayer' => [
                'etiqueta' => 'Ayer',
                'desde'    => $hoy->modify('-1 day')->format('Y-m-d'),
                'hasta'    => $hoy->modify('-1 day')->format('Y-m-d'),
            ],
            'semana' => [
                'etiqueta' => 'Esta semana',
                'desde'    => $lunes->format('Y-m-d'),
                'hasta'    => $hoy->format('Y-m-d'),
            ],
            'mes' => [
                'etiqueta' => 'Este mes',
                'desde'    => $mes->format('Y-m-d'),
                'hasta'    => $hoy->format('Y-m-d'),
            ],
            'mes_anterior' => [
                'etiqueta' => 'Mes anterior',
                'desde'    => $mesPuntoF->format('Y-m-d'),
                'hasta'    => $finMesPunt->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Deja el período pedido en condiciones de usarse.
     *
     * Hace tres cosas, y las tres importan:
     *   - si las fechas no sirven, usa el mes en vez de romperse
     *   - si vienen al revés, las ordena (nadie busca "del 10 al 3")
     *   - si el rango es enorme, lo recorta al máximo y avisa
     *
     * @return array{
     *     desde: string, hasta: string, dias: int, periodo: string,
     *     ajustado: bool, avisos: array<int, string>
     * }
     */
    public static function rango(?string $desde, ?string $hasta, string $periodo = ''): array
    {
        $avisos = [];

        $periodos = self::periodos();
        $maxDias  = self::maxDias();

        // --- Botón de período ---
        if ($periodo !== '' && isset($periodos[$periodo])) {
            $desde = $periodos[$periodo]['desde'];
            $hasta = $periodos[$periodo]['hasta'];
        }

        // --- Fechas escritas a mano ---
        $desdeOk = self::fechaValida($desde);
        $hastaOk = self::fechaValida($hasta);

        if (!$desdeOk || !$hastaOk) {
            $porDefecto = $periodos['mes'];

            if ($desde !== null && $desde !== '' && !$desdeOk) {
                $avisos[] = 'La fecha de inicio no era válida, así que se usó el mes.';
            }

            if ($hasta !== null && $hasta !== '' && !$hastaOk) {
                $avisos[] = 'La fecha de fin no era válida, así que se usó el mes.';
            }

            $desde = $desdeOk ? $desde : $porDefecto['desde'];
            $hasta = $hastaOk ? $hasta : $porDefecto['hasta'];
            $periodo = 'mes';
        }

        // --- Al revés ---
        if ($desde > $hasta) {
            $cambio = $desde;
            $desde  = $hasta;
            $hasta  = $cambio;

            $avisos[] = 'El período estaba al revés (del ' . $desde . ' al ' . $hasta . '), así que se dio vuelta.';

            $periodo = '';
        }

        // --- Cuántos días ---
        $dias = self::diasEntre($desde, $hasta) + 1;

        if ($dias > $maxDias) {
            $desde = (new DateTimeImmutable($desde))->format('Y-m-d');
            $hasta = (new DateTimeImmutable($desde))->modify('+' . ($maxDias - 1) . ' days')->format('Y-m-d');

            $avisos[] = 'Se miraron los últimos ' . $maxDias . ' días y no más: '
                . 'este hosting es lento y con más años la página se demora demasiado. '
                . 'Para ver más, mirá por partes.';

            $dias    = $maxDias;
            $periodo = '';
        }

        return [
            'desde'    => $desde,
            'hasta'    => $hasta,
            'dias'     => $dias,
            'periodo'  => $periodo,
            'ajustado' => $avisos !== [],
            'avisos'   => $avisos,
        ];
    }

    /**
     * ¿Es una fecha que se puede usar en una consulta?
     */
    private static function fechaValida(?string $fecha): bool
    {
        if ($fecha === null || $fecha === '') {
            return false;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }

        // Comprobación real: "2026-02-31" tiene forma de fecha pero no
        // existe, y MySQL la convertiría en 3 de marzo sin avisar.
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Días entre dos fechas, sin contar horas.
     */
    private static function diasEntre(string $desde, string $hasta): int
    {
        $a = new DateTimeImmutable($desde);
        $b = new DateTimeImmutable($hasta);

        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }

        return (int) $a->diff($b)->days;
    }

    /**
     * El rango en los dos formatos que necesitan las consultas.
     *
     * Se usa `>= desde 00:00:00` y `< (hasta+1) 00:00:00` en vez de
     * `BETWEEN`: es la única forma de que un cobro hecho a las 23:59
     * no se quede afuera por una cuestión de segundos.
     *
     * @return array{desde: string, hasta: string}
     */
    private static function limites(array $rango): array
    {
        $fin = new DateTimeImmutable($rango['hasta']);

        return [
            'desde' => $rango['desde'] . ' 00:00:00',
            'hasta' => $fin->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
    }

    /**
     * Los totales del período, para el cartel de arriba.
     *
     * @return array{
     *     ventas: int, comensales: int, bruto: float, descuentos: float,
     *     neto: float, costo: float, ganancia: float, porcentaje: float|null,
     *     ventas_sin_costo: int
     * }
     */
    public static function resumen(array $rango): array
    {
        $lim = self::limites($rango);

        $sql = 'SELECT
                    COUNT(*)                                        AS ventas,
                    COALESCE(SUM(v.comensales), 0)                   AS comensales,
                    COALESCE(SUM(v.total_bruto), 0)                  AS bruto,
                    COALESCE(SUM(v.descuento_importe), 0)            AS descuentos,
                    COALESCE(SUM(v.total_neto), 0)                   AS neto,
                    COALESCE(SUM(c.costo), 0)                        AS costo,
                    COALESCE(SUM(CASE WHEN c.costo IS NULL THEN 1 ELSE 0 END), 0) AS ventas_sin_costo
               FROM ventas v
               JOIN comandas cd ON cd.id = v.comanda_id
               LEFT JOIN (
                    SELECT ci.comanda_id,
                           SUM(ci.costo_unitario * ci.cantidad) AS costo
                      FROM comanda_items ci
                      JOIN ventas v2 ON v2.comanda_id = ci.comanda_id
                     WHERE ci.estado = :estado
                       AND v2.estado = :estado2
                       AND v2.cobrada_en >= :desdeA
                       AND v2.cobrada_en <  :hastaA
                     GROUP BY ci.comanda_id
               ) c ON c.comanda_id = cd.id
              WHERE v.estado = :estado3
                AND v.cobrada_en >= :desdeB
                AND v.cobrada_en <  :hastaB';

        $fila = DB::one($sql, [
            'estado'   => 'pendiente',
            'estado2'  => 'cobrada',
            'estado3'  => 'cobrada',
            'desdeA'   => $lim['desde'],
            'hastaA'   => $lim['hasta'],
            'desdeB'   => $lim['desde'],
            'hastaB'   => $lim['hasta'],
        ]) ?? [];

        $neto   = (float) ($fila['neto'] ?? 0);
        $costo  = (float) ($fila['costo'] ?? 0);
        $margen = $neto - $costo;

        return [
            'ventas'           => (int) ($fila['ventas'] ?? 0),
            'comensales'       => (int) ($fila['comensales'] ?? 0),
            'bruto'            => (float) ($fila['bruto'] ?? 0),
            'descuentos'       => (float) ($fila['descuentos'] ?? 0),
            'neto'             => $neto,
            'costo'            => $costo,
            'ganancia'         => $margen,
            'porcentaje'       => $neto > 0 ? round(($margen / $neto) * 100, 1) : null,
            'ventas_sin_costo' => (int) ($fila['ventas_sin_costo'] ?? 0),
        ];
    }

    /**
     * Ventas agrupadas por día.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ventasPorDia(array $rango): array
    {
        $lim = self::limites($rango);

        $sql = 'SELECT
                    DATE(v.cobrada_en)                              AS dia,
                    COUNT(*)                                        AS ventas,
                    COALESCE(SUM(v.comensales), 0)                  AS comensales,
                    COALESCE(SUM(v.total_bruto), 0)                 AS bruto,
                    COALESCE(SUM(v.descuento_importe), 0)           AS descuentos,
                    COALESCE(SUM(v.total_neto), 0)                  AS neto,
                    COALESCE(SUM(c.costo), 0)                       AS costo,
                    COALESCE(SUM(CASE WHEN c.costo IS NULL THEN 1 ELSE 0 END), 0) AS ventas_sin_costo
               FROM ventas v
               JOIN comandas cd ON cd.id = v.comanda_id
               LEFT JOIN (
                    SELECT ci.comanda_id,
                           SUM(ci.costo_unitario * ci.cantidad) AS costo
                      FROM comanda_items ci
                      JOIN ventas v2 ON v2.comanda_id = ci.comanda_id
                     WHERE ci.estado = :estado
                       AND v2.estado = :estado2
                       AND v2.cobrada_en >= :desdeA
                       AND v2.cobrada_en <  :hastaA
                     GROUP BY ci.comanda_id
               ) c ON c.comanda_id = cd.id
              WHERE v.estado = :estado3
                AND v.cobrada_en >= :desdeB
                AND v.cobrada_en <  :hastaB
              GROUP BY DATE(v.cobrada_en)
              ORDER BY dia DESC';

        return DB::all($sql, [
            'estado'  => 'pendiente',
            'estado2' => 'cobrada',
            'estado3' => 'cobrada',
            'desdeA'  => $lim['desde'],
            'hastaA'  => $lim['hasta'],
            'desdeB'  => $lim['desde'],
            'hastaB'  => $lim['hasta'],
        ]);
    }

    /**
     * Qué se vende más: agrupado por producto.
     *
     * El nombre sale del producto actual y, si el producto fue
     * desactivado, del nombre que quedó guardado en la línea.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function productosMasVendidos(array $rango, int $limite = 100): array
    {
        $lim     = self::limites($rango);
        $limite  = max(1, min(1000, $limite));

        $sql = 'SELECT
                    ci.producto_id                                AS producto_id,
                    COALESCE(p.nombre, MAX(ci.producto_nombre))   AS nombre,
                    COALESCE(p.unidad, \'unidad\')                   AS unidad,
                    COALESCE(c.nombre, \'Sin categoría\')            AS categoria,
                    SUM(ci.cantidad)                               AS unidades,
                    SUM(ci.importe)                                AS importe,
                    COALESCE(SUM(ci.costo_unitario * ci.cantidad), 0) AS costo,
                    SUM(CASE WHEN ci.costo_unitario IS NULL THEN 1 ELSE 0 END) AS lineas_sin_costo,
                    COUNT(DISTINCT ci.comanda_id)                  AS ventas
               FROM comanda_items ci
               JOIN ventas v   ON v.comanda_id = ci.comanda_id
               LEFT JOIN productos p ON p.id = ci.producto_id
               LEFT JOIN categorias c ON c.id = p.categoria_id
              WHERE ci.estado = :estado
                AND v.estado  = :estado2
                AND v.cobrada_en >= :desde
                AND v.cobrada_en <  :hasta
              GROUP BY ci.producto_id, p.nombre, p.unidad, c.nombre
              ORDER BY unidades DESC, importe DESC
              LIMIT ' . (int) $limite;

        $filas = DB::all($sql, [
            'estado'  => 'pendiente',
            'estado2' => 'cobrada',
            'desde'   => $lim['desde'],
            'hasta'   => $lim['hasta'],
        ]);

        // El costo se separa: si hay productos sin costo cargado, el
        // total que sale aca NO es el costo real de todo lo vendido.
        foreach ($filas as $i => $fila) {
            $costo        = (float) $fila['costo'];
            $filas[$i]['costo']    = $costo;
            $filas[$i]['importe'] = (float) $fila['importe'];
            $filas[$i]['ganancia'] = (float) $fila['importe'] - $costo;
            $filas[$i]['parcial'] = (int) $fila['lineas_sin_costo'] > 0;
        }

        return $filas;
    }

    /**
     * Por mesa, y el resumen por zona.
     *
     * @return array{mesas: array<int, array<string, mixed>>, zonas: array<int, array<string, mixed>>}
     */
    public static function porMesa(array $rango): array
    {
        $lim = self::limites($rango);

        $filas = DB::all(
            'SELECT
                 m.id                                             AS mesa_id,
                 m.numero                                         AS numero,
                 m.nombre                                         AS nombre,
                 m.zona                                           AS zona,
                 COUNT(*)                                         AS ventas,
                 COALESCE(SUM(v.comensales), 0)                    AS comensales,
                 COALESCE(SUM(v.total_neto), 0)                    AS neto,
                 COALESCE(SUM(c.costo), 0)                         AS costo,
                 COALESCE(SUM(CASE WHEN c.costo IS NULL THEN 1 ELSE 0 END), 0) AS ventas_sin_costo
               FROM ventas v
               JOIN comandas cd ON cd.id = v.comanda_id
               JOIN mesas m     ON m.id = cd.mesa_id
               LEFT JOIN (
                    SELECT ci.comanda_id,
                           SUM(ci.costo_unitario * ci.cantidad) AS costo
                      FROM comanda_items ci
                      JOIN ventas v2 ON v2.comanda_id = ci.comanda_id
                     WHERE ci.estado = :estado
                       AND v2.estado = :estado2
                       AND v2.cobrada_en >= :desdeA
                       AND v2.cobrada_en <  :hastaA
                     GROUP BY ci.comanda_id
               ) c ON c.comanda_id = cd.id
              WHERE v.estado = :estado3
                AND v.cobrada_en >= :desdeB
                AND v.cobrada_en <  :hastaB
              GROUP BY m.id, m.numero, m.nombre, m.zona
              ORDER BY neto DESC, m.numero ASC',
            [
                'estado'  => 'pendiente',
                'estado2' => 'cobrada',
                'estado3' => 'cobrada',
                'desdeA'  => $lim['desde'],
                'hastaA'  => $lim['hasta'],
                'desdeB'  => $lim['desde'],
                'hastaB'  => $lim['hasta'],
            ]
        );

        $mesas = [];

        foreach ($filas as $fila) {
            $neto           = (float) $fila['neto'];
            $costo          = (float) $fila['costo'];
            $mesas[] = [
                'mesa_id'   => (int) $fila['mesa_id'],
                'numero'    => (string) $fila['numero'],
                'nombre'    => (string) $fila['nombre'],
                'zona'      => (string) $fila['zona'],
                'ventas'    => (int) $fila['ventas'],
                'comensales'=> (int) $fila['comensales'],
                'neto'      => $neto,
                'costo'     => $costo,
                'ganancia'  => $neto - $costo,
                'parcial'   => (int) $fila['ventas_sin_costo'] > 0,
            ];
        }

        return [
            'mesas' => $mesas,
            'zonas' => self::resumenPorZona($mesas),
        ];
    }

    /**
     * Suma las mesas por zona.
     *
     * @param  array<int, array<string, mixed>> $mesas
     * @return array<int, array<string, mixed>>
     */
    private static function resumenPorZona(array $mesas): array
    {
        $zonas = [];

        foreach ($mesas as $mesa) {
            $zona = $mesa['zona'];

            if (!isset($zonas[$zona])) {
                $zonas[$zona] = [
                    'zona'        => $zona,
                    'mesas'       => 0,
                    'ventas'      => 0,
                    'comensales'  => 0,
                    'neto'        => 0.0,
                    'costo'       => 0.0,
                    'parcial'     => false,
                ];
            }

            $zonas[$zona]['mesas']      += 1;
            $zonas[$zona]['ventas']     += $mesa['ventas'];
            $zonas[$zona]['comensales'] += $mesa['comensales'];
            $zonas[$zona]['neto']       += $mesa['neto'];
            $zonas[$zona]['costo']      += $mesa['costo'];
            $zonas[$zona]['parcial']     = $zonas[$zona]['parcial'] || $mesa['parcial'];
        }

        foreach ($zonas as $zona => $datos) {
            $zonas[$zona]['ganancia'] = $datos['neto'] - $datos['costo'];
        }

        // La zona que más facturó va primero: es la que más le
        // interesa al dueño ver.
        uasort($zonas, static fn ($a, $b) => $b['neto'] <=> $a['neto']);

        return array_values($zonas);
    }

    /**
     * Por medio de pago.
     *
     * Solo el efectivo es plata que estuvo en el cajón; la tarjeta y
     * la transferencia se registran para saber cómo se cobró, pero no
     * se cuentan. Por eso cada medio trae si entró al cajón o no.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function porMedioPago(array $rango): array
    {
        $lim = self::limites($rango);

        $filas = DB::all(
            'SELECT
                 mp.id                          AS metodo_id,
                 mp.clave                       AS clave,
                 mp.nombre                      AS nombre,
                 mp.afecta_caja                 AS afecta_caja,
                 COUNT(DISTINCT vp.venta_id)    AS cobros,
                 COALESCE(SUM(vp.importe), 0)   AS importe
               FROM venta_pagos vp
               JOIN ventas v        ON v.id = vp.venta_id
               JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
              WHERE v.estado = :estado
                AND v.cobrada_en >= :desde
                AND v.cobrada_en <  :hasta
              GROUP BY mp.id, mp.clave, mp.nombre, mp.afecta_caja
              ORDER BY importe DESC, mp.nombre ASC',
            [
                'estado' => 'cobrada',
                'desde'  => $lim['desde'],
                'hasta'  => $lim['hasta'],
            ]
        );

        $medios = [];
        $total  = 0.0;

        foreach ($filas as $fila) {
            $importe      = (float) $fila['importe'];
            $medios[] = [
                'clave'       => (string) $fila['clave'],
                'nombre'      => (string) $fila['nombre'],
                'afecta_caja' => (int) $fila['afecta_caja'] === 1,
                'cobros'      => (int) $fila['cobros'],
                'importe'     => $importe,
                'porcentaje'  => null,
            ];

            $total += $importe;
        }

        // El porcentaje se calcula recién con el total conocido: si
        // no, "el 80% en efectivo" puede ser en realidad el 50%.
        if ($total > 0) {
            foreach ($medios as $i => $medio) {
                $medios[$i]['porcentaje'] = round(($medio['importe'] / $total) * 100, 1);
            }
        }

        return $medios;
    }

    /**
     * El nombre legible del período, para el título y el CSV.
     */
    public static function etiquetaRango(array $rango): string
    {
        $desde = DateTimeImmutable::createFromFormat('Y-m-d', $rango['desde']);
        $hasta = DateTimeImmutable::createFromFormat('Y-m-d', $rango['hasta']);

        if ($desde === false || $hasta === false) {
            return $rango['desde'] . ' al ' . $rango['hasta'];
        }

        if ($rango['desde'] === $rango['hasta']) {
            return fecha_corta($rango['desde']);
        }

        return fecha_corta($rango['desde']) . ' al ' . fecha_corta($rango['hasta']);
    }

    /**
     * Los encabezados de cada reporte, para el CSV.
     *
     * Cada reporte devuelve sus datos y sus columnas juntos, así que
     * el CSV no puede quedar con columnas que no coinciden con los
     * datos que se descargaron.
     *
     * @return array{columnas: array<int, string>, filas: array<int, array<int, mixed>>}
     */
    public static function paraCsv(string $vista, array $rango): array
    {
        switch ($vista) {
            case 'dia':
                $columnas = ['Dia', 'Ventas', 'Comensales', 'Bruto', 'Descuentos', 'Vendido', 'Costo', 'Ganancia'];
                $filas    = [];

                foreach (self::ventasPorDia($rango) as $fila) {
                    $neto      = (float) $fila['neto'];
                    $costo     = (float) $fila['costo'];
                    $filas[] = [
                        $fila['dia'],
                        (int) $fila['ventas'],
                        (int) $fila['comensales'],
                        (float) $fila['bruto'],
                        (float) $fila['descuentos'],
                        $neto,
                        $costo,
                        $neto - $costo,
                    ];
                }

                return ['columnas' => $columnas, 'filas' => $filas];

            case 'productos':
                $columnas = ['Producto', 'Categoria', 'Unidades', 'Unidad de medida', 'Ventas', 'Vendido', 'Costo', 'Ganancia'];
                $filas    = [];

                foreach (self::productosMasVendidos($rango) as $fila) {
                    $filas[] = [
                        $fila['nombre'],
                        $fila['categoria'],
                        (float) $fila['unidades'],
                        $fila['unidad'],
                        (int) $fila['ventas'],
                        (float) $fila['importe'],
                        (float) $fila['costo'],
                        (float) $fila['ganancia'],
                    ];
                }

                return ['columnas' => $columnas, 'filas' => $filas];

            case 'mesas':
                $columnas = ['Mesa', 'Nombre', 'Zona', 'Ventas', 'Comensales', 'Vendido', 'Costo', 'Ganancia'];
                $filas    = [];

                foreach (self::porMesa($rango)['mesas'] as $fila) {
                    $filas[] = [
                        $fila['numero'],
                        $fila['nombre'],
                        $fila['zona'],
                        (int) $fila['ventas'],
                        (int) $fila['comensales'],
                        (float) $fila['neto'],
                        (float) $fila['costo'],
                        (float) $fila['ganancia'],
                    ];
                }

                return ['columnas' => $columnas, 'filas' => $filas];

            case 'pagos':
            default:
                $columnas = ['Medio de pago', 'Entra al cajon', 'Cobros', 'Importe', 'Porcentaje'];
                $filas    = [];

                foreach (self::porMedioPago($rango) as $fila) {
                    $filas[] = [
                        $fila['nombre'],
                        $fila['afecta_caja'] ? 'si' : 'no',
                        (int) $fila['cobros'],
                        (float) $fila['importe'],
                        $fila['porcentaje'] === null ? '' : $fila['porcentaje'] . ' %',
                    ];
                }

                return ['columnas' => $columnas, 'filas' => $filas];
        }
    }

    /**
     * El archivo CSV armado para que el Excel en español lo abra bien.
     *
     * Tres cosas, y las tres hacen falta:
     *   - punto y coma, porque con coma el Excel en español lo lee
     *     como una sola columna gigante
     *   - coma decimal y punto de miles, que es como escribe un
     *     argentino y como lo entiende ese Excel
     *   - el BOM al principio del archivo: sin él, el Excel lee las
     *     tildes como dos letras raras y "Café" aparece con la A
     *     acentuada y la e con un signo arriba
     *
     * El título va en la primera fila y pasa por el mismo
     * escapado: sin eso, un archivo sin nombre de período no se
     * entiende a la semana, y si el título tuviera un punto y
     * coma partiría la fila.
     */
    public static function csv(array $columnas, array $filas, string $titulo = ''): string
    {
        $salida = "\xEF\xBB\xBF";   // BOM: sin esto el Excel rompe las tildes

        // El titulo pasa por el mismo escapado que el resto: si
        // tuviera un punto y coma, partiria la fila en dos.
        if ($titulo !== '') {
            $salida .= self::lineaCsv([$titulo]);
        }

        $salida .= self::lineaCsv($columnas);

        foreach ($filas as $fila) {
            $salida .= self::lineaCsv($fila);
        }

        return $salida;
    }

    /**
     * Una línea de CSV, con los valores ya formateados.
     */
    private static function lineaCsv(array $valores): string
    {
        $partes = [];

        foreach ($valores as $valor) {
            if (is_bool($valor)) {
                $texto = $valor ? 'si' : 'no';
            } elseif (is_float($valor)) {
                $texto = numero_csv($valor);
            } elseif (is_int($valor)) {
                $texto = (string) $valor;
            } elseif ($valor === null) {
                $texto = '';
            } else {
                $texto = (string) $valor;
            }

            // El separador del CSV es el punto y coma, pero si un
            // texto tiene un punto y coma (o comillas, o un salto de
            // línea) tiene que ir entre comillas, si no la fila se
            // parte en dos.
            if (strpbrk($texto, ";\r\n\"") !== false) {
                $texto = '"' . str_replace('"', '""', $texto) . '"';
            }

            $partes[] = $texto;
        }

        return implode(';', $partes) . "\r\n";
    }
}