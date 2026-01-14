<?php

namespace core;

use \src\Config;
use PDO;
use Exception;
use src\utils\GetSqlFocco;

class Database {
    private static $_pdo;

     /**
     * Chama o banco cadastro em Config.php
     * se parametro $db for passado, e for uma numero idFilial, chama o banco da filial
     * @param string $db
     */
    public static function getInstance($db = 'sabium') {
       
        switch ($db) {
            case 'ccg':
                $cx = Config::CCG_DRIVER.":host=".Config::CCG_HOST.";port=".Config::CCG_PORT.";dbname=".Config::CCG_DATABASE;
                self::$_pdo = new \PDO($cx, Config::CCG_USER, Config::CCG_PASS);
                break;
            case 'sabium':
                $cx = Config::SB_DRIVER.":host=".Config::SB_HOST.";port=".Config::SB_PORT.";dbname=".Config::SB_DATABASE;
                self::$_pdo = new \PDO($cx, Config::SB_USER, Config::SB_PASS);
                break;
            case 'focco':
                $tns = "(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".Config::FOCCO_HOST.")(PORT = ".Config::FOCCO_PORT.")))(CONNECT_DATA = (SERVICE_NAME = ".Config::FOCCO_DATABASE.")))";
                $username = Config::FOCCO_USER;
                $password = Config::FOCCO_PASS;
                $cx = "oci:dbname=".$tns;
                self::$_pdo = new \PDO($cx, $username, $password);
                break;
        }
		
			
		self::$_pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        self::$_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return self::$_pdo;
    }


    /**
     * Retorna ou executa um SQL
     * se penultimo for false retorna SQL com parametros subtituidos
     * ultimo parametro ira logar SQL e retorno do mesmo nos LOGs caso seja true
     * sempre verificar tipos dos campos nos Parametros do SQL EX:int,string ...
     * retorno array ['retorno']
     * erro em ['error']
     * 
     * $result['retorno'][0]['currval']  <- ultimo ID inserido (oracle)
     * @param string  $banco
     * @param array   $params
     * @param string  $sqlnome
     * @param boolean $exec
     * @param boolean $log
     *
     * @return array ['retorno']
     */
    public static function switchParams(
        $banco,
        $params,
        $sqlnome,
        $exec = false,
        $log = true,
        $idHints = null, // usar EXCLUSIVAMENTE p/ Oracle
        $sqlDireto = null // Parâmetro adicional para SQL direto
    ) {
        // Se SQL direto foi fornecido, usa ele; senão busca pelo idsql
        if (!empty($sqlDireto)) {
            $sql = $sqlDireto;
        } elseif (!empty($sqlnome)) {
            $sql = GetSqlFocco::buscaIdSql($sqlnome);
        } else {
            throw new Exception('Deve fornecer ou sqlnome ou sqlDireto');
        }
        
        $res = ['retorno' => false, 'error' => false];

        try {
            $pdo = $banco instanceof PDO ? $banco : self::getInstance($banco);
            // legado: replace textual
            if (!empty($params)) {
                foreach ($params as $nome => $valor) {
                    @$rpl = str_replace('\"', "'", $valor);
                    $valor = is_string($valor) ? trim($rpl) : $valor;
                    @$sql = preg_replace('/:' . (string)$nome . '\b/i', $valor, $sql);
                }
            }
            $sql = str_replace('idsql=', 'idsql=E', $sql);

            if ($exec) {
                $pdo->beginTransaction();

                $stmt = $pdo->query($sql);
                $hasResultset = $stmt->columnCount() > 0;

                if ($hasResultset) {
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    // Converte CLOBs (resources) para strings
                    foreach ($rows as &$row) {
                        foreach ($row as $key => &$value) {
                            if (is_resource($value)) {
                                $value = stream_get_contents($value);
                            }
                        }
                    }
                    unset($row, $value);
                } else {
                    $rows = [];
                }

                $ret = $rows; // padrão p/ todos os drivers
                if ($idHints) {
                    if (empty($idHints['table']) || strpos($idHints['table'], '.') === false) {
                        throw new \InvalidArgumentException("Oracle: passe ['table' => 'FOCCO3I.GAZIN_PII_SIMULACAO'] em \$idHints.");
                    }
                    [$owner, $table] = array_map(
                        fn($s) => strtoupper(str_replace('"', '', trim($s))),
                        explode('.', $idHints['table'], 2)
                    );

                    // 1) descobre a sequence do IDENTITY
                    $seq = self::getIdentitySeqName($pdo, $owner, $table);

                    // 2) se existir (coluna identity), pega o CURRVAL na MESMA sessão
                    if ($seq) {
                        $ret[]['currval'] = $pdo->query("SELECT ".$seq.".CURRVAL as currval FROM DUAL ")->fetchColumn();
                    } // se não for identity, mantém $rows
                }

                $res['retorno'] = $ret;
                $pdo->commit();
            } else {
                $res['retorno'] = $sql; // dry-run
            }

            if ($log) {
                $logjv = [
                    'data' => date('Y-m-d H:i:s'),
                    'sql'  => $sql,
                    'params' => $params,
                    'res'  => $res['retorno']
                ];
                file_put_contents('./exec' . date('Y-m-d') . '-sql.txt', print_r($logjv, true), FILE_APPEND);
            }
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $logjv = [
                'data' => date('Y-m-d H:i:s'),
                'msg'  => $e->getMessage(),
                'sql'  => $sql
            ];
            file_put_contents('./error' . date('Y-m-d') . '-sql.txt', print_r($logjv, true), FILE_APPEND);
            $res['error'] = $e->getMessage();
        }

        unset($pdo);
        return $res;
    }


	
	public static function replaceParams( $sql, $params ){
		foreach($params as $nome => $valor){
			@$rpl = str_replace('\"', "'", $valor);
			$valor = is_string($valor)? trim($rpl) : $valor;
			@$sql = preg_replace( '/:'.(string)$nome.'\b/i', $valor, $sql);
		};
		return $sql;
	}



     private static function getIdentitySeqName(PDO $pdo, string $owner, string $table): ?string
    {
        $owner = strtoupper($owner);
        $table = strtoupper($table);

        $sqlSeq = "
            SELECT sequence_name
            FROM all_tab_identity_cols
            WHERE owner = '".$owner."'
            AND table_name = '".$table."'
       ";
        $st = $pdo->query($sqlSeq);
        $seq = $st->fetch() ?? '';

        return $seq['sequence_name'] ?? '';
    }

    public function __construct() { }
    public function __clone() { }
    public function __wakeup() { }
}
