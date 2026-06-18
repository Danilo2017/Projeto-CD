<?php

namespace src\controllers\Faturamento;

use \core\Controller as ctrl;
use src\handlers\Faturamento\FaturamentoProgramacaoHandler;

class FaturamentoProgramacaoController extends ctrl
{
    public function index()
    {
        $this->render('faturamento/programacao', [
            'titulo' => 'Programação de Pedidos',
            'pagina' => 'Faturamento',
        ]);
    }

    public function listar()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::listar();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resumoDashboard()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::resumoDashboard();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function ocupacao()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::ocupacao();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
