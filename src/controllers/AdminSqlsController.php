<?php

namespace src\controllers;

use core\Request;
use src\models\GazinSqls;
use \core\Controller as ctrl;

class AdminSqlsController extends ctrl
{
    public function index()
    {
        $dados = [
            'titulo' => 'Gerenciar SQLs do Sistema',
            'pagina' => 'Admin SQLs',
        ];

        $this->render('admin/sqls', $dados);
    }

    public function listar()
    {
        try {
            $busca = Request::get('busca');
            $sqls = GazinSqls::listar($busca);

            self::response([
                'success' => true,
                'data' => $sqls
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function buscar()
    {
        try {
            $idsql = Request::get('idsql');
            if (!$idsql) {
                self::response(['success' => false, 'error' => 'idsql é obrigatório'], 400);
                return;
            }

            $sql = GazinSqls::buscarPorId($idsql);
            if (!$sql) {
                self::response(['success' => false, 'error' => 'SQL não encontrado'], 404);
                return;
            }

            self::response([
                'success' => true,
                'data' => $sql
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function salvar()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';
            $sql = $body['sql'] ?? '';

            if (empty($idsql) || empty($sql)) {
                self::response(['success' => false, 'error' => 'idsql e sql são obrigatórios'], 400);
                return;
            }

            // Validar formato do idsql (modulo.entidade.acao)
            if (!preg_match('/^[a-z0-9]+\.[a-z0-9]+\.[a-zA-Z0-9]+$/', $idsql)) {
                self::response(['success' => false, 'error' => 'Formato do idsql inválido. Use: modulo.entidade.acao'], 400);
                return;
            }

            $existente = GazinSqls::buscarPorId($idsql);
            if ($existente) {
                self::response(['success' => false, 'error' => 'idsql já existe. Use a opção de editar.'], 409);
                return;
            }

            $ok = GazinSqls::inserir($idsql, $sql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao inserir SQL'], 500);
                return;
            }

            self::response(['success' => true, 'message' => 'SQL inserido com sucesso'], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function atualizar()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';
            $sql = $body['sql'] ?? '';

            if (empty($idsql) || empty($sql)) {
                self::response(['success' => false, 'error' => 'idsql e sql são obrigatórios'], 400);
                return;
            }

            $ok = GazinSqls::atualizar($idsql, $sql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao atualizar SQL'], 500);
                return;
            }

            self::response(['success' => true, 'message' => 'SQL atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function excluir()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';

            if (empty($idsql)) {
                self::response(['success' => false, 'error' => 'idsql é obrigatório'], 400);
                return;
            }

            $ok = GazinSqls::excluir($idsql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao excluir SQL'], 500);
                return;
            }

            self::response(['success' => true, 'message' => 'SQL excluído com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
