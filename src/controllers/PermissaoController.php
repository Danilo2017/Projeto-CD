<?php

namespace src\controllers;

use core\Request;
use src\models\PerfilAcesso;
use \core\Controller as ctrl;

/**
 * Controller para gerenciamento de permissões de acesso
 * Usa tabelas TGAZIN_USUARIO_PERFIL / TGAZIN_PERFIL_ACESSO
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
            'pagina' => 'Permissões',
            'is_admin' => $_SESSION['user']['is_admin'] ?? false,
        ];

        $this->render('permissao/index', $dados);
    }

    /**
     * API - Lista perfis disponíveis (para popular selects/checkboxes)
     */
    public function listarPerfis()
    {
        try {
            $perfis = PerfilAcesso::listarPerfisAtivos();

            self::response([
                'success' => true,
                'data' => $perfis
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Lista usuários com seus perfis
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

            if (!empty($_GET['perfil_id'])) {
                $filtros['perfil_id'] = $_GET['perfil_id'];
            }
            
            $usuarios = PerfilAcesso::listarUsuariosAgrupados($filtros);
            
            self::response([
                'success' => true,
                'data' => $usuarios
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Busca perfis de um usuário por login
     */
    public function buscar()
    {
        try {
            $login = $_GET['login'] ?? null;
            
            if (!$login) {
                throw new \Exception('Login é obrigatório');
            }
            
            $perfis = PerfilAcesso::buscarPerfisUsuario($login);
            
            self::response([
                'success' => true,
                'data' => [
                    'LOGIN_USUARIO' => strtoupper($login),
                    'PERFIS' => $perfis,
                    'PERFIS_IDS' => array_column($perfis, 'PERFIL_ID'),
                ]
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Define perfis de um usuário (novo ou existente)
     */
    public function salvar()
    {
        try {
            $body = Request::getJsonBody();
            
            $login = $body['login'] ?? null;
            $perfisIds = $body['perfis'] ?? [];
            
            if (!$login) {
                throw new \Exception('Login do usuário é obrigatório');
            }

            if (empty($perfisIds)) {
                throw new \Exception('Selecione ao menos um perfil');
            }
            
            PerfilAcesso::definirPerfisUsuario($login, $perfisIds);
            
            self::response([
                'success' => true,
                'message' => 'Perfis salvos com sucesso'
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Atualiza perfis de um usuário
     */
    public function atualizar()
    {
        try {
            $body = Request::getJsonBody();
            
            $login = $body['login'] ?? null;
            $perfisIds = $body['perfis'] ?? [];
            
            if (!$login) {
                throw new \Exception('Login do usuário é obrigatório');
            }

            if (empty($perfisIds)) {
                throw new \Exception('Selecione ao menos um perfil');
            }
            
            PerfilAcesso::definirPerfisUsuario($login, $perfisIds);
            
            self::response([
                'success' => true,
                'message' => 'Perfis atualizados com sucesso'
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Remove todos os perfis de um usuário (inativa)
     */
    public function excluir()
    {
        try {
            $body = Request::getJsonBody();
            $login = $body['login'] ?? null;
            
            if (!$login) {
                throw new \Exception('Login é obrigatório');
            }
            
            PerfilAcesso::definirPerfisUsuario($login, []);
            
            self::response([
                'success' => true,
                'message' => 'Permissões removidas com sucesso'
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
