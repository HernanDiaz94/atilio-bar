-- ==================================================================
--  REINICIAR-BASE-PRODUCCION.sql
--  Deja la base de MySQL VACÍA, para poder importar de nuevo.
--
-- ------------------------------------------------------------------
--  CUÁNDO USARLO
--
--  Cuando la importación de ATILIO-INSTALAR.sql se cortó a mitad de
--  camino y quedaron tablas a medias. Con tablas sueltas ya creadas,
--  la siguiente importación puede fallar.
--
--  ⚠ BORRA TODO. En una base recien instalada no hay nada que
--  perder. NO lo corras si ya estás vendiendo: te deja la base sin
--  mesas, sin productos, sin ventas y sin usuarios, y hay que
--  instalar todo de cero.
--
-- ------------------------------------------------------------------
--  CÓMO USARLO (phpMyAdmin → tu base → pestaña SQL → Continuar)
--
--  1. Corré este archivo.
--  2. Después importá ATILIO-INSTALAR.sql.
--  3. Después entrá a install.php para crear los usuarios.
--
-- ------------------------------------------------------------------
--  POR QUÉ ESTE ARCHIVO EXISTE
--
--  ByetHost no deja DROP DATABASE al usuario normal: solo puede
--  borrar tablas. Por eso esto las borra una por una.
-- ==================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `horarios_cierres`;
DROP TABLE IF EXISTS `horarios`;
DROP TABLE IF EXISTS `venta_pagos`;
DROP TABLE IF EXISTS `ventas`;
DROP TABLE IF EXISTS `caja_movimientos`;
DROP TABLE IF EXISTS `cajas`;
DROP TABLE IF EXISTS `metodos_pago`;
DROP TABLE IF EXISTS `reservas`;
DROP TABLE IF EXISTS `comanda_item_stock`;
DROP TABLE IF EXISTS `comanda_items`;
DROP TABLE IF EXISTS `comandas`;
DROP TABLE IF EXISTS `mesas`;
DROP TABLE IF EXISTS `stock_movimientos`;
DROP TABLE IF EXISTS `compras_detalle`;
DROP TABLE IF EXISTS `compras`;
DROP TABLE IF EXISTS `combo_componentes`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `auditoria`;
DROP TABLE IF EXISTS `configuracion`;
DROP TABLE IF EXISTS `usuarios_login_intentos`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==================================================================
--  FIN. La base quedó vacía. Ahora importá ATILIO-INSTALAR.sql.
-- ==================================================================
