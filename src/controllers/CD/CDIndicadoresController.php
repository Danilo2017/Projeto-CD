<?php

namespace src\controllers\CD;

use \core\Controller as ctrl;
use src\handlers\CD\CDIndicadoresHandler;

/**
 * Controller dos Indicadores Mensais de Recebimento CD
 */
class CDIndicadoresController extends ctrl
{
    /**
     * Exibe a página de Indicadores
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Indicadores Mensais - Recebimento CD',
            'pagina' => 'Indicadores CD'
        ];

        $this->render('cd/indicadores', $dados);
    }

    /**
     * API dos indicadores do mês (GET ?mes=YYYY-MM)
     */
    public function dados()
    {
        try {
            $mes = $_GET['mes'] ?? date('Y-m');

            if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mes, $m)) {
                throw new \Exception('Mês inválido. Use o formato AAAA-MM.');
            }

            $resultado = CDIndicadoresHandler::indicadoresMes((int)$m[1], (int)$m[2]);

            self::response([
                'success' => true,
                'data' => $resultado
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
