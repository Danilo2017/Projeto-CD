<?php
/**
 * Roda, em segundo plano, o MRP da última demanda gerada pelo "Gerar Linha" da tela Compra Direta Crossdocking.
 * Disparado pelo CompraDiretaCrossHandler::gerarLinha (processo PHP de linha de comando, fora da pasta pública):
 * a tela não fica presa durante o cálculo (8 a 10 minutos) e acompanha o andamento pelo mrpStatus.
 *
 * Uso: php src/cli/compra-direta-cross-mrp.php <LOGIN>
 */
if (PHP_SAPI !== 'cli') exit(1);
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';

$usuario = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9._-]{1,60}$/', $usuario)) exit(1);
set_time_limit(0);
src\handlers\Planejamento\CompraDiretaCrossHandler::executarMrp($usuario);
