<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Controlador frontal (front controller) de "Atilio".
 *
 * Todas las pantallas de la aplicación se piden por acá:
 *   index.php?r=dashboard
 *   index.php?r=usuarios
 *   index.php?r=usuarios/formulario&id=3
 *
 * No usamos .htaccess para reescribir URLs porque en ByetHost el
 * módulo mod_rewrite no siempre está disponible. Con el parámetro ?r=
 * la app funciona en la raíz de htdocs o en una subcarpeta sin tocar
 * nada más.
 *
 * install.php y login.php son los dos únicos archivos que se acceden
 * de forma directa, y ambos llaman antes a bootstrap.php.
 * -----------------------------------------------------------------
 */

// 1) Arranque: configuración, errores, base de datos, helpers, sesión.
require_once __DIR__ . '/includes/bootstrap.php';

// 2) Resolver la ruta pedida y ejecutar el módulo correspondiente.
//    El router se ocupa de: instalar, sesión, rol, CSRF y 404/403.
router_resolver();