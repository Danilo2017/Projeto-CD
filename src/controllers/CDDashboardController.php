<?php

namespace src\controllers;

use \core\Controller as ctrl;
use src\models\AvisosRecebimento;
use src\models\AgendamentoRecebimento;

class CDDashboardController extends ctrl
{
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
            $model = new AvisosRecebimento();
            $avisos = $model->listarAvisosHoje();
            
            // Buscar totais do mês
            $totaisMes = $model->getTotaisMes();

            $resultado = [
                'success' => true,
                'data' => $avisos,
                'resumo' => [
                    'total' => $totaisMes['total'],
                    'pendentes' => $totaisMes['pendentes'],
                    'iniciados' => $totaisMes['iniciados'],
                    'finalizados' => $totaisMes['finalizados']
                ],
                'ultima_atualizacao' => date('d/m/Y H:i:s')
            ];

            self::response($resultado, 200);
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
            $model = new AgendamentoRecebimento();
            $agendamentos = $model->listarPendentes();

            $resultado = [
                'success' => true,
                'data' => $agendamentos,
                'total' => count($agendamentos)
            ];

            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
