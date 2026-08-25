<?php
/** @var callable $render */
/** @var string   $base */
$acesso = $is_admin || in_array('apontamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Apontamento de Produção',
    'showNavbar' => false,
    'pageActive' => 'apontamento-producao',
]) ?>
<style>
.apont-nav{background:#0d47a1;color:#fff;padding:10px 14px;display:flex;align-items:center;gap:10px;position:sticky;top:0;z-index:100}
.apont-nav a{color:#fff;opacity:.75;font-size:20px;text-decoration:none;line-height:1}
.apont-nav a:hover{opacity:1}
.apont-nav-title{font-size:15px;font-weight:600}
</style>
<div class="apont-nav">
    <a href="<?= $base ?>apontamento-producao" title="Início">&#9776;</a>
    <span class="apont-nav-title">Apontamento de Produção</span>
</div>

<style>
.apont-wrap{max-width:480px;margin:40px auto;padding:0 16px}
.apont-card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);overflow:hidden}
.apont-header{background:#1565c0;color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px}
.apont-header h5{margin:0;font-size:16px;font-weight:600}
.apont-body{padding:24px 20px}
.apont-field{margin-bottom:16px}
.apont-field label{display:block;font-size:12px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.apont-field .input-group input{font-size:15px;border-radius:6px 0 0 6px!important}
.apont-field .btn-scan{border-radius:0 6px 6px 0;background:#e3f2fd;border:1px solid #90caf9;color:#1565c0;padding:0 14px}
.apont-resolved{font-size:12px;color:#2e7d32;margin-top:4px;display:none}
.apont-resolved.visible{display:block}
.apont-token{font-size:13px;font-family:monospace}
.btn-iniciar{width:100%;padding:12px;font-size:15px;font-weight:600;background:#1565c0;border:none;border-radius:8px;color:#fff;cursor:pointer;margin-top:8px}
.btn-iniciar:hover{background:#0d47a1}
.apont-tip{font-size:11px;color:#999;text-align:center;margin-top:12px}
@media(max-width:500px){.apont-wrap{margin:0;padding:0}.apont-card{border-radius:0;min-height:100vh}}
</style>

<div class="apont-wrap">
<div class="apont-card">
    <div class="apont-header">
        <i class="bi bi-qr-code-scan" style="font-size:22px"></i>
        <div>
            <h5>Apontamento de Produção</h5>
            <small style="opacity:.8">Parametrização da sessão</small>
        </div>
    </div>
    <div class="apont-body">
        <div id="alertMsg"></div>

        <div class="apont-field">
            <label>Empresa</label>
            <div style="background:#f0f4ff;border:1px solid #c5cae9;border-radius:6px;padding:8px 12px;font-size:14px;color:#1a237e;font-weight:600">
                <i class="bi bi-building" style="margin-right:6px"></i>
                <?= htmlspecialchars(($_SESSION['empresa']['id'] ?? '') . ' — ' . ($_SESSION['empresa']['nome'] ?? '')) ?>
            </div>
        </div>

        <div class="apont-field">
            <label>Funcionário <small>(código ou ID)</small></label>
            <div class="input-group input-group-sm">
                <input type="text" id="inFunc" class="form-control" placeholder="Scan ou digite o código">
                <button class="btn-scan btn" type="button" id="btnScanFunc">
                    <i class="bi bi-upc-scan"></i>
                </button>
            </div>
            <div id="resolvedFunc" class="apont-resolved"><i class="bi bi-check-circle"></i> <span></span></div>
            <input type="hidden" id="funcId">
        </div>

        <div class="apont-field">
            <label>Operação <small>(código ou ID)</small></label>
            <div class="input-group input-group-sm">
                <input type="text" id="inOpe" class="form-control" placeholder="Scan ou digite o código">
                <button class="btn-scan btn" type="button" id="btnScanOpe">
                    <i class="bi bi-upc-scan"></i>
                </button>
            </div>
            <div id="resolvedOpe" class="apont-resolved"><i class="bi bi-check-circle"></i> <span></span></div>
            <input type="hidden" id="opeId">
        </div>

        <div class="apont-field">
            <label>Recurso / Máquina <small style="color:#888">(opcional — pode ser escaneado depois)</small></label>
            <div class="input-group input-group-sm">
                <input type="text" id="inMaq" class="form-control" placeholder="Scan ou digite o código (opcional)">
                <button class="btn-scan btn" type="button" id="btnScanMaq">
                    <i class="bi bi-upc-scan"></i>
                </button>
            </div>
            <div id="resolvedMaq" class="apont-resolved"><i class="bi bi-check-circle"></i> <span></span></div>
            <input type="hidden" id="maqId">
        </div>

        <button class="btn-iniciar" id="btnIniciar">INICIAR</button>
        <p class="apont-tip">Após iniciar a sessão, você poderá fazer apontamentos via scanner ou teclado.</p>
    </div>
</div>
</div>

<script>
async function resolverCodigo(tipo, inputId, resolvedId, hiddenId) {
    const input = document.getElementById(inputId);
    const codigo = input.value.trim();
    if (!codigo) return false;

    const r = await fetch(`${BASE}apontamento-api-buscar-codigo?tipo=${tipo}&codigo=${encodeURIComponent(codigo)}`);
    const d = await r.json();

    const el = document.getElementById(resolvedId);
    const hd = document.getElementById(hiddenId);

    if (d.error) {
        el.className = 'apont-resolved visible';
        el.style.color = '#c62828';
        el.innerHTML = `<i class="bi bi-x-circle"></i> ${d.error}`;
        hd.value = '';
        return false;
    }

    const nome = d.data.NOME || d.data.DESCRICAO || '';
    const id   = d.data.ID;
    el.className = 'apont-resolved visible';
    el.style.color = '#2e7d32';
    el.innerHTML = `<i class="bi bi-check-circle"></i> ${id} — ${nome}`;
    hd.value = id;
    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    const tipos  = ['funcionario','operacao','maquina'];
    const inputs = ['inFunc','inOpe','inMaq'];
    const resIds = ['resolvedFunc','resolvedOpe','resolvedMaq'];
    const hidIds = ['funcId','opeId','maqId'];
    const btns = ['btnScanFunc','btnScanOpe','btnScanMaq'];
    // Após resolver com sucesso, foca o próximo campo (ou o botão Iniciar)
    const proximos = ['inOpe', 'inMaq', 'btnIniciar'];

    inputs.forEach(function (inputId, i) {
        const el = document.getElementById(inputId);
        if (el) el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverCodigo(tipos[i], inputId, resIds[i], hidIds[i]).then(function (ok) {
                    if (ok) {
                        const prox = document.getElementById(proximos[i]);
                        if (prox) { prox.focus(); if (prox.select) prox.select(); }
                    }
                });
            }
        });
        const btn = document.getElementById(btns[i]);
        if (btn) btn.addEventListener('click', function () { resolverCodigo(tipos[i], inputId, resIds[i], hidIds[i]); });
    });

    document.getElementById('btnIniciar').addEventListener('click', async function () {
        const funcId  = document.getElementById('funcId').value;
        const opeId   = document.getElementById('opeId').value;
        const maqId   = document.getElementById('maqId').value;
        const alertEl = document.getElementById('alertMsg');

        if (!funcId || !opeId) {
            alertEl.innerHTML = '<div class="alert alert-warning py-2">Resolva funcionário e operação antes de iniciar.</div>';
            return;
        }

        const r = await fetch(`${BASE}apontamento-api-iniciar-sessao`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ func_id: +funcId, operacao_id: +opeId, maquina_id: +maqId }),
        });
        const d = await r.json();

        if (d.error) {
            alertEl.innerHTML = `<div class="alert alert-danger py-2">${d.error}</div>`;
            return;
        }

        window.location.href = BASE + 'apontamento-producao-operacao';
    });
});
</script>

<?= $render('footer') ?>
