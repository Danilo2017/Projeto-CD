<?php

namespace src\handlers\Processo;

use src\models\Processo\MovEstoqueRelatorio;

class MovEstoqueRelatorioHandler
{
    public static function listar(array $dados): array
    {
        $dtIni = trim((string) ($dados['dt_ini'] ?? ''));
        $dtFim = trim((string) ($dados['dt_fim'] ?? ''));

        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtIni)) throw new \Exception('Data início inválida (DD/MM/YYYY).', 400);
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtFim)) throw new \Exception('Data fim inválida (DD/MM/YYYY).', 400);

        $rows = MovEstoqueRelatorio::listar($dtIni, $dtFim);
        return ['success' => true, 'rows' => $rows, 'total' => count($rows)];
    }
}
