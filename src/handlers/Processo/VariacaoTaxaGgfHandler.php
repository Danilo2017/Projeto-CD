<?php

namespace src\handlers\Processo;

use src\models\Processo\VariacaoTaxaGgf;

class VariacaoTaxaGgfHandler
{
    public static function listar(array $dados): array
    {
        $mesAno = trim((string) ($dados['mes_ano'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}$/', $mesAno)) {
            throw new \Exception('Mês/Ano inválido (formato esperado: YYYY-MM).', 400);
        }

        $rows = VariacaoTaxaGgf::listar($mesAno);

        return ['success' => true, 'rows' => $rows, 'total' => count($rows)];
    }
}
