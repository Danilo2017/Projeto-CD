<?php

namespace src\controllers;

use core\Request;
use src\models\PermissaoUsuario;
use \core\Controller as ctrl;

/**
 * Controller para gerenciamento de permissões de acesso
 * Usa tabela TGAZIN_ACESSO_USUARIO
 */
class PermissaoController extends ctrl
{
    /**
     * Página de cadastro de permissões
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Gerenciar Permissões de Acesso',
            'pagina' => 'Permissões'
        ];

        $this->render('permissao/index', $dados);
    }

    /**
     * API - Lista todas as permissões de usuários
     */
    public function listar()
    {
        try {
            $filtros = [];
            
            if (!empty($_GET['login'])) {
                $filtros['login'] = $_GET['login'];
            }
            
            if (isset($_GET['ativo']) && $_GET['ativo'] !== '') {
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
     * API - Busca uma permissão específica por ID ou login
     */
    public function buscar()
    {
        try {
            $id = $_GET['id'] ?? null;
            $login = $_GET['login'] ?? null;
            
            if (!$id && !$login) {
                throw new \Exception('ID ou Login é obrigatório');
            }
            
            $permissao = null;
            
            if ($id) {
                $permissao = PermissaoUsuario::buscarPorId($id);
            } else {
                $permissao = PermissaoUsuario::buscarPorLogin($login);
            }
            
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
     * API - Salva nova permissão
     */
    public function salvar()
    {
        try {
            $body = Request::getJsonBody();
            
            $login = $body['login'] ?? null;
            $acessoCd = $body['acesso_cd'] ?? 'N';
            $acessoComissao = $body['acesso_comissao'] ?? 'N';
            $admin = $body['admin'] ?? 'N';
            
            if (!$login) {
                throw new \Exception('Login do usuário é obrigatório');
            }
            
            $id = PermissaoUsuario::inserir($login, $acessoCd, $acessoComissao, $admin);
            
            self::response([
                'success' => true,
                'message' => 'Permissão salva com sucesso',
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
     * API - Atualiza permissão existente
     */
    public function atualizar()
    {
        try {
            $body = Request::getJsonBody();
            
            $id = $body['id'] ?? null;
            $acessoCd = $body['acesso_cd'] ?? 'N';
            $acessoComissao = $body['acesso_comissao'] ?? 'N';
            $admin = $body['admin'] ?? 'N';
            $ativo = $body['ativo'] ?? 'S';
            
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
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
            $body = Request::getJsonBody();
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
}
