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
            $recebimentos = CDCalendarioHandler::listarRecebimentos();

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

            $id = CDCalendarioHandler::salvarRecebimento($input);

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

            CDCalendarioHandler::atualizarRecebimento($input);

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

            CDCalendarioHandler::excluirRecebimento((int)$input['id']);

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

            $resultado = CDCalendarioHandler::alterarStatusRecebimento((int)$input['id']);

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

            $resultado = CDCalendarioHandler::gerarRecibo($input);

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

            $recibo = CDCalendarioHandler::buscarRecibo($id);

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

            $recibos = CDCalendarioHandler::listarRecibosPorAgendamento($agendamentoId);

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

    /**
     * API para listar anexos de um agendamento (GET ?agendamento_id=)
     */
    public function listarAnexos()
    {
        try {
            $agendamentoId = (int) ($_GET['agendamento_id'] ?? 0);
            if ($agendamentoId <= 0) {
                throw new \Exception('ID do agendamento não informado');
            }

            self::response([
                'success' => true,
                'data' => CDCalendarioHandler::listarAnexos($agendamentoId)
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para anexar comprovante (POST multipart: agendamento_id, arquivo)
     */
    public function uploadAnexo()
    {
        try {
            $id = CDCalendarioHandler::salvarAnexo(
                (int) ($_POST['agendamento_id'] ?? 0),
                $_FILES['arquivo'] ?? null,
                $_SESSION['user']['login'] ?? 'desconhecido'
            );

            self::response([
                'success' => true,
                'id' => $id,
                'message' => 'Comprovante anexado com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download/visualização do anexo (GET ?id=)
     */
    public function downloadAnexo()
    {
        try {
            $anexo = CDCalendarioHandler::downloadAnexo((int) ($_GET['id'] ?? 0));
            if (!$anexo) {
                http_response_code(404);
                echo 'Arquivo não encontrado';
                return;
            }

            // inline: PDF/imagem abrem direto no navegador
            header('Content-Type: ' . ($anexo['MIME_TYPE'] ?: 'application/octet-stream'));
            header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($anexo['NOME_ORIG']));
            header('Content-Length: ' . strlen($anexo['CONTEUDO']));
            echo $anexo['CONTEUDO'];
        } catch (\Exception $e) {
            http_response_code(500);
            echo $e->getMessage();
        }
    }

    /**
     * API para excluir anexo (POST JSON: id)
     */
    public function excluirAnexo()
    {
        try {
            $input = Request::getJsonBody();
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception('ID não informado');
            }

            CDCalendarioHandler::excluirAnexo($id);

            self::response([
                'success' => true,
                'message' => 'Anexo excluído com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


