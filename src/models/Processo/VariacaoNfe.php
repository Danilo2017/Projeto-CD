<?php

namespace src\models\Processo;

use core\Database;

class VariacaoNfe
{
    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listar(string $dtIni, string $dtFim, int $codEmp): array
    {
        $dtIni  = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim  = preg_replace('/[^0-9\/]/', '', $dtFim);
        $codEmp = (int) $codEmp;

        $result = Database::switchParams('focco', [
            'codEmp' => $codEmp,
            'dtIni'  => $dtIni,
            'dtFim'  => $dtFim,
        ], 'processo.variacao_nfe.listar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
