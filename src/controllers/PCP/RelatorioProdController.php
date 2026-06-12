<?php

namespace src\controllers\PCP;

use core\Controller;
use src\handlers\PCP\RelatorioProdHandler;

class RelatorioProdController extends Controller
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
        $this->render('pcp/relatorio-producao', []);
    }

    public function buscar(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscar($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
