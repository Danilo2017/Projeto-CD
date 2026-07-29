<?php

namespace src\controllers\PCP;

use core\Controller;
use src\handlers\PCP\RelatorioProdHandler;

class RelatorioProdController extends Controller
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
        $this->render('pcp/relatorio-producao', []);
    }

    public function indexPillow(): void
    {
        $this->render('pcp/relatorio-pillow', []);
    }

    public function indexFpt(): void
    {
        $this->render('pcp/relatorio-fpt', []);
    }

    public function indexMesaFaixa(): void
    {
        $this->render('pcp/relatorio-mesa-faixa', []);
    }

    public function indexOptron(): void
    {
        $this->render('pcp/relatorio-optron', []);
    }

    public function indexTampoLiso(): void
    {
        $this->render('pcp/relatorio-tampo-liso', []);
    }

    public function indexTampoBordado(): void
    {
        $this->render('pcp/relatorio-tampo-bordado', []);
    }

    public function indexTampoBordadoMesa(): void
    {
        $this->render('pcp/relatorio-tampo-bordado-mesa', []);
    }

    public function indexManta(): void
    {
        $this->render('pcp/relatorio-manta', []);
    }

    public function indexMantaMesa(): void
    {
        $this->render('pcp/relatorio-manta-mesa', []);
    }

    public function indexMesaDeCorte(): void
    {
        $this->render('pcp/relatorio-mesa-de-corte', []);
    }

    public function indexBordadeira(): void
    {
        $this->render('pcp/relatorio-bordadeira', []);
    }

    public function indexTapecaria(): void
    {
        $this->render('pcp/relatorio-tapecaria', []);
    }

    public function indexRobotec(): void
    {
        $this->render('pcp/relatorio-robotec', []);
    }

    public function indexRoloBordado(): void
    {
        $this->render('pcp/relatorio-rolo-bordado', []);
    }

    public function indexConjugado(): void
    {
        $this->render('pcp/relatorio-conjugado', []);
    }

    public function indexTravePeze(): void
    {
        $this->render('pcp/relatorio-trave-peze', []);
    }

    public function indexMolasBordas(): void
    {
        $this->render('pcp/relatorio-molas-bordas', []);
    }

    public function indexCaixote(): void
    {
        $this->render('pcp/relatorio-caixote', []);
    }

    public function indexCaixaBox(): void
    {
        $this->render('pcp/relatorio-caixa-box', []);
    }

    public function buscar(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $robotec   = RelatorioProdHandler::buscarRobotec($emprId, $numLote);
            $tapecaria = RelatorioProdHandler::buscarTapecaria($emprId, $numLote);
            $conjugado = RelatorioProdHandler::buscarConjugado($emprId, $numLote);
            $travePeze = RelatorioProdHandler::buscarTravePeze($emprId, $numLote);

            $result = [
                'success'         => true,
                'data_lote'       => $robotec['data_lote']          ?? '',
                'linha_rows'      => $robotec['linha_rows']         ?? [],
                'mesa_rows'       => $robotec['mesa_rows']          ?? [],
                'colchaobox_rows' => $tapecaria['colchaobox_rows']  ?? [],
                'cabeceira_rows'  => $tapecaria['cabeceira_rows']   ?? [],
                'conjugado_rows'  => $conjugado['conjugado_rows']   ?? [],
                'travepeze_rows'  => $travePeze['travepeze_rows']   ?? [],
            ];

            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarPillow(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarPillow($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarFpt(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarFpt($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarMesaFaixa(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarMesaFaixa($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarOptron(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarOptron($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarTampoLiso(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarTampoLiso($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarTampoBordado(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarTampoBordado($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarTampoBordadoMesa(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarTampoBordadoMesa($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarManta(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarManta($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarMantaMesa(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarMantaMesa($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarMesaDeCorte(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarMesaDeCorte($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarBordadeira(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarBordadeira($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarTapecaria(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarTapecaria($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarRobotec(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarRobotec($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarRoloBordado(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RelatorioProdHandler::buscarRoloBordado($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarConjugado(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarConjugado($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarTravePeze(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarTravePeze($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarMolasBordas(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarMolasBordas($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarCaixote(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscar($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexRobotecAbastecedor(): void
    {
        $this->render('pcp/relatorio-robotec-abastecedor', []);
    }

    public function buscarRobotecAbastecedor(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarRobotecAbastecedor($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarCaixaBox(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarCaixaBox($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexVerticalEspuma(): void
    {
        $this->render('pcp/relatorio-vertical-espuma', []);
    }

    public function buscarVerticalEspuma(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarVerticalEspuma($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexResumoDeLote(): void
    {
        $this->render('pcp/resumo-lote', []);
    }

    public function buscarResumoDeLote(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarResumoDeLote($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexHorizontalEspuma(): void
    {
        $this->render('pcp/relatorio-horizontal-espuma', []);
    }

    public function buscarHorizontalEspuma(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = $this->emprIdSessao();
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarHorizontalEspuma($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexPcpMolas(): void
    {
        $this->render('pcp/relatorio-pcp-molas', []);
    }

    public function buscarPcpMolas(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarPcpMolas($numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexPcpCordao(): void
    {
        $this->render('pcp/relatorio-pcp-cordao', []);
    }

    public function buscarPcpCordao(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            if ($numLote <= 0) throw new \Exception('Número do lote é obrigatório.', 400);
            $result = RelatorioProdHandler::buscarPcpCordao($numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
