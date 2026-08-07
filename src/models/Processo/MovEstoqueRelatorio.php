<?php

namespace src\models\Processo;

use core\Database;

class MovEstoqueRelatorio
{
    public static function listar(string $dtIni, string $dtFim): array
    {
        $dtIni = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim = preg_replace('/[^0-9\/]/', '', $dtFim);

        $result = Database::switchParams('focco', [
            'dtIni' => $dtIni,
            'dtFim' => $dtFim,
        ], 'processo.mov_estoque.relatorio', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
