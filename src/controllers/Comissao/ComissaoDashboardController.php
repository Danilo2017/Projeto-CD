<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\Recurso;
use src\models\Comissao\Funcionario;
use src\models\Comissao\Empresa;
use src\models\Comissao\Vinculo;

/**
 * Controller do Dashboard do Sistema de Comissão
 * Gerencia visão geral e resumos
 * 
 * NOTA: As rotas deste controller estão comentadas em routes.php
 */
class ComissaoDashboardController extends ctrl
{
    /**
     * Página principal do Dashboard
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Dashboard - Sistema de Comissão',
            'pagina' => 'Dashboard'
        ];

        $this->render('comissao/dashboard', $dados);
    }

    /**
     * API - Listar filiais
     */
    public function getFiliais()
    {
        try {
            $model = new Empresa();
            $filiais = $model->listarAtivas();

            self::response([
                'success' => true,
                'data' => $filiais,
                'total' => count($filiais)
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Resumo geral
     */
    public function getResumoGeral()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new ApontamentoProducao();
            $resumo = $model->resumoGeral($dataInicio, $dataFim, $emprId);

            self::response([
                'success' => true,
                'resumo' => $resumo
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Ranking de funcionários
     */
    public function getRankingFuncionarios()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);
            $limite = $_GET['limite'] ?? 10;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new ApontamentoProducao();
            $ranking = $model->rankingFuncionarios($dataInicio, $dataFim, $emprId, $limite);

            self::response([
                'success' => true,
                'ranking' => $ranking
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Resumo por centro de trabalho
     */
    public function getResumoPorCentro()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new ApontamentoProducao();
            $resumo = $model->resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId);

            self::response([
                'success' => true,
                'resumo' => $resumo
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Resumo por recurso
     */
    public function getResumoPorRecurso()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = $_GET['centroTrabId'] ?? null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new ApontamentoProducao();
            $resumo = $model->resumoPorRecurso($dataInicio, $dataFim, $emprId, $centroTrabId);

            self::response([
                'success' => true,
                'resumo' => $resumo
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Simular comissões
     */
    public function simularComissoes()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = $_GET['centroTrabId'] ?? null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new Comissao();
            $simulacao = $model->calcularComissaoTodos($dataInicio, $dataFim, $emprId, $centroTrabId);

            // Calcular totais
            $totais = [
                'total_funcionarios' => count($simulacao),
                'total_pontos' => array_sum(array_column($simulacao, 'TOTAL_PONTOS')),
                'total_comissao' => array_sum(array_column($simulacao, 'VALOR_COMISSAO'))
            ];

            self::response([
                'success' => true,
                'totais' => $totais,
                'simulacao' => $simulacao
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
