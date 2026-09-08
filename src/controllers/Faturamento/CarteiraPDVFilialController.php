<?php

namespace src\controllers\Faturamento;

use core\Controller as ctrl;
use src\models\Faturamento\CarteiraPDVFilial;

class CarteiraPDVFilialController extends ctrl
{
    public function index()
    {
        $this->render('faturamento/carteira-pdv-filial', [
            'titulo' => 'Carteira PDV — Visão por Filial',
            'pagina' => 'Faturamento',
        ]);
    }

    /** GET /faturamento-carteira-filial-l1?data_ini=YYYY-MM-DD&data_fim=YYYY-MM-DD */
    public function getNivel1()
    {
        try {
            $di = $_GET['data_ini'] ?? date('Y-m-01');
            $df = $_GET['data_fim'] ?? date('Y-m-t');
            self::response([
                'success' => true,
                'tanques' => CarteiraPDVFilial::getTanques($di, $df),
                'totais'  => CarteiraPDVFilial::getTotais($di, $df),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /faturamento-carteira-filial-l2?data_ini=...&data_fim=...&tanque=X */
    public function getNivel2()
    {
        try {
            $di     = $_GET['data_ini'] ?? date('Y-m-01');
            $df     = $_GET['data_fim'] ?? date('Y-m-t');
            $tanque = $_GET['tanque']   ?? '';
            self::response([
                'success' => true,
                'data'    => CarteiraPDVFilial::getSubGrupos($di, $df, $tanque),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /faturamento-carteira-filial-l3?data_ini=...&data_fim=...&tanque=X&sub_grupo=Y&familia=Z&sub_familia=W */
    public function getNivel3()
    {
        try {
            $di        = $_GET['data_ini']    ?? date('Y-m-01');
            $df        = $_GET['data_fim']    ?? date('Y-m-t');
            $tanque    = $_GET['tanque']      ?? '';
            $sg        = $_GET['sub_grupo']   ?? '';
            $fam       = $_GET['familia']     ?? '';
            $sfam      = $_GET['sub_familia'] ?? '';
            self::response([
                'success' => true,
                'data'    => CarteiraPDVFilial::getItens($di, $df, $tanque, $sg, $fam, $sfam),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
