<?php

namespace src\controllers\Cd;

use core\Controller;
use src\handlers\Cd\ProjecaoCargaHandler;

class ProjecaoCargaController extends Controller
{
    private function emprIdSessao(): int
    {
        $id = (int) ($_SESSION['empresa']['id'] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Nenhuma empresa selecionada na sessão.', 400);
        }
        return $id;
    }

    public function index(): void
    {
        $this->render('cd/projecao-carga', []);
    }

    public function listar(): void
    {
        try {
            $body       = self::getBody() ?? [];
            $dataFiltro = $body['data_filtro'] ?? date('Y-m-d');
            $codEmp     = $_SESSION['empresa']['codigo'] ?? '';
            $wmsSchema  = (ctype_digit((string)$codEmp) && $codEmp > 0)
                            ? 'FOCCOWMS' . $codEmp . 'A'
                            : 'FOCCOWMS14A';
            $result     = ProjecaoCargaHandler::listar([
                'empr_id'     => $this->emprIdSessao(),
                'data_filtro' => $dataFiltro,
                'wms_schema'  => $wmsSchema,
            ]);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function salvar(): void
    {
        try {
            $dados          = self::getBody() ?? [];
            $dados['empr_id'] = $this->emprIdSessao();
            $usuario        = $_SESSION['user']['login'] ?? 'desconhecido';
            $result         = ProjecaoCargaHandler::salvar($dados, $usuario);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function listarLog(): void
    {
        try {
            $dados = [
                'empr_id'   => $this->emprIdSessao(),
                'num_carga' => $_GET['num_carga'] ?? '',
            ];
            $result = ProjecaoCargaHandler::listarLog($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function listarAnexos(): void
    {
        try {
            $numCarga = (int) ($_GET['num_carga'] ?? 0);
            if ($numCarga <= 0) { self::response(['error' => 'num_carga obrigatório.'], 400); return; }
            $lista = \src\models\Cd\ProjecaoCarga::listarAnexos($this->emprIdSessao(), $numCarga);
            self::response(['success' => true, 'data' => $lista], 200);
        } catch (\Exception $e) {
            self::response(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadAnexo(): void
    {
        try {
            $numCarga = (int) ($_POST['num_carga'] ?? 0);
            $arquivo  = $_FILES['arquivo'] ?? null;
            if ($numCarga <= 0 || !$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
                self::response(['error' => 'Arquivo inválido ou carga não informada.'], 400);
                return;
            }
            $id = \src\models\Cd\ProjecaoCarga::salvarAnexo(
                $this->emprIdSessao(),
                $numCarga,
                $arquivo['name'],
                $arquivo['type'] ?: 'application/octet-stream',
                (int) $arquivo['size'],
                file_get_contents($arquivo['tmp_name']),
                $_SESSION['user']['login'] ?? 'desconhecido'
            );
            self::response(['success' => true, 'id' => $id], 200);
        } catch (\Exception $e) {
            self::response(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadAnexo(): void
    {
        try {
            $id    = (int) ($_GET['id'] ?? 0);
            $anexo = \src\models\Cd\ProjecaoCarga::downloadAnexo($this->emprIdSessao(), $id);
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
            if ($id <= 0) { self::response(['error' => 'ID inválido.'], 400); return; }
            \src\models\Cd\ProjecaoCarga::excluirAnexo($this->emprIdSessao(), $id);
            self::response(['success' => true], 200);
        } catch (\Exception $e) {
            self::response(['error' => $e->getMessage()], 500);
        }
    }
}
