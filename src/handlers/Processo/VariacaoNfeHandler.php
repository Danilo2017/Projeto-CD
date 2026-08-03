<?php

namespace src\handlers\Processo;

use src\models\Processo\VariacaoNfe;

class VariacaoNfeHandler
{
    public static function listar(array $dados): array
    {
        $dtIni  = $dados['dt_ini']  ?? '';
        $dtFim  = $dados['dt_fim']  ?? '';
        $codEmp = (int) ($dados['cod_emp'] ?? 1);

        if (!$dtIni || !$dtFim) throw new \Exception('Informe as datas de início e fim.', 422);
        if ($codEmp <= 0)       throw new \Exception('Informe a empresa.', 422);

        return ['rows' => VariacaoNfe::listar($dtIni, $dtFim, $codEmp)];
    }
}
