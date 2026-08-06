<?php

namespace src\controllers\Qualidade;

use core\Controller;
use src\handlers\PCP\RelatorioProdHandler;
use src\handlers\Qualidade\RastreabilidadeHandler;

class RastreabilidadeController extends Controller
{
    public function indexCostura(): void
    {
        $this->render('qualidade/rastreabilidade-costura', []);
    }

    public function indexLinhaMontagem(): void
    {
        $this->render('qualidade/rastreabilidade-linha-montagem', []);
    }

    public function buscarLinhaMontagem(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = (int) ($_SESSION['empresa']['id'] ?? 0);

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RastreabilidadeHandler::buscarLinhaMontagem($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexTampoBordado(): void
    {
        $this->render('qualidade/rastreabilidade-tampo-bordado', []);
    }

    public function buscarTampoBordado(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = (int) ($_SESSION['empresa']['id'] ?? 0);

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RastreabilidadeHandler::buscarTampoBordado($emprId, $numLote);
            $result['empresa_nome'] = strtoupper(
                $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?? ''
            );
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function indexMolas(): void
    {
        $this->render('qualidade/rastreabilidade-molas', []);
    }

    public function indexCordaoMolas(): void
    {
        $this->render('qualidade/rastreabilidade-cordao-molas', []);
    }

    public function indexBordaMolas(): void
    {
        $this->render('qualidade/rastreabilidade-borda-molas', []);
    }

    public function indexFixacaoBorda(): void
    {
        $this->render('qualidade/rastreabilidade-fixacao-borda', []);
    }

    public function buscarMolas(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = (int) ($_SESSION['empresa']['id'] ?? 0);

            if ($numLote <= 0) {
                throw new \Exception('Número do lote é obrigatório.', 400);
            }

            $result = RastreabilidadeHandler::buscarMolas($emprId, $numLote);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarCostura(): void
    {
        try {
            $body    = self::getBody() ?? [];
            $numLote = (int) ($body['num_lote'] ?? 0);
            $emprId  = (int) ($_SESSION['empresa']['id'] ?? 0);

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
}
