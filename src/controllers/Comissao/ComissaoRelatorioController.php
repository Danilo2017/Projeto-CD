<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use \core\Request;
use src\handlers\Comissao\ComissaoRelatorioHandler;

/**
 * Controller de Relatórios do Sistema de Comissão
 * Gerencia relatórios de produtividade e comissões
 */
class ComissaoRelatorioController extends ctrl
{
    // ==================== PÁGINAS ====================

    /**
     * Página principal de relatórios
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Relatórios - Sistema de Comissão',
            'pagina' => 'Relatórios'
        ];

        $this->render('comissao/relatorio', $dados);
    }

    /**
     * Página de relatório de produtividade diária
     */
    public function produtividadeDiariaIndex()
    {
        $dados = [
            'titulo' => 'Relatório de Produtividade Diária',
            'pagina' => 'Produtividade Diária'
        ];

        $this->render('comissao/relatorio-diario', $dados);
    }

    /**
     * Página de relatório de comissões
     */
    public function comissoesIndex()
    {
        $dados = [
            'titulo' => 'Relatório de Comissões',
            'pagina' => 'Comissões'
        ];

        $this->render('comissao/relatorio-comissoes', $dados);
    }

    /**
     * Página de relatório por funcionário
     */
    public function porFuncionarioIndex()
    {
        $dados = [
            'titulo' => 'Relatório por Funcionário',
            'pagina' => 'Por Funcionário'
        ];

        $this->render('comissao/relatorio-funcionario', $dados);
    }

    // ==================== API RELATÓRIOS ====================

    /**
     * API - Relatório de produtividade diária detalhado
     */
    public function getProdutividadeDiaria()
    {
        try {
            $data = $_GET['data'] ?? date('Y-m-d');
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = isset($_GET['centroTrabId']) || isset($_GET['centro_trab_id']) 
                ? (int)($_GET['centroTrabId'] ?? $_GET['centro_trab_id']) 
                : null;
            $recursoId = isset($_GET['recursoId']) || isset($_GET['recurso_id']) 
                ? (int)($_GET['recursoId'] ?? $_GET['recurso_id']) 
                : null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoRelatorioHandler::getProdutividadeDiaria($data, (int)$emprId, $recursoId, $centroTrabId, $dataFim);

            self::response([
                'success' => true,
                'resumo' => $resultado['resumo'],
                'produtividade' => $resultado['produtividade'],
                'apontamentos' => $resultado['apontamentos']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Listar comissões calculadas
     */
    public function getComissoes()
    {
        try {
            set_time_limit(300);
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $centroTrabId = isset($_GET['centroTrabId']) || isset($_GET['centro_trab_id']) 
                ? (int)($_GET['centroTrabId'] ?? $_GET['centro_trab_id']) 
                : null;
            $status = $_GET['status'] ?? null;
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoRelatorioHandler::getComissoes($dataInicio, $dataFim, (int)$emprId, $centroTrabId, $status);

            self::response([
                'success' => true,
                'resumo' => $resultado['resumo'],
                'porCentro' => $resultado['porCentro'],
                'comissoes' => $resultado['comissoes']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Detalhes de uma comissão específica
     */
    public function getComissaoDetalhes()
    {
        try {
            set_time_limit(120);
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $funcId = $_GET['funcId'] ?? $_GET['func_id'] ?? null;
            $centroTrabId = isset($_GET['centroTrabId']) || isset($_GET['centro_trab_id']) 
                ? (int)($_GET['centroTrabId'] ?? $_GET['centro_trab_id']) 
                : null;

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$funcId) {
                throw new \Exception('Funcionário é obrigatório');
            }

            $resultado = ComissaoRelatorioHandler::getComissaoDetalhes((int)$funcId, $dataInicio, $dataFim, $centroTrabId);

            self::response([
                'success' => true,
                'apontamentos' => $resultado['apontamentos']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Processar comissões (cálculo simples)
     */
    public function processarComissoes()
    {
        try {
            set_time_limit(300);
            $dados = Request::getJsonBody();

            $dataInicio = $dados['dataInicio'] ?? $dados['data_inicio'] ?? null;
            $dataFim = $dados['dataFim'] ?? $dados['data_fim'] ?? null;
            $centroTrabId = isset($dados['centroTrabId']) || isset($dados['centro_trab_id']) 
                ? (int)($dados['centroTrabId'] ?? $dados['centro_trab_id']) 
                : null;
            $emprId = $dados['emprId'] ?? $dados['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;

            $resultado = ComissaoRelatorioHandler::processarComissoes($dataInicio, $dataFim, (int)$emprId, $centroTrabId, $usuId);

            self::response([
                'success' => true,
                'message' => $resultado['message'],
                'processadas' => $resultado['processadas']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Processar comissões completo (com todas as regras)
     */
    public function processarComissoesCompleto()
    {
        try {
            set_time_limit(300);
            $dados = Request::getJsonBody();

            $dataInicio = $dados['dataInicio'] ?? $dados['data_inicio'] ?? null;
            $dataFim = $dados['dataFim'] ?? $dados['data_fim'] ?? null;
            $centroTrabId = isset($dados['centroTrabId']) || isset($dados['centro_trab_id']) 
                ? (int)($dados['centroTrabId'] ?? $dados['centro_trab_id']) 
                : null;
            $emprId = $dados['emprId'] ?? $dados['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;

            $resultado = ComissaoRelatorioHandler::processarComissoesCompleto($dataInicio, $dataFim, (int)$emprId, $centroTrabId, $usuId);

            self::response([
                'success' => true,
                'message' => $resultado['message'],
                'processadas' => $resultado['processadas'],
                'resultado' => $resultado['resultado']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Aprovar comissões
     */
    public function aprovarComissao()
    {
        try {
            $dados = Request::getJsonBody();

            if (empty($dados['comissoes']) || !is_array($dados['comissoes'])) {
                throw new \Exception('Nenhuma comissão selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;
            if (!$usuId) {
                throw new \Exception('Usuário não autenticado');
            }

            $resultado = ComissaoRelatorioHandler::aprovarComissoes($dados['comissoes'], (int)$usuId);

            self::response([
                'success' => true,
                'message' => $resultado['message']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Cancelar comissões
     */
    public function cancelarComissao()
    {
        try {
            $dados = Request::getJsonBody();

            if (empty($dados['comissoes']) || !is_array($dados['comissoes'])) {
                throw new \Exception('Nenhuma comissão selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;
            if (!$usuId) {
                throw new \Exception('Usuário não autenticado');
            }

            $motivo = $dados['motivo'] ?? null;

            $resultado = ComissaoRelatorioHandler::cancelarComissoes($dados['comissoes'], (int)$usuId, $motivo);

            self::response([
                'success' => true,
                'message' => $resultado['message']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Relatório por funcionário
     */
    public function getRelatorioFuncionario()
    {
        set_time_limit(120);
        try {
            $funcionarioId = $_GET['funcionarioId'] ?? $_GET['funcionario_id'] ?? $_GET['funcId'] ?? null;
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = isset($_GET['centroTrabId']) || isset($_GET['centro_trab_id']) 
                ? (int)($_GET['centroTrabId'] ?? $_GET['centro_trab_id']) 
                : null;

            if (!$funcionarioId) {
                throw new \Exception('Funcionário é obrigatório');
            }

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $resultado = ComissaoRelatorioHandler::getRelatorioFuncionario((int)$funcionarioId, $dataInicio, $dataFim, (int)$emprId, $centroTrabId);

            self::response([
                'success' => true,
                'funcionario' => $resultado['funcionario'],
                'resumo' => $resultado['resumo'],
                'diario' => $resultado['diario'],
                'apontamentos' => $resultado['apontamentos'],
                'comissoes' => $resultado['comissoes'],
                'vinculos' => $resultado['vinculos'],
                'faltas' => $resultado['faltas']
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
