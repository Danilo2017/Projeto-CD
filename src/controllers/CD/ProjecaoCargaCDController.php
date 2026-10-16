<?php

namespace src\controllers\CD;

use core\Controller;
use src\handlers\CD\ProjecaoCargaCDHandler;
use src\handlers\CD\ProjecaoCargaHandler;
use src\models\CD\ProjecaoCarga;
use src\models\CD\ProjecaoCargaCD;

/**
 * Projeção de Carga do CD — mesmas ações da Projeção de Carga, mas a empresa
 * vem da própria carga (validada contra os clientes do CD) e não da sessão.
 */
class ProjecaoCargaCDController extends Controller
{
    public function index(): void
    {
        $this->render('cd/projecao-carga-cd', []);
    }

    private function erro(\Exception $e): void
    {
        $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
        self::response(['error' => $e->getMessage()], $code ?: 500);
    }

    public function listar(): void
    {
        try {
            $body = self::getBody() ?? [];
            $hoje = date('Y-m-d');
            self::response(ProjecaoCargaCDHandler::listar(
                $body['data_inicio'] ?? $hoje,
                $body['data_fim'] ?? $hoje
            ), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function salvar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            $dados['empr_id'] = ProjecaoCargaCDHandler::empresaDaCarga((int) ($dados['num_carga'] ?? 0));
            $usuario = $_SESSION['user']['login'] ?? 'desconhecido';
            self::response(ProjecaoCargaHandler::salvar($dados, $usuario), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function listarLog(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            self::response(ProjecaoCargaHandler::listarLog([
                'empr_id'   => ProjecaoCargaCDHandler::empresaDaCarga($numCarga),
                'num_carga' => $numCarga,
            ]), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function listarAnexos(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            $emprId = ProjecaoCargaCDHandler::empresaDaCarga($numCarga);
            self::response(['success' => true, 'data' => ProjecaoCarga::listarAnexos($emprId, $numCarga)], 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function uploadAnexo(): void
    {
        try {
            $numCarga = (int) ($_POST['num_carga'] ?? 0);
            $arquivo  = $_FILES['arquivo'] ?? null;
            if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Arquivo inválido.', 400);
            }
            $emprId = ProjecaoCargaCDHandler::empresaDaCarga($numCarga);
            $id = ProjecaoCarga::salvarAnexo(
                $emprId,
                $numCarga,
                $arquivo['name'],
                $arquivo['type'] ?: 'application/octet-stream',
                (int) $arquivo['size'],
                file_get_contents($arquivo['tmp_name']),
                $_SESSION['user']['login'] ?? 'desconhecido'
            );
            $erroTransicao = null;
            try {
                ProjecaoCarga::marcarFinalizado($emprId, $numCarga);
            } catch (\Throwable $t) {
                $erroTransicao = $t->getMessage();
            }
            $resp = ['success' => true, 'id' => $id];
            if ($erroTransicao) $resp['_transicao_erro'] = $erroTransicao;
            self::response($resp, 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function downloadAnexo(): void
    {
        try {
            $emprId = ProjecaoCargaCDHandler::empresaDaCarga((int) ($_GET['num_carga'] ?? 0));
            $anexo  = ProjecaoCarga::downloadAnexo($emprId, (int) ($_GET['id'] ?? 0));
            if (!$anexo) { http_response_code(404); echo 'Arquivo não encontrado'; return; }
            header('Content-Type: ' . ($anexo['MIME_TYPE'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . rawurlencode($anexo['NOME_ORIG']) . '"');
            header('Content-Length: ' . strlen($anexo['CONTEUDO']));
            echo $anexo['CONTEUDO'];
        } catch (\Exception $e) {
            http_response_code(500);
            echo $e->getMessage();
        }
    }

    public function excluirAnexo(): void
    {
        try {
            $body = self::getBody() ?? [];
            $id   = (int) ($body['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID inválido.', 400);
            $emprId = ProjecaoCargaCDHandler::empresaDaCarga((int) ($body['num_carga'] ?? 0));
            ProjecaoCarga::excluirAnexo($emprId, $id);
            self::response(['success' => true], 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function listarRota(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            self::response(ProjecaoCargaHandler::listarRota([
                'empr_id'   => ProjecaoCargaCDHandler::empresaDaCarga($numCarga),
                'num_carga' => $numCarga,
            ]), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function salvarRota(): void
    {
        try {
            $dados = self::getBody() ?? [];
            ProjecaoCargaCDHandler::empresaDaCarga((int) ($dados['num_carga'] ?? 0));
            self::response(ProjecaoCargaHandler::salvarRota($dados), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function listarItens(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            self::response(ProjecaoCargaHandler::listarItens([
                'empr_id'   => ProjecaoCargaCDHandler::empresaDaCarga($numCarga),
                'num_carga' => $numCarga,
            ]), 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }

    public function listarItensExpedicao(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            ProjecaoCargaCDHandler::empresaDaCarga($numCarga);
            self::response([
                'success' => true,
                'data'    => ProjecaoCargaCD::listarItensExpedicao($numCarga, ProjecaoCargaCDHandler::WMS_SCHEMA),
            ], 200);
        } catch (\Exception $e) {
            $this->erro($e);
        }
    }
}
