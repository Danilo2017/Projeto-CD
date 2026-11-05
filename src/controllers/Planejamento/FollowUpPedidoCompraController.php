<?php

namespace src\controllers\Planejamento;

use core\Controller;
use src\handlers\Planejamento\FollowUpPedidoCompraHandler as H;

class FollowUpPedidoCompraController extends Controller
{
    public function index(): void
    {
        $this->render('planejamento/follow-up-pedido-compra');
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

    public function filtros(): void      { $this->exec(fn() => H::filtros()); }
    public function fornecedores(): void { $this->exec(fn() => H::fornecedores(self::getBody() ?? [])); }
    public function mascaras(): void     { $this->exec(fn() => H::mascaras(self::getBody() ?? [])); }
    public function listar(): void       { $this->exec(fn() => H::listar(self::getBody() ?? [])); }
}
