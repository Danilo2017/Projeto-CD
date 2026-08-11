<?php

namespace src\models\Faturamento;

use core\Database;

class EficienciaUep
{
    public static function listar(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.eficiencia.uep', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function detalhe(int $emprId, string $classificacao): array
    {
        $classSafe = str_replace("'", "''", $classificacao);
        $result = Database::switchParams('focco', [
            'empr_id'       => $emprId,
            'classificacao' => $classSafe,
        ], 'faturamento.eficiencia.uep.detalhe', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
