<?php

namespace src\controllers\Faturamento;

use core\Controller as ctrl;
use src\models\Faturamento\CarteiraPDV;

class CarteiraPDVController extends ctrl
{
    public function index()
    {
        $this->render('faturamento/carteira-pdv', [
            'titulo' => 'Carteira PDV',
            'pagina' => 'Faturamento',
        ]);
    }

    /** GET /faturamento-carteira-api-l1?data_ini=YYYY-MM-DD&data_fim=YYYY-MM-DD */
    public function getNivel1()
    {
        try {
            $di = $_GET['data_ini'] ?? date('Y-m-01');
            $df = $_GET['data_fim'] ?? date('Y-m-t');
            self::response([
                'success' => true,
                'data'    => CarteiraPDV::getNivel1($di, $df),
                'total'   => CarteiraPDV::getTotalGeral($di, $df),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /faturamento-carteira-api-l2?empr_id=X&data_ini=...&data_fim=... */
    public function getNivel2()
    {
        try {
            $emprId = (int)($_GET['empr_id'] ?? 1);
            $di     = $_GET['data_ini'] ?? date('Y-m-01');
            $df     = $_GET['data_fim'] ?? date('Y-m-t');
            self::response([
                'success' => true,
                'data'    => CarteiraPDV::getNivel2($emprId, $di, $df),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /faturamento-carteira-api-l3?empr_id=X&data_ini=...&data_fim=...&tanque=Y */
    public function getNivel3()
    {
        try {
            $emprId = (int)($_GET['empr_id'] ?? 1);
            $di     = $_GET['data_ini'] ?? date('Y-m-01');
            $df     = $_GET['data_fim'] ?? date('Y-m-t');
            $tanque = $_GET['tanque']   ?? '';
            self::response([
                'success' => true,
                'data'    => CarteiraPDV::getNivel3($emprId, $di, $df, $tanque),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /faturamento-carteira-api-l4?empr_id=X&...&tanque=Y&sub_grupo=Z&familia=W&sub_familia=V */
    public function getNivel4()
    {
        try {
            $emprId    = (int)($_GET['empr_id']  ?? 1);
            $di        = $_GET['data_ini']        ?? date('Y-m-01');
            $df        = $_GET['data_fim']        ?? date('Y-m-t');
            $tanque    = $_GET['tanque']          ?? '';
            $subGrupo  = $_GET['sub_grupo']       ?? '';
            $familia   = $_GET['familia']         ?? '';
            $subFamilia= $_GET['sub_familia']     ?? '';
            self::response([
                'success' => true,
                'data'    => CarteiraPDV::getNivel4($emprId, $di, $df, $tanque, $subGrupo, $familia, $subFamilia),
            ], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
