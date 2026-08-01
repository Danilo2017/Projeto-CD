<?php

namespace src\controllers\PD;

use core\Controller;
use src\handlers\PD\InativacaoPrecoHandler;

class InativacaoPrecoController extends Controller
{
    private function emprIdSessao(): int
    {
        $id = (int) ($_SESSION['empresa']['id'] ?? 0);
        if ($id <= 0) throw new \Exception('Nenhuma empresa selecionada na sessão.', 400);
        return $id;
    }

    public function index(): void
    {
        $emprId = (int) ($_SESSION['empresa']['id'] ?? 0);
        $this->render('pd/inativacao-preco', ['emprId' => $emprId]);
    }

    public function buscarItens(): void
    {
        try {
            $body  = self::getBody() ?? [];
            $dados = array_merge($body, ['empr_id' => $this->emprIdSessao()]);
            self::response(InativacaoPrecoHandler::buscarItens($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function listarCadastros(): void
    {
        try {
            $dados = ['empr_id' => $this->emprIdSessao()];
            self::response(InativacaoPrecoHandler::listarCadastros($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function cadastrarItens(): void
    {
        try {
            $body  = self::getBody() ?? [];
            $dados = array_merge($body, ['empr_id' => $this->emprIdSessao()]);
            self::response(InativacaoPrecoHandler::cadastrarItens($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function excluirItem(): void
    {
        try {
            $body  = self::getBody() ?? [];
            $dados = array_merge($body, ['empr_id' => $this->emprIdSessao()]);
            self::response(InativacaoPrecoHandler::excluirItem($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function processarInativacao(): void
    {
        try {
            $dados = ['empr_id' => $this->emprIdSessao()];
            self::response(InativacaoPrecoHandler::processarInativacao($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
