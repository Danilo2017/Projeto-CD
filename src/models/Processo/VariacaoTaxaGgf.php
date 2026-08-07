<?php

namespace src\models\Processo;

use core\Database;

class VariacaoTaxaGgf
{
    public static function listar(string $mesAno): array
    {
        // $mesAno format: YYYY-MM  →  DATE 'YYYY-MM-01'
        [$ano, $mes] = explode('-', $mesAno);
        $dateStr = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);

        $result = Database::switchParams('focco', [
            'dateStr' => $dateStr,
        ], 'processo.variacao_taxa_ggf.listar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
