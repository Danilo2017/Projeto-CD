<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Faixas de Comissão
 * Tabela customizada: FOCCO3I.TGAZIN_FAIXA_COMISSAO
 * 
 * Estrutura da tabela:
 * - ID_FAIXA (PK)
 * - DESCRICAO
 * - TIPO (P=Percentual, Q=Quantidade)
 * - PONTO_INICIAL
 * - PONTO_FINAL
 * - VALOR_COMISSAO
 * - CENTRO_TRAB_ID (FK para TCENTROS_TRAB.ID)
 * - DT_VIGENCIA_INI
 * - DT_VIGENCIA_FIM
 * - ATIVO (S/N)
 * - DT_CADASTRO
 * - ID_USUARIO_CAD
 * - DT_ALTERACAO
 * - ID_USUARIO_ALT
 */
class FaixaComissao
{
    const TIPO_PERCENTUAL = 'P';
    const TIPO_QUANTIDADE = 'Q';

    /**
     * Listar todas as faixas ativas
     * @param int $emprId ID da empresa (opcional)
     * @param int $centroTrabId ID do centro de trabalho (opcional)
     * @return array
     */
    public function listarAtivas($emprId = null, $centroTrabId = null)
    {
        $sql = "SELECT 
                    FC.ID_FAIXA,
                    FC.DESCRICAO,
                    FC.TIPO,
                    CASE FC.TIPO 
                        WHEN 'P' THEN 'Percentual' 
                        WHEN 'Q' THEN 'Quantidade de Pontos' 
                    END AS DESC_TIPO,
                    FC.PONTO_INICIAL,
                    FC.PONTO_FINAL,
                    FC.VALOR_COMISSAO,
                    FC.CENTRO_TRAB_ID,
                    FC.ATIVO,
                    FC.DT_VIGENCIA_INI,
                    FC.DT_VIGENCIA_FIM,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    CASE 
                        WHEN CT.ID IS NOT NULL THEN CT.COD_CENTRO || ' - ' || CT.DESCRICAO 
                        ELSE NULL 
                    END AS CENTRO_DESCRICAO,
                    E.ID AS EMPR_ID,
                    E.COD_EMP AS COD_EMPRESA
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = FC.CENTRO_TRAB_ID
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = CT.EMPR_ID
                WHERE FC.ATIVO = 'S'
                AND (FC.DT_VIGENCIA_FIM IS NULL OR FC.DT_VIGENCIA_FIM >= TRUNC(SYSDATE))
                AND FC.DT_VIGENCIA_INI <= TRUNC(SYSDATE)";
        
        if ($emprId) {
            $sql .= " AND E.ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND (FC.CENTRO_TRAB_ID = :centro_trab_id OR FC.CENTRO_TRAB_ID IS NULL)";
        }
        
        $sql .= " ORDER BY FC.PONTO_INICIAL";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar faixa por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    FC.ID_FAIXA,
                    FC.DESCRICAO,
                    FC.TIPO,
                    FC.PONTO_INICIAL,
                    FC.PONTO_FINAL,
                    FC.VALOR_COMISSAO,
                    FC.CENTRO_TRAB_ID,
                    FC.ATIVO,
                    TO_CHAR(FC.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
                    TO_CHAR(FC.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = FC.CENTRO_TRAB_ID
                WHERE FC.ID_FAIXA = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar faixa aplicável para determinada pontuação
     * @param float $pontuacao
     * @param int $centroTrabId
     * @param string $dataReferencia
     * @return array|null
     */
    public function buscarFaixaAplicavel($pontuacao, $centroTrabId = null, $dataReferencia = null)
    {
        $dataRef = $dataReferencia ?? date('Y-m-d');
        
        $sql = "SELECT 
                    FC.ID_FAIXA,
                    FC.DESCRICAO,
                    FC.TIPO,
                    FC.PONTO_INICIAL,
                    FC.PONTO_FINAL,
                    FC.VALOR_COMISSAO
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC
                WHERE FC.ATIVO = 'S'
                AND FC.PONTO_INICIAL <= :pontuacao
                AND (FC.PONTO_FINAL IS NULL OR FC.PONTO_FINAL >= :pontuacao2)
                AND FC.DT_VIGENCIA_INI <= TO_DATE(:data_ref, 'YYYY-MM-DD')
                AND (FC.DT_VIGENCIA_FIM IS NULL OR FC.DT_VIGENCIA_FIM >= TO_DATE(:data_ref2, 'YYYY-MM-DD'))";
        
        if ($centroTrabId) {
            $sql .= " AND (FC.CENTRO_TRAB_ID = :centro_trab_id OR FC.CENTRO_TRAB_ID IS NULL)";
        } else {
            $sql .= " AND FC.CENTRO_TRAB_ID IS NULL";
        }
        
        $sql .= " ORDER BY FC.CENTRO_TRAB_ID NULLS LAST, FC.PONTO_INICIAL DESC FETCH FIRST 1 ROW ONLY";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pontuacao', $pontuacao, PDO::PARAM_STR);
        $stmt->bindParam(':pontuacao2', $pontuacao, PDO::PARAM_STR);
        $stmt->bindParam(':data_ref', $dataRef, PDO::PARAM_STR);
        $stmt->bindParam(':data_ref2', $dataRef, PDO::PARAM_STR);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar se existe conflito de faixa
     * (mesmo centro de trabalho, faixa de pontos e vigência que se sobrepõem)
     * @param array $dados
     * @param int|null $idExcluir ID da faixa a excluir da verificação (para edição)
     * @return array|null Retorna a faixa conflitante ou null se não houver
     */
    public function verificarConflito($dados, $idExcluir = null)
    {
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $pontoInicial = $dados['ponto_inicial'];
        $pontoFinal = $dados['ponto_final'] ?? null;
        $dtVigenciaIni = $dados['dt_vigencia_ini'];
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        
        // Verifica se há sobreposição de faixas de pontos E vigência
        $sql = "SELECT 
                    FC.ID_FAIXA,
                    FC.DESCRICAO,
                    FC.PONTO_INICIAL,
                    FC.PONTO_FINAL,
                    FC.DT_VIGENCIA_INI,
                    FC.DT_VIGENCIA_FIM,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = FC.CENTRO_TRAB_ID
                WHERE FC.ATIVO = 'S'";
        
        // Verificar centro de trabalho (incluindo NULL)
        if ($centroTrabId) {
            $sql .= " AND FC.CENTRO_TRAB_ID = :centro_trab_id";
        } else {
            $sql .= " AND FC.CENTRO_TRAB_ID IS NULL";
        }
        
        // Verificar sobreposição de faixas de pontos
        // Nova faixa [ponto_inicial, ponto_final] sobrepõe se:
        // (novo_ini <= existente_fim OR existente_fim IS NULL) AND (novo_fim >= existente_ini OR novo_fim IS NULL)
        $sql .= " AND (
            (:ponto_inicial <= FC.PONTO_FINAL OR FC.PONTO_FINAL IS NULL)
            AND (:ponto_final >= FC.PONTO_INICIAL OR :ponto_final2 IS NULL)
        )";
        
        // Verificar sobreposição de vigência
        // (nova_ini <= existente_fim OR existente_fim IS NULL) AND (nova_fim >= existente_ini OR nova_fim IS NULL)
        $sql .= " AND (
            (TO_DATE(:dt_vig_ini, 'YYYY-MM-DD') <= FC.DT_VIGENCIA_FIM OR FC.DT_VIGENCIA_FIM IS NULL)
            AND (TO_DATE(:dt_vig_fim, 'YYYY-MM-DD') >= FC.DT_VIGENCIA_INI OR :dt_vig_fim2 IS NULL)
        )";
        
        // Excluir registro atual (para edição)
        if ($idExcluir) {
            $sql .= " AND FC.ID_FAIXA != :id_excluir";
        }
        
        $sql .= " FETCH FIRST 1 ROW ONLY";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':ponto_inicial', $pontoInicial, PDO::PARAM_STR);
        $stmt->bindParam(':ponto_final', $pontoFinal, PDO::PARAM_STR);
        $stmt->bindParam(':ponto_final2', $pontoFinal, PDO::PARAM_STR);
        $stmt->bindParam(':dt_vig_ini', $dtVigenciaIni, PDO::PARAM_STR);
        $stmt->bindParam(':dt_vig_fim', $dtVigenciaFim, PDO::PARAM_STR);
        $stmt->bindParam(':dt_vig_fim2', $dtVigenciaFim, PDO::PARAM_STR);
        
        if ($idExcluir) {
            $stmt->bindParam(':id_excluir', $idExcluir, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Inserir nova faixa
     * @param array $dados
     * @return int ID inserido
     */
    public function inserir($dados)
    {
        $sql = "INSERT INTO FOCCO3I.TGAZIN_FAIXA_COMISSAO (
                    ID_FAIXA,
                    DESCRICAO,
                    TIPO,
                    PONTO_INICIAL,
                    PONTO_FINAL,
                    VALOR_COMISSAO,
                    CENTRO_TRAB_ID,
                    DT_VIGENCIA_INI,
                    DT_VIGENCIA_FIM,
                    ATIVO,
                    DT_CADASTRO,
                    ID_USUARIO_CAD
                ) VALUES (
                    FOCCO3I.SEQ_GAZIN_FAIXA_COMISSAO.NEXTVAL,
                    :descricao,
                    :tipo,
                    :ponto_inicial,
                    :ponto_final,
                    :valor_comissao,
                    :centro_trab_id,
                    TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'),
                    TO_DATE(:dt_vigencia_fim, 'YYYY-MM-DD'),
                    'S',
                    SYSDATE,
                    :id_usuario
                )";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':descricao', $dados['descricao'], PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $dados['tipo'], PDO::PARAM_STR);
        $stmt->bindParam(':ponto_inicial', $dados['ponto_inicial'], PDO::PARAM_STR);
        $pontoFinal = $dados['ponto_final'] ?? null;
        $stmt->bindParam(':ponto_final', $pontoFinal, PDO::PARAM_STR);
        $stmt->bindParam(':valor_comissao', $dados['valor_comissao'], PDO::PARAM_STR);
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        $stmt->bindParam(':dt_vigencia_ini', $dados['dt_vigencia_ini'], PDO::PARAM_STR);
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $stmt->bindParam(':dt_vigencia_fim', $dtVigenciaFim, PDO::PARAM_STR);
        $idUsuario = $dados['id_usuario'] ?? null;
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Buscar o último ID inserido
        $sqlId = "SELECT FOCCO3I.SEQ_GAZIN_FAIXA_COMISSAO.CURRVAL AS ID FROM DUAL";
        $stmtId = $pdo->query($sqlId);
        $result = $stmtId->fetch(PDO::FETCH_ASSOC);
        
        return $result['ID'] ?? null;
    }

    /**
     * Atualizar faixa
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_FAIXA_COMISSAO SET
                    DESCRICAO = :descricao,
                    TIPO = :tipo,
                    PONTO_INICIAL = :ponto_inicial,
                    PONTO_FINAL = :ponto_final,
                    VALOR_COMISSAO = :valor_comissao,
                    CENTRO_TRAB_ID = :centro_trab_id,
                    DT_VIGENCIA_INI = TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'),
                    DT_VIGENCIA_FIM = TO_DATE(:dt_vigencia_fim, 'YYYY-MM-DD'),
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_FAIXA = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':descricao', $dados['descricao'], PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $dados['tipo'], PDO::PARAM_STR);
        $stmt->bindParam(':ponto_inicial', $dados['ponto_inicial'], PDO::PARAM_STR);
        $pontoFinal = $dados['ponto_final'] ?? null;
        $stmt->bindParam(':ponto_final', $pontoFinal, PDO::PARAM_STR);
        $stmt->bindParam(':valor_comissao', $dados['valor_comissao'], PDO::PARAM_STR);
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        $stmt->bindParam(':dt_vigencia_ini', $dados['dt_vigencia_ini'], PDO::PARAM_STR);
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $stmt->bindParam(':dt_vigencia_fim', $dtVigenciaFim, PDO::PARAM_STR);
        $idUsuario = $dados['id_usuario'] ?? null;
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Ativar/Desativar faixa
     * @param int $id
     * @param string $ativo (S/N)
     * @param int $idUsuario
     * @return bool
     */
    public function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_FAIXA_COMISSAO SET
                    ATIVO = :ativo,
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_FAIXA = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':ativo', $ativo, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Inativar faixa
     * @param int $id
     * @param int $idUsuario
     * @return bool
     */
    public function inativar($id, $idUsuario = null)
    {
        return $this->alterarStatus($id, 'N', $idUsuario);
    }

    /**
     * Listar todas as faixas (incluindo inativas) para histórico
     * @return array
     */
    public function listarTodas($emprId = null)
    {
        $sql = "SELECT 
                    FC.ID_FAIXA,
                    FC.DESCRICAO,
                    FC.TIPO,
                    CASE FC.TIPO 
                        WHEN 'P' THEN 'Percentual' 
                        WHEN 'Q' THEN 'Quantidade de Pontos' 
                    END AS DESC_TIPO,
                    FC.PONTO_INICIAL,
                    FC.PONTO_FINAL,
                    FC.VALOR_COMISSAO,
                    FC.CENTRO_TRAB_ID,
                    FC.ATIVO,
                    CASE FC.ATIVO WHEN 'S' THEN 'Ativo' ELSE 'Inativo' END AS DESC_ATIVO,
                    FC.DT_VIGENCIA_INI,
                    FC.DT_VIGENCIA_FIM,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    CASE 
                        WHEN CT.ID IS NOT NULL THEN CT.COD_CENTRO || ' - ' || CT.DESCRICAO 
                        ELSE NULL 
                    END AS CENTRO_DESCRICAO,
                    E.ID AS EMPR_ID,
                    E.COD_EMP AS COD_EMPRESA
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = FC.CENTRO_TRAB_ID
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = CT.EMPR_ID
                WHERE 1=1";
        
        if ($emprId) {
            $sql .= " AND E.ID = :empr_id";
        }
        
        $sql .= " ORDER BY FC.PONTO_INICIAL, FC.ATIVO DESC";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


