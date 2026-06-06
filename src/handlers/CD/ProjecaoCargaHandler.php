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
        $emprId     = (int) $dados['empr_id'];
        $dataFiltro = $dados['data_filtro'] ?? date('Y-m-d');
        $wmsSchema  = $dados['wms_schema']  ?? 'FOCCOWMS14A';
        $lista      = ProjecaoCarga::listar($emprId, $dataFiltro, $wmsSchema);

        // Auto-transição: FT/FP sem status → AGUARDANDO DOCUMENTAÇÃO
        $semStatus = array_values(array_filter($lista, fn($r) =>
            in_array($r['POS_PLC'] ?? '', ['FT', 'FP']) && empty($r['SITUACAO_CAMINHAO'])
        ));
        if ($semStatus) {
            $numCargas = array_column($semStatus, 'NUM_CARGA');
            ProjecaoCarga::marcarAguardandoDocumentacao($emprId, $numCargas);
            foreach ($lista as &$r) {
                if (in_array($r['POS_PLC'] ?? '', ['FT', 'FP']) && empty($r['SITUACAO_CAMINHAO'])) {
                    $r['SITUACAO_CAMINHAO'] = 'AGUARDANDO DOCUMENTAÇÃO';
                }
            }
            unset($r);
        }

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
