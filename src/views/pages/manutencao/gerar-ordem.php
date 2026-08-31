<?php
/** @var bool $is_admin @var array $rotas_permitidas @var string $base @var callable $render */
$acesso = $is_admin || in_array('manutencao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Gerar Ordem de Manutenção',
    'showNavbar' => true,
    'pageActive' => 'manutencao-gerar-ordem',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.go-wrap { padding: 14px; max-width: 680px; margin: 0 auto; }
.go-card  { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.1); padding: 20px; }
.go-title { font-size: 15px; font-weight: 700; color: #1565c0; margin-bottom: 16px; }
.go-row   { display: flex; gap: 10px; flex-wrap: wrap; }
.go-row .form-group { flex: 1 1 200px; }
.form-group { margin-bottom: 12px; }
.form-group label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 3px; }
.form-group select, .form-group input, .form-group textarea {
    width: 100%; border: 1px solid #ccc; border-radius: 6px;
    padding: 7px 10px; font-size: 13px; box-sizing: border-box;
}
.form-group textarea { resize: vertical; min-height: 70px; }
.go-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }

/* Autocomplete */
.ac-wrap { position: relative; }
.ac-drop { display:none;position:absolute;top:100%;left:0;right:0;background:#fff;
           border:1px solid #ccc;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);
           z-index:300;max-height:240px;overflow-y:auto; }
.ac-drop-item { padding:8px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f0f0f0; }
.ac-drop-item:hover { background:#e3f2fd; }
.ac-drop-item:last-child { border-bottom:none; }
.ac-sel { font-size:11px;color:#1565c0;font-weight:600;margin-top:3px;display:none; }

/* Checklist modal */
.chk-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; align-items: center; justify-content: center; }
.chk-overlay.show { display: flex; }
.chk-modal  { background: #fff; border-radius: 10px; padding: 24px; width: 90%; max-width: 480px; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,.25); }
.chk-title  { font-size: 14px; font-weight: 700; color: #1565c0; margin-bottom: 14px; }
.chk-item   { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.chk-item:last-child { border-bottom: none; }
.chk-item input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; margin-top: 2px; flex-shrink: 0; }
.chk-item label { font-size: 13px; cursor: pointer; }
.chk-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
</style>

<div class="go-wrap">
    <div class="go-card">
        <div class="go-title">Nova Ordem de Manutenção</div>
        <form id="frmOrdem" onsubmit="return false">

            <div class="form-group">
                <label>Recurso (Máquina) *</label>
                <div class="ac-wrap">
                    <input type="text" id="fRecursoText" placeholder="Clique para listar ou digite para filtrar..."
                           autocomplete="off" onfocus="abrirMaquinas()" oninput="filtrarMaquinas()">
                    <div class="ac-drop" id="acMaqDrop"></div>
                </div>
                <div class="ac-sel" id="acMaqSel"></div>
            </div>

            <div class="go-row">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select id="fTipo">
                        <option value="C">Corretiva</option>
                        <option value="M">Melhoria/TPM</option>
                        <option value="P">Preventiva</option>
                        <option value="G">Programada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Situação *</label>
                    <select id="fSituacao">
                        <option value="L">Liberada</option>
                        <option value="F">Firme</option>
                    </select>
                </div>
            </div>

            <div class="go-row">
                <div class="form-group">
                    <label>Tipo do Problema</label>
                    <select id="fTpProblema">
                        <option value="">—</option>
                        <option value="1">Elétrico</option>
                        <option value="2">Mecânico</option>
                        <option value="3">Ferramenta</option>
                        <option value="4">Pneumático</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Máquina Parada</label>
                    <select id="fTpParada">
                        <option value="">—</option>
                        <option value="1">Sim</option>
                        <option value="2">Não</option>
                        <option value="3">Melhoria</option>
                    </select>
                </div>
            </div>

            <div class="go-row">
                <div class="form-group">
                    <label>Data Solicitação *</label>
                    <input type="text" id="fDtSol" placeholder="DD/MM/AAAA" maxlength="10">
                </div>
                <div class="form-group">
                    <label>Data Prevista</label>
                    <input type="text" id="fDtPrev" placeholder="DD/MM/AAAA" maxlength="10">
                </div>
            </div>

            <div class="form-group">
                <label>Solicitante</label>
                <div class="ac-wrap">
                    <input type="text" id="fSolicitanteText" placeholder="Clique para listar ou digite para filtrar..."
                           autocomplete="off" onfocus="abrirSolicitantes()" oninput="filtrarSolicitantes()">
                    <div class="ac-drop" id="acSolDrop"></div>
                </div>
                <div class="ac-sel" id="acSolSel"></div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="fUrgente" style="width:auto;margin-right:6px">
                    Urgente
                </label>
            </div>

            <div class="form-group">
                <label>Problema / Descrição</label>
                <textarea id="fProblema" maxlength="2000" placeholder="Descreva o problema..."></textarea>
            </div>

            <div class="go-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="limparForm()">Limpar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSalvar" onclick="tentarSalvar()">
                    <i class="bi bi-check-lg"></i> Salvar Ordem
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Checklist -->
<div class="chk-overlay" id="chkOverlay">
    <div class="chk-modal">
        <div class="chk-title">Checklist de Verificação</div>
        <div id="chkLista"></div>
        <div class="chk-footer">
            <button class="btn btn-secondary btn-sm" onclick="fecharChecklist()">Cancelar</button>
            <button class="btn btn-primary btn-sm" id="btnConfirmar" onclick="confirmarEsalvar()">
                <i class="bi bi-check-lg"></i> Confirmar e Salvar
            </button>
        </div>
    </div>
</div>

<div id="go-app-data"
     data-base="<?= htmlspecialchars($base) ?>"
     data-empr="<?= (int) ($_SESSION['empresa']['id'] ?? 0) ?>"
     style="display:none"></div>
<script src="<?= $base ?>src/js/manutencao-gerar-ordem.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
