<?php

namespace src\handlers\Cd;

use core\Controller;
use src\models\Cd\ProjecaoCarga;

class ProjecaoCargaHandler
{
    public static function listarEmpresas(): array
    {
        return ProjecaoCarga::listarEmpresas();
    }

    public static function listar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id']);
        $dataFiltro = $dados['data_filtro'] ?? date('Y-m-d');
        $lista = ProjecaoCarga::listar((int) $dados['empr_id'], $dataFiltro);
        return ['success' => true, 'data' => $lista, 'total' => count($lista)];
    }

    public static function salvar(array $dados, string $usuario): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_carga']);

        $emprId   = (int) $dados['empr_id'];
        $numCarga = (int) $dados['num_carga'];

        if ($emprId <= 0 || $numCarga <= 0) {
            throw new \Exception('Empresa ou número de carga inválidos.', 400);
        }

        $resultado = ProjecaoCarga::salvar($emprId, $numCarga, $dados, $usuario);
        return ['success' => true, 'alteracoes' => $resultado['alteracoes']];
    }

    public static function listarLog(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_carga']);
        $log = ProjecaoCarga::listarLog((int) $dados['empr_id'], (int) $dados['num_carga']);
        return ['success' => true, 'data' => $log];
    }
}
