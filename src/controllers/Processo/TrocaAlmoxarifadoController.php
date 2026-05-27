<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\TrocaAlmoxarifadoHandler;

class TrocaAlmoxarifadoController extends Controller
{
    public function index(): void
    {
        $empresas = TrocaAlmoxarifadoHandler::listarEmpresas();
        $this->render('processo/troca-almoxarifado', compact('empresas'));
    }

    public function listarAlmoxarifados(): void
    {
        try {
            $dados  = ['empr_id' => $_GET['empr_id'] ?? ''];
            $result = TrocaAlmoxarifadoHandler::listarAlmoxarifados($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarOrdens(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $result = TrocaAlmoxarifadoHandler::buscarOrdens($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function executar(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $result = TrocaAlmoxarifadoHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
