<?php

namespace src\controllers\Planejamento;

use core\Controller;
use src\handlers\Planejamento\PlanejamentoReposicaoCDHandler;

class PlanejamentoReposicaoCDController extends Controller
{
    public function index(): void
    {
        $this->render('planejamento/planejamento-reposicao-cd');
    }

    private function usuario(): string
    {
        return (string) ($_SESSION['user']['login'] ?? 'SISTEMA');
    }

    private function exec(callable $fn): void
    {
        try {
            self::response($fn(), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    public function listar(): void
    {
        $this->exec(fn() => PlanejamentoReposicaoCDHandler::listar());
    }

    public function recalcular(): void
    {
        $this->exec(fn() => PlanejamentoReposicaoCDHandler::recalcular($this->usuario()));
    }

    public function detalhe(): void
    {
        $this->exec(fn() => PlanejamentoReposicaoCDHandler::detalhe(self::getBody() ?? []));
    }

    public function planejadores(): void
    {
        $this->exec(fn() => PlanejamentoReposicaoCDHandler::planejadores());
    }

    public function alterar(): void
    {
        $this->exec(fn() => PlanejamentoReposicaoCDHandler::alterar(self::getBody() ?? [], $this->usuario()));
    }
}
