<?php

namespace src\controllers;

use core\Request;
use src\models\GazinSqls;
use \core\Controller as ctrl;

class AdminSqlsController extends ctrl
{
    public function index()
    {
        $dados = [
            'titulo' => 'Gerenciar SQLs do Sistema',
            'pagina' => 'Admin SQLs',
        ];

        $this->render('admin/sqls', $dados);
    }

    public function listar()
    {
        try {
            $busca = Request::get('busca');
            $sqls = GazinSqls::listar($busca);

            self::response([
                'success' => true,
                'data' => $sqls
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function buscar()
    {
        try {
            $idsql = Request::get('idsql');
            if (!$idsql) {
                self::response(['success' => false, 'error' => 'idsql é obrigatório'], 400);
                return;
            }

            $sql = GazinSqls::buscarPorId($idsql);
            if (!$sql) {
                self::response(['success' => false, 'error' => 'SQL não encontrado'], 404);
                return;
            }

            self::response([
                'success' => true,
                'data' => $sql
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function salvar()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';
            $sql = $body['sql'] ?? '';
            $observacao = $body['observacao'] ?? null;

            if (empty($idsql) || empty($sql)) {
                self::response(['success' => false, 'error' => 'idsql e sql são obrigatórios'], 400);
                return;
            }

            // Validar formato do idsql (modulo.entidade.acao)
            if (!preg_match('/^[a-z0-9]+\.[a-z0-9]+\.[a-zA-Z0-9]+$/', $idsql)) {
                self::response(['success' => false, 'error' => 'Formato do idsql inválido. Use: modulo.entidade.acao'], 400);
                return;
            }

            $existente = GazinSqls::buscarPorId($idsql);
            if ($existente) {
                self::response(['success' => false, 'error' => 'idsql já existe. Use a opção de editar.'], 409);
                return;
            }

            $ok = GazinSqls::inserir($idsql, $sql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao inserir SQL'], 500);
                return;
            }

            // Registrar log (inserção = SQL anterior é NULL)
            $usuario = $_SESSION['user']['login'] ?? 'SISTEMA';
            GazinSqls::registrarLog($idsql, 'INSERT', null, $sql, $usuario, $observacao ?? 'Inserção de novo SQL');

            self::response(['success' => true, 'message' => 'SQL inserido com sucesso'], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function atualizar()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';
            $sql = $body['sql'] ?? '';
            $observacao = $body['observacao'] ?? null;

            if (empty($idsql) || empty($sql)) {
                self::response(['success' => false, 'error' => 'idsql e sql são obrigatórios'], 400);
                return;
            }

            // Buscar SQL anterior para log
            $existente = GazinSqls::buscarPorId($idsql);
            $sqlAnterior = $existente['SQL'] ?? null;

            $ok = GazinSqls::atualizar($idsql, $sql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao atualizar SQL'], 500);
                return;
            }

            // Registrar log
            $usuario = $_SESSION['user']['login'] ?? 'SISTEMA';
            GazinSqls::registrarLog($idsql, 'UPDATE', $sqlAnterior, $sql, $usuario, $observacao ?? 'Atualização de SQL');

            self::response(['success' => true, 'message' => 'SQL atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function excluir()
    {
        try {
            $body = Request::getJsonBody();
            $idsql = $body['idsql'] ?? '';
            $observacao = $body['observacao'] ?? null;

            if (empty($idsql)) {
                self::response(['success' => false, 'error' => 'idsql é obrigatório'], 400);
                return;
            }

            // Buscar SQL anterior para log
            $existente = GazinSqls::buscarPorId($idsql);
            $sqlAnterior = $existente['SQL'] ?? null;

            $ok = GazinSqls::excluir($idsql);
            if (!$ok) {
                self::response(['success' => false, 'error' => 'Erro ao excluir SQL'], 500);
                return;
            }

            // Registrar log (exclusão = SQL novo é vazio)
            $usuario = $_SESSION['user']['login'] ?? 'SISTEMA';
            GazinSqls::registrarLog($idsql, 'DELETE', $sqlAnterior, null, $usuario, $observacao ?? 'Exclusão de SQL');

            self::response(['success' => true, 'message' => 'SQL excluído com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validar sintaxe de um SQL (dry-run)
     */
    public function validar()
    {
        try {
            $body = Request::getJsonBody();
            $sql = $body['sql'] ?? '';

            if (empty($sql)) {
                self::response(['success' => false, 'error' => 'sql é obrigatório'], 400);
                return;
            }

            $resultado = GazinSqls::validarSintaxe($sql);

            self::response([
                'success' => true,
                'valido' => $resultado['valido'],
                'erro' => $resultado['erro']
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** TEMPORÁRIO — remover após execução */
    public function tmpInsertVerticalEspuma(): void
    {
        $idsql = 'pcp.relatorioProd.verticalEspuma';
        $sql   = "SELECT TABLES.ORD                 ORD,
       TORDENS.NUM_LOTE_PRO           LOTE,
       TABLES.NUM_ORDEM               NUM_ORDEM,
       TABLES.DESCICAO                DESCICAO,
       TITENS.DESC_TECNICA            DESC_TECNICA,
       TMASC_ITEM.MASCARA             MASCARA,
       SUM(TDEMANDAS.QTDE)            QTDE
  FROM TITENS_PLANEJAMENTO,
       TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(
               PI_EMPR_ID  => TORDENS.EMPR_ID,
               PI_LOTE     => TORDENS.NUM_LOTE_PRO,
               PI_ORDEM_ID => TORDENS.ID,
               PI_ORDEM    => ROWNUM)) TABLES,
       TDEMANDAS,
       TITENS_EMPR,
       TITENS,
       TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
GROUP BY TABLES.ORD,
         TORDENS.NUM_LOTE_PRO,
         TABLES.NUM_ORDEM,
         TABLES.DESCICAO,
         TITENS.DESC_TECNICA,
         TMASC_ITEM.MASCARA
ORDER BY MIN(TABLES.ORDEM) ASC";

        $novoSql = "SELECT TABLES.ORD                       ORD,
       TORDENS.NUM_LOTE_PRO               LOTE,
       TORDENS.NUM_ORDEM                  NUM_ORDEM,
       TABLES.DESC_TECNICA                DESCRICAO,
       TABLES.MASCARA                     MASCARA,
       TABLES.QTDE_OF                     QTDE,
       TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.MASCARA                 MASCARA_ITEM
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=>TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID=>TORDENS.ID,PI_ORDEM=>ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
ORDER BY TABLES.ORDEM ASC";

        try {
            $pdo = \core\Database::getInstance('focco');

            // 1. Lê o que está no Oracle agora
            $sel = $pdo->prepare("SELECT DBMS_LOB.SUBSTR(sql, 300, 1) atual FROM focco3i.gazin_sqls WHERE idsql = :idsql");
            $sel->execute([':idsql' => $idsql]);
            $sqlAtual = $sel->fetchColumn();

            // 2. UPDATE no Oracle
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE focco3i.gazin_sqls SET sql = :sql WHERE idsql = :idsql");
            $upd->bindParam(':idsql', $idsql,    \PDO::PARAM_STR);
            $upd->bindParam(':sql',   $novoSql,  \PDO::PARAM_STR);
            $upd->execute();
            $rowsUpdated = $upd->rowCount();
            $pdo->commit();

            // 3. Apaga TODOS os arquivos do cache de SQL do Focco (força releitura)
            $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
            $deleted  = [];
            $failed   = [];
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
                    if (unlink($f)) {
                        $deleted[] = basename($f);
                    } else {
                        $failed[] = basename($f);
                    }
                }
            }

            // 4. Lê de volta para confirmar o que ficou no Oracle
            $sel2 = $pdo->prepare("SELECT DBMS_LOB.SUBSTR(sql, 300, 1) novo FROM focco3i.gazin_sqls WHERE idsql = :idsql");
            $sel2->execute([':idsql' => $idsql]);
            $sqlNovo = $sel2->fetchColumn();

            self::response([
                'ok'           => true,
                'rows_updated' => $rowsUpdated,
                'sql_antes'    => substr($sqlAtual ?: '', 0, 200),
                'sql_depois'   => substr($sqlNovo  ?: '', 0, 200),
                'cache_deleted'=> $deleted,
                'cache_failed' => $failed,
                'cache_dir'    => $cacheDir,
            ], 200);
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            self::response(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar histórico de alterações de um SQL
     */
    public function historico()
    {
        try {
            $idsql = Request::get('idsql');
            if (!$idsql) {
                self::response(['success' => false, 'error' => 'idsql é obrigatório'], 400);
                return;
            }

            $historico = GazinSqls::listarHistorico($idsql);

            self::response([
                'success' => true,
                'data' => $historico
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
