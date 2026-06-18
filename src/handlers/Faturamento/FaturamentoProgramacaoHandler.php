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

    /**
     * Resumo agregado para o dashboard — retorna ~4 números em vez de 28.900 linhas.
     *
     * Estratégia (Apache mod_php, sem fastcgi_finish_request):
     *  - Cache fresco (1h): devolve imediatamente.
     *  - Cache stale (até 2h): devolve imediatamente (aceita dado ligeiramente defasado).
     *  - Sem cache: computa sincrono (só ocorre 1×/mês ou 1ª vez — usa listar cache se existir).
     */
    public static function resumoDashboard(): array
    {
        $hoje    = new \DateTime();
        $proximo = (clone $hoje)->modify('first day of next month');
        $agMes   = $hoje->format('01/m/Y');
        $agProx  = $proximo->format('01/m/Y');

        $cacheKey = 'programacao.resumo_dashboard_' . $hoje->format('Y-m');

        $fresh = DashboardCache::get($cacheKey);
        if ($fresh !== null) return $fresh;

        $stale = DashboardCache::getStale($cacheKey);
        if ($stale !== null) {
            // FPM: refresh em background. Apache: aceita stale para não travar 220s.
            if (function_exists('fastcgi_finish_request')) {
                register_shutdown_function(function () use ($cacheKey, $agMes, $agProx) {
                    fastcgi_finish_request();
                    DashboardCache::set($cacheKey, self::_buildResumo($agMes, $agProx), 3600);
                });
            }
            return $stale;
        }

        // Sem cache algum — computa sincronamente (primeira carga do mês).
        $result = self::_buildResumo($agMes, $agProx);
        DashboardCache::set($cacheKey, $result, 3600);
        return $result;
    }

    private static function _buildResumo(string $agMes, string $agProx): array
    {
        // Aproveita cache do listar (página de programação) para não re-consultar o Oracle.
        $listar = DashboardCache::get('programacao.listar')
               ?? DashboardCache::getStale('programacao.listar');
        $dados  = ($listar && isset($listar['data'])) ? $listar['data'] : ProgramacaoPedidos::listar();

        $libMes = 0.0; $proxMes = 0.0; $semAgenda = 0.0;
        $libMesEmp = [];

        foreach ($dados as $row) {
            $val    = (float) str_replace(',', '', (string)($row['PDV_VALOR_PENDENTE'] ?? 0));
            $emprId = (string)($row['EMPR_ID'] ?? '');
            $agenda = (string)($row['AGENDA'] ?? '');

            if ($agenda === $agMes) {
                $libMes += $val;
                if ($emprId !== '') $libMesEmp[$emprId] = ($libMesEmp[$emprId] ?? 0.0) + $val;
            }
            if ($agenda === $agProx)                         $proxMes   += $val;
            if ($agenda === '' || $agenda === 'SEM AGENDA') $semAgenda += $val;
        }

        return [
            'success'     => true,
            'lib_mes'     => $libMes,
            'prox_mes'    => $proxMes,
            'sem_agenda'  => $semAgenda,
            'lib_mes_emp' => $libMesEmp,
        ];
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
