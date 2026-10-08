<?php
/**
 * modules/caja/abrir.php
 * -----------------------------------------------------------------
 * Abrir el turno de caja, o registrar una entrada o salida de
 * plata.
 *
 * Son dos cosas en la misma pantalla porque se usan juntas: al
 * abrir el turno muchas veces hay que dejar asentado un gasto que
 * se pagó de la caja antes de abrirla (el hielo, el gas), y no
 * vale la pena hacer dos pantallas.
 *
 * GET  muestra la pantalla.
 * POST abre la caja, o guarda el movimiento si vino uno.
 *
 * Solo el administrador abre la caja. Registrar un movimiento lo
 * puede hacer cualquiera del turno.
 *
 * -----------------------------------------------------------------
 * POR QUE LA RUTA ES DE 'vendedor' Y NO DE 'administrador'
 *
 * La pantalla es doble: si la caja esta cerrada muestra el formulario
 * para abrirla, y si ya esta abierta muestra SOLO el de entrada o
 * salida de plata. El boton "Registrar movimiento" de la pantalla de
 * caja lo ven los dos y apunta aca.
 *
 * Con la ruta en 'administrador', el vendedor que le daba click a ese
 * boton se comia un 403 "Acceso denegado": el sistema le mostraba un
 * boton y despues le decia que no podia usarlo. Por eso la ruta la
 * abre cualquiera, y lo que es solo del administrador (abrir la caja)
 * se controla mas abajo, que es donde se puede distinguir la accion.
 * -----------------------------------------------------------------
 */

$accion = post('accion', 'abrir');

// ------------------------------------------------------------
// Abrir la caja es solo del administrador.
//
// Se controla aca y no en el router porque el router solo puede
// decidir si la PANTALLA se abre, y esta pantalla la usan los dos
// roles: la de abrir es del admin y la de movimiento es de
// cualquiera del turno.
//
// OJO CON EL es_post(): sin esta comilla el vendedor no llega nunca
// a ver el formulario. En un GET, post('accion') devuelve 'abrir'
// (que es el valor por defecto), asi que frenar por el POST expulsa
// al vendedor antes de que vea la pantalla. Solo se frena el POST
// que intenta ABRIR la caja.
//
// Sin la guarda, abrir la ruta para el vendedor abriria la puerta de
// abrir la caja tambien, que es lo contrario de lo que dice el
// comentario de arriba.
// ------------------------------------------------------------
// Registrar una entrada o salida
//
// OJO: la guarda de "solo el administrador abre la caja" que estaba
// aca se SACO. Dejaba al vendedor afuera antes de que llegara a la
// comprobacion de la clave, asi que el mensaje que veia era "Abrir la
// caja lo hace el administrador" y nunca le de chance de escribir la
// clave que el mismo administrador le habia dado.
//
// La regla ahora es una sola, y esta mas abajo: la clave.
// ------------------------------------------------------------
if ($accion === 'movimiento') {
    $caja = Caja::abierta();

    if ($caja === null) {
        flash_error('No hay ninguna caja abierta.');
        redirigir('caja/abrir');
    }

    $tipo     = post('tipo');
    $importe  = a_numero(post('importe', '0'));
    $motivo   = post('motivo');

    $resultado = Caja::movimiento((int) $caja['id'], $tipo, $importe, $motivo);

    if (!$resultado['ok']) {
        guardar_entradas([
            'tipo'     => $tipo,
            'importe'  => cantidad($importe, 2),
            'motivo'   => $motivo,
        ]);

        flash_error($resultado['mensaje']);
        redirigir('caja/abrir');
    }

    Auditoria::registrar('movimiento_caja', 'cajas', (int) $caja['id'], null, [
        'tipo'    => $tipo,
        'importe' => $importe,
        'motivo'  => $motivo,
    ]);

    flash_exito(
        (Caja::MOVIMIENTOS[$tipo] ?? 'Movimiento') . ': '
        . money($tipo === 'ingreso' ? $importe : -$importe)
    );

    redirigir('caja');
}

// ------------------------------------------------------------
// La clave de caja
//
// El administrador no la pone: es suya. El vendedor si, y sin clave
// correcta no abre el turno.
//
// Va ANTES de mirar los campos del formulario a proposito: si se
// validara despues, un POST armado a mano sin el campo de la clave
// pasaria el control entero.
// ------------------------------------------------------------
if (es_post() && $accion === 'abrir' && !Auth::es_admin()) {
    if (!Caja::clave_configurada()) {
        flash_error('Todavia no hay clave de caja. Pedile al administrador que la ponga en Caja, arriba a la derecha.');
        redirigir('caja');
    }

    if (!Caja::clave_verificar(post_crudo('clave_caja'))) {
        $estado = Caja::clave_estado();

        if ($estado['frenado']) {
            flash_error('Clave incorrecta. Espera ' . $estado['minutos'] . ' minutos antes de volver a intentar.');
        } else {
            $quedan = max(0, Caja::CLAVE_INTENTOS - $estado['intentos']);
            flash_error('La clave de caja no es correcta. Te ' . ($quedan === 1 ? 'queda 1 intento' : 'quedan ' . $quedan . ' intentos') . '.');
        }

        redirigir('caja');
    }
}

// ------------------------------------------------------------
// Abrir la caja
// ------------------------------------------------------------
$entradas = recuperar_entradas();
$erroresGuardados = tomar_errores();
$errores = $erroresGuardados['campos'];

if (es_post() && $accion === 'abrir') {
    $nombre    = post('nombre');
    $importe   = a_numero(post('apertura_importe', '0'));
    $observacion = post('apertura_observacion');

    if ($importe <= 0) {
        $errores['apertura_importe'] = 'Contá cuánta plata hay en el cajón.';
    }

    if (!empty($errores)) {
        guardar_entradas([
            'nombre'               => $nombre,
            'apertura_importe'     => cantidad($importe, 2),
            'apertura_observacion' => $observacion,
        ]);

        guardar_errores($errores);
        flash_error('Revisá los campos marcados.');
        redirigir('caja/abrir');
    }

    $resultado = Caja::abrir($nombre, $importe, $observacion);

    if (!$resultado['ok']) {
        guardar_entradas([
            'nombre'               => $nombre,
            'apertura_importe'     => cantidad($importe, 2),
            'apertura_observacion' => $observacion,
        ]);

        guardar_errores([], [$resultado['mensaje']]);
        flash_error($resultado['mensaje']);
        redirigir('caja/abrir');
    }

    flash_exito('Caja abierta con ' . money($importe) . '. Ya se puede cobrar.');
    redirigir('caja');
}

// ------------------------------------------------------------
// GET: la pantalla
//
// OJO: aca ya NO hay guarda de rol. Antes el vendedor que no
// encontraba ninguna caja abierta se lo mandaba de vuelta a la caja,
// porque abrir el turno era solo del administrador.
//
// Eso quedo viejo cuando se le permitio abrir al vendedor con la
// clave: si se dejaba la guarda, el vendedor era expulsado de la
// pantalla justo cuando era el momento de abrir el turno, que es
// cuando mas la necesita.
//
// Ahora lo que decide es la clave, y eso se resuelve mas arriba, en
// el POST.
// ------------------------------------------------------------
$caja = Caja::abierta();

if ($caja !== null) {
    // Si la caja ya está abierta, esta pantalla solo sirve para
    // registrar movimientos: se manda a la de caja.
    $titulo    = 'Movimiento de caja';
    $subtitulo = 'Entró o salió plata del cajón';

    $volver = url('caja');
    $soloMovimiento = true;
} else {
    $titulo    = 'Abrir caja';
    $subtitulo = 'Contá la plata del cajón para empezar el turno';
    $volver = url('caja');
    $soloMovimiento = false;
}

require APP_ROOT . '/modules/caja/vistas/abrir.php';
