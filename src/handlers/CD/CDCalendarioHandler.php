<?php

namespace src\handlers\CD;

use src\models\CD\AgendamentoRecebimento;
use src\models\CD\ReciboDescarga;

/**
 * Handler para lógica de negócio do Calendário CD
 * 
 * Responsabilidades:
 * - Chamar models para obter/manipular dados
 * - Aplicar regras de negócio (validação de duplicatas, etc)
 * - Montar e formatar dados de resposta
 */
class CDCalendarioHandler
{
    /**
     * Listar todos os recebimentos
     * 
     * @return array Lista de recebimentos
     */
    public static function listarRecebimentos(): array
    {
        return AgendamentoRecebimento::listarTodos();
    }

    /**
     * Salvar novo recebimento
     * 
     * @param array $dados Dados do recebimento
     * @return array Resultado com ID do registro criado
     * @throws \Exception Se duplicata recente ou erro
     */
    public static function salvarRecebimento(array $dados): array
    {
        // Verificar duplicata recente (regra de negócio)
        if (AgendamentoRecebimento::verificarDuplicataRecente($dados)) {
            throw new \Exception('Aguarde alguns segundos antes de cadastrar novamente');
        }

        $id = AgendamentoRecebimento::inserir($dados);

        return [
            'id' => $id,
            'message' => 'Recebimento salvo com sucesso!'
        ];
    }

    /**
     * Atualizar recebimento existente
     * 
     * @param array $dados Dados do recebimento (deve incluir 'id')
     * @return array Resultado da operação
     */
    public static function atualizarRecebimento(array $dados): array
    {
        AgendamentoRecebimento::atualizar($dados);

        return [
            'message' => 'Recebimento atualizado com sucesso!'
        ];
    }

    /**
     * Excluir recebimento
     * 
     * @param int $id ID do recebimento
     * @return array Resultado da operação
     */
    public static function excluirRecebimento(int $id): array
    {
        AgendamentoRecebimento::excluir($id);

        return [
            'message' => 'Recebimento excluído com sucesso!'
        ];
    }

    /**
     * Alterar status do recebimento
     * 
     * @param int $id ID do recebimento
     * @return array Dados atualizados
     */
    public static function alterarStatusRecebimento(int $id): array
    {
        $resultado = AgendamentoRecebimento::alterarStatus($id);

        return [
            'data' => $resultado,
            'message' => 'Status alterado com sucesso!'
        ];
    }

    /**
     * Gerar recibo de descarga
     * 
     * @param array $dados Dados do recibo
     * @return array Dados do recibo gerado (id e numero_recibo)
     */
    public static function gerarRecibo(array $dados): array
    {
        return ReciboDescarga::inserir($dados);
    }

    /**
     * Buscar recibo por ID
     * 
     * @param int $id ID do recibo
     * @return array|null Dados do recibo ou null se não encontrado
     */
    public static function buscarRecibo(int $id): ?array
    {
        return ReciboDescarga::buscarPorId($id);
    }

    /**
     * Listar recibos de um agendamento
     * 
     * @param int $agendamentoId ID do agendamento
     * @return array Lista de recibos
     */
    public static function listarRecibosPorAgendamento(int $agendamentoId): array
    {
        return ReciboDescarga::listarPorAgendamento($agendamentoId);
    }
}
