<?php

namespace src\controllers\Manutencao;

use core\Controller;
use src\models\Manutencao\OrdemManutencao;
use src\models\Manutencao\OrdemChecklist;
use src\models\Manutencao\DashboardManutencao;

class ManutencaoController extends Controller
{
    public function index(): void
    {
        $this->render('manutencao/gestao-ordens', []);
    }

    public function listarEmpresas(): void
    {
        try {
            self::response(['success' => true, 'data' => OrdemManutencao::listarEmpresas()], 200);
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
            self::response(['success' => true, 'data' => OrdemManutencao::listarAberta($emprId, $dataIni, $dataFim)], 200);
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
            self::response(['success' => true, 'data' => OrdemManutencao::detalharAberta($maqId, $prio, $emprId, $dataIni, $dataFim)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarAtendimento(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarAtendimento($emprId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function detalharAtendimento(): void
    {
        try {
            $maqId = (int) ($_GET['maquina_id'] ?? 0);
            $prio  = (int) ($_GET['prioridade']  ?? 0);
            self::response(['success' => true, 'data' => OrdemManutencao::detalharAtendimento($maqId, $prio)], 200);
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
            self::response(['success' => true, 'data' => OrdemManutencao::listarLiberada($emprId, $dataIni, $dataFim)], 200);
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
            self::response(['success' => true, 'data' => OrdemManutencao::listarProgramada($emprId, $dataIni, $dataFim)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarFuncionarios(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarFuncionarios($emprId)], 200);
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
            self::response(['success' => true, 'message' => 'Atendimento registrado'], 200);
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
            self::response(['success' => true, 'message' => 'OK registrado'], 200);
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
            self::response(['success' => true, 'message' => 'OK desmarcado'], 200);
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
            self::response(['success' => true, 'message' => 'Ordem(ns) fechada(s)'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function liberacaoOrdens(): void
    {
        $this->render('manutencao/liberacao-ordens', []);
    }

    public function gerarOrdem(): void
    {
        $this->render('manutencao/gerar-ordem', []);
    }

    public function chklistConfig(): void
    {
        $this->render('manutencao/chklist-config', []);
    }

    public function apiMaquinas(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarMaquinas($emprId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiSolicitantes(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarSolicitantes($emprId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiGerarOrdem(): void
    {
        try {
            $body = self::getBody();
            $body['empr_id'] = (int) ($_SESSION['empresa']['id'] ?? 0);
            $ordemId = OrdemManutencao::gerarOrdem($body);

            $chklist = (array) ($body['checklist'] ?? []);
            if ($ordemId && !empty($chklist)) {
                OrdemChecklist::registrarRespostas($ordemId, $chklist);
            }
            self::response(['success' => true, 'ordem_id' => $ordemId, 'message' => 'Ordem gerada com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiChklistMaquina(): void
    {
        try {
            $maqId = (int) ($_GET['maquina_id'] ?? 0);
            self::response(['success' => true, 'data' => OrdemChecklist::listarPorMaquina($maqId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiChklistTodos(): void
    {
        try {
            $maqId = (int) ($_GET['maquina_id'] ?? 0);
            self::response(['success' => true, 'data' => OrdemChecklist::listarTodosPorMaquina($maqId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiChklistSalvar(): void
    {
        try {
            $body  = self::getBody();
            $maqId = (int) ($body['maquina_id'] ?? 0);
            $desc  = trim($body['descricao'] ?? '');
            if (!$maqId || $desc === '') throw new \Exception('Dados inválidos', 400);
            OrdemChecklist::salvar($maqId, $desc);
            self::response(['success' => true, 'message' => 'Item salvo'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function apiChklistExcluir(): void
    {
        try {
            $body = self::getBody();
            $id   = (int) ($body['id'] ?? 0);
            if (!$id) throw new \Exception('ID inválido', 400);
            OrdemChecklist::excluir($id);
            self::response(['success' => true, 'message' => 'Item excluído'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function listarLiberacao(): void
    {
        try {
            $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
            self::response(['success' => true, 'data' => OrdemManutencao::listarLiberacao($emprId)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function atenderLiberacao(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::atender($ids);
            self::response(['success' => true, 'message' => 'Atendimento registrado'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function fecharLiberacao(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::fecharLiberacao($ids);
            self::response(['success' => true, 'message' => 'Ordem(ns) fechada(s)'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function dashboardManutencao(): void
    {
        $this->render('manutencao/dashboard', []);
    }

    private function dashParams(): array
    {
        $emprId = (int) ($_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 0));
        $di     = $_GET['data_ini'] ?? date('Y-m-01');
        $df     = $_GET['data_fim'] ?? date('Y-m-t');
        return [$emprId, $di, $df];
    }

    public function apiDashResumo(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getResumo($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashDistrib(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getDistribuicao($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashGeradas(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getGeradasAtendidas($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashGrupos(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getOrdensGrupo($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashPreventivas(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getPreventivas($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashFuncOrdens(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getFuncOrdens($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashMinutos(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getMinutosPorTipo($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDashFuncHoras(): void
    {
        try {
            [$emprId, $di, $df] = $this->dashParams();
            self::response(['success' => true, 'data' => DashboardManutencao::getFuncHoras($emprId, $di, $df)], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function excluir(): void
    {
        try {
            $body = self::getBody();
            $ids  = array_map('intval', (array) ($body['ids'] ?? []));
            if (empty($ids)) throw new \Exception('Nenhuma ordem selecionada', 400);
            OrdemManutencao::excluir($ids);
            self::response(['success' => true, 'message' => 'Ordem(ns) excluída(s)'], 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['success' => false, 'error' => $e->getMessage()], $code ?: 500);
        }
    }
}
