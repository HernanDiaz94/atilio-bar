<?php
/**
 * includes/auditoria.php
 * -----------------------------------------------------------------
 * Registro de acciones sensibles.
 *
 * Se guarda quién hizo qué, cuándo, desde qué IP y con qué datos
 * antes y después. La tabla nunca se borra: es el historial.
 *
 * Importante: los datos del usuario se guardan como texto plano en
 * json_encode, nunca objetos, para que la auditoría siga siendo
 * legible aunque el usuario se borre después.
 * -----------------------------------------------------------------
 */

/**
 * Clase Auditoria
 */
final class Auditoria
{
    /**
     * Registra una acción en la tabla auditoria.
     *
     * @param string      $accion  Qué pasó: 'crear_usuario', 'anular_venta'...
     * @param string      $entidad  Tabla o módulo afectado: 'usuarios', 'ventas'...
     * @param int|null    $entidadId Id del registro afectado
     * @param array|null  $antes    Estado previo (se convierte a JSON)
     * @param array|null  $despues  Estado nuevo (se convierte a JSON)
     */
    public static function registrar(
        string $accion,
        string $entidad,
        ?int $entidadId = null,
        ?array $antes = null,
        ?array $despues = null
    ): void {
        try {
            $usuario = Auth::logueado() ? Auth::usuario() : null;

            DB::insertar('auditoria', [
                'usuario_id'     => $usuario ? (int) $usuario['id'] : null,
                'usuario_nombre' => $usuario
                    ? $usuario['usuario'] . ' (' . $usuario['rol'] . ')'
                    : 'sistema',
                'accion'         => mb_substr($accion, 0, 60),
                'entidad'        => mb_substr($entidad, 0, 60),
                'entidad_id'     => $entidadId,
                'datos_antes'    => $antes  ? json_encode($antes, JSON_UNESCAPED_UNICODE)  : null,
                'datos_despues'  => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                'ip'             => ip_cliente(),
                'user_agent'     => user_agent(),
                'creado_en'      => ahora(),
            ]);
        } catch (Throwable $e) {
            // Si falla la auditoría no queremos tumbar la operación principal.
            // Solo queda registrado en el log de errores.
            error_log('Auditoria::registrar fallo: ' . $e->getMessage());
        }
    }

    /**
     * Lista los últimos movimientos, para la pantalla de auditoría
     * y para el dashboard del administrador.
     */
    public static function ultimos(int $limite = 20, string $entidad = ''): array
    {
        $limite = max(1, min(200, $limite));

        if ($entidad !== '') {
            return DB::all(
                "SELECT * FROM auditoria
                 WHERE entidad = :entidad
                 ORDER BY id DESC
                 LIMIT {$limite}",
                ['entidad' => $entidad]
            );
        }

        // El límite va interpolado pero ya fue casteado a entero
        // y acotado arriba, así que no hay inyección posible.
        return DB::all("SELECT * FROM auditoria ORDER BY id DESC LIMIT {$limite}");
    }
}