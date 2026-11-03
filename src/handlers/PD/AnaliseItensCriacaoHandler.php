<?php

namespace src\handlers\PD;

use src\models\PD\AnaliseItensCriacao;

class AnaliseItensCriacaoHandler
{
    public static function listar(array $dados): array
    {
        $dtIni  = $dados['dt_ini']  ?? '';
        $dtFim  = $dados['dt_fim']  ?? '';
        $codEmp = (int) ($dados['cod_emp'] ?? 0);

        $iso = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($iso, $dtIni) || !preg_match($iso, $dtFim)) throw new \Exception('Informe as datas de início e fim.', 422);
        if ($codEmp <= 0) throw new \Exception('Informe a empresa.', 422);

        return ['rows' => AnaliseItensCriacao::listar($dtIni, $dtFim, $codEmp)];
    }

    public static function inativar(array $dados): array
    {
        $id = (int) ($dados['id'] ?? 0);
        if ($id <= 0) throw new \Exception('Máscara inválida.', 422);

        AnaliseItensCriacao::inativar($id);
        return ['success' => true, 'message' => 'Máscara inativada em todas as pastas.'];
    }
}
