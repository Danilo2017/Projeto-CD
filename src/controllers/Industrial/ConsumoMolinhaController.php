<?php

namespace src\controllers\Industrial;

use core\Controller;
use src\handlers\Industrial\ConsumoMolinhaHandler as H;

class ConsumoMolinhaController extends Controller
{
    public function index(): void
    {
        $this->render('industrial/consumo-molinha');
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
}
