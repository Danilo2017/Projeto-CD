<?php

namespace src\controllers\Planejamento;

use core\Controller;
use src\handlers\Planejamento\CompraDiretaCrossHandler as H;

class CompraDiretaCrossController extends Controller
{
    public function index(): void
    {
        $this->render('planejamento/compra-direta-cross');
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

    public function filtros(): void     { $this->exec(fn() => H::filtros($this->usuario())); }
    public function gerar(): void       { $this->exec(fn() => H::gerar(self::getBody() ?? [], $this->usuario())); }
    public function listar(): void      { $this->exec(fn() => H::listar($this->usuario())); }
    public function detalhe(): void     { $this->exec(fn() => H::detalhe(self::getBody() ?? [], $this->usuario())); }
    public function status(): void      { $this->exec(fn() => H::alterarStatus(self::getBody() ?? [], $this->usuario())); }
    public function opcoesLinha(): void { $this->exec(fn() => H::opcoesLinha($this->usuario())); }
    public function mrpStatus(): void   { $this->exec(fn() => H::mrpStatus($this->usuario())); }
    public function liberar(): void     { $this->exec(fn() => H::liberarOrdens(self::getBody() ?? [], $this->usuario())); }
    public function concluir(): void    { $this->exec(fn() => H::concluirMrp($this->usuario())); }

    /** Gerar Linha: cria a demanda e dispara o MRP em segundo plano (a tela acompanha pelo mrpStatus). */
    public function gerarLinha(): void  { $this->exec(fn() => H::gerarLinha(self::getBody() ?? [], $this->usuario())); }
}
