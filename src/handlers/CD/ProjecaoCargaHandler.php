<?php

namespace src\handlers\CD;

use core\Controller;
use src\models\CD\ProjecaoCarga;

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
        $erroTransicao = null;
        $naoTransicionar = ['AGUARDANDO DOCUMENTAÇÃO', 'FINALIZADO'];
        $semStatus = array_values(array_filter($lista, fn($r) =>
            in_array($r['POS_PLC'] ?? '', ['FT', 'FP'])
            && !in_array($r['SITUACAO_CAMINHAO'] ?? '', $naoTransicionar)
        ));
        if ($semStatus) {
            $numCargas = array_column($semStatus, 'NUM_CARGA');
            try {
                ProjecaoCarga::marcarAguardandoDocumentacao($emprId, $numCargas);
            } catch (\Throwable $t) {
                $erroTransicao = $t->getMessage();
            }
            foreach ($lista as &$r) {
                if (in_array($r['POS_PLC'] ?? '', ['FT', 'FP'])
                    && !in_array($r['SITUACAO_CAMINHAO'] ?? '', $naoTransicionar)) {
                    $r['SITUACAO_CAMINHAO'] = 'AGUARDANDO DOCUMENTAÇÃO';
                }
            }
            unset($r);
        }

        $ret = ['success' => true, 'data' => $lista, 'total' => count($lista)];
        if ($erroTransicao) $ret['_transicao_erro'] = $erroTransicao;
        return $ret;
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

    public static function listarRota(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_carga']);
        $rows = ProjecaoCarga::listarRota((int) $dados['empr_id'], (int) $dados['num_carga']);
        return ['success' => true, 'data' => $rows];
    }

    public static function salvarRota(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['plc_id', 'sequencias']);
        if (!is_array($dados['sequencias']) || empty($dados['sequencias'])) {
            throw new \Exception('Nenhuma sequência informada.', 400);
        }
        ProjecaoCarga::salvarSequenciaRota((int) $dados['plc_id'], $dados['sequencias']);
        return ['success' => true];
    }
}
