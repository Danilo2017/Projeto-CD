<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Gestão de Retrabalho
 * 
 * Tabela: FOCCO3I.TGAZIN_RETRABALHO
 * 
 * Regras:
 * - Retrabalho é separado da produção normal
 * - Impacta a comissão conforme regra definida (percentual, valor fixo ou penalização)
 * - Permite edição e exclusão com auditoria
 * - Incluído no cálculo final da comissão
 */
class Retrabalho
{
    const TIPO_PERCENTUAL = 'P';
    const TIPO_VALOR_FIXO = 'V';
    const TIPO_ZERAR = 'Z';

    /**
     * Inserir retrabalho
     * @param array $dados
     * @return int ID do retrabalho inserido
     */
    public function inserir($dados)
    {
        $sql = "INSERT INTO FOCCO3I.TGAZIN_RETRABALHO (
                    ID_EMPR,
                    ID_FUNCIONARIO,
                    ID_RECURSO,
                    ID_ITEM,
                    ID_MASCARA,
                    ID_ORDEM,
                    DT_RETRABALHO,
                    QUANTIDADE,
                    MOTIVO,
                    TIPO_IMPACTO,
                    VALOR_IMPACTO,
                    ATIVO,
                    DT_CADASTRO,
                    ID_USUARIO_CAD
                ) VALUES (
                    :id_empr,
                    :id_funcionario,
                    :id_recurso,
                    :id_item,
                    :id_mascara,
                    :id_ordem,
                    TO_DATE(:dt_retrabalho, 'YYYY-MM-DD'),
                    :quantidade,
                    :motivo,
                    :tipo_impacto,
                    :valor_impacto,
                    'S',
                    SYSDATE,
                    :id_usuario
                )";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmt->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_recurso', $dados['id_recurso'], PDO::PARAM_INT);
        $stmt->bindParam(':id_item', $dados['id_item'], PDO::PARAM_INT);
        $stmt->bindParam(':id_mascara', $dados['id_mascara'], PDO::PARAM_INT);
        $stmt->bindParam(':id_ordem', $dados['id_ordem'], PDO::PARAM_INT);
        $stmt->bindParam(':dt_retrabalho', $dados['dt_retrabalho'], PDO::PARAM_STR);
        $stmt->bindParam(':quantidade', $dados['quantidade'], PDO::PARAM_STR);
        $stmt->bindParam(':motivo', $dados['motivo'], PDO::PARAM_STR);
        $tipoImpacto = $dados['tipo_impacto'] ?? self::TIPO_PERCENTUAL;
        $stmt->bindParam(':tipo_impacto', $tipoImpacto, PDO::PARAM_STR);
        $valorImpacto = $dados['valor_impacto'] ?? 0;
        $stmt->bindParam(':valor_impacto', $valorImpacto, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);

        $stmt->execute();

        // Buscar o ID inserido (tabela usa IDENTITY, não sequence)
        $sqlId = "SELECT MAX(ID_RETRABALHO) FROM FOCCO3I.TGAZIN_RETRABALHO 
                  WHERE ID_FUNCIONARIO = :id_funcionario 
                  AND DT_RETRABALHO = TO_DATE(:dt_retrabalho, 'YYYY-MM-DD')
                  AND ID_EMPR = :id_empr";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmtId->bindParam(':dt_retrabalho', $dados['dt_retrabalho'], PDO::PARAM_STR);
        $stmtId->bindParam(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmtId->execute();
        $id = $stmtId->fetchColumn();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_RETRABALHO', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Listar retrabalhos com filtros
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $sql = "SELECT 
                    R.ID_RETRABALHO,
                    R.ID_EMPR,
                    E.RAZAO_SOCIAL AS EMPRESA,
                    R.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    R.ID_RECURSO,
                    M.COD_MAQUINA,
                    M.DESCRICAO AS DESC_RECURSO,
                    R.ID_ITEM,
                    I.COD_ITEM,
                    I.DESC_TECNICA AS DESC_ITEM,
                    R.ID_MASCARA,
                    MI.MASCARA,
                    R.ID_ORDEM,
                    O.NUM_ORDEM,
                    TO_CHAR(R.DT_RETRABALHO, 'DD/MM/YYYY') AS DT_RETRABALHO_FMT,
                    TO_CHAR(R.DT_RETRABALHO, 'YYYY-MM-DD') AS DT_RETRABALHO,
                    R.QUANTIDADE,
                    R.MOTIVO,
                    R.TIPO_IMPACTO,
                    CASE R.TIPO_IMPACTO 
                        WHEN 'P' THEN 'Percentual' 
                        WHEN 'V' THEN 'Valor Fixo' 
                        WHEN 'Z' THEN 'Zerar Comissão' 
                    END AS DESC_TIPO_IMPACTO,
                    R.VALOR_IMPACTO,
                    R.ATIVO,
                    TO_CHAR(R.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') AS DT_CADASTRO,
                    R.ID_USUARIO_CAD
                FROM FOCCO3I.TGAZIN_RETRABALHO R
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = R.ID_FUNCIONARIO
                INNER JOIN FOCCO3I.TITENS I ON I.ID = R.ID_ITEM
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = R.ID_EMPR
                LEFT JOIN FOCCO3I.TMAQUINAS M ON M.ID = R.ID_RECURSO
                LEFT JOIN FOCCO3I.TMASC_ITEM MI ON MI.ID = R.ID_MASCARA
                LEFT JOIN FOCCO3I.TORDENS O ON O.ID = R.ID_ORDEM
                WHERE R.ATIVO = 'S'";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND R.ID_EMPR = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['id_funcionario'])) {
            $sql .= " AND R.ID_FUNCIONARIO = :id_funcionario";
            $params[':id_funcionario'] = $filtros['id_funcionario'];
        }

        if (!empty($filtros['id_recurso'])) {
            $sql .= " AND R.ID_RECURSO = :id_recurso";
            $params[':id_recurso'] = $filtros['id_recurso'];
        }

        if (!empty($filtros['dt_inicio'])) {
            $sql .= " AND R.DT_RETRABALHO >= TO_DATE(:dt_inicio, 'YYYY-MM-DD')";
            $params[':dt_inicio'] = $filtros['dt_inicio'];
        }

        if (!empty($filtros['dt_fim'])) {
            $sql .= " AND R.DT_RETRABALHO <= TO_DATE(:dt_fim, 'YYYY-MM-DD')";
            $params[':dt_fim'] = $filtros['dt_fim'];
        }

        $sql .= " ORDER BY R.DT_RETRABALHO DESC, F.NOME";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar retrabalho por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    R.ID_RETRABALHO,
                    R.ID_EMPR,
                    R.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    R.ID_RECURSO,
                    R.ID_ITEM,
                    I.COD_ITEM,
                    I.DESC_TECNICA AS DESC_ITEM,
                    R.ID_MASCARA,
                    R.ID_ORDEM,
                    TO_CHAR(R.DT_RETRABALHO, 'YYYY-MM-DD') AS DT_RETRABALHO,
                    R.QUANTIDADE,
                    R.MOTIVO,
                    R.TIPO_IMPACTO,
                    R.VALOR_IMPACTO,
                    R.ATIVO
                FROM FOCCO3I.TGAZIN_RETRABALHO R
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = R.ID_FUNCIONARIO
                INNER JOIN FOCCO3I.TITENS I ON I.ID = R.ID_ITEM
                WHERE R.ID_RETRABALHO = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar retrabalho
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = $this->buscarPorId($id);

        $sql = "UPDATE FOCCO3I.TGAZIN_RETRABALHO SET
                    ID_FUNCIONARIO = :id_funcionario,
                    ID_RECURSO = :id_recurso,
                    ID_ITEM = :id_item,
                    ID_MASCARA = :id_mascara,
                    ID_ORDEM = :id_ordem,
                    DT_RETRABALHO = TO_DATE(:dt_retrabalho, 'YYYY-MM-DD'),
                    QUANTIDADE = :quantidade,
                    MOTIVO = :motivo,
                    TIPO_IMPACTO = :tipo_impacto,
                    VALOR_IMPACTO = :valor_impacto,
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_RETRABALHO = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_recurso', $dados['id_recurso'], PDO::PARAM_INT);
        $stmt->bindParam(':id_item', $dados['id_item'], PDO::PARAM_INT);
        $stmt->bindParam(':id_mascara', $dados['id_mascara'], PDO::PARAM_INT);
        $stmt->bindParam(':id_ordem', $dados['id_ordem'], PDO::PARAM_INT);
        $stmt->bindParam(':dt_retrabalho', $dados['dt_retrabalho'], PDO::PARAM_STR);
        $stmt->bindParam(':quantidade', $dados['quantidade'], PDO::PARAM_STR);
        $stmt->bindParam(':motivo', $dados['motivo'], PDO::PARAM_STR);
        $stmt->bindParam(':tipo_impacto', $dados['tipo_impacto'], PDO::PARAM_STR);
        $stmt->bindParam(':valor_impacto', $dados['valor_impacto'], PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_RETRABALHO', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return $result;
    }

    /**
     * Excluir retrabalho (exclusão lógica)
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public function excluir($id, $usuId)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = $this->buscarPorId($id);

        $sql = "UPDATE FOCCO3I.TGAZIN_RETRABALHO SET
                    ATIVO = 'N',
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_RETRABALHO = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_usuario', $usuId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_RETRABALHO', $id, 'D', $dadosAnteriores, null, $usuId);

        return $result;
    }

    /**
     * Buscar retrabalhos de funcionários em um período
     * @param array $funcIds
     * @param string $dataIni (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array
     */
    public function buscarPorFuncionariosPeriodo($funcIds, $dataIni, $dataFim, $emprId = null)
    {
        if (empty($funcIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($funcIds), '?'));

        $sql = "SELECT 
                    R.ID_RETRABALHO,
                    R.ID_FUNCIONARIO,
                    R.ID_ITEM,
                    I.COD_ITEM,
                    R.ID_MASCARA,
                    TO_CHAR(R.DT_RETRABALHO, 'YYYY-MM-DD') AS DT_RETRABALHO,
                    R.QUANTIDADE,
                    R.TIPO_IMPACTO,
                    R.VALOR_IMPACTO,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP
                FROM FOCCO3I.TGAZIN_RETRABALHO R
                INNER JOIN FOCCO3I.TITENS I ON I.ID = R.ID_ITEM
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = R.ID_ITEM
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= R.DT_RETRABALHO
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= R.DT_RETRABALHO)
                WHERE R.ID_FUNCIONARIO IN ($placeholders)
                AND R.DT_RETRABALHO >= TO_DATE(?, 'YYYY-MM-DD')
                AND R.DT_RETRABALHO <= TO_DATE(?, 'YYYY-MM-DD')
                AND R.ATIVO = 'S'";

        if ($emprId) {
            $sql .= " AND R.ID_EMPR = ?";
        }

        $sql .= " ORDER BY R.ID_FUNCIONARIO, R.DT_RETRABALHO";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $i = 1;
        foreach ($funcIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        $stmt->bindValue($i++, $dataIni, PDO::PARAM_STR);
        $stmt->bindValue($i++, $dataFim, PDO::PARAM_STR);

        if ($emprId) {
            $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular impacto do retrabalho na comissão
     * @param float $valorComissaoOriginal
     * @param float $pontosRetrabalho
     * @param string $tipoImpacto
     * @param float $valorImpacto
     * @return array [valor_desconto, valor_final]
     */
    public function calcularImpacto($valorComissaoOriginal, $pontosRetrabalho, $tipoImpacto, $valorImpacto)
    {
        $desconto = 0;

        switch ($tipoImpacto) {
            case self::TIPO_PERCENTUAL:
                // Percentual de desconto sobre a comissão
                $desconto = $valorComissaoOriginal * ($valorImpacto / 100);
                break;

            case self::TIPO_VALOR_FIXO:
                // Valor fixo por ponto de retrabalho
                $desconto = $pontosRetrabalho * $valorImpacto;
                break;

            case self::TIPO_ZERAR:
                // Zera a comissão
                $desconto = $valorComissaoOriginal;
                break;
        }

        $valorFinal = max(0, $valorComissaoOriginal - $desconto);

        return [
            'valor_desconto' => round($desconto, 2),
            'valor_final' => round($valorFinal, 2)
        ];
    }

    /**
     * Registrar log de auditoria
     */
    private function registrarLog($tabela, $idRegistro, $operacao, $dadosAnteriores, $dadosNovos, $usuId)
    {
        try {
            $sql = "INSERT INTO FOCCO3I.TGAZIN_LOG_AUDITORIA (
                        ID_EMPR,
                        TABELA,
                        ID_REGISTRO,
                        OPERACAO,
                        DADOS_ANTERIORES,
                        DADOS_NOVOS,
                        DT_OPERACAO,
                        ID_USUARIO,
                        IP_USUARIO
                    ) VALUES (
                        :id_empr,
                        :tabela,
                        :id_registro,
                        :operacao,
                        :dados_anteriores,
                        :dados_novos,
                        SYSDATE,
                        :id_usuario,
                        :ip_usuario
                    )";

            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);

            $emprId = $_SESSION['empresa']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $dadosAntJson = $dadosAnteriores ? json_encode($dadosAnteriores) : null;
            $dadosNovJson = $dadosNovos ? json_encode($dadosNovos) : null;

            $stmt->bindParam(':id_empr', $emprId, PDO::PARAM_INT);
            $stmt->bindParam(':tabela', $tabela, PDO::PARAM_STR);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindParam(':operacao', $operacao, PDO::PARAM_STR);
            $stmt->bindParam(':dados_anteriores', $dadosAntJson, PDO::PARAM_STR);
            $stmt->bindParam(':dados_novos', $dadosNovJson, PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $usuId, PDO::PARAM_INT);
            $stmt->bindParam(':ip_usuario', $ip, PDO::PARAM_STR);

            $stmt->execute();
        } catch (\Exception $e) {
            // Log de erro silenciado - não interrompe a operação principal
        }
    }
}


