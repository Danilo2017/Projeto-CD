<?php

namespace src\handlers\Processo;

use src\models\Processo\DespesaCentroCusto;

class DespesaCentroCustoHandler
{
    public static function listar(array $dados): array
    {
        $dtIni  = $dados['dt_ini']  ?? '';
        $dtFim  = $dados['dt_fim']  ?? '';
        $codEmp = (int) ($dados['cod_emp'] ?? 0);

        $iso = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($iso, $dtIni) || !preg_match($iso, $dtFim)) throw new \Exception('Informe as datas de início e fim.', 422);
        if ($codEmp <= 0) throw new \Exception('Informe a empresa.', 422);

        return ['rows' => DespesaCentroCusto::listar(
            $dtIni,
            $dtFim,
            $codEmp,
            (string) ($dados['cc'] ?? ''),
            (string) ($dados['grupo_conta'] ?? ''),
            (string) ($dados['cod_red'] ?? '')
        )];
    }

    public static function detalhe(array $dados): array
    {
        $lctoId = (int) ($dados['lcto_id'] ?? 0);
        if ($lctoId <= 0) throw new \Exception('Lançamento inválido.', 422);

        return ['rows' => DespesaCentroCusto::detalhe($lctoId)];
    }
}
