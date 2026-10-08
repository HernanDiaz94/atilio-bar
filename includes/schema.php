<?php
/**
 * includes/schema.php
 * -----------------------------------------------------------------
 * Detecta que falte alguna migración y lo dice con todas las letras.
 *
 * POR QUÉ ESTO EXISTE
 *
 * La aplicación se entrega por etapas y cada una trae su archivo
 * SQL. Si alguien sube los archivos pero olvida importar uno, la
 * primera pantalla que consulta esa tabla se cae con un error 500
 * en blanco y no dice nada. En un hosting compartido eso además
 * queda guardado en un log que el dueño no va a leer nunca.
 *
 * Con esta comprobación, lo que pasa es que el sistema abre con un
 * cartel claro: "falta importar sql/etapa4.sql". Eso se puede
 * resolver en un minuto.
 *
 * POR QUÉ NO ES CARO
 *
 * La comprobación es un solo SHOW TABLES y el resultado se guarda
 * en una variable estática durante la petición. Con la base ya
 * conectada no se vuelve a consultar.
 * -----------------------------------------------------------------
 */

final class Schema
{
    /**
     * Tablas que tienen que existir para que la aplicación ande.
     *
     * Se chequean TODAS, no solo las de la última etapa: así,
     * si alguien sube el proyecto entero pero no importa ningún
     * SQL, el mensaje dice exactamente qué falta.
     */
    private const REQUERIDAS = [
        // Etapa 1
        'usuarios' => 'etapa 1 (install.php)',
        'configuracion' => 'etapa 1 (install.php)',

        // Etapa 2
        'productos' => 'etapa 2 (sql/etapa2.sql)',
        'stock_movimientos' => 'etapa 2 (sql/etapa2.sql)',

        // Etapa 3
        'mesas' => 'etapa 3 (sql/etapa3.sql)',
        'comandas' => 'etapa 3 (sql/etapa3.sql)',

        // Etapa 6
        'horarios' => 'etapa 6 (sql/etapa6.sql)',

        // Etapa 4
        'metodos_pago' => 'etapa 4 (sql/etapa4.sql)',
        'cajas' => 'etapa 4 (sql/etapa4.sql)',
        'ventas' => 'etapa 4 (sql/etapa4.sql)',
    ];

    /**
     * Columnas que tienen que existir, y de qué archivo salen.
     *
     * PUESTEN ESTAR Y POR QUÉ IMPORTA
     *
     * La Etapa 5 agrega `costo_unitario` a `comanda_items` porque al
     * cargar un producto a una mesa se guarda el costo de ese momento.
     * Si los archivos PHP se suben pero `sql/etapa5.sql` no se importa,
     * la columna no esta: no avisa ningun error de sintaxis ni de PHP,
     * simplemente falla el INSERT al agregar un producto y el mozo no
     * puede cargar el pedido.
     *
     * Es el peor tipo de falla: silenciosa, y recien aparece cuando
     * alguien esta atendiendo una mesa. Por eso se chequea al
     * arrancar y se dice qué archivo falta, como con las tablas.
     *
     * @var array<string, string> "tabla.columna" => archivo
     */
    private const COLUMNAS = [
        'comanda_items.costo_unitario' => 'etapa 5 (sql/etapa5.sql)',
    ];

    /**
     * Se puede siquiera intentar leer el catalogo de tablas.
     *
     * Si la conexion falla esto da falso y deja que el manejo de
     * errores de la aplicacion muestre su propio mensaje, en vez de
     * reemplazarlo por uno de migración.
     */
    public static function puedeHaberTablas(): bool
    {
        try {
            DB::valor('SELECT 1');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Tablas que faltan, indexadas por archivo de migración.
     *
     * @return array<string, array<int, string>> archivo => tablas
     */
    public static function faltan(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            $filas     = DB::all('SHOW TABLES');
            $existentes = [];

            foreach ($filas as $fila) {
                $existentes[array_values($fila)[0]] = true;
            }
        } catch (Throwable $e) {
            // Si ni siquiera se puede leer el catálogo, el problema
            // es la conexión: lo reporta el propio manejo de
            // errores de arriba.
            return [];
        }

        $cache = [];

        foreach (self::REQUERIDAS as $tabla => $archivo) {
            if (isset($existentes[$tabla])) {
                continue;
            }

            $cache[$archivo][] = $tabla;
        }

        // Las columnas solo se miran si su tabla esta: si la tabla no
        // esta, ya se aviso de eso y la columna no viene al caso.
        foreach (self::COLUMNAS as $completa => $archivo) {
            [$tabla, $columna] = explode('.', $completa, 2);

            if (!isset($existentes[$tabla])) {
                continue;
            }

            $tiene = DB::valor(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = :tabla
                    AND COLUMN_NAME  = :columna',
                ['tabla' => $tabla, 'columna' => $columna]
            );

            if ((int) $tiene > 0) {
                continue;
            }

            $cache[$archivo][] = $tabla . '.' . $columna;
        }

        return $cache;
    }

    /**
     * ¿La aplicación tiene todo lo que necesita?
     */
    public static function listo(): bool
    {
        return self::faltan() === [];
    }

    /**
     * Muestra el cartel de "falta importar" y corta la ejecución.
     *
     * Se llama desde el arranque antes de que cualquier pantalla
     * toque una tabla que todavía no existe.
     */
    public static function mostrarAviso(array $faltan): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(503);

        $lineas = '';

        foreach ($faltan as $archivo => $tablas) {
            $lineas .= '<li><code>' . htmlspecialchars($archivo, ENT_QUOTES, 'UTF-8') . '</code>'
                . ' &rarr; falta: ' . htmlspecialchars(implode(', ', $tablas), ENT_QUOTES, 'UTF-8')
                . '</li>' . "\n";
        }

        echo '<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Falta importar una migracion &middot; Atilio</title>
<style>
    body { font-family: system-ui, sans-serif; background: #FFF9E6; color: #2F4F2F;
           margin: 0; padding: 2rem 1rem; }
    main { max-width: 640px; margin: 0 auto; background: #fff; border: 3px solid #B08A00;
           border-radius: 12px; padding: 2rem; }
    h1 { font-size: 1.4rem; margin: 0 0 1rem; }
    ul { padding-left: 1.2rem; }
    li { margin-bottom: .5rem; }
    code { background: #F3F3F3; padding: .1rem .35rem; border-radius: 4px; }
    .pasos { background: #F0F7EC; border: 2px solid #6E9E63; border-radius: 8px;
             padding: 1rem; margin-top: 1.5rem; }
    .pasos p { margin: .25rem 0; }
</style>
</head>
<body>
<main>
    <h1>Falta importar una migracion de la base de datos</h1>

    <p>
        Los archivos PHP estan subidos, pero a la base le faltan tablas.
        Esto pasa cuando se sube el proyecto sin importar los archivos
        <code>sql/etapaN.sql</code> correspondientes.
    </p>

    <ul>' . $lineas . '</ul>

    <div class="pasos">
        <p><strong>Como se arregla:</strong></p>
        <p>1. Entr&aacute; a <strong>phpMyAdmin</strong> en el hosting.</p>
        <p>2. Eleg&iacute; tu base de datos.</p>
        <p>3. Entr&aacute; a la pestan&aacute; <strong>Importar</strong>.</p>
        <p>4. Eleg&iacute; el archivo <code>sql/etapaN.sql</code> que falta y presion&aacute; <strong>Continuar</strong>.</p>
        <p>5. Volv&eacute; a cargar esta p&aacute;gina.</p>
        <p style="margin-top:.75rem">Es seguro correrlos otra vez: no borran nada.</p>
    </div>
</main>
</body>
</html>';

        exit;
    }
}
