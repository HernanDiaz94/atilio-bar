<?php
/**
 * modules/horarios/index.php
 * -----------------------------------------------------------------
 * El horario del bar y los días cerrados.
 *
 * Solo el administrador: es un dato que cambia pocas veces y hay que
 * que quede bien, porque es lo primero que consulta la gente en la
 * página pública.
 * -----------------------------------------------------------------
 */

$semana   = Horarios::semana();
$estado   = Horarios::estado();

// Los cierres de este año y del que viene, que es lo que se va
// marcando con el tiempo. Tres años por si el dueño quiere dejar
// cargadas las vacaciones del verano con anticipación.
$hoy   = new DateTimeImmutable('today');
$desde = $hoy->modify('-1 year')->format('Y-m-d');
$hasta = $hoy->modify('+3 years')->format('Y-m-d');

$cierres = Horarios::cierres($desde, $hasta);

// Los valores que se editan en pantalla, para que al volver de un
// error no se pierda lo que ya estaba escrito.
$entradas = recuperar_entradas();

$titulo    = 'Horarios';
$subtitulo = 'Lo que ve la gente en la página pública';

$accionesCabecera = '<a href="' . e(url('publico')) . '" class="btn btn-atilio-amarillo" target="_blank" rel="noopener">'
    . '<i class="bi bi-box-arrow-up-right"></i> Ver la página pública</a>';

require APP_ROOT . '/modules/horarios/vistas/index.php';