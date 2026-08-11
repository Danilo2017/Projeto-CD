<?php

namespace src\controllers\Faturamento;

use core\Controller;
use src\handlers\Faturamento\EficienciaUepHandler;

class EficienciaUepController extends Controller
{
    public function index(): void
    {
        $this->render('faturamento/eficiencia-uep', []);
    }

    public function listar(): void
    {
        try {
            self::response(EficienciaUepHandler::listar(), 200);
        } catch (\Exception $e) {
            self::response(['error' => $e->getMessage()], 500);
        }
    }

    public function paginaDetalhe(): void
    {
        $this->render('faturamento/eficiencia-uep-detalhe', []);
    }

    public function detalhe(): void
    {
        try {
            $emprId        = (int) ($_GET['empr_id'] ?? 0);
            $classificacao = trim($_GET['classificacao'] ?? '');
            if ($emprId <= 0 || $classificacao === '') {
                self::response(['error' => 'empr_id e classificacao são obrigatórios.'], 400);
                return;
            }
            $dados = \src\models\Faturamento\EficienciaUep::detalhe($emprId, $classificacao);
            self::response(['success' => true, 'data' => $dados], 200);
        } catch (\Exception $e) {
            self::response(['error' => $e->getMessage()], 500);
        }
    }
}
