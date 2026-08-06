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
            $dados  = self::getBody() ?? [];
            $emprId = (int) ($_SESSION['empresa']['id'] ?? 0);
            self::response(MovEstoqueVariacaoCustoHandler::listar($dados, $emprId), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
