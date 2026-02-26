<?php

namespace src\controllers\CD;

use \core\Controller as ctrl;
use \core\Request;
use src\handlers\CD\CDCalendarioHandler;

/**
 * Controller do Calendário CD
 * Responsável por orquestrar requisições, delegando lógica ao Handler
 */
class CDCalendarioController extends ctrl
{
    private CDCalendarioHandler $handler;

    public function __construct()
    {
        $this->handler = new CDCalendarioHandler();
    }

    /**
     * Exibe a página do Calendário de Recebimento
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Calendário de Recebimento',
            'pagina' => 'Calendário CD'
        ];

        $this->render('cd/calendario', $dados);
    }

    /**
     * API para listar todos os recebimentos (GET)
     */
    public function listar()
    {
        try {
            $recebimentos = $this->handler->listarRecebimentos();

            self::response([
                'success' => true,
                'data' => $recebimentos
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para salvar um novo recebimento (POST)
     */
    public function salvar()
    {
        try {
            $input = Request::getJsonBody();

            if (!isset($input['data']) || !isset($input['fornecedor'])) {
                throw new \Exception('Campos obrigatórios não preenchidos');
            }

            $id = $this->handler->salvarRecebimento($input);

            self::response([
                'success' => true,
                'id' => $id,
                'message' => 'Recebimento salvo com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para atualizar um recebimento (PUT)
     */
    public function atualizar()
    {
        try {
            $input = Request::getJsonBody();

            if (!isset($input['id'])) {
                throw new \Exception('ID não informado');
            }

            $this->handler->atualizarRecebimento($input);

            self::response([
                'success' => true,
                'message' => 'Recebimento atualizado com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para excluir um recebimento (DELETE)
     */
    public function excluir()
    {
        try {
            $input = Request::getJsonBody();

            if (!isset($input['id'])) {
                throw new \Exception('ID não informado');
            }

            $this->handler->excluirRecebimento((int)$input['id']);

            self::response([
                'success' => true,
                'message' => 'Recebimento excluído com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para alterar status de um recebimento (PATCH)
     */
    public function alterarStatus()
    {
        try {
            $input = Request::getJsonBody();

            if (!isset($input['id'])) {
                throw new \Exception('ID não informado');
            }

            $resultado = $this->handler->alterarStatusRecebimento((int)$input['id']);

            self::response([
                'success' => true,
                'data' => $resultado,
                'message' => 'Status alterado com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para gerar recibo de descarga (POST)
     */
    public function gerarRecibo()
    {
        try {
            $input = Request::getJsonBody();

            if (empty($input['agendamento_id']) || empty($input['empresa_pagadora']) || !isset($input['valor_pago'])) {
                throw new \Exception('Preencha todos os campos obrigatórios.');
            }

            $resultado = $this->handler->gerarRecibo($input);

            self::response([
                'success' => true,
                'data' => $resultado,
                'message' => 'Recibo gerado com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API para buscar recibo por ID (GET)
     */
    public function buscarRecibo()
    {
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;

            if (!$id) {
                throw new \Exception('ID do recibo não informado');
            }

            $recibo = $this->handler->buscarRecibo($id);

            if (!$recibo) {
                throw new \Exception('Recibo não encontrado');
            }

            self::response([
                'success' => true,
                'data' => $recibo
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API para listar recibos de um agendamento (GET)
     */
    public function listarRecibos()
    {
        try {
            $agendamentoId = isset($_GET['agendamento_id']) ? intval($_GET['agendamento_id']) : null;

            if (!$agendamentoId) {
                throw new \Exception('ID do agendamento não informado');
            }

            $recibos = $this->handler->listarRecibosPorAgendamento($agendamentoId);

            self::response([
                'success' => true,
                'data' => $recibos
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}


