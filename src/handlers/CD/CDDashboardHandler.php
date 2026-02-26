<?php

namespace src\handlers\CD;

use src\models\CD\AvisosRecebimento;
use src\models\CD\AgendamentoRecebimento;

/**
 * Handler para lógica de negócio do Dashboard CD
 * 
 * Responsabilidades:
 * - Chamar models para obter dados
 * - Aplicar regras de negócio
 * - Montar e formatar dados de resposta
 */
class CDDashboardHandler
{
    /**
     * Obter avisos de recebimento do dia com resumo mensal
     * 
     * @return array Dados formatados para resposta
     */
    public function getAvisosRecebimento(): array
    {
        $model = new AvisosRecebimento();
        $avisos = $model->listarAvisosHoje();
        $totaisMes = $model->getTotaisMes();

        return [
            'avisos' => $avisos,
            'resumo' => [
                'total' => $totaisMes['total'],
                'pendentes' => $totaisMes['pendentes'],
                'iniciados' => $totaisMes['iniciados'],
                'finalizados' => $totaisMes['finalizados']
            ],
            'ultima_atualizacao' => date('d/m/Y H:i:s')
        ];
    }

    /**
     * Obter agendamentos pendentes
     * 
     * @return array Dados formatados para resposta
     */
    public function getAgendamentosPendentes(): array
    {
        $model = new AgendamentoRecebimento();
        $agendamentos = $model->listarPendentes();

        return [
            'data' => $agendamentos,
            'total' => count($agendamentos)
        ];
    }
}
