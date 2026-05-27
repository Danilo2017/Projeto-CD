<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\ProgramacaoPedidos;
use src\utils\DashboardCache;

class FaturamentoProgramacaoHandler
{
    private static function cached(string $key, int $ttl, callable $fn): array
    {
        $fresh = DashboardCache::get($key);
        if ($fresh !== null) {
            return $fresh;
        }

        $stale = DashboardCache::getStale($key);
        if ($stale !== null && function_exists('fastcgi_finish_request')) {
            register_shutdown_function(function () use ($key, $ttl, $fn) {
                fastcgi_finish_request();
                DashboardCache::set($key, $fn(), $ttl);
            });
            return $stale;
        }

        $result = $fn();
        DashboardCache::set($key, $result, $ttl);
        return $result;
    }

    public static function listar(): array
    {
        return self::cached('programacao.listar', 240, function () {
            $dados = ProgramacaoPedidos::listar();
            return [
                'success' => true,
                'data'    => $dados,
                'total'   => count($dados),
            ];
        });
    }

    public static function ocupacao(): array
    {
        return self::cached('programacao.ocupacao', 240, function () {
            $tanques   = ProgramacaoPedidos::listarTanques();
            $diasUteis = ProgramacaoPedidos::listarDiasUteis();
            return [
                'success'   => true,
                'tanques'   => $tanques,
                'diasUteis' => $diasUteis,
            ];
        });
    }
}
