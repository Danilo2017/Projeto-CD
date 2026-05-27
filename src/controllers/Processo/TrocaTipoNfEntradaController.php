<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\TrocaTipoNfEntradaHandler;

class TrocaTipoNfEntradaController extends Controller
{
    public function index(): void
    {
        $empresas = TrocaTipoNfEntradaHandler::listarEmpresas();
        $this->render('processo/troca-tipo-nf-entrada', compact('empresas'));
    }

    public function listarTipos(): void
    {
        try {
            $dados  = ['empr_id' => $_GET['empr_id'] ?? ''];
            $result = TrocaTipoNfEntradaHandler::listarTipos($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarNf(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $result = TrocaTipoNfEntradaHandler::buscarNf($dados);
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
            $result = TrocaTipoNfEntradaHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
