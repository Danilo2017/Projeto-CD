<?php

namespace src\controllers\Cd;

use core\Controller;
use src\handlers\Cd\ProjecaoCargaHandler;

class ProjecaoCargaController extends Controller
{
    private function emprIdSessao(): int
    {
        $id = (int) ($_SESSION['empresa']['id'] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Nenhuma empresa selecionada na sessão.', 400);
        }
        return $id;
    }

    public function index(): void
    {
        $this->render('cd/projecao-carga', []);
    }

    public function listar(): void
    {
        try {
            $emprId = $this->emprIdSessao();
            $result = ProjecaoCargaHandler::listar(['empr_id' => $emprId]);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function salvar(): void
    {
        try {
            $dados          = self::getBody() ?? [];
            $dados['empr_id'] = $this->emprIdSessao();
            $usuario        = $_SESSION['user']['login'] ?? 'desconhecido';
            $result         = ProjecaoCargaHandler::salvar($dados, $usuario);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function listarLog(): void
    {
        try {
            $dados = [
                'empr_id'   => $this->emprIdSessao(),
                'num_carga' => $_GET['num_carga'] ?? '',
            ];
            $result = ProjecaoCargaHandler::listarLog($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
