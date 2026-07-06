<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\TransferenciaEstoqueHandler;

class TransferenciaEstoqueController extends Controller
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
        $almoxs = $emprId ? TransferenciaEstoqueHandler::listarAlmoxarifados($emprId) : [];
        $this->render('processo/transferencia-estoque', compact('almoxs'));
    }

    public function listarAlmoxarifados(): void
    {
        try {
            $data = TransferenciaEstoqueHandler::listarAlmoxarifados($this->emprIdSessao());
            self::response(['success' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarSaldo(): void
    {
        try {
            $dados = self::getBody() ?? [];
            $dados['empr_id'] = $this->emprIdSessao();
            $result = TransferenciaEstoqueHandler::buscarSaldo($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function executar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            $dados['empr_id'] = $this->emprIdSessao();
            $result = TransferenciaEstoqueHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
