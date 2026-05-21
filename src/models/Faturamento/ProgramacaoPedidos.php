<?php

namespace src\models\Faturamento;

use core\Database;

class ProgramacaoPedidos
{
    public static function listar(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.programacao.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarTanques(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.programacao.tanques', true);
        return $result['retorno'] ?? [];
    }

    public static function listarDiasUteis(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.programacao.dias-uteis', true);
        return $result['retorno'] ?? [];
    }
}
