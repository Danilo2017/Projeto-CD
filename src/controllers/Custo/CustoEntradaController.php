<?php

namespace src\controllers\Custo;

use core\Controller;
use src\handlers\Custo\CustoEntradaHandler as H;

class CustoEntradaController extends Controller
{
    public function index(): void
    {
        try {
            $empresas = H::empresas();
        } catch (\Throwable $e) {
            $empresas = [];
        }
        $this->render('custo/dashboard-entrada', compact('empresas'));
    }

    public function listar(): void
    {
        try {
            $body = self::getBody() ?? [];
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            @set_time_limit(300);
            self::response(H::listar($body), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    public function razao(): void
    {
        try {
            $body = self::getBody() ?? [];
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            @set_time_limit(120);
            self::response(H::razao($body), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }
}
