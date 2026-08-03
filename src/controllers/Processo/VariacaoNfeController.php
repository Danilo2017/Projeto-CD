<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\VariacaoNfeHandler;

class VariacaoNfeController extends Controller
{
    public function index(): void
    {
        $empresas = \src\models\Processo\VariacaoNfe::listarEmpresas();
        $this->render('processo/relatorio-variacao-nfe', compact('empresas'));
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(VariacaoNfeHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
