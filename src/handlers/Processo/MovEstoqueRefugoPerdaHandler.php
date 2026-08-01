<?php

namespace src\handlers\Processo;

use src\models\Processo\MovEstoqueRefugoPerda;

class MovEstoqueRefugoPerdaHandler
{
    public static function listar(array $dados): array
    {
        $dtIni = trim((string) ($dados['dt_ini'] ?? ''));
        $dtFim = trim((string) ($dados['dt_fim'] ?? ''));

        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtIni)) throw new \Exception('Data início inválida (DD/MM/YYYY).', 400);
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtFim)) throw new \Exception('Data fim inválida (DD/MM/YYYY).', 400);

        $rows = MovEstoqueRefugoPerda::listar($dtIni, $dtFim);
        return ['success' => true, 'rows' => $rows, 'total' => count($rows)];
    }
}
