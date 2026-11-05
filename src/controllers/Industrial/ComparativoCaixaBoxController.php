<?php

namespace src\controllers\Industrial;

use core\Controller;
use src\handlers\Industrial\ComparativoCaixaBoxHandler as H;

class ComparativoCaixaBoxController extends Controller
{
    public function index(): void
    {
        $this->render('industrial/comparativo-caixa-box');
    }

    /** Dados de um mês (a tela pede os meses em paralelo e vai montando os gráficos). */
    public function mes(): void
    {
        try {
            $body = self::getBody() ?? [];
            // Libera a sessão para as requisições dos outros meses rodarem em paralelo
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            @set_time_limit(300);
            self::response(H::mes($body), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }
}
