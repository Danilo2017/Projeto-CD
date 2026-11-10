<?php

namespace src\models\Custo;

use core\Database;
use src\utils\DashboardCache;

/**
 * Dashboard de Custo de Entrada — somente leitura.
 * Itens das notas de compra do mês com o frete ligado (nota da transportadora) e o custo médio (TCUSTO_MEDIO).
 * SQL na FOCCO3I.GAZIN_SQLS (registro em database/gazin-sqls/2026-11-06-custo.php).
 */
class CustoEntrada
{
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    /** Mês atual 30 min; mês anterior 12h; meses mais antigos 7 dias (frete pode chegar depois da compra). */
    private static function ttl(string $anoMes): int
    {
        return $anoMes >= date('Y-m') ? 1800 : ($anoMes >= date('Y-m', strtotime('first day of last month')) ? 43200 : 604800);
    }

    /** Empresas (ID, código, razão social) — SQL já existente na GAZIN_SQLS. */
    public static function empresas(): array
    {
        $val = DashboardCache::get('custo_entrada_empresas');
        if ($val !== null) return $val;
        $val = self::sql('acesso.empresa.listar');
        DashboardCache::set('custo_entrada_empresas', $val, 43200);
        return $val;
    }

    /** Razão de custo do item (TCUSTO_ESTQ) entre duas datas 'YYYY-MM-DD', em ordem de lançamento. */
    public static function razao(int $emprId, int $itestqId, int $mascId, string $dtIni, string $dtFim): array
    {
        foreach ([$dtIni, $dtFim] as $d) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) throw new \InvalidArgumentException("Data inválida: $d", 400);
        }
        if ($emprId <= 0 || $itestqId <= 0 || $mascId < 0) throw new \InvalidArgumentException('Item inválido', 400);
        return self::sql('custo.entradaNf.razaoItem', [
            'empr_id' => $emprId, 'itestq_id' => $itestqId, 'masc_id' => $mascId, 'dt_ini' => "'$dtIni'", 'dt_fim' => "'$dtFim'",
        ]);
    }

    /** Itens das notas de compra do mês (uma linha por item da nota). */
    public static function itensMes(int $emprId, string $anoMes): array
    {
        if ($emprId <= 0) throw new \InvalidArgumentException('Empresa inválida', 400);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) throw new \InvalidArgumentException("Ano/mês inválido: $anoMes", 400);

        $chave = "custo_entrada_itens_{$emprId}_{$anoMes}";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;

        $val = self::sql('custo.entradaNf.listarItens', ['empr_id' => $emprId, 'ano_mes' => "'$anoMes'"]);
        DashboardCache::set($chave, $val, self::ttl($anoMes));
        return $val;
    }
}
