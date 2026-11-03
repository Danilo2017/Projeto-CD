<?php

namespace src\controllers\PD;

use core\Controller;
use src\handlers\PD\AnaliseItensCriacaoHandler;
use src\models\PD\AnaliseItensCriacao;

class AnaliseItensCriacaoController extends Controller
{
    public function index(): void
    {
        $empresas = AnaliseItensCriacao::listarEmpresas();
        $this->render('pd/analise-itens-criacao', compact('empresas'));
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(AnaliseItensCriacaoHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function inativar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(AnaliseItensCriacaoHandler::inativar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
