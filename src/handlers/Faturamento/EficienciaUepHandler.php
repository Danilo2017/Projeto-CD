<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\EficienciaUep;

class EficienciaUepHandler
{
    public static function listar(): array
    {
        $dados = EficienciaUep::listar();
        return ['success' => true, 'data' => $dados];
    }
}
