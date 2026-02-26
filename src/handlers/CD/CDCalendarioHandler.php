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
    private AgendamentoRecebimento $agendamentoModel;
    private ReciboDescarga $reciboModel;

    public function __construct()
    {
        $this->agendamentoModel = new AgendamentoRecebimento();
        $this->reciboModel = new ReciboDescarga();
    }

    /**
     * Listar todos os recebimentos
     * 
     * @return array Lista de recebimentos
     */
    public function listarRecebimentos(): array
    {
        return $this->agendamentoModel->listarTodos();
    }

    /**
     * Salvar novo recebimento
     * 
     * @param array $dados Dados do recebimento
     * @return array Resultado com ID do registro criado
     * @throws \Exception Se duplicata recente ou erro
     */
    public function salvarRecebimento(array $dados): array
    {
        // Verificar duplicata recente (regra de negócio)
        if ($this->agendamentoModel->verificarDuplicataRecente($dados)) {
            throw new \Exception('Aguarde alguns segundos antes de cadastrar novamente');
        }

        $id = $this->agendamentoModel->inserir($dados);

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
    public function atualizarRecebimento(array $dados): array
    {
        $this->agendamentoModel->atualizar($dados);

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
    public function excluirRecebimento(int $id): array
    {
        $this->agendamentoModel->excluir($id);

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
    public function alterarStatusRecebimento(int $id): array
    {
        $resultado = $this->agendamentoModel->alterarStatus($id);

        return [
            'data' => $resultado,
            'message' => 'Status alterado com sucesso!'
        ];
    }

    /**
     * Gerar recibo de descarga
     * 
     * @param array $dados Dados do recibo
     * @return array Resultado com dados do recibo gerado
     */
    public function gerarRecibo(array $dados): array
    {
        $resultado = $this->reciboModel->inserir($dados);

        return [
            'data' => $resultado,
            'message' => 'Recibo gerado com sucesso!'
        ];
    }

    /**
     * Buscar recibo por ID
     * 
     * @param int $id ID do recibo
     * @return array|null Dados do recibo ou null se não encontrado
     */
    public function buscarRecibo(int $id): ?array
    {
        return $this->reciboModel->buscarPorId($id);
    }

    /**
     * Listar recibos de um agendamento
     * 
     * @param int $agendamentoId ID do agendamento
     * @return array Lista de recibos
     */
    public function listarRecibosPorAgendamento(int $agendamentoId): array
    {
        return $this->reciboModel->listarPorAgendamento($agendamentoId);
    }
}
