<?php

namespace src\controllers\PCP;

use core\Controller;
use src\handlers\PCP\ApontamentoProducaoHandler;

class ApontamentoProducaoController extends Controller
{
    public function index(): void
    {
        $this->render('pcp/apontamento-producao', []);
    }

    public function operacao(): void
    {
        if (empty($_SESSION['apont_sessao'])) {
            header('Location: ' . ($_SESSION['base'] ?? '/') . 'pcp-apontamento-producao');
            exit;
        }
        $this->render('pcp/apontamento-producao-operacao', []);
    }

    public function iniciarSessao(): void
    {
        header('Content-Type: application/json');
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $emprId    = (int) ($_SESSION['empresa']['id'] ?? 0);
            $funcId    = (int) ($body['func_id']    ?? 0);
            $operacaoId= (int) ($body['operacao_id'] ?? 0);
            $maquinaId = (int) ($body['maquina_id'] ?? 0);

            if (!$funcId || !$operacaoId) {
                echo json_encode(['error' => 'Preencha funcionário e operação.']);
                return;
            }

            $func    = ApontamentoProducaoHandler::buscarFuncionario($emprId, $funcId);
            $operacao= ApontamentoProducaoHandler::buscarOperacao($emprId, $operacaoId);
            $maquina = $maquinaId ? ApontamentoProducaoHandler::buscarMaquina($emprId, $maquinaId) : null;

            if (!$func)    { echo json_encode(['error' => 'Funcionário não encontrado.']); return; }
            if (!$operacao){ echo json_encode(['error' => 'Operação não encontrada.']);    return; }
            if ($maquinaId && !$maquina) { echo json_encode(['error' => 'Máquina não encontrada.']); return; }

            $_SESSION['apont_sessao'] = [
                'empr_id'      => $emprId,
                'func_id'      => $funcId,
                'func_nome'    => $func['NOME'],
                'operacao_id'  => $operacaoId,
                'operacao_nome'=> $operacao['DESCRICAO'],
                'maquina_id'   => $maquina ? $maquinaId : 0,
                'maquina_nome' => $maquina ? $maquina['DESCRICAO'] : '',
                'maquina_fixa' => $maquina ? true : false,
            ];

            echo json_encode(['ok' => true]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function encerrarSessao(): void
    {
        unset($_SESSION['apont_sessao']);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function buscarCodigo(): void
    {
        header('Content-Type: application/json');
        try {
            $tipo  = $_GET['tipo']   ?? '';
            $codigo= trim($_GET['codigo'] ?? '');
            $emprId= (int) ($_SESSION['empresa']['id'] ?? 0);

            $operacaoId = (int) ($_SESSION['apont_sessao']['operacao_id'] ?? 0);
            $result = match ($tipo) {
                'funcionario' => ApontamentoProducaoHandler::buscarFuncionarioPorCodigo($emprId, $codigo),
                'operacao'    => ApontamentoProducaoHandler::buscarOperacaoPorCodigo($emprId, $codigo),
                'maquina'     => ApontamentoProducaoHandler::buscarMaquinaPorCodigo($emprId, $codigo),
                'ordem'       => ApontamentoProducaoHandler::buscarOrdem($emprId, $codigo, $operacaoId),
                default       => null,
            };

            if (!$result) {
                echo json_encode(['error' => 'Código não encontrado.']);
                return;
            }

            // Valida APONTAMENTO independente do tipo (NUMBER 1/0 ou VARCHAR S/N)
            if ($tipo === 'ordem') {
                $etiqId = !empty($result['ETIQ_ID']) ? (int) $result['ETIQ_ID'] : null;

                if ($etiqId) {
                    // Etiqueta cancelada no Focco
                    if (!empty($result['CANCELADA']) && $result['CANCELADA'] == 1) {
                        echo json_encode(['error' => 'Etiqueta cancelada.']);
                        return;
                    }
                    // Etiqueta já lida (LIDO_ORD no banco ou lista da sessão)
                    $lidaNoBanco  = !empty($result['LIDO_ORD']) && $result['LIDO_ORD'] == 1;
                    $lidas        = $_SESSION['apont_sessao']['etiquetas_lidas'] ?? [];
                    $lidaNaSessao = in_array($etiqId, $lidas);
                    if ($lidaNoBanco || $lidaNaSessao) {
                        echo json_encode(['error' => 'Etiqueta já foi lida.']);
                        return;
                    }
                }

                $apont = $result['PERMITE_APONT'] ?? null;
                $permitido = ($apont == 1 || $apont === 'S' || $apont === '1');
                if (!$permitido) {
                    echo json_encode(['error' => 'Esse roteiro não permite apontamentos.']);
                    return;
                }
            }

            echo json_encode(['ok' => true, 'data' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function apontar(): void
    {
        header('Content-Type: application/json');
        try {
            $sessao = $_SESSION['apont_sessao'] ?? null;
            if (!$sessao) {
                echo json_encode(['error' => 'Sessão não iniciada.']);
                return;
            }

            $body       = json_decode(file_get_contents('php://input'), true) ?? [];
            $ordemRotId = (int) ($body['ordem_rot_id'] ?? 0);
            $quantidade = (float) ($body['quantidade']  ?? 1);
            $etiqId     = !empty($body['etiq_id']) ? (int) $body['etiq_id'] : null;
            $codBarra   = trim($body['cod_barra'] ?? '');

            if (!$ordemRotId) {
                echo json_encode(['error' => 'OrdemRoteiro inválida.']);
                return;
            }

            $resultado = ApontamentoProducaoHandler::apontar(
                $ordemRotId,
                $sessao['func_id'],
                $sessao['maquina_id'],
                $quantidade,
                'TP',
                $codBarra
            );

            // Bloqueia a etiqueta assim que vai pra fila (independente de sucesso)
            if ($etiqId) {
                ApontamentoProducaoHandler::marcarEtiquetaLida($etiqId);
                if (!in_array($etiqId, $_SESSION['apont_sessao']['etiquetas_lidas'] ?? [])) {
                    $_SESSION['apont_sessao']['etiquetas_lidas'][] = $etiqId;
                }
            }

            echo json_encode($resultado);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function listarOrdens(): void
    {
        header('Content-Type: application/json');
        try {
            $sessao = $_SESSION['apont_sessao'] ?? null;
            if (!$sessao) {
                echo json_encode(['error' => 'Sessão não iniciada.']);
                return;
            }
            $ordens = ApontamentoProducaoHandler::listarOrdens(
                $sessao['empr_id'],
                $sessao['func_id'],
                $sessao['operacao_id'],
                $sessao['maquina_id']
            );
            echo json_encode(['ok' => true, 'data' => $ordens]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function setMaquina(): void
    {
        header('Content-Type: application/json');
        try {
            $sessao = $_SESSION['apont_sessao'] ?? null;
            if (!$sessao) { echo json_encode(['error' => 'Sessão não iniciada.']); return; }

            $body     = json_decode(file_get_contents('php://input'), true) ?? [];
            $codigo   = trim($body['codigo'] ?? '');
            $emprId   = (int) $sessao['empr_id'];

            if (!$codigo) { echo json_encode(['error' => 'Código inválido.']); return; }

            $maquina = ApontamentoProducaoHandler::buscarMaquinaPorCodigo($emprId, $codigo);
            if (!$maquina) { echo json_encode(['error' => 'Máquina não encontrada.']); return; }

            $_SESSION['apont_sessao']['maquina_id']   = (int) $maquina['ID'];
            $_SESSION['apont_sessao']['maquina_nome'] = $maquina['DESCRICAO'];

            echo json_encode(['ok' => true, 'maquina' => $maquina]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function sessaoAtual(): void
    {
        header('Content-Type: application/json');
        $sessao = $_SESSION['apont_sessao'] ?? null;
        if (!$sessao) {
            echo json_encode(['ativa' => false]);
            return;
        }
        $pub = $sessao;
        unset($pub['token']);
        echo json_encode(['ativa' => true, 'sessao' => $pub]);
    }
}
