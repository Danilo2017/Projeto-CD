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
        $hoje       = date('Y-m-d');
        $emprId     = (int) $dados['empr_id'];
        $dataInicio = $dados['data_inicio'] ?? $hoje;
        $dataFim    = $dados['data_fim']    ?? $hoje;
        $wmsSchema  = $dados['wms_schema']  ?? 'FOCCOWMS14A';
        $lista      = ProjecaoCarga::listar($emprId, $dataInicio, $dataFim, $wmsSchema);

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

    /** Sequência de carregamento no formato "17" ou "17-1" (cada parte de 1 a 9999), ou vazio para voltar à ordem padrão. */
    public static function salvarSequencia(array $dados, string $usuario): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_carga']);

        $emprId   = (int) $dados['empr_id'];
        $numCarga = (int) $dados['num_carga'];
        if ($emprId <= 0 || $numCarga <= 0) {
            throw new \Exception('Empresa ou número de carga inválidos.', 400);
        }

        $bruto = trim((string) ($dados['seq'] ?? ''));
        // traço "do Word" e espaços viram o hífen simples
        $bruto = preg_replace('/\s+/', '', str_replace(["\u{2013}", "\u{2014}"], '-', $bruto));
        if ($bruto === '') {
            $seq = null;
        } elseif (preg_match('/^(\d{1,4})(?:-(\d{1,4}))?$/', $bruto, $m)
                  && (int) $m[1] >= 1 && (!isset($m[2]) || (int) $m[2] >= 1)) {
            // normaliza (sem zeros à esquerda): "017-01" vira "17-1"
            $seq = (string) (int) $m[1] . (isset($m[2]) ? '-' . (int) $m[2] : '');
        } else {
            throw new \Exception('A sequência deve ser como 17 ou 17-1 (números de 1 a 9999), ou vazia.', 400);
        }

        $r = ProjecaoCarga::salvarSequencia($emprId, $numCarga, $seq, $usuario);
        return ['success' => true, 'alterado' => $r['alterado'], 'seq' => $seq];
    }

    /**
     * Descrição da carga só na tela (TGAZIN_CARGA_AGEND.DESCRICAO_TELA, VARCHAR2(70), banco em Latin-1). A TCARGAS não é alterada.
     * Descrição vazia = remove a da tela e volta a valer a original do Focco.
     */
    public static function salvarDescricao(array $dados, string $usuario): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'plc_id']);

        $emprId = (int) $dados['empr_id'];
        $plcId  = (int) $dados['plc_id'];
        if ($emprId <= 0 || $plcId <= 0) {
            throw new \Exception('Empresa ou carga inválidas.', 400);
        }

        $desc = (string) ($dados['descricao'] ?? '');
        // aspas e travessões "inteligentes" (colados do Word) viram os equivalentes simples
        $desc = strtr($desc, ["\u{201C}" => '"', "\u{201D}" => '"', "\u{2018}" => "'", "\u{2019}" => "'", "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...']);
        $desc = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $desc));

        if (mb_strlen($desc) > 70) {
            throw new \Exception('A descrição pode ter no máximo 70 caracteres.', 400);
        }
        $latin1 = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $desc);
        if ($latin1 === false || mb_strlen(iconv('ISO-8859-1', 'UTF-8', $latin1)) !== mb_strlen($desc)) {
            throw new \Exception('A descrição tem caracteres que o Focco não aceita (ex.: emoji). Use letras, números e pontuação comum.', 400);
        }

        $r = ProjecaoCarga::atualizarDescricao($emprId, $plcId, $desc, $usuario);
        return [
            'success' => true, 'alterado' => $r['alterado'], 'num_carga' => $r['num_carga'],
            'descricao' => $r['descricao'], 'descricao_orig' => $r['original'],
        ];
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

    public static function listarItens(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_carga']);
        $itens = ProjecaoCarga::listarItens((int) $dados['empr_id'], (int) $dados['num_carga']);
        return ['success' => true, 'data' => $itens];
    }

    public static function listarItensExpedicao(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['num_carga']);
        $itens = ProjecaoCarga::listarItensExpedicao(
            (int) $dados['num_carga'],
            $dados['wms_schema'] ?? 'FOCCOWMS14A'
        );
        return ['success' => true, 'data' => $itens];
    }
}
