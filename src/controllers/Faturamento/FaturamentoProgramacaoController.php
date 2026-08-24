<?php

namespace src\controllers\Faturamento;

use \core\Controller as ctrl;
use src\handlers\Faturamento\FaturamentoProgramacaoHandler;
use src\utils\DashboardCache;
use src\utils\GetSqlFocco;

class FaturamentoProgramacaoController extends ctrl
{
    public function index()
    {
        $this->render('faturamento/programacao', [
            'titulo' => 'Programação de Pedidos',
            'pagina' => 'Faturamento',
        ]);
    }

    public function listar()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::listar();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resumoDashboard()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::resumoDashboard();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function ocupacao()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::ocupacao();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function buscarCliente()
    {
        try {
            $cod = (int) ($_GET['cod'] ?? 0);
            if (!$cod) {
                self::response(['success' => false, 'error' => 'Código inválido.'], 400);
                return;
            }
            $cliente = FaturamentoProgramacaoHandler::buscarCliente($cod);
            if (!$cliente) {
                self::response(['success' => false, 'error' => 'Cliente não encontrado.'], 404);
                return;
            }
            self::response(['success' => true, 'data' => $cliente], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarClientes()
    {
        try {
            $clientes = FaturamentoProgramacaoHandler::listarClientesProgramacao();
            self::response(['success' => true, 'data' => $clientes], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function incluirCliente()
    {
        try {
            $body       = json_decode(file_get_contents('php://input'), true) ?? [];
            $codCli     = (int) ($body['cod_cli']     ?? 0);
            $descricao  = trim($body['descricao']     ?? '');
            $tipo       = trim($body['tipo']           ?? '');
            $canal      = trim($body['canal']          ?? '');
            $programacao= trim($body['programacao']    ?? '');
            $editar     = !empty($body['editar']);

            if (!$codCli || !$tipo || !$canal || !$programacao) {
                self::response(['success' => false, 'error' => 'Preencha todos os campos.'], 400);
                return;
            }

            FaturamentoProgramacaoHandler::salvarCliente($codCli, $descricao, $tipo, $canal, $programacao, $editar);
            self::response(['success' => true], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function flushCache()
    {
        GetSqlFocco::invalidar('faturamento.programacao.listar');
        GetSqlFocco::invalidar('faturamento.programacao.tanques');
        GetSqlFocco::invalidar('faturamento.programacao.dias-uteis');
        DashboardCache::forget('programacao.listar');
        DashboardCache::forget('programacao.ocupacao');
        $mes = (new \DateTime())->format('Y-m');
        DashboardCache::forget('programacao.resumo_dashboard_' . $mes);
        self::response(['success' => true, 'message' => 'Cache limpo com sucesso.'], 200);
    }
}
