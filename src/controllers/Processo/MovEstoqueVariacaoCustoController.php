<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\MovEstoqueVariacaoCustoHandler;

class MovEstoqueVariacaoCustoController extends Controller
{
    public function index(): void
    {
        $this->render('processo/relatorio-mov-estoque-variacao-custo', []);
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(MovEstoqueVariacaoCustoHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
