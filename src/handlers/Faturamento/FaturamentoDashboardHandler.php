<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\FaturamentoMensal;
use src\models\Faturamento\PainelVendas;
use src\models\Faturamento\Pedidos;
use src\utils\DashboardCache;

/**
 * Handler do Dashboard de Faturamento Indústrias
 *
 * Usa stale-while-revalidate:
 *   - TTL fresco = 4 min  → dado expira antes do auto-refresh de 5 min
 *   - Quando expirado: retorna dado antigo INSTANTANEAMENTE + atualiza Oracle em background
 *   - Usuário nunca espera 50s após o primeiro acesso
 *
 * TTLs (fresco):
 *   - resumo, painel, pedidos, planejado, programação → 4 min
 *   - vlr-faltante-carga (query pesada)               → 4 min
 *   - dias-mes / dias-mes-empresa (dado mensal)        → 60 min
 */
class FaturamentoDashboardHandler
{
    /**
     * Cache com stale-while-revalidate.
     * Se dado fresco → retorna imediatamente.
     * Se expirado + stale disponível → retorna stale + agenda refresh em background (FPM).
     * Se sem cache → busca Oracle de forma síncrona.
     */
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

    public static function getResumoMensal(): array
    {
        return self::cached('dashboard.resumo_mensal', 240, function () {
            $dados = FaturamentoMensal::getResumoMensal();
            return [
                'success'            => true,
                'data'               => $dados,
                'total'              => count($dados),
                'ultima_atualizacao' => date('Y-m-d H:i:s'),
            ];
        });
    }

    public static function getPainelVendas(): array
    {
        return self::cached('dashboard.painel_vendas', 240, function () {
            $dados = PainelVendas::getPainelVendas();
            return [
                'sucesso'            => true,
                'total_registros'    => count($dados),
                'ultima_atualizacao' => date('Y-m-d H:i:s'),
                'dados'              => $dados,
            ];
        });
    }

    public static function getPedidos(): array
    {
        return self::cached('dashboard.pedidos', 240, function () {
            $dados = Pedidos::getPedidosCarteira();
            return [
                'success'            => true,
                'data'               => $dados,
                'ultima_atualizacao' => date('Y-m-d H:i:s'),
            ];
        });
    }

    public static function getPedidosPlanejado(): array
    {
        return self::cached('dashboard.pedidos_planejado', 240, function () {
            $dados = Pedidos::getPedidosPlanejado();
            return [
                'success'            => true,
                'data'               => $dados,
                'ultima_atualizacao' => date('Y-m-d H:i:s'),
            ];
        });
    }

    public static function getDiasMes(): array
    {
        $key = 'dashboard.dias_mes_' . date('Y-m');
        return self::cached($key, 3600, function () {
            $dados = FaturamentoMensal::getDiasMes();
            return ['success' => true, 'data' => $dados];
        });
    }

    public static function getDiasMesEmpresa(): array
    {
        $key = 'dashboard.dias_mes_empresa_' . date('Y-m');
        return self::cached($key, 3600, function () {
            $dados = FaturamentoMensal::getDiasMesEmpresa();
            return ['success' => true, 'data' => $dados];
        });
    }

    public static function getVlrFaltanteCarga(): array
    {
        return self::cached('dashboard.vlr_faltante_carga', 240, function () {
            $dados = PainelVendas::getVlrFaltanteCarga();
            return ['success' => true, 'data' => $dados];
        });
    }
}
