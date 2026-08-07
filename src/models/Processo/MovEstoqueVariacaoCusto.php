<?php

namespace src\models\Processo;

use core\Database;

class MovEstoqueVariacaoCusto
{
    public static function listar(string $dtIni, string $dtFim, string $dtIniAnt, string $dtFimAnt, array $emprIds = []): array
    {
        $dtIni    = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim    = preg_replace('/[^0-9\/]/', '', $dtFim);
        $dtIniAnt = preg_replace('/[^0-9\/]/', '', $dtIniAnt);
        $dtFimAnt = preg_replace('/[^0-9\/]/', '', $dtFimAnt);
        $emprIds  = array_values(array_filter(array_map('intval', $emprIds), fn($id) => $id > 0));

        $filtroEmpr = !empty($emprIds) ? 'AND VW.EMPR_ID IN (' . implode(',', $emprIds) . ')' : '';

        $result = Database::switchParams('focco', [
            'dtIni'      => $dtIni,
            'dtFim'      => $dtFim,
            'dtIniAnt'   => $dtIniAnt,
            'dtFimAnt'   => $dtFimAnt,
            'filtro_empr'=> $filtroEmpr,
        ], 'processo.mov_estoque.variacao_custo', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
