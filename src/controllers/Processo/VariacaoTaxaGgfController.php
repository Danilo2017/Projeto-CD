<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\VariacaoTaxaGgfHandler;

class VariacaoTaxaGgfController extends Controller
{
    public function index(): void
    {
        $this->render('processo/relatorio-variacao-taxa-ggf', []);
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(VariacaoTaxaGgfHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
