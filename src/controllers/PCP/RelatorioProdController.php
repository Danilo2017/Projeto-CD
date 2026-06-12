<?php

namespace src\controllers\PCP;

use core\Controller;
use src\handlers\PCP\RelatorioProdHandler;

class RelatorioProdController extends Controller
{
    public function index(): void
    {
        $empresas = RelatorioProdHandler::listarEmpresas();
        $this->render('pcp/relatorio-producao', ['empresas' => $empresas]);
    }

    public function buscar(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $emprId  = (int) ($body['empr_id']  ?? 0);
            $numLote = (int) ($body['num_lote']  ?? 0);

            if ($emprId <= 0 || $numLote <= 0) {
                throw new \Exception('Empresa e número do lote são obrigatórios.', 400);
            }

            $result = RelatorioProdHandler::buscar($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
