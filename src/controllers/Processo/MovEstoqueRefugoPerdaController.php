<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\MovEstoqueRefugoPerdaHandler;

class MovEstoqueRefugoPerdaController extends Controller
{
    public function index(): void
    {
        $this->render('processo/relatorio-mov-estoque-refugo', []);
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(MovEstoqueRefugoPerdaHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
