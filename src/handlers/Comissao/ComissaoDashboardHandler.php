<?php

namespace src\handlers\Comissao;

use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;
use src\models\Comissao\Empresa;

/**
 * Handler do Dashboard do Sistema de Comissão
 * Gerencia visão geral e resumos
 */
class ComissaoDashboardHandler
{
    private ApontamentoProducao $apontamentoModel;
    private Comissao $comissaoModel;
    private Empresa $empresaModel;

    public function __construct()
    {
        $this->apontamentoModel = new ApontamentoProducao();
        $this->comissaoModel = new Comissao();
        $this->empresaModel = new Empresa();
    }

    /**
     * Listar filiais ativas
     */
    public function listarFiliais(): array
    {
        $filiais = $this->empresaModel->listarAtivas();
        
        return [
            'success' => true,
            'data' => $filiais,
            'total' => count($filiais)
        ];
    }

    /**
     * Buscar resumo geral do período
     */
    public function getResumoGeral(string $dataInicio, string $dataFim, int $emprId): array
    {
        $resumo = $this->apontamentoModel->resumoGeral($dataInicio, $dataFim, $emprId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Buscar ranking de funcionários
     */
    public function getRankingFuncionarios(string $dataInicio, string $dataFim, int $emprId, int $limite = 10): array
    {
        $ranking = $this->apontamentoModel->rankingFuncionarios($dataInicio, $dataFim, $emprId, $limite);

        return [
            'success' => true,
            'ranking' => $ranking
        ];
    }

    /**
     * Buscar resumo por centro de trabalho
     */
    public function getResumoPorCentro(string $dataInicio, string $dataFim, int $emprId): array
    {
        $resumo = $this->apontamentoModel->resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Buscar resumo por recurso
     */
    public function getResumoPorRecurso(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        $resumo = $this->apontamentoModel->resumoPorRecurso($dataInicio, $dataFim, $emprId, $centroTrabId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Simular comissões
     */
    public function simularComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        $simulacao = $this->comissaoModel->calcularComissaoTodos($dataInicio, $dataFim, $emprId, $centroTrabId);

        // Calcular totais
        $totais = [
            'total_funcionarios' => count($simulacao),
            'total_pontos' => array_sum(array_column($simulacao, 'TOTAL_PONTOS')),
            'total_comissao' => array_sum(array_column($simulacao, 'VALOR_COMISSAO'))
        ];

        return [
            'success' => true,
            'totais' => $totais,
            'simulacao' => $simulacao
        ];
    }
}
