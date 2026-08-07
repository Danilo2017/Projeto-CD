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
            $dados    = self::getBody() ?? [];
            $emprIds  = array_values(array_filter(array_map('intval', (array) ($dados['empr_ids'] ?? [])), fn($id) => $id > 0));
            if (empty($emprIds)) {
                $sessionId = (int) ($_SESSION['empresa']['id'] ?? 0);
                if ($sessionId > 0) $emprIds = [$sessionId];
            }
            self::response(MovEstoqueVariacaoCustoHandler::listar($dados, $emprIds), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
