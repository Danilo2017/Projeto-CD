<?php

namespace src\models;

use core\Database;
use src\utils\GetSqlFocco;

/**
 * Model para gerenciar perfis de acesso (tabelas novas)
 * TGAZIN_PERFIL_ACESSO, TGAZIN_PERFIL_ROTA, TGAZIN_USUARIO_PERFIL
 */
class PerfilAcesso
{
    // ==================== PERFIS ====================

    public static function listarPerfis()
    {
        $result = Database::switchParams('focco', [], 'acesso.perfil.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarPerfisAtivos()
    {
        $result = Database::switchParams('focco', [], 'acesso.perfil.listarAtivos', true);
        $perfis = $result['retorno'] ?? [];

        $temPD           = false;
        $temQualidade    = false;
        $temApontamento  = false;
        $temPCP          = false;
        foreach ($perfis as $p) {
            $nome = strtoupper($p['NOME'] ?? '');
            if ($nome === 'P&D')          $temPD          = true;
            if ($nome === 'QUALIDADE')    $temQualidade   = true;
            if ($nome === 'APONTAMENTO')  $temApontamento = true;
            if ($nome === 'PCP')          $temPCP         = true;
        }

        $temManutencao = false;
        foreach ($perfis as $p) {
            if (strtoupper($p['NOME'] ?? '') === 'MANUTENÇÃO') $temManutencao = true;
        }

        if (!$temPD) {
            $pd = self::garantirPerfilPD();
            if ($pd !== null) $perfis[] = $pd;
        }

        if (!$temManutencao) {
            $m = self::garantirPerfilManutencao();
            if ($m !== null) $perfis[] = $m;
        }

        if (!$temQualidade) {
            $q = self::garantirPerfilQualidade();
            if ($q !== null) $perfis[] = $q;
        }

        if (!$temApontamento) {
            $ap = self::garantirPerfilApontamento();
            if ($ap !== null) $perfis[] = $ap;
        }

        if (!$temPCP) {
            $pcp = self::garantirPerfilPCP();
            if ($pcp !== null) $perfis[] = $pcp;
        }

        return $perfis;
    }

    private static function garantirPerfilPD(): ?array
    {
        try {
            $chk      = Database::switchParams('focco', [], null, true, false, null,
                        "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'P&D'");
            $existing = $chk['retorno'][0] ?? null;

            if ($existing) {
                $id = (int)$existing['ID_PERFIL'];

                // Garante que a rota existe (pode estar faltando de criação anterior com bug)
                $rotaChk = Database::switchParams('focco', [], null, true, false, null,
                    "SELECT COUNT(1) AS CNT FROM TGAZIN_PERFIL_ROTA WHERE PERFIL_ID = $id AND PREFIXO_ROTA = 'pd'");
                $cnt = (int)(($rotaChk['retorno'][0] ?? [])['CNT'] ?? 0);
                if ($cnt === 0) {
                    Database::switchParams('focco', [], null, true, false, null,
                        "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                         VALUES ($id, 'pd', SYSDATE)");
                    Database::getInstance('focco')->exec('COMMIT');
                }

                return ['ID_PERFIL' => $id, 'NOME' => 'P&D', 'DESCRICAO' => 'Acesso ao módulo P&D'];
            }

            $resA = Database::switchParams('focco', [], null, true, false, null,
                    "INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
                     VALUES ('P&D', 'Acesso ao módulo P&D', 'S', SYSDATE)");
            if (!empty($resA['error'])) return null;

            $idRes  = Database::switchParams('focco', [], null, true, false, null,
                      "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'P&D'");
            $novoId = (int)($idRes['retorno'][0]['ID_PERFIL'] ?? 0);
            if ($novoId === 0) return null;

            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                 VALUES ($novoId, 'pd', SYSDATE)");

            Database::getInstance('focco')->exec('COMMIT');

            return ['ID_PERFIL' => $novoId, 'NOME' => 'P&D', 'DESCRICAO' => 'Acesso ao módulo P&D'];
        } catch (\Throwable $e) {
            error_log('[PerfilAcesso] garantirPerfilPD erro: ' . $e->getMessage());
            return null;
        }
    }

    private static function garantirPerfilManutencao(): ?array
    {
        try {
            $chk      = Database::switchParams('focco', [], null, true, false, null,
                        "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'MANUTENÇÃO'");
            $existing = $chk['retorno'][0] ?? null;

            if ($existing) {
                $id = (int)$existing['ID_PERFIL'];
                $rotaChk = Database::switchParams('focco', [], null, true, false, null,
                    "SELECT COUNT(1) AS CNT FROM TGAZIN_PERFIL_ROTA WHERE PERFIL_ID = $id AND PREFIXO_ROTA = 'manutencao'");
                $cnt = (int)(($rotaChk['retorno'][0] ?? [])['CNT'] ?? 0);
                if ($cnt === 0) {
                    Database::switchParams('focco', [], null, true, false, null,
                        "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                         VALUES ($id, 'manutencao', SYSDATE)");
                    Database::getInstance('focco')->exec('COMMIT');
                }
                return ['ID_PERFIL' => $id, 'NOME' => 'Manutenção', 'DESCRICAO' => 'Acesso ao módulo Manutenção'];
            }

            $resA = Database::switchParams('focco', [], null, true, false, null,
                    "INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
                     VALUES ('Manutenção', 'Acesso ao módulo Manutenção', 'S', SYSDATE)");
            if (!empty($resA['error'])) return null;

            $idRes  = Database::switchParams('focco', [], null, true, false, null,
                      "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'MANUTENÇÃO'");
            $novoId = (int)($idRes['retorno'][0]['ID_PERFIL'] ?? 0);
            if ($novoId === 0) return null;

            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                 VALUES ($novoId, 'manutencao', SYSDATE)");
            Database::getInstance('focco')->exec('COMMIT');

            return ['ID_PERFIL' => $novoId, 'NOME' => 'Manutenção', 'DESCRICAO' => 'Acesso ao módulo Manutenção'];
        } catch (\Throwable $e) {
            error_log('[PerfilAcesso] garantirPerfilManutencao erro: ' . $e->getMessage());
            return null;
        }
    }

    private static function garantirPerfilQualidade(): ?array
    {
        try {
            $chk      = Database::switchParams('focco', [], null, true, false, null,
                        "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'QUALIDADE'");
            $existing = $chk['retorno'][0] ?? null;

            if ($existing) {
                return ['ID_PERFIL' => (int)$existing['ID_PERFIL'], 'NOME' => 'Qualidade', 'DESCRICAO' => 'Acesso ao módulo Qualidade'];
            }

            /* ID_PERFIL e ID_ROTA são IDENTITY — Oracle gera automaticamente */
            $resA = Database::switchParams('focco', [], null, true, false, null,
                    "INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
                     VALUES ('Qualidade', 'Acesso ao modulo Qualidade', 'S', SYSDATE)");
            if (!empty($resA['error'])) {
                return null;
            }

            $idRes  = Database::switchParams('focco', [], null, true, false, null,
                      "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'QUALIDADE'");
            $novoId = (int)($idRes['retorno'][0]['ID_PERFIL'] ?? 0);
            if ($novoId === 0) return null;

            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                 VALUES ($novoId, 'qualidade', SYSDATE)");

            Database::getInstance('focco')->exec('COMMIT');

            return ['ID_PERFIL' => $novoId, 'NOME' => 'Qualidade', 'DESCRICAO' => 'Acesso ao modulo Qualidade'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function garantirPerfilPCP(): ?array
    {
        try {
            $chk      = Database::switchParams('focco', [], null, true, false, null,
                        "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'PCP'");
            $existing = $chk['retorno'][0] ?? null;
            if ($existing) {
                return ['ID_PERFIL' => (int)$existing['ID_PERFIL'], 'NOME' => 'PCP', 'DESCRICAO' => 'Acesso aos relatorios PCP'];
            }

            $resA = Database::switchParams('focco', [], null, true, false, null,
                    "INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
                     VALUES ('PCP', 'Acesso aos relatorios PCP', 'S', SYSDATE)");
            if (!empty($resA['error'])) return null;

            $idRes  = Database::switchParams('focco', [], null, true, false, null,
                      "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'PCP'");
            $novoId = (int)($idRes['retorno'][0]['ID_PERFIL'] ?? 0);
            if ($novoId === 0) return null;

            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                 VALUES ($novoId, 'pcp', SYSDATE)");

            Database::getInstance('focco')->exec('COMMIT');

            return ['ID_PERFIL' => $novoId, 'NOME' => 'PCP', 'DESCRICAO' => 'Acesso aos relatorios PCP'];
        } catch (\Throwable $e) {
            error_log('[PerfilAcesso] garantirPerfilPCP erro: ' . $e->getMessage());
            return null;
        }
    }

    private static function garantirPerfilApontamento(): ?array
    {
        try {
            // Já existe como Apontamento?
            $chk      = Database::switchParams('focco', [], null, true, false, null,
                        "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'APONTAMENTO'");
            $existing = $chk['retorno'][0] ?? null;
            if ($existing) {
                return ['ID_PERFIL' => (int)$existing['ID_PERFIL'], 'NOME' => 'Apontamento', 'DESCRICAO' => 'Acesso ao modulo Apontamento PCP'];
            }

            // Nenhum existe — insere novo
            $resA = Database::switchParams('focco', [], null, true, false, null,
                    "INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
                     VALUES ('Apontamento', 'Acesso ao modulo Apontamento PCP', 'S', SYSDATE)");
            if (!empty($resA['error'])) {
                error_log('[PerfilAcesso] garantirPerfilApontamento INSERT erro: ' . json_encode($resA['error']));
                return null;
            }

            $idRes  = Database::switchParams('focco', [], null, true, false, null,
                      "SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'APONTAMENTO'");
            $novoId = (int)($idRes['retorno'][0]['ID_PERFIL'] ?? 0);
            if ($novoId === 0) return null;

            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
                 VALUES ($novoId, 'apontamento', SYSDATE)");

            Database::getInstance('focco')->exec('COMMIT');

            return ['ID_PERFIL' => $novoId, 'NOME' => 'Apontamento', 'DESCRICAO' => 'Acesso ao modulo Apontamento PCP'];
        } catch (\Throwable $e) {
            error_log('[PerfilAcesso] garantirPerfilApontamento erro: ' . $e->getMessage());
            return null;
        }
    }

    // ==================== USUÁRIOS X PERFIS ====================

    public static function listarUsuarios($filtros = [])
    {
        $params = [
            'filtro_login'  => !empty($filtros['login'])
                ? "AND UPPER(UP.LOGIN_USUARIO) LIKE UPPER('%" . str_replace("'", "''", $filtros['login']) . "%')"
                : '--',
            'filtro_ativo'  => isset($filtros['ativo'])
                ? "AND UP.ATIVO = '" . ($filtros['ativo'] === 'S' ? 'S' : 'N') . "'"
                : '--',
            'filtro_perfil' => !empty($filtros['perfil_id'])
                ? "AND UP.PERFIL_ID = " . intval($filtros['perfil_id'])
                : '--',
        ];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarUsuariosAgrupados($filtros = [])
    {
        $dados = self::listarUsuarios($filtros);

        $usuarios = [];
        foreach ($dados as $row) {
            $login = $row['LOGIN_USUARIO'];

            if (!isset($usuarios[$login])) {
                $usuarios[$login] = [
                    'LOGIN_USUARIO' => $login,
                    'ATIVO'         => 'N',
                    'PERFIS'        => [],
                    'PERFIS_IDS'    => [],
                    'DT_CADASTRO'   => $row['DT_CADASTRO'],
                ];
            }

            if ($row['ATIVO'] === 'S') {
                $usuarios[$login]['ATIVO'] = 'S';
                $usuarios[$login]['PERFIS'][]     = $row['PERFIL_NOME'];
                $usuarios[$login]['PERFIS_IDS'][] = $row['PERFIL_ID'];
            }
        }

        return array_values($usuarios);
    }

    public static function buscarPerfisUsuario($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarPerfis', true);
        return $result['retorno'] ?? [];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarPorId', true);
        return ($result['retorno'][0] ?? null) ?: null;
    }

    public static function adicionarPerfilUsuario($login, $perfilId)
    {
        $loginEsc  = str_replace("'", "''", $login);
        $perfilInt = intval($perfilId);

        // Verificar se já existe (ativo ou inativo)
        $checkParams = ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt];
        $checkResult = Database::switchParams('focco', $checkParams, 'acesso.usuario.verificarPerfil', true);
        $registros   = $checkResult['retorno'] ?? [];

        if (!empty($registros)) {
            // Algum está ativo — retorna sem criar duplicata
            foreach ($registros as $reg) {
                if ($reg['ATIVO'] === 'S') {
                    return $reg['ID_USUARIO_PERFIL'];
                }
            }

            $primeiroId = intval($registros[0]['ID_USUARIO_PERFIL']);

            // Remover registros inativos duplicados (manter apenas o mais recente)
            if (count($registros) > 1) {
                $idsParaDeletar = [];
                for ($i = 1; $i < count($registros); $i++) {
                    $idsParaDeletar[] = intval($registros[$i]['ID_USUARIO_PERFIL']);
                }
                $pdo = Database::getInstance('focco');
                $sqlDelete = str_replace('{lista_ids}', implode(',', $idsParaDeletar), GetSqlFocco::getSql('acesso.usuario.removerPerfisDuplicados'));
                $pdo->exec($sqlDelete);
            }

            // Reativar o registro mais recente
            Database::switchParams('focco', ['id' => $primeiroId], 'acesso.usuario.reativarPerfil', true);
            return $primeiroId;
        }

        // Inserir novo vínculo
        Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt], 'acesso.usuario.inserirPerfil', true);

        // Buscar ID inserido
        $idResult = Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt], 'acesso.usuario.buscarIdPerfil', true);
        return intval($idResult['retorno'][0]['ID'] ?? 0);
    }

    public static function definirPerfisUsuario($login, array $perfisIds)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        Database::switchParams('focco', $params, 'acesso.usuario.inativarPerfis', true);

        foreach ($perfisIds as $perfilId) {
            if ($perfilId) {
                self::adicionarPerfilUsuario($login, $perfilId);
            }
        }

        return true;
    }

    public static function removerPerfilUsuario($id)
    {
        $result = Database::switchParams('focco', ['id' => intval($id)], 'acesso.usuario.removerPerfil', true);
        return empty($result['error']);
    }

    public static function isAdmin($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.isAdmin', true);
        return !empty($result['retorno']);
    }

    public static function temAcessoModulo($login, $modulo)
    {
        $params = [
            'login'  => "'" . str_replace("'", "''", $login) . "'",
            'modulo' => "'" . str_replace("'", "''", $modulo) . "'",
        ];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.temAcessoModulo', true);
        return !empty($result['retorno']);
    }

    // ==================== USUÁRIOS X FILIAIS ====================

    public static function listarEmpresas()
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function buscarFiliaisUsuario($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarFiliais', true);
        return $result['retorno'] ?? [];
    }

    public static function adicionarFilialUsuario($login, $emprId)
    {
        $loginEsc = str_replace("'", "''", $login);
        $emprInt  = intval($emprId);

        // Verificar se já existe (ativo ou inativo)
        $checkParams = ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt];
        $checkResult = Database::switchParams('focco', $checkParams, 'acesso.usuario.verificarFilial', true);
        $registros   = $checkResult['retorno'] ?? [];

        if (!empty($registros)) {
            foreach ($registros as $reg) {
                if ($reg['ATIVO'] === 'S') {
                    return $reg['ID_USUARIO_FILIAL'];
                }
            }

            $primeiroId = intval($registros[0]['ID_USUARIO_FILIAL']);

            // Remover registros inativos duplicados
            if (count($registros) > 1) {
                $idsParaDeletar = [];
                for ($i = 1; $i < count($registros); $i++) {
                    $idsParaDeletar[] = intval($registros[$i]['ID_USUARIO_FILIAL']);
                }
                $pdo = Database::getInstance('focco');
                $sqlDelete = str_replace('{lista_ids}', implode(',', $idsParaDeletar), GetSqlFocco::getSql('acesso.usuario.removerFiliaisDuplicadas'));
                $pdo->exec($sqlDelete);
            }

            // Reativar o registro mais recente
            Database::switchParams('focco', ['id' => $primeiroId], 'acesso.usuario.reativarFilial', true);
            return $primeiroId;
        }

        // Inserir nova filial
        Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt], 'acesso.usuario.inserirFilial', true);

        // Buscar ID inserido
        $idResult = Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt], 'acesso.usuario.buscarIdFilial', true);
        return intval($idResult['retorno'][0]['ID'] ?? 0);
    }

    public static function definirFiliaisUsuario($login, array $filiaisIds)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        Database::switchParams('focco', $params, 'acesso.usuario.inativarFiliais', true);

        foreach ($filiaisIds as $emprId) {
            if ($emprId) {
                self::adicionarFilialUsuario($login, $emprId);
            }
        }

        return true;
    }

    public static function temAcessoFilial($login, $emprId)
    {
        $filiais = self::buscarFiliaisUsuario($login);
        if (empty($filiais)) {
            return true;
        }

        foreach ($filiais as $filial) {
            if ($filial['EMPR_ID'] == $emprId) {
                return true;
            }
        }

        return false;
    }

    public static function getFiliaisPermitidas($login)
    {
        $filiais = self::buscarFiliaisUsuario($login);
        return array_column($filiais, 'EMPR_ID');
    }
}
