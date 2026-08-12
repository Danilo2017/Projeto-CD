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

    public static function listarTanques(): array
    {
        $dados = EficienciaUep::listarTanques();
        return ['success' => true, 'data' => $dados];
    }

    public static function listarClassificacoesPorTanque(int $emprId, int $codTanque): array
    {
        $dados = EficienciaUep::listarClassificacoesPorTanque($emprId, $codTanque);
        return ['success' => true, 'data' => $dados];
    }
}
