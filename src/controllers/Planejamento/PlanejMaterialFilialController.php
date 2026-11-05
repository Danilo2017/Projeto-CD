<?php

namespace src\controllers\Planejamento;

use core\Controller;
use src\handlers\Planejamento\PlanejMaterialFilialHandler as H;

class PlanejMaterialFilialController extends Controller
{
    public function index(): void
    {
        $this->render('planejamento/material-filial');
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

    public function filtros(): void      { $this->exec(fn() => H::filtros($this->usuario())); }
    public function gerar(): void        { $this->exec(fn() => H::gerar(self::getBody() ?? [], $this->usuario())); }
    public function listar(): void       { $this->exec(fn() => H::listar($this->usuario())); }
    public function selecionar(): void   { $this->exec(fn() => H::selecionar(self::getBody() ?? [], $this->usuario())); }
    public function detalhe(): void      { $this->exec(fn() => H::detalhe(self::getBody() ?? [], $this->usuario())); }
    public function resumo(): void       { $this->exec(fn() => H::resumo($this->usuario())); }
    public function qtde(): void         { $this->exec(fn() => H::alterarQtde(self::getBody() ?? [], $this->usuario())); }
    public function interfabrica(): void { $this->exec(fn() => H::interfabrica($this->usuario())); }
    public function mensagens(): void    { $this->exec(fn() => H::mensagens($this->usuario())); }
}
