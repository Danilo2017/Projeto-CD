<?php

namespace src\controllers;

use src\models\PermissaoUsuario;
use \core\Controller as ctrl;

/**
 * Controller para gerenciamento de permissões de acesso
 */
class PermissaoController extends ctrl
{
    /**
     * Página de cadastro de permissões
     */
    public function index()
    {
        // Verificar se é admin
        $this->verificarAdmin();
        
        $dados = [
            'titulo' => 'Gerenciar Permissões de Acesso',
            'pagina' => 'Permissões'
        ];

        $this->render('permissao/index', $dados);
    }

    /**
     * API - Lista todas as permissões
     */
    public function listar()
    {
        try {
            $this->verificarAdmin();
            
            $filtros = [];
            
            if (!empty($_GET['login'])) {
                $filtros['login'] = $_GET['login'];
            }
            
            if (isset($_GET['ativo'])) {
                $filtros['ativo'] = $_GET['ativo'];
            }
            
            $permissoes = PermissaoUsuario::listar($filtros);
            
            self::response([
                'success' => true,
                'data' => $permissoes
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Busca uma permissão por ID
     */
    public function buscar()
    {
        try {
            $this->verificarAdmin();
            
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            $permissao = PermissaoUsuario::buscarPorId($id);
            
            if (!$permissao) {
                throw new \Exception('Permissão não encontrada');
            }
            
            self::response([
                'success' => true,
                'data' => $permissao
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Salva uma nova permissão
     */
    public function salvar()
    {
        try {
            $this->verificarAdmin();
            
            $body = ctrl::getBody();
            
            $login = $body['login'] ?? null;
            $acessoCd = $body['acesso_cd'] ?? 'N';
            $acessoComissao = $body['acesso_comissao'] ?? 'N';
            $admin = $body['admin'] ?? 'N';
            
            if (!$login) {
                throw new \Exception('Login do usuário é obrigatório');
            }
            
            // Normalizar valores
            $acessoCd = strtoupper($acessoCd) === 'S' ? 'S' : 'N';
            $acessoComissao = strtoupper($acessoComissao) === 'S' ? 'S' : 'N';
            $admin = strtoupper($admin) === 'S' ? 'S' : 'N';
            
            $id = PermissaoUsuario::inserir($login, $acessoCd, $acessoComissao, $admin);
            
            self::response([
                'success' => true,
                'message' => 'Permissão cadastrada com sucesso',
                'id' => $id
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Atualiza uma permissão existente
     */
    public function atualizar()
    {
        try {
            $this->verificarAdmin();
            
            $body = ctrl::getBody();
            
            $id = $body['id'] ?? null;
            $acessoCd = $body['acesso_cd'] ?? 'N';
            $acessoComissao = $body['acesso_comissao'] ?? 'N';
            $admin = $body['admin'] ?? 'N';
            $ativo = $body['ativo'] ?? 'S';
            
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            // Normalizar valores
            $acessoCd = strtoupper($acessoCd) === 'S' ? 'S' : 'N';
            $acessoComissao = strtoupper($acessoComissao) === 'S' ? 'S' : 'N';
            $admin = strtoupper($admin) === 'S' ? 'S' : 'N';
            $ativo = strtoupper($ativo) === 'S' ? 'S' : 'N';
            
            PermissaoUsuario::atualizar($id, $acessoCd, $acessoComissao, $admin, $ativo);
            
            self::response([
                'success' => true,
                'message' => 'Permissão atualizada com sucesso'
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Exclui (inativa) uma permissão
     */
    public function excluir()
    {
        try {
            $this->verificarAdmin();
            
            $body = ctrl::getBody();
            $id = $body['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            PermissaoUsuario::excluir($id);
            
            self::response([
                'success' => true,
                'message' => 'Permissão removida com sucesso'
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se o usuário logado é administrador
     */
    private function verificarAdmin()
    {
        $login = $_SESSION['user']['login'] ?? null;
        
        if (!$login) {
            throw new \Exception('Usuário não autenticado');
        }
        
        // Verificar se é admin
        if (!PermissaoUsuario::isAdmin($login)) {
            throw new \Exception('Acesso negado. Apenas administradores podem gerenciar permissões.');
        }
    }
}
