<?php
/**
 * includes/ventas.php
 * -----------------------------------------------------------------
 * Cobro, ventas y caja (Etapa 4).
 *
 * TRES DECISIONES QUE VIENEN DEL DUEÑO DEL BAR
 *
 * 1) LOS DESCUENTOS LOS HACE SOLO EL ADMINISTRADOR.
 *    El vendedor cobra el total tal cual. No es desconfianza: es
 *    que un descuento es plata que sale del bar, y si el sistema
 *    deja que cualquiera lo haga, un error de tipeo se come la
 *    ganancia sin que nadie se entere hasta el cierre.
 *    Si algún día lo necesitás, se cambia el interruptor
 *    'venta_permitir_descuento_vendedor' de la configuración y no
 *    hay que tocar código.
 *
 * 2) SE PAGA EN EFECTIVO, TARJETA O TRANSFERENCIA, Y SE PUEDE
 *    MEZCLAR. Por eso los pagos son filas en venta_pagos y no
 *    columnas: cobrar $5000 en efectivo y $3000 con tarjeta es un
 *    caso normal, no una excepción.
 *
 * 3) HAY CAJA, CON CONTEO.
 *    Se abre al empezar el turno con la plata que hay adentro. Al
 *    cerrar se cuenta otra vez y el sistema dice cuánto debía
 *    haber. La diferencia es lo que falta o sobra.
 *
 * UNA VENTA ES DISTINTA DE UNA COMANDA
 *
 * La comanda dice QUÉ se pidió. La venta dice CUÁNTO se cobró, con
 * qué medios y con qué descuento. Son hechos distintos y de
 * fechas distintas: la comanda se carga durante dos horas, el
 * cobro pasa en un minuto. Si fueran la misma cosa, un cambio de
 * precio a las 23:50 reescribiría lo que se pidió a las 20:00.
 * -----------------------------------------------------------------
 */

/**
 * Clase MetodoPago: los medios con los que se puede cobrar.
 */
final class MetodoPago
{
    /**
     * Métodos activos, en el orden en que se muestran.
     */
    public static function activos(): array
    {
        return DB::all(
            'SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY orden ASC, nombre ASC'
        );
    }

    /**
     * Un método por su clave: efectivo, tarjeta, transferencia.
     */
    public static function porClave(string $clave): ?array
    {
        return DB::one(
            'SELECT * FROM metodos_pago WHERE clave = :clave',
            ['clave' => $clave]
        );
    }

    /**
     * Un método por su id.
     */
    public static function porId(int $id): ?array
    {
        return DB::one(
            'SELECT * FROM metodos_pago WHERE id = :id',
            ['id' => $id]
        );
    }
}

/**
 * Clase Caja: los turnos y el dinero del cajón.
 */
final class Caja
{
    /** Tipos de movimiento de dinero que no son ventas. */
    public const MOVIMIENTOS = [
        'ingreso' => 'Entró plata',
        'retiro'  => 'Retiro del dueño',
        'gasto'   => 'Gasto del bar',
    ];

    /**
     * La caja abierta ahora, o null si no hay ninguna.
     */
    public static function abierta(): ?array
    {
        return DB::one(
            "SELECT c.*, ua.usuario AS abierta_por, uc.usuario AS cerrada_por
             FROM cajas c
             LEFT JOIN usuarios ua ON ua.id = c.usuario_apertura_id
             LEFT JOIN usuarios uc ON uc.id = c.usuario_cierre_id
             WHERE c.estado = 'abierta'
             ORDER BY c.id DESC
             LIMIT 1"
        );
    }

    /**
     * ¿Hay alguna caja abierta?
     */
    public static function hay_abierta(): bool
    {
        return self::abierta() !== null;
    }

    /**
     * Abre un turno de caja.
     *
     * Solo puede haber una abierta: es un solo cajón. Si ya hay
     * una, esta función no hace nada y lo dice, para que nadie
     * abra dos por error y las ventas queden partidas en dos
     * Mitades que no se pueden sumar.
     */
    public static function abrir(string $nombre, float $importe, string $observacion = ''): array
    {
        $error = ['ok' => false, 'mensaje' => '', 'caja_id' => 0];

        if ($importe < 0) {
            return $error + ['mensaje' => 'La cantidad con la que abrís la caja no puede ser negativa.'];
        }

        if ($importe > 99999999.99) {
            return $error + ['mensaje' => 'Esa cantidad es demasiado grande. Revisá el número.'];
        }

        if (self::hay_abierta()) {
            return $error + ['mensaje' => 'Ya hay una caja abierta. Cerrala antes de abrir otra.'];
        }

        try {
            $id = DB::insertar('cajas', [
                'nombre'               => mb_substr($nombre, 0, 80),
                'usuario_apertura_id'  => Auth::id(),
                'apertura_importe'     => $importe,
                'apertura_observacion' => mb_substr($observacion, 0, 255),
                'estado'               => 'abierta',
                'abierto_en'           => ahora(),
                'creado_en'            => ahora(),
            ]);

            Auditoria::registrar('abrir_caja', 'cajas', $id, null, [
                'apertura' => $importe,
                'nombre'   => $nombre,
            ]);

            return ['ok' => true, 'mensaje' => '', 'caja_id' => $id];
        } catch (Throwable $e) {
            error_log('Error abriendo caja: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo abrir la caja. Probá de nuevo.'];
        }
    }

    /**
     * Cuánto dinero debería haber en el cajón ahora mismo.
     *
     * El desglose se devuelve porque al cerrar el turno hay que
     * poder mostrar de dónde sale el número, no solo el número.
     * Si el dueño ve que falten $500 y el sistema le muestra solo
     * "$500", no puede saber si es un error de tipeo o que se
     *olvidó de cargar un gasto.
     *
     * @return array{efectivo: float, ingresos: float, retiros: float,
     *               gastos: float, esperado: float, ventas: int}
     */
    public static function esperado(int $cajaId): array
    {
        $caja = DB::one(
            'SELECT apertura_importe FROM cajas WHERE id = :id',
            ['id' => $cajaId]
        );

        $apertura = $caja !== null ? (float) $caja['apertura_importe'] : 0.0;

        // Solo el efectivo entra al cajón. La tarjeta y la
        // transferencia se registran como venta, pero la plata
        // nunca estuvo en el cajón: va a la cuenta.
        $efectivo = (float) DB::valor(
            "SELECT COALESCE(SUM(p.importe), 0)
             FROM venta_pagos p
             INNER JOIN ventas v ON v.id = p.venta_id
             INNER JOIN metodos_pago m ON m.id = p.metodo_pago_id
             WHERE v.caja_id = :caja
               AND v.estado = 'cobrada'
               AND m.afecta_caja = 1",
            ['caja' => $cajaId]
        );

        $ventas = (int) DB::valor(
            "SELECT COUNT(*) FROM ventas WHERE caja_id = :caja AND estado = 'cobrada'",
            ['caja' => $cajaId]
        );

        $movimientos = DB::all(
            'SELECT tipo, COALESCE(SUM(importe), 0) AS total
             FROM caja_movimientos
             WHERE caja_id = :caja
             GROUP BY tipo',
            ['caja' => $cajaId]
        );

        $ingresos = 0.0;
        $retiros  = 0.0;
        $gastos   = 0.0;

        foreach ($movimientos as $movimiento) {
            $total = (float) $movimiento['total'];

            if ($movimiento['tipo'] === 'ingreso') {
                $ingresos += $total;
            } elseif ($movimiento['tipo'] === 'retiro') {
                $retiros += $total;
            } elseif ($movimiento['tipo'] === 'gasto') {
                $gastos += $total;
            }
        }

        return [
            'apertura' => $apertura,
            'efectivo' => $efectivo,
            'ingresos' => $ingresos,
            'retiros'  => $retiros,
            'gastos'   => $gastos,
            'esperado' => round($apertura + $efectivo + $ingresos - $retiros - $gastos, 2),
            'ventas'   => $ventas,
        ];
    }

    /**
     * Registra una entrada o salida de plata que no es una venta.
     */
    public static function movimiento(int $cajaId, string $tipo, float $importe, string $motivo = ''): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        if (!array_key_exists($tipo, self::MOVIMIENTOS)) {
            return $error + ['mensaje' => 'Ese tipo de movimiento no existe.'];
        }

        if ($importe <= 0) {
            return $error + ['mensaje' => 'El importe tiene que ser mayor a cero.'];
        }

        // El motivo no es obligatorio en todos los casos, pero en
        // un retiro del dueño o un gasto sí: sin eso no se puede
        // auditar después.
        if (mb_strlen(trim($motivo)) < 3) {
            return $error + ['mensaje' => 'Contá por qué, aunque sea una palabra.'];
        }

        $caja = DB::one('SELECT * FROM cajas WHERE id = :id', ['id' => $cajaId]);

        if ($caja === null) {
            return $error + ['mensaje' => 'La caja no existe.'];
        }

        if ($caja['estado'] !== 'abierta') {
            return $error + ['mensaje' => 'La caja está cerrada. Abrí una nueva.'];
        }

        try {
            $id = DB::insertar('caja_movimientos', [
                'caja_id'    => $cajaId,
                'tipo'       => $tipo,
                'importe'    => $importe,
                'motivo'     => mb_substr($motivo, 0, 255),
                'usuario_id' => Auth::id(),
                'creado_en'  => ahora(),
            ]);

            Auditoria::registrar('movimiento_caja', 'caja_movimientos', $id, null, [
                'tipo'    => $tipo,
                'importe' => $importe,
                'motivo'  => $motivo,
            ]);

            return ['ok' => true, 'mensaje' => ''];
        } catch (Throwable $e) {
            error_log('Error registrando movimiento de caja: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo guardar el movimiento.'];
        }
    }

    /**
     * Cierra el turno con la plata contada.
     *
     * El importe se guarda tal como se contó, no se "corrige": la
     * diferencia entre lo contado y lo esperado es justamente el
     * dato más valioso que deja el cierre.
     */
    public static function cerrar(int $cajaId, float $contado, string $observacion = ''): array
    {
        $error = ['ok' => false, 'mensaje' => '', 'diferencia' => 0.0];

        if ($contado < 0) {
            return $error + ['mensaje' => 'No se puede cerrar la caja con una cantidad negativa.'];
        }

        $caja = DB::one('SELECT * FROM cajas WHERE id = :id', ['id' => $cajaId]);

        if ($caja === null) {
            return $error + ['mensaje' => 'La caja no existe.'];
        }

        if ($caja['estado'] !== 'abierta') {
            return $error + ['mensaje' => 'Esta caja ya está cerrada.'];
        }

        $esperado = self::esperado($cajaId);
        $diferencia = round($contado - $esperado['esperado'], 2);

        // Si la caja no cuadró y está prohibido dejarlo asentado
        // sin explicación, se avisa. Está apagado por defecto
        // porque en un turno normal hay diferencias de redondeo y
        // no tiene que frenar el cierre.
        if ($diferencia != 0.0
            && config_bool('caja_pedir_motivo_cierre')
            && mb_strlen(trim($observacion)) < 3) {
            return $error + [
                'mensaje'    => 'La caja no cuadra. Contá por qué antes de cerrarla.',
                'diferencia' => $diferencia,
            ];
        }

        try {
            DB::actualizar('cajas', $cajaId, [
                'usuario_cierre_id'  => Auth::id(),
                'cierre_importe'     => $contado,
                'esperado_calculado' => $esperado['esperado'],
                'diferencia'         => $diferencia,
                'cierre_observacion' => mb_substr($observacion, 0, 255),
                'estado'             => 'cerrada',
                'cerrado_en'         => ahora(),
            ]);

            Auditoria::registrar('cerrar_caja', 'cajas', $cajaId, [
                'estado' => 'abierta',
            ], [
                'estado'     => 'cerrada',
                'contado'    => $contado,
                'esperado'   => $esperado['esperado'],
                'diferencia' => $diferencia,
            ]);

            return ['ok' => true, 'mensaje' => '', 'diferencia' => $diferencia];
        } catch (Throwable $e) {
            error_log('Error cerrando caja: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo cerrar la caja. Probá de nuevo.', 'diferencia' => $diferencia];
        }
    }

    // ================================================================
    //  CLAVE DE CAJA
    //
    //  El administrador la define y el vendedor tiene que escribirla
    //  para abrir y cerrar el turno. El administrador no la pide nunca:
    //  es suya.
    //
    //  Se guarda el hash, no la clave. Y se usa password_hash() a
    //  proposito: es caro de calcular (a proposito), asi que probar
    //  claves a fuerza tambien sale caro.
    // ================================================================

    /** La clave de la configuracion donde vive el hash. */
    public const CLAVE_CONFIG = 'caja_clave_hash';

    /** Intentos fallidos antes de frenar un rato. */
    public const CLAVE_INTENTOS = 5;

    /** Minutos de freno tras pasarse de intentos. */
    public const CLAVE_MINUTOS_FRENO = 5;

    /**
     * Hay una clave de caja puesta?
     *
     * Si no hay, el vendedor no puede abrir ni cerrar. No es un
     * olvido: es la unica forma de que el dueño sepa que todavia no
     * configuro nada. Si esto devolviera true cuando no hay clave, el
     * vendedor pasaria y el control seria de mentira.
     */
    public static function clave_configurada(): bool
    {
        return self::clave_hash() !== '';
    }

    /** El hash guardado. Nadie lo muestra, ni siquiera el admin. */
    private static function clave_hash(): string
    {
        return (string) config_valor(self::CLAVE_CONFIG, '');
    }

    /**
     * Guarda la clave de caja.
     *
     * Crea la fila si no existe: asi el dueño no tiene que importar
     * una migracion nueva para poder usar esto.
     */
    public static function clave_guardar(string $clave): void
    {
        $hash = password_hash($clave, PASSWORD_DEFAULT);

        DB::query(
            "INSERT INTO `configuracion`
                (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`, `actualizado_en`)
             VALUES
                (:clave, :valor, 'texto', :descripcion, 'caja', 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `valor` = :valor_otra,
                `actualizado_en` = NOW()",
            [
                'clave'       => self::CLAVE_CONFIG,
                'valor'       => $hash,
                'valor_otra'  => $hash,
                'descripcion' => 'Clave que pide el sistema para abrir o cerrar la caja. Se guarda el hash, no la clave.',
            ]
        );
    }

    /** Saca la clave de caja. El vendedor vuelve a poder operar sin clave. */
    public static function clave_quitar(): void
    {
        DB::query(
            'UPDATE `configuracion` SET `valor` = :vacio, `actualizado_en` = NOW() WHERE `clave` = :clave',
            ['vacio' => '', 'clave' => self::CLAVE_CONFIG]
        );
    }

    /**
     * Comprueba la clave que escribio el usuario.
     *
     * Devuelve true si la clave esta bien. Lleva la cuenta de los
     * intentos fallidos en la sesion y frena un rato cuando se pasa:
     * sin eso, probar claves a fuerza seria gratis.
     *
     * OJO: se usa password_verify(), que es caro a proposito. Por eso
     * el freno por intentos no es lo unico que protege: probar claves
     * a fuerza sale lento.
     */
    public static function clave_verificar(string $escrita): bool
    {
        $hash = self::clave_hash();

        if ($hash === '') {
            return false;
        }

        $intentos = (int) ($_SESSION['caja_clave_intentos'] ?? 0);
        $hasta    = (int) ($_SESSION['caja_clave_freno_hasta'] ?? 0);

        // Esta frenado todavia: no se ni intenta comparar.
        if ($intentos >= self::CLAVE_INTENTOS && $hasta > time()) {
            return false;
        }

        // Se cumplio el freno: arranca de cero.
        if ($hasta > 0 && $hasta <= time()) {
            $intentos = 0;
            $hasta    = 0;
        }

        if (password_verify($escrita, $hash)) {
            unset($_SESSION['caja_clave_intentos'], $_SESSION['caja_clave_freno_hasta']);

            return true;
        }

        $intentos++;
        $_SESSION['caja_clave_intentos'] = $intentos;

        if ($intentos >= self::CLAVE_INTENTOS) {
            $_SESSION['caja_clave_freno_hasta'] = time() + (self::CLAVE_MINUTOS_FRENO * 60);
        }

        return false;
    }

    /** Como viene la cosa de los intentos, para poder avisar. */
    public static function clave_estado(): array
    {
        $intentos = (int) ($_SESSION['caja_clave_intentos'] ?? 0);
        $hasta    = (int) ($_SESSION['caja_clave_freno_hasta'] ?? 0);

        return [
            'intentos' => $intentos,
            'frenado'  => $intentos >= self::CLAVE_INTENTOS && $hasta > time(),
            'minutos'  => $hasta > time() ? (int) ceil(($hasta - time()) / 60) : 0,
        ];
    }

    /**
     * Cajas ya cerradas, para el historial.
     */
    public static function historial(int $limite = 30): array
    {
        $limite = max(1, min(200, $limite));

        return DB::all(
            "SELECT c.*, ua.usuario AS abierta_por, uc.usuario AS cerrada_por,
                    (SELECT COALESCE(SUM(v.total_neto), 0) FROM ventas v
                     WHERE v.caja_id = c.id AND v.estado = 'cobrada') AS total_vendido,
                    (SELECT COUNT(*) FROM ventas v
                     WHERE v.caja_id = c.id AND v.estado = 'cobrada') AS ventas
             FROM cajas c
             LEFT JOIN usuarios ua ON ua.id = c.usuario_apertura_id
             LEFT JOIN usuarios uc ON uc.id = c.usuario_cierre_id
             ORDER BY c.id DESC
             LIMIT {$limite}"
        );
    }

    /**
     * Ventas de una caja, para el detalle.
     */
    public static function ventas(int $cajaId): array
    {
        return DB::all(
            "SELECT v.*, m.numero AS mesa_numero, ua.usuario AS cobrada_por
             FROM ventas v
             LEFT JOIN comandas c ON c.id = v.comanda_id
             LEFT JOIN mesas m ON m.id = c.mesa_id
             LEFT JOIN usuarios ua ON ua.id = v.usuario_id
             WHERE v.caja_id = :caja
             ORDER BY v.cobrada_en ASC",
            ['caja' => $cajaId]
        );
    }

    /**
     * Movimientos de una caja.
     */
    public static function movimientos(int $cajaId): array
    {
        return DB::all(
            'SELECT m.*, u.usuario
             FROM caja_movimientos m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.caja_id = :caja
             ORDER BY m.id DESC',
            ['caja' => $cajaId]
        );
    }

    /**
     * Totales de la caja abierta, por método de pago. Se usa en el
     * panel para que el dueño vea cómo viene el turno.
     */
    public static function resumenPorMetodo(int $cajaId): array
    {
        return DB::all(
            "SELECT m.nombre, mp.clave, mp.afecta_caja,
                    COALESCE(SUM(p.importe), 0) AS total,
                    COUNT(p.id) AS operaciones
             FROM venta_pagos p
             INNER JOIN ventas v ON v.id = p.venta_id
             INNER JOIN metodos_pago mp ON mp.id = p.metodo_pago_id
             INNER JOIN metodos_pago m ON m.id = mp.id
             WHERE v.caja_id = :caja AND v.estado = 'cobrada'
             GROUP BY mp.id, m.nombre, mp.clave, mp.afecta_caja
             ORDER BY mp.orden ASC",
            ['caja' => $cajaId]
        );
    }
}

/**
 * Clase Venta: el hecho de cobrar una mesa.
 */
final class Venta
{
    /**
     * Cobra una comanda.
     *
     * Todo o nada: si algo falla (por ejemplo, la caja se cerró
     * entre medio), no queda una venta a medio hacer ni una comanda
     * cobrada sin venta. Por eso va en una transacción.
     *
     * @param array $pagos Lista de ['metodo_id' => int, 'importe' => float,
     *                            'referencia' => string, 'recibido' => float]
     *
     * @return array{ok: bool, mensaje: string, venta_id: int, vuelto: float}
     */
    public static function cobrar(int $comandaId, array $pagos, float $descuento = 0.0, string $motivoDescuento = ''): array
    {
        $error = [
            'ok' => false, 'mensaje' => '', 'venta_id' => 0, 'vuelto' => 0.0,
        ];

        // --- La comanda tiene que estar lista para cobrar ---
        $comanda = DB::one(
            'SELECT c.*, m.numero AS mesa_numero
             FROM comandas c
             LEFT JOIN mesas m ON m.id = c.mesa_id
             WHERE c.id = :id',
            ['id' => $comandaId]
        );

        if ($comanda === null) {
            return $error + ['mensaje' => 'El pedido no existe.'];
        }

        if ($comanda['estado'] === 'cerrada') {
            return $error + ['mensaje' => 'Este pedido ya fue cobrado.'];
        }

        if ($comanda['estado'] === 'anulada') {
            return $error + ['mensaje' => 'Este pedido está anulado.'];
        }

        if ($comanda['estado'] !== 'por_cobrar') {
            return $error + [
                'mensaje' => 'Este pedido todavía no pidió la cuenta. Marcá "Pedir la cuenta" antes de cobrar.',
            ];
        }

        // --- Tiene que haber caja abierta ---
        // Cobrar sin caja abierta dejaría la venta en el limbo: no
        // se sabría a qué turno pertenece ni cómo contarlo.
        $caja = Caja::abierta();

        if ($caja === null) {
            return $error + [
                'mensaje' => 'No hay ninguna caja abierta. Abrí la caja para poder cobrar.',
            ];
        }

        // --- El total ---
        $bruto = (float) DB::valor(
            "SELECT COALESCE(SUM(importe), 0) FROM comanda_items
             WHERE comanda_id = :comanda AND estado = 'pendiente'",
            ['comanda' => $comandaId]
        );

        if ($bruto <= 0) {
            return $error + ['mensaje' => 'Este pedido está vacío: no hay nada que cobrar.'];
        }

        // --- El descuento ---
        if ($descuento < 0) {
            return $error + ['mensaje' => 'El descuento no puede ser negativo.'];
        }

        if ($descuento > $bruto) {
            return $error + [
                'mensaje' => 'El descuento no puede ser más grande que el total.',
            ];
        }

        // El vendedor no descuenta, salvo que se abra el
        // interruptor en la configuración.
        if ($descuento > 0 && !Auth::es_admin()
            && !config_bool('venta_permitir_descuento_vendedor')) {
            return $error + [
                'mensaje' => 'Los descuentos los aplica el administrador. Avisale y lo hace.',
            ];
        }

        // Un descuento grande sin motivo no sirve de nada después.
        if ($descuento > 0 && mb_strlen(trim($motivoDescuento)) < 3) {
            return $error + ['mensaje' => 'Contá por qué se hizo el descuento.'];
        }

        $neto = round($bruto - $descuento, 2);

        // --- Los pagos ---
        if (empty($pagos)) {
            return $error + ['mensaje' => 'Falta cargar con cómo se paga.'];
        }

        $totalPagado = 0.0;
        $vueltoTotal = 0.0;
        $metodos = [];

        foreach ($pagos as $pago) {
            $metodoId = (int) ($pago['metodo_id'] ?? 0);
            $importe  = round((float) ($pago['importe'] ?? 0), 2);
            $recibido = round((float) ($pago['recibido'] ?? $importe), 2);

            if ($metodoId <= 0 || $importe <= 0) {
                return $error + ['mensaje' => 'Hay un pago con un importe inválido.'];
            }

            if (!isset($metodos[$metodoId])) {
                $metodo = MetodoPago::porId($metodoId);

                if ($metodo === null || (int) $metodo['activo'] !== 1) {
                    return $error + ['mensaje' => 'Ese medio de pago no está disponible.'];
                }

                $metodos[$metodoId] = $metodo;
            }

            // Si el importe recibido es menor que el importe de la venta,
            // entregó más plata de la que entró: eso no puede ser.
            if ($recibido < $importe) {
                return $error + ['mensaje' => 'El vuelto no puede ser negativo.'];
            }

            $totalPagado = round($totalPagado + $importe, 2);
            $vueltoTotal += ($recibido - $importe);
        }

        // Se compara al centavo. Con floats, 0.1 + 0.2 no da 0.3
        // y eso haría fallar cobros que están bien.
        if (abs($totalPagado - $neto) > 0.005) {
            $faltante = round($neto - $totalPagado, 2);

            return $error + [
                'mensaje' => $faltante > 0
                    ? 'Falta ' . money(abs($faltante)) . ' para completar el cobro.'
                    : 'Te pasaste por ' . money(abs($faltante)) . '.',
            ];
        }

        // --- Guardar ---
        try {
            $ventaId = DB::enTransaccion(
                function () use ($comandaId, $caja, $bruto, $descuento, $motivoDescuento, $neto, $comanda, $pagos) {
                    $numero = self::siguiente_numero();

                    $nuevoId = DB::insertar('ventas', [
                        'numero'               => $numero,
                        'comanda_id'           => $comandaId,
                        'caja_id'              => (int) $caja['id'],
                        'total_bruto'          => $bruto,
                        'descuento_importe'    => $descuento,
                        'descuento_motivo'     => mb_substr($motivoDescuento, 0, 255),
                        'descuento_usuario_id' => $descuento > 0 ? Auth::id() : null,
                        'total_neto'           => $neto,
                        'estado'               => 'cobrada',
                        'comensales'           => (int) $comanda['comensales'],
                        'cobrada_en'           => ahora(),
                        'usuario_id'           => Auth::id(),
                        'creado_en'            => ahora(),
                    ]);

                    foreach ($pagos as $pago) {
                        DB::insertar('venta_pagos', [
                            'venta_id'       => $nuevoId,
                            'metodo_pago_id' => (int) $pago['metodo_id'],
                            'importe'        => round((float) $pago['importe'], 2),
                            'referencia'     => mb_substr((string) ($pago['referencia'] ?? ''), 0, 60),
                            'recibido'       => round((float) ($pago['recibido'] ?? $pago['importe']), 2),
                            'usuario_id'     => Auth::id(),
                            'creado_en'      => ahora(),
                        ]);
                    }

                    // La comanda queda cerrada y libre: la mesa
                    // vuelve a la grilla como disponible.
                    DB::actualizar('comandas', $comandaId, [
                        'estado'            => 'cerrada',
                        'venta_id'          => $nuevoId,
                        'cerrada_en'        => ahora(),
                        'usuario_cierre_id' => Auth::id(),
                    ]);

                    return $nuevoId;
                }
            );
        } catch (Throwable $e) {
            error_log('Error cobrando la mesa: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo guardar el cobro. Probá de nuevo.'];
        }

        Auditoria::registrar('cobrar_mesa', 'ventas', $ventaId, null, [
            'mesa'       => (string) ($comanda['mesa_numero'] ?? '?'),
            'bruto'      => $bruto,
            'descuento'  => $descuento,
            'neto'       => $neto,
        ]);

        return [
            'ok'       => true,
            'mensaje'  => '',
            'venta_id' => $ventaId,
            'vuelto'   => round($vueltoTotal, 2),
        ];
    }

    /**
     * Anula una venta cobrada (solo administrador).
     *
     * No es lo mismo que anular una mesa sin cobrar: acá la plata
     * entró al cajón y hay que sacarla, y además hay que devolver
     * el stock, porque los productos ya salieron del depósito.
     *
     * La venta queda marcada como anulada y no se borra: el
     * histórico tiene que mostrar que se cobró y se devolvió.
     */
    public static function anular(int $ventaId, string $motivo): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        if (mb_strlen(trim($motivo)) < 5) {
            return $error + ['mensaje' => 'Contá por qué se anula la venta. Aunque sea una línea.'];
        }

        $venta = DB::one('SELECT * FROM ventas WHERE id = :id', ['id' => $ventaId]);

        if ($venta === null) {
            return $error + ['mensaje' => 'La venta no existe.'];
        }

        if ($venta['estado'] === 'anulada') {
            return $error + ['mensaje' => 'Esta venta ya estaba anulada.'];
        }

        $caja = DB::one('SELECT * FROM cajas WHERE id = :id', ['id' => (int) $venta['caja_id']]);

        if ($caja === null || $caja['estado'] !== 'abierta') {
            return $error + [
                'mensaje' => 'La caja de esa venta ya está cerrada. '
                    . 'Anulala desde la caja del día en que se cobró.',
            ];
        }

        try {
            DB::enTransaccion(function () use ($ventaId, $venta, $motivo) {
                // El stock vuelve: los productos salieron del
                // depósito cuando se cargaron al pedido.
                Comanda::anular_tras_anular_venta(
                    (int) $venta['comanda_id'],
                    'Venta ' . $venta['numero'] . ' anulada: ' . $motivo
                );

                DB::actualizar('ventas', $ventaId, [
                    'estado'           => 'anulada',
                    'motivo_anulacion' => mb_substr($motivo, 0, 255),
                    'anulada_en'       => ahora(),
                ]);
            });
        } catch (Throwable $e) {
            error_log('Error anulando venta: ' . $e->getMessage());

            return $error + ['mensaje' => 'No se pudo anular la venta.'];
        }

        Auditoria::registrar('anular_venta', 'ventas', $ventaId, [
            'estado' => 'cobrada',
        ], [
            'estado' => 'anulada',
            'motivo' => $motivo,
            'neto'   => $venta['total_neto'],
        ]);

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Número correlativo de venta: V-000001.
     *
     * Global y no por día: es más simple y evita el problema de
     * dos ventas con el mismo número el mismo día.
     */
    private static function siguiente_numero(): string
    {
        $ultimo = DB::valor('SELECT numero FROM ventas ORDER BY id DESC LIMIT 1');
        $siguiente = 1;

        if ($ultimo !== null && preg_match('/(\d+)$/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        }

        return 'V-' . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Una venta con sus pagos.
     */
    public static function obtener(int $ventaId): ?array
    {
        $venta = DB::one(
            'SELECT v.*, m.numero AS mesa_numero, m.zona AS mesa_zona,
                    ua.usuario AS cobrada_por, ud.usuario AS descuento_por,
                    ca.nombre AS caja_nombre, ca.estado AS caja_estado
             FROM ventas v
             LEFT JOIN comandas c ON c.id = v.comanda_id
             LEFT JOIN mesas m ON m.id = c.mesa_id
             LEFT JOIN usuarios ua ON ua.id = v.usuario_id
             LEFT JOIN usuarios ud ON ud.id = v.descuento_usuario_id
             LEFT JOIN cajas ca ON ca.id = v.caja_id
             WHERE v.id = :id',
            ['id' => $ventaId]
        );

        if ($venta === null) {
            return null;
        }

        $venta['pagos'] = DB::all(
            'SELECT p.*, m.nombre AS metodo_nombre, m.clave AS metodo_clave,
                    m.afecta_caja
             FROM venta_pagos p
             INNER JOIN metodos_pago m ON m.id = p.metodo_pago_id
             WHERE p.venta_id = :venta
             ORDER BY p.id ASC',
            ['venta' => $ventaId]
        );

        $venta['items'] = DB::all(
            "SELECT producto_nombre, precio_unitario, cantidad, importe
             FROM comanda_items
             WHERE comanda_id = :comanda AND estado = 'pendiente'
             ORDER BY id ASC",
            ['comanda' => (int) $venta['comanda_id']]
        );

        return $venta;
    }

    /**
     * Ventas de un período, para el listado.
     */
    public static function listar(int $limite = 50, int $pagina = 1): array
    {
        $limite  = max(1, min(200, $limite));
        $pagina  = max(1, $pagina);
        $offset  = ($pagina - 1) * $limite;

        return DB::all(
            "SELECT v.*, m.numero AS mesa_numero, ua.usuario AS cobrada_por,
                    ca.nombre AS caja_nombre,
                    (SELECT GROUP_CONCAT(mp.nombre ORDER BY p.id SEPARATOR ' + ')
                     FROM venta_pagos p
                     INNER JOIN metodos_pago mp ON mp.id = p.metodo_pago_id
                     WHERE p.venta_id = v.id) AS medios
             FROM ventas v
             LEFT JOIN comandas c ON c.id = v.comanda_id
             LEFT JOIN mesas m ON m.id = c.mesa_id
             LEFT JOIN usuarios ua ON ua.id = v.usuario_id
             LEFT JOIN cajas ca ON ca.id = v.caja_id
             ORDER BY v.cobrada_en DESC
             LIMIT {$limite} OFFSET {$offset}"
        );
    }

    /**
     * Totales de ventas de un rango de fechas, por estado y medio.
     */
    public static function totales(?string $desde = null, ?string $hasta = null): array
    {
        $filtro = '';
        $params = [];

        if ($desde !== null && $hasta !== null) {
            $filtro = ' AND DATE(v.cobrada_en) BETWEEN :desde AND :hasta';
            $params = ['desde' => $desde, 'hasta' => $hasta];
        }

        return DB::one(
            "SELECT COUNT(*) AS ventas,
                    COALESCE(SUM(v.total_bruto), 0) AS bruto,
                    COALESCE(SUM(v.descuento_importe), 0) AS descuentos,
                    COALESCE(SUM(v.total_neto), 0) AS neto
             FROM ventas v
             WHERE v.estado = 'cobrada' {$filtro}",
            $params
        ) ?? ['ventas' => 0, 'bruto' => 0.0, 'descuentos' => 0.0, 'neto' => 0.0];
    }

    /**
     * Ventas que están esperando pago: mesas por cobrar.
     */
    public static function pendientesDeCobro(): array
    {
        return DB::all(
            "SELECT c.id, c.numero, c.comensales, c.abierta_en,
                    m.numero AS mesa_numero, u.usuario AS vendedor,
                    COALESCE((SELECT SUM(ci.importe) FROM comanda_items ci
                              WHERE ci.comanda_id = c.id AND ci.estado = 'pendiente'), 0) AS total
             FROM comandas c
             LEFT JOIN mesas m ON m.id = c.mesa_id
             LEFT JOIN usuarios u ON u.id = c.vendedor_id
             WHERE c.estado = 'por_cobrar'
             ORDER BY c.abierta_en ASC"
        );
    }
}
