<?php

namespace src\controllers;

use \core\Controller as ctrl;
use \core\Request;
use src\models\AgendamentoRecebimento;
use src\models\ReciboDescarga;

class CDCalendarioController extends ctrl
{
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
            $model = new AgendamentoRecebimento();
            $recebimentos = $model->listarTodos();

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

            $model = new AgendamentoRecebimento();
            
            // Verificar duplicata recente
            if ($model->verificarDuplicataRecente($input)) {
                throw new \Exception('Aguarde alguns segundos antes de cadastrar novamente');
            }

            $id = $model->inserir($input);

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

            $model = new AgendamentoRecebimento();
            $model->atualizar($input);

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

            $model = new AgendamentoRecebimento();
            $model->excluir($input['id']);

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

            $model = new AgendamentoRecebimento();
            $resultado = $model->alterarStatus($input['id']);

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

            // Validação dos campos obrigatórios
            if (empty($input['agendamento_id']) || empty($input['empresa_pagadora']) || !isset($input['valor_pago'])) {
                throw new \Exception('Preencha todos os campos obrigatórios.');
            }

            $model = new ReciboDescarga();
            $resultado = $model->inserir($input);

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

            $model = new ReciboDescarga();
            $recibo = $model->buscarPorId($id);

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

            $model = new ReciboDescarga();
            $recibos = $model->listarPorAgendamento($agendamentoId);

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
