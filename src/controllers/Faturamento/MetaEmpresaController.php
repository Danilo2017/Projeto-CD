<?php

namespace src\controllers\Faturamento;

use \core\Controller as ctrl;
use src\handlers\Faturamento\MetaEmpresaHandler;

/**
 * Controller de Meta Empresa
 * Gerencia metas de faturamento e estoque por empresa/mês
 */
class MetaEmpresaController extends ctrl
{
    /**
     * Página principal de gestão de metas
     */
    public function index(): void
    {
        $dados = [
            'titulo' => 'Gestão de Metas',
            'pagina' => 'Meta Empresa'
        ];
        
        $this->render('faturamento/meta-empresa', $dados);
    }

    /**
     * API: Listar metas
     */
    public function listar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $mesAno = $_GET['mes_ano'] ?? null;
        
        $resultado = MetaEmpresaHandler::listar($mesAno);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * API: Buscar meta específica
     */
    public function buscar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $emprId = (int) ($_GET['empr_id'] ?? 0);
        $mesAno = $_GET['mes_ano'] ?? '';
        
        if (!$emprId || !$mesAno) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos']);
            return;
        }
        
        $resultado = MetaEmpresaHandler::buscar($emprId, $mesAno);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * API: Salvar meta (POST)
     */
    public function salvar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
            return;
        }
        
        $dados = json_decode(file_get_contents('php://input'), true);
        
        if (!$dados) {
            $dados = $_POST;
        }
        
        $resultado = MetaEmpresaHandler::salvar($dados);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * API: Excluir meta (DELETE)
     */
    public function excluir(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $dados = json_decode(file_get_contents('php://input'), true);
        
        $emprId = (int) ($dados['empr_id'] ?? $_GET['empr_id'] ?? 0);
        $mesAno = $dados['mes_ano'] ?? $_GET['mes_ano'] ?? '';
        
        if (!$emprId || !$mesAno) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos']);
            return;
        }
        
        $resultado = MetaEmpresaHandler::excluir($emprId, $mesAno);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * API: Listar empresas
     */
    public function empresas(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $resultado = MetaEmpresaHandler::listarEmpresas();
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }
}
