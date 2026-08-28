<?php

namespace src\controllers\Manutencao;

use core\Controller;
use src\models\Manutencao\OrdemManutencao;

class ManutencaoController extends Controller
{
    public function index(): void
    {
        $this->render('manutencao/gestao-ordens', []);
    }

    public function listarEmpresas(): void
    {
        try {
            self::response(['success' => true, 'data' => OrdemManutencao::listarEmpresas()]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarAberta(): void
    {
        try {
            $emprId  = (int) ($_GET['empr_id']  ?? ($_SESSION['empresa']['id'] ?? 0));
            $dataIni = $_GET['data_ini'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            self::response(['success' => true, 'data' => OrdemManutencao::listarAberta($emprId, $dataIni, $dataFim)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function detalharAberta(): void
    {
        try {
            $maqId   = (int) ($_GET['maquina_id'] ?? 0);
            $prio    = (int) ($_GET['prioridade']  ?? 0);
            $emprId  = (int) ($_GET['empr_id']     ?? ($_SESSION['empresa']['id'] ?? 0));
            $dataIni = $_GET['data_ini'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            self::response(['success' => true, 'data' => OrdemManutencao::detalharAberta($maqId, $prio, $emprId, $dataIni, $dataFim)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarAtendimento(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarAtendimento($emprId)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function detalharAtendimento(): void
    {
        try {
            $maqId = (int) ($_GET['maquina_id'] ?? 0);
            $prio  = (int) ($_GET['prioridade']  ?? 0);
            self::response(['success' => true, 'data' => OrdemManutencao::detalharAtendimento($maqId, $prio)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarLiberada(): void
    {
        try {
            $emprId  = (int) ($_GET['empr_id']  ?? ($_SESSION['empresa']['id'] ?? 0));
            $dataIni = $_GET['data_ini'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            self::response(['success' => true, 'data' => OrdemManutencao::listarLiberada($emprId, $dataIni, $dataFim)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarProgramada(): void
    {
        try {
            $emprId  = (int) ($_GET['empr_id']  ?? ($_SESSION['empresa']['id'] ?? 0));
            $dataIni = $_GET['data_ini'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            self::response(['success' => true, 'data' => OrdemManutencao::listarProgramada($emprId, $dataIni, $dataFim)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarFuncionarios(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarFuncionarios($emprId)]);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function atender(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::atender($ids);
            self::response(['success' => true, 'message' => 'Atendimento registrado']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function marcarOk(): void
    {
        try {
            $body   = self::getBody();
            $ids    = array_map('intval', (array) ($body['ids']     ?? []));
            $funcId = (int) ($body['func_id'] ?? 0);
            if (empty($ids))   throw new \Exception('Nenhuma ordem selecionada', 400);
            if (!$funcId)      throw new \Exception('Selecione o funcionário', 400);
            OrdemManutencao::marcarOk($ids, $funcId);
            self::response(['success' => true, 'message' => 'OK registrado']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function desmarcarOk(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::desmarcarOk($ids);
            self::response(['success' => true, 'message' => 'OK desmarcado']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function fechar(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            $obs  = trim($body['obs'] ?? '');
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::fechar($ids, $obs);
            self::response(['success' => true, 'message' => 'Ordem(ns) fechada(s)']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function excluir(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::excluir($ids);
            self::response(['success' => true, 'message' => 'Ordem(ns) excluída(s)']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }
}
