<?php

namespace src\controllers\CD;

use \core\Controller as ctrl;
use src\handlers\CD\CDDashboardHandler;

/**
 * Controller do Dashboard CD
 * Responsável por orquestrar requisições, delegando lógica ao Handler
 */
class CDDashboardController extends ctrl
{
    private CDDashboardHandler $handler;

    public function __construct()
    {
        $this->handler = new CDDashboardHandler();
    }

    /**
     * Exibe a página principal do Dashboard CD
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Dashboard - Aviso de Recebimento',
            'pagina' => 'CD Dashboard'
        ];

        $this->render('cd/dashboard', $dados);
    }

    /**
     * API para retornar avisos de recebimento (JSON)
     */
    public function getAvisosRecebimento()
    {
        try {
            $resultado = $this->handler->getAvisosRecebimento();

            self::response([
                'success' => true,
                'data' => $resultado['avisos'],
                'resumo' => $resultado['resumo'],
                'ultima_atualizacao' => date('d/m/Y H:i:s')
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para retornar agendamentos pendentes (JSON)
     */
    public function getAgendamentosPendentes()
    {
        try {
            $resultado = $this->handler->getAgendamentosPendentes();

            self::response([
                'success' => true,
                'data' => $resultado['data'],
                'total' => $resultado['total']
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


