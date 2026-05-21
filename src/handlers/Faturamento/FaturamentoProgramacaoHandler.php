<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\ProgramacaoPedidos;

class FaturamentoProgramacaoHandler
{
    public static function listar(): array
    {
        $dados = ProgramacaoPedidos::listar();
        return [
            'success' => true,
            'data'    => $dados,
            'total'   => count($dados),
        ];
    }

    public static function ocupacao(): array
    {
        $tanques   = ProgramacaoPedidos::listarTanques();
        $diasUteis = ProgramacaoPedidos::listarDiasUteis();
        return [
            'success'   => true,
            'tanques'   => $tanques,
            'diasUteis' => $diasUteis,
        ];
    }
}
