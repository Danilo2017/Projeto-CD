<?php

namespace src\controllers\Industrial;

use core\Controller;
use src\handlers\Industrial\PecasColaboradorHandler as H;

class PecasColaboradorController extends Controller
{
    public function index(): void
    {
        $this->render('industrial/pecas-colaborador');
    }

    public function listar(): void
    {
        try {
            @set_time_limit(300);
            self::response(H::listar(self::getBody() ?? []), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }
}
