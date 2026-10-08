<?php
/**
 * includes/horarios.php
 * -----------------------------------------------------------------
 * El horario del bar y las fechas en que está cerrado (Etapa 6).
 *
 * EL PROBLEMA DE MEDIANOCHE
 *
 * Un bar abre el martes a las 20 y cierra el miércoles a las 2. Las
 * dos horas son números: "02:00" es más chico que "20:00", así que
 * un `cierra > abre` idiomático concluiría que el bar cierra antes de
 * abrir y daría esa noche por cerrada. Al revés también pasa: un
 * bar que abre a la 00 y cierra a las 2 se rompería igual.
 *
 * Por eso el cierre dice a qué día pertenece (`cierra_dia`). No se
 * deduce: lo marca el dueño, porque "se deduce" significa "a
 * veces", y un horario mal leído manda gente a un bar cerrado.
 *
 * LAS TRES COSAS QUE HACE ESTE ARCHIVO
 *
 * 1) Decir si está abierto AHORA. Mira el turno de hoy y el de ayer,
 *    porque a la 01:30 del miércoles el turno que está corriendo es
 *    el del martes.
 *
 * 2) Decir CUÁNDO ABRE LA PRÓXIMA VEZ, saltando fechas cerradas. Es
 *    lo que contesta "¿lo encuentro ahora?" sin que el visitante
 *    tenga que hacer cuentas.
 *
 * 3) Mandar los datos a Google. `schemaJsonLd()` arma el bloque
 *    `openingHoursSpecification`, que es lo que Google usa para
 *    mostrar los horarios en el buscador.
 *
 * OJO CON LOS DÍAS CERRADOS
 *
 * La fecha es el día en que el bar NO ABRE. No corta el turno
 * anterior: si el jueves está marcado y el turno del miércoles cierra
 * el jueves a las 2, ese turno sigue. Lo que no hay es un turno
 * nuevo el jueves a la noche. Al revés sería imposible cerrar una
 * sola noche, porque el turno anterior siempre "invadiría".
 * -----------------------------------------------------------------
 */

final class Horarios
{
    /** Los días en el orden en que los lee un argentino. */
    public const DIAS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    /**
     * La semana entera, con los siete días siempre presentes.
     *
     * Se devuelven los siete aunque no estén en la base, para que la
     * pantalla de configuración no tenga que inventar los que
     * faltan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function semana(): array
    {
        $filas = DB::all(
            'SELECT dia_semana, abre, cierra, cierra_dia, activo
               FROM horarios'
        );

        $porDia = [];

        foreach ($filas as $fila) {
            $porDia[(int) $fila['dia_semana']] = $fila;
        }

        $semana = [];

        foreach (self::DIAS as $numero => $nombre) {
            $fila = $porDia[$numero] ?? null;

            $abre   = $fila['abre']   ?? null;
            $cierra = $fila['cierra'] ?? null;

            // Cerrado = no hay horario, o tiene el día desactivado.
            $cerrado = $fila === null
                || (int) $fila['activo'] !== 1
                || $abre === null
                || $cierra === null;

            $semana[] = [
                'dia'        => $numero,
                'nombre'     => $nombre,
                'cerrado'    => $cerrado,
                'abre'       => $abre,
                'cierra'     => $cierra,
                'cierra_dia' => (int) ($fila['cierra_dia'] ?? 1),
                'texto'      => $cerrado
                    ? 'Cerrado'
                    : self::textoHorario($abre, $cierra, (int) ($fila['cierra_dia'] ?? 1), $numero),
            ];
        }

        return $semana;
    }

    /**
     * El horario de un día como texto: "20:00 a 02:00 (Miércoles)".
     *
     * Cuando el cierre es al día siguiente, se dice a qué día. Es la
     * parte que más confunde a la gente y no cuesta nada aclararla.
     *
     * OJO CON EL DÍA DEL CIERRE
     *
     * Sale de `diaSemana + 1`, NO de la hora de cierre. La hora dice
     * 03:00, que no dice ningún día: si se stormea, un viernes que
     * cierra a las 03:00 del sábado iba a decir "Miércoles".
     *
     * @param int|null $diaSemana 0 = domingo. Sin esto, el día del
     *                             cierre no se puede saber y no se
     *                             pone entre paréntesis.
     */
    public static function textoHorario(
        ?string $abre,
        ?string $cierra,
        int $cierraDia,
        ?int $diaSemana = null
    ): string {
        if ($abre === null || $cierra === null) {
            return 'Cerrado';
        }

        $a = self::hora($abre);
        $c = self::hora($cierra);

        if ($cierraDia === 0 || $diaSemana === null) {
            return $a . ' a ' . $c;
        }

        $nombre = self::DIAS[($diaSemana + 1) % 7];

        return $a . ' a ' . $c . ' (' . $nombre . ')';
    }

    /**
     * "20:00:00" -> "20:00". Si algo viene raro, se devuelve vacío
     * para que se note antes de guardarlo, no después.
     */
    private static function hora(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $t = DateTimeImmutable::createFromFormat('H:i:s', $valor);

        return $t === false ? '' : $t->format('H:i');
    }

    /**
     * Valida una hora del formulario. Devuelve null si no sirve.
     */
    public static function validarHora(string $valor): ?string
    {
        $valor = trim($valor);

        // Acepta "20:00", "20:00:00" y "9:30".
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $valor, $m)) {
            return null;
        }

        $hora = (int) $m[1];
        $min  = (int) $m[2];

        if ($hora > 23 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hora, $min);
    }

    /**
     * Valida y arma el horario de la semana que viene del formulario.
     *
     * POR QUÉ ESTÁ ACÁ Y NO EN EL CONTROLADOR
     *
     * Porque es una regla del negocio ("abre pero no cierra es un
     * error", "sin apertura el día queda cerrado"), y las reglas se
     * prueban sin levantar un servidor web, sin sesiones y sin
     * cookies. Cuando estaba en el controlador, probarla exigía todo
     * el rodeo del login y un POST por caso, y un error de sesión se
     * confundía con un error de la regla.
     *
     * Reglas:
     *   - un día sin hora de apertura queda cerrado;
     *   - un día con apertura pero sin cierre es un ERROR (abrir y
     *     nunca cerrar significaría "siempre abierto");
     *   - una hora que no existe es un ERROR;
     *   - "cierra al día siguiente" se deduce si el cierre es más
     *     temprano que la apertura, salvo que el dueño lo marque.
     *
     * @param  array<string, string> $datos  tal cual llega del POST
     * @return array{errores: array<string,string>, guardado: array<int,array>}
     */
    public static function validarSemana(array $datos): array
    {
        $errores  = [];
        $guardado = [];

        foreach (self::DIAS as $numero => $nombre) {
            $abreCrudo   = trim((string) ($datos['abre_' . $numero] ?? ''));
            $cierraCrudo = trim((string) ($datos['cierra_' . $numero] ?? ''));

            $abre   = $abreCrudo === '' ? null : self::validarHora($abreCrudo);
            $cierra = $cierraCrudo === '' ? null : self::validarHora($cierraCrudo);

            if ($abreCrudo !== '' && $abre === null) {
                $errores['abre_' . $numero] = 'La hora de apertura de ' . mb_strtolower($nombre) . ' no es válida.';
            }

            if ($cierraCrudo !== '' && $cierra === null) {
                $errores['cierra_' . $numero] = 'La hora de cierre de ' . mb_strtolower($nombre) . ' no es válida.';
            }

            // Sin apertura, el día queda cerrado. No es un error: es la
            // forma de decir "el lunes no abro".
            if ($abre === null) {
                $guardado[$numero] = ['abre' => null, 'cierra' => null, 'cierra_dia' => 0];

                continue;
            }

            // Abre pero no cierra: error, y ese día no se guarda.
            if ($cierra === null) {
                if (!isset($errores['cierra_' . $numero])) {
                    $errores['cierra_' . $numero] = 'Falta la hora de cierre de ' . mb_strtolower($nombre) . '.';
                }

                $guardado[$numero] = ['abre' => null, 'cierra' => null, 'cierra_dia' => 0];

                continue;
            }

            // El cierre al día siguiente se deduce, salvo que el
            // dueño lo marque: puede tener un horario rareza.
            $marcado  = ($datos['cierra_al_dia_siguiente_' . $numero] ?? '') !== '';

            // "<" y no "<=": si abre y cierra a la misma hora (09:30 a
            // 09:30) NO cruza la medianoche. Con "<=" se guardaba como
            // "cierra al dia siguiente", que es falso.
            $deducido = $cierra < $abre;

            $guardado[$numero] = [
                'abre'       => $abre,
                'cierra'     => $cierra,
                'cierra_dia' => $marcado ? 1 : ($deducido ? 1 : 0),
            ];
        }

        return ['errores' => $errores, 'guardado' => $guardado];
    }

    /**
     * Escribe en la base el horario ya validado.
     *
     * @param array<int, array{abre: ?string, cierra: ?string, cierra_dia: int}> $guardado
     */
    public static function guardarSemana(array $guardado): void
    {
        foreach ($guardado as $numero => $datos) {
            DB::query(
                'INSERT INTO horarios (dia_semana, abre, cierra, cierra_dia, activo, creado_en)
                      VALUES (:dia, :abre, :cierra, :cierraDia, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                      abre            = :abre2,
                      cierra          = :cierra2,
                      cierra_dia      = :cierraDia2,
                      activo          = 1,
                      actualizado_en  = NOW()',
                [
                    'dia'        => $numero,
                    'abre'       => $datos['abre'],
                    'cierra'     => $datos['cierra'],
                    'cierraDia'  => $datos['cierra_dia'],
                    'abre2'      => $datos['abre'],
                    'cierra2'    => $datos['cierra'],
                    'cierraDia2' => $datos['cierra_dia'],
                ]
            );
        }
    }

    // ------------------------------------------------------------
    //  Fechas cerradas
    // ------------------------------------------------------------

    /**
     * Las fechas marcadas como cerrado, de una en una.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cierres(?string $desde = null, ?string $hasta = null): array
    {
        $sql = 'SELECT id, fecha, motivo, creado_en
                  FROM horarios_cierres';
        $params = [];

        if ($desde !== null) {
            $sql .= ' WHERE fecha >= :desde';
            $params['desde'] = $desde;
        }

        if ($hasta !== null) {
            $sql .= (isset($params['desde']) ? ' AND' : ' WHERE') . ' fecha <= :hasta';
            $params['hasta'] = $hasta;
        }

        $sql .= ' ORDER BY fecha ASC';

        return DB::all($sql, $params);
    }

    /**
     * Las fechas cerradas como un mapa, para consultar rápido.
     *
     * @return array<string, string> 'Y-m-d' => motivo
     */
    public static function mapaCierres(?string $desde = null, ?string $hasta = null): array
    {
        $mapa = [];

        foreach (self::cierres($desde, $hasta) as $fila) {
            $mapa[(string) $fila['fecha']] = (string) $fila['motivo'];
        }

        return $mapa;
    }

    /**
     * Marca una fecha como cerrada.
     *
     * Volver a marcar la misma fecha no crea otra: solo actualiza el
     * motivo. Si no, un fechado de diciembre terminaría con cuatro
     * renglones del mismo día y el aviso de "cerrado" se vería raro.
     */
    public static function marcarCierre(string $fecha, string $motivo): array
    {
        $error = ['ok' => false, 'mensaje' => ''];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $error + ['mensaje' => 'La fecha no es válida.'];
        }

        $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        if ($fechaObj === false || $fechaObj->format('Y-m-d') !== $fecha) {
            return $error + ['mensaje' => 'Esa fecha no existe.'];
        }

        $motivo = trim($motivo);

        // Sin sesión (por ejemplo desde la línea de comandos) no hay
        // usuario: se guarda NULL, que la columna admite. Pasar el 0
        // que devuelve Auth::id() haría fallar la clave foránea.
        $usuario = Auth::id();
        $usuario = $usuario > 0 ? $usuario : null;

        DB::query(
            'INSERT INTO horarios_cierres (fecha, motivo, creado_en, creado_por)
                  VALUES (:fecha, :motivo, NOW(), :usuario)
             ON DUPLICATE KEY UPDATE motivo = :motivo2',
            [
                'fecha'   => $fecha,
                'motivo'  => mb_substr($motivo, 0, 120),
                'usuario' => $usuario,
                'motivo2' => mb_substr($motivo, 0, 120),
            ]
        );

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * Saca una fecha de las cerradas.
     */
    public static function sacarCierre(int $id): bool
    {
        DB::query('DELETE FROM horarios_cierres WHERE id = :id', ['id' => $id]);

        return true;
    }

    // ------------------------------------------------------------
    //  ¿Está abierto ahora?
    // ------------------------------------------------------------

    /**
     * El estado del bar en este momento.
     *
     * @return array{
     *     abierto: bool, ahora: DateTimeImmutable,
     *     cierra: ?DateTimeImmutable, proxima: ?DateTimeImmutable,
     *     motivo: string, dia_texto: string
     * }
     */
    public static function estado(?DateTimeImmutable $momento = null): array
    {
        $ahora = $momento ?? new DateTimeImmutable('now');

        $semana = self::semana();

        // Las fechas cerradas se piden UNA vez. Antes se llamaba a
        // mapaCierres() tres veces en el mismo pedido, o sea tres
        // consultas a la base para el mismo dato.
        $cerrados = self::mapaCierres(
            $ahora->format('Y-m-d'),
            $ahora->modify('+1 day')->format('Y-m-d')
        );

        // ---- 1) ¿Está corriendo el turno de hoy? ----
        $hoy = $semana[(int) $ahora->format('w')];

        if (!$hoy['cerrado'] && !isset($cerrados[(string) $ahora->format('Y-m-d')])) {
            $turno = self::turnoDelDia($hoy, $ahora);

            if ($turno !== null && $ahora >= $turno['inicio'] && $ahora < $turno['fin']) {
                return [
                    'abierto'   => true,
                    'ahora'     => $ahora,
                    'cierra'    => $turno['fin'],
                    'proxima'   => null,
                    'motivo'    => '',
                    'dia_texto' => self::textoHorario($hoy['abre'], $hoy['cierra'], $hoy['cierra_dia'], $hoy['dia']),
                ];
            }
        }

        // ---- 2) ¿Sigue vivo el turno de ayer? ----
        // A la 01:30 del miércoles, el turno que corre es el del martes.
        $ayer   = $semana[(int) $ahora->modify('-1 day')->format('w')];
        $fAyer  = (string) $ahora->modify('-1 day')->format('Y-m-d');
        $cerradoAyer = isset($cerrados[$fAyer]);

        if (!$ayer['cerrado'] && !$cerradoAyer) {
            $turno = self::turnoDelDia($ayer, $ahora->modify('-1 day'));

            if ($turno !== null && $ahora >= $turno['inicio'] && $ahora < $turno['fin']) {
                return [
                    'abierto'   => true,
                    'ahora'     => $ahora,
                    'cierra'    => $turno['fin'],
                    'proxima'   => null,
                    'motivo'    => '',
                    'dia_texto' => self::textoHorario($ayer['abre'], $ayer['cierra'], $ayer['cierra_dia'], $ayer['dia']),
                ];
            }
        }

        // ---- 3) Cerrado: cuándo vuelve a abrir ----
        $proxima = self::proximaApertura($ahora);
        $motivo  = $cerrados[(string) $ahora->format('Y-m-d')] ?? '';

        return [
            'abierto'   => false,
            'ahora'     => $ahora,
            'cierra'    => null,
            'proxima'   => $proxima,
            'motivo'    => $motivo,
            'dia_texto' => $hoy['cerrado'] ? 'Cerrado' : $hoy['texto'],
        ];
    }

    /**
     * El momento exacto en que abre y cierra un turno.
     *
     * Devuelve null si el día está cerrado. El cierre se construye a
     * partir de `cierra_dia`: si dice 1, se le suma un día, y eso es
     * lo que hace que "20:00 a 02:00" signifique la noche del martes.
     *
     * @return array{inicio: DateTimeImmutable, fin: DateTimeImmutable}|null
     */
    public static function turnoDelDia(array $dia, DateTimeImmutable $fecha): ?array
    {
        if ($dia['cerrado'] || $dia['abre'] === null || $dia['cierra'] === null) {
            return null;
        }

        $inicio = self::aHora($fecha, (string) $dia['abre']);
        $fin    = self::aHora($fecha, (string) $dia['cierra'], (int) $dia['cierra_dia']);

        return ['inicio' => $inicio, 'fin' => $fin];
    }

    /**
     * Junta una fecha con una hora "HH:MM:SS".
     *
     * `diaSiguiente` 1 quiere decir que la hora es del día después:
     * si a las 02:00 se le suma un día, queda 02:00 del día
     * siguiente, que es exactamente lo que pasa con un bar que cierra
     * después de medianoche.
     */
    private static function aHora(DateTimeImmutable $fecha, string $hora, int $diaSiguiente = 0): DateTimeImmutable
    {
        $h = (int) substr($hora, 0, 2);
        $m = (int) substr($hora, 3, 2);

        $base = $fecha->setTime($h, $m);

        if ($diaSiguiente === 1) {
            $base = $base->modify('+1 day');
        }

        return $base;
    }

    /**
     * Cuándo vuelve a abrir, saltando los días cerrados.
     *
     * Mira dos semanas adelante: siete días encontrarían "mañana",
     * pero para decir "el martes que viene" hay que ver fourteen. Si
     * en dos semanas tampoco aparece (un mes entero de vacaciones),
     * devuelve null y la página no promete nada falso.
     */
    public static function proximaApertura(?DateTimeImmutable $desde = null): ?DateTimeImmutable
    {
        $desde = $desde ?? new DateTimeImmutable('now');
        $semana = self::semana();

        $hoy = $desde->format('Y-m-d');
        $limite = $desde->modify('+14 days')->format('Y-m-d');

        $cerrados = self::mapaCierres($hoy, $limite);

        // Empieza por hoy (puede ser que falte un rato para abrir) y
        // sigue hasta dos semanas.
        for ($i = 0; $i <= 14; $i++) {
            $fecha = $desde->modify('+' . $i . ' days');
            $clave  = $fecha->format('Y-m-d');

            if (isset($cerrados[$clave])) {
                continue;
            }

            $dia = $semana[(int) $fecha->format('w')];

            if ($dia['cerrado']) {
                continue;
            }

            $turno = self::turnoDelDia($dia, $fecha);

            if ($turno === null) {
                continue;
            }

            // Hoy puede ser que ya haya pasado la apertura.
            if ($turno['inicio'] > $desde) {
                return $turno['inicio'];
            }
        }

        return null;
    }

    /**
     * El próximo día que abre, en palabras: "hoy", "mañana" o el
     * nombre del día. Para el texto de la página.
     */
    public static function textoProxima(?DateTimeImmutable $desde = null): string
    {
        $desde = $desde ?? new DateTimeImmutable('now');
        $prox  = self::proximaApertura($desde);

        if ($prox === null) {
            return 'Próximamente informamos los horarios.';
        }

        // diff()->days cuenta días completos, no fechas: si la
        // apertura es mañana a la tarde, el diff puede dar 0. Por
        // eso se comparan las dos fechas a medianoche.
        $hoyMedianoche = $desde->setTime(0, 0);
        $proxMedianoche = $prox->setTime(0, 0);
        $dias = (int) $hoyMedianoche->diff($proxMedianoche)->days;

        $cuando = match (true) {
            $dias === 0 => 'hoy a las ' . $prox->format('H:i'),
            $dias === 1 => 'mañana a las ' . $prox->format('H:i'),
            $dias < 7   => self::DIAS[(int) $prox->format('w')] . ' a las ' . $prox->format('H:i'),
            default     => 'el ' . $prox->format('d/m') . ' a las ' . $prox->format('H:i'),
        };

        return 'Abrimos ' . $cuando;
    }

    // ------------------------------------------------------------
    //  Para Google
    // ------------------------------------------------------------

    /**
     * El bloque `openingHoursSpecification` que Google lee para
     * mostrar los horarios en el buscador.
     *
     * Va como JSON-LD dentro de la página. Google lo usa para mostrar
     * "Abierto · Cierra 02:00" en los resultados.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function schemaJsonLd(): array
    {
        $salida = [];

        foreach (self::semana() as $dia) {
            if ($dia['cerrado']) {
                continue;
            }

            $abre   = self::hora($dia['abre']);
            $cierra = self::hora($dia['cierra']);

            if ($abre === '' || $cierra === '') {
                continue;
            }

            // El esquema de Google usa los nombres de día en inglés y
            // escribe la hora sin el ":00" final.
            $nombre = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(int) $dia['dia']];

            // Cuando el cierre es más temprano que la apertura
            // ("20:00 a 02:00"), Google lo interpreta solo como "cierra
            // al día siguiente". No hace falta inventar un renglón de
            // "00:00 a 02:00" para el día siguiente: eso lo tomaría
            // como que el bar abre a medianoche, que no es cierto.
            $salida[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $nombre,
                'opens'     => $abre,
                'closes'    => $cierra,
            ];
        }

        return $salida;
    }
}