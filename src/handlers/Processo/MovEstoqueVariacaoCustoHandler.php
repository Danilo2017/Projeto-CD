<?php

namespace src\handlers\Processo;

use src\models\Processo\MovEstoqueVariacaoCusto;

class MovEstoqueVariacaoCustoHandler
{
    public static function listar(array $dados, array $emprIds = []): array
    {
        $dtIni = trim((string) ($dados['dt_ini'] ?? ''));
        $dtFim = trim((string) ($dados['dt_fim'] ?? ''));

        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtIni)) throw new \Exception('Data início inválida (DD/MM/YYYY).', 400);
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dtFim)) throw new \Exception('Data fim inválida (DD/MM/YYYY).', 400);

        $base     = \DateTime::createFromFormat('d/m/Y', $dtIni);
        $dtIniAnt = (clone $base)->modify('first day of previous month')->format('d/m/Y');
        $dtFimAnt = (clone $base)->modify('last day of previous month')->format('d/m/Y');

        $rows = MovEstoqueVariacaoCusto::listar($dtIni, $dtFim, $dtIniAnt, $dtFimAnt, $emprIds);

        return [
            'success'    => true,
            'rows'       => $rows,
            'total'      => count($rows),
            'dt_ini_ant' => $dtIniAnt,
            'dt_fim_ant' => $dtFimAnt,
        ];
    }
}
