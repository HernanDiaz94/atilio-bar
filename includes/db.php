<?php
/**
 * includes/db.php
 * -----------------------------------------------------------------
 * Conexión a la base de datos con PDO y funciones de consulta.
 *
 * Regla del proyecto: TODO acceso a datos pasa por acá y siempre
 * con consultas preparadas. Nunca se concatena texto del usuario
 * dentro de un SQL.
 * -----------------------------------------------------------------
 */

/**
 * Clase DB: patrón Singleton, una única conexión por request.
 */
final class DB
{
    /** @var PDO|null Conexión compartida */
    private static ?PDO $pdo = null;

    /** @var bool|null Cache del chequeo "¿ya está instalada la app?" */
    private static ?bool $instalado = null;

    /**
     * Devuelve la conexión PDO, creándola la primera vez.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                // Ejecutar de verdad las consultas preparadas del lado del servidor.
                // Con emulación activada hay riesgos de inyección.
                PDO::ATTR_EMULATE_PREPARES => false,
                // Los errores se lanzan como excepciones y no como warnings.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Traer todo como string y formatearlo nosotros con number_format().
                // Evita sorpresas de punto flotante y de ceros agregados.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Fuerza utf8mb4 y horario de Buenos Aires en la sesión de MySQL.
            self::$pdo->exec("SET NAMES '" . DB_CHARSET . "'");
            self::$pdo->exec("SET time_zone = '-03:00'");
        }

        return self::$pdo;
    }

    /**
     * Ejecuta una consulta preparada y devuelve el statement.
     *
     * REGLA IMPORTANTE: con EMULATE_PREPARES apagado, MySQL no
     * admite repetir el mismo placeholder con nombre en una misma
     * consulta. Esto:
     *
     *   WHERE nombre LIKE :buscar OR apellido LIKE :buscar   <-- MAL
     *   WHERE nombre LIKE :b1    OR apellido LIKE :b2       <-- BIEN
     *
     * falla con "SQLSTATE[HY093]: Invalid parameter number".
     * Con placeholders posicionales (?) tampoco hay problema,
     * porque ahí se pasa el valor una vez por cada ?
     *
     * @param string $sql    Consulta con placeholders ? o :nombre
     * @param array  $params Valores de los placeholders
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Devuelve todas las filas de una consulta como array asociativo.
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Devuelve la primera fila o null si no hay resultados.
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $fila = self::query($sql, $params)->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Devuelve el primer valor de la primera fila (útil para COUNT, SUM, MAX...).
     */
    public static function valor(string $sql, array $params = [], mixed $porDefecto = null): mixed
    {
        $valor = self::query($sql, $params)->fetchColumn();

        return $valor === false ? $porDefecto : $valor;
    }

    /**
     * Inserta una fila y devuelve el id generado.
     */
    public static function insertar(string $tabla, array $datos): int
    {
        $columnas = array_keys($datos);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $tabla,
            '`' . implode('`, `', $columnas) . '`',
            ':' . implode(', :', $columnas)
        );

        self::query($sql, $datos);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Actualiza filas de una tabla por su id.
     */
    public static function actualizar(string $tabla, int $id, array $datos): int
    {
        if (empty($datos)) {
            return 0;
        }

        $asignaciones = [];
        $params = ['id' => $id];

        foreach ($datos as $columna => $valor) {
            $asignaciones[] = sprintf('`%s` = :%s', $columna, $columna);
            $params[$columna] = $valor;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = :id',
            $tabla,
            implode(', ', $asignaciones)
        );

        return self::query($sql, $params)->rowCount();
    }

    /**
     * Elimina una fila por su id.
     */
    public static function eliminar(string $tabla, int $id): int
    {
        return self::query(sprintf('DELETE FROM `%s` WHERE `id` = :id', $tabla), ['id' => $id])
            ->rowCount();
    }

    /**
     * ¿La aplicación ya está instalada (existe la tabla usuarios con datos)?
     *
     * Se usa para no romper la app antes de correr install.php y para
     * saber si tiene sentido mostrar el login.
     */
    public static function instalado(): bool
    {
        if (self::$instalado !== null) {
            return self::$instalado;
        }

        try {
            $cantidad = self::valor('SELECT COUNT(*) FROM usuarios');
            self::$instalado = ((int) $cantidad) > 0;
        } catch (Throwable $e) {
            // La tabla no existe todavía: la app todavía no se instaló.
            self::$instalado = false;
        }

        return self::$instalado;
    }

    /** Permite reiniciar la caché de "¿instalado?" (lo usa install.php). */
    public static function resetearCacheInstalado(): void
    {
        self::$instalado = null;
    }

    // ------------------------------------------------------------
    // Transacciones: se usan en operaciones que tocan varias tablas
    // (por ejemplo cobrar una venta: venta + items + pagos + stock).
    // ------------------------------------------------------------

    public static function iniciarTransaccion(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function confirmarTransaccion(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function deshacerTransaccion(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * Ejecuta un callback dentro de una transacción.
     * Si el callback lanza una excepción, se deshace todo.
     */
    public static function enTransaccion(callable $callback): mixed
    {
        self::iniciarTransaccion();

        try {
            $resultado = $callback();
            self::confirmarTransaccion();

            return $resultado;
        } catch (Throwable $e) {
            self::deshacerTransaccion();
            throw $e;
        }
    }
}