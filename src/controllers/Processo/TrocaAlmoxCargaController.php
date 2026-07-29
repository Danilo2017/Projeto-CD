<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\TrocaAlmoxCargaHandler;

class TrocaAlmoxCargaController extends Controller
{
    public function index(): void
    {
        $empresas = TrocaAlmoxCargaHandler::listarEmpresas();
        $this->render('processo/troca-almox-carga', compact('empresas'));
    }

    public function buscarItensCarga(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $result = TrocaAlmoxCargaHandler::buscarItensCarga($dados);
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
            $result = TrocaAlmoxCargaHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
