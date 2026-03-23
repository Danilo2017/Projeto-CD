<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use src\handlers\Comissao\ComissaoDashboardHandler;

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
            $resultado = ComissaoDashboardHandler::listarFiliais();
            self::response($resultado, 200);
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

            $resultado = ComissaoDashboardHandler::getResumoGeral($dataInicio, $dataFim, (int)$emprId);
            self::response($resultado, 200);
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
            $limite = (int)($_GET['limite'] ?? 10);

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoDashboardHandler::getRankingFuncionarios($dataInicio, $dataFim, (int)$emprId, $limite);
            self::response($resultado, 200);
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

            $resultado = ComissaoDashboardHandler::getResumoPorCentro($dataInicio, $dataFim, (int)$emprId);
            self::response($resultado, 200);
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
            $centroTrabId = isset($_GET['centroTrabId']) ? (int)$_GET['centroTrabId'] : null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoDashboardHandler::getResumoPorRecurso($dataInicio, $dataFim, (int)$emprId, $centroTrabId);
            self::response($resultado, 200);
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
            $centroTrabId = isset($_GET['centroTrabId']) ? (int)$_GET['centroTrabId'] : null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoDashboardHandler::simularComissoes($dataInicio, $dataFim, (int)$emprId, $centroTrabId);
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Dashboard completo com comissões calculadas
     */
    public function getDashboardCompleto()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? date('Y-m-01');
            $dataFim = $_GET['dataFim'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoDashboardHandler::getDashboardCompleto($dataInicio, $dataFim, (int)$emprId);
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
