(function () {
    'use strict';

    const histLeituras = [];
    let processando      = false;
    let maquinaVinculada = false;
    let maquinaFixa      = false; // true quando máquina foi definida no setup (capa)
    // BASE já declarado pelo header.php

    // ── Utilitários ──────────────────────────────────────────────────────────

    function hora() {
        const d = new Date();
        return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0');
    }

    function modalAberto() {
        const m = document.getElementById('modalMaquina');
        return m && m.style.display === 'block';
    }

    function focarInput() {
        if (modalAberto()) return;
        const el = document.getElementById('scanInput');
        if (el) { el.focus(); el.select(); }
    }

    function setStatus(msg, cor) {
        const el = document.getElementById('scanStatus');
        if (!el) return;
        el.innerHTML = msg;
        el.style.color = cor || '#1565c0';
    }

    function setFeedback(html) {
        const el = document.getElementById('feedbackArea');
        if (el) el.innerHTML = html;
    }

    // ── Abas ─────────────────────────────────────────────────────────────────

    window.trocarTab = function (tab) {
        document.querySelectorAll('.ap-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        document.querySelectorAll('.ap-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab' + cap(tab)));
        if (tab === 'ordens') recarregarOrdens();
    };

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    // ── Lista de ordens ───────────────────────────────────────────────────────

    window.recarregarOrdens = async function () {
        const el = document.getElementById('listaOrdens');
        if (!el) return;
        el.innerHTML = '<p style="text-align:center;color:#aaa;font-size:13px;padding:20px">Carregando...</p>';
        try {
            const r = await fetch(BASE + 'apontamento-api-ordens');
            const d = await r.json();
            if (d.error) { el.innerHTML = `<p style="color:#c62828;font-size:13px;padding:10px">${d.error}</p>`; return; }
            renderOrdens(d.data || []);
        } catch (e) {
            el.innerHTML = `<p style="color:#c62828;font-size:13px;padding:10px">Erro ao carregar ordens.</p>`;
        }
    };

    function renderOrdens(ordens) {
        const el = document.getElementById('listaOrdens');
        if (!ordens.length) {
            el.innerHTML = '<p style="text-align:center;color:#aaa;font-size:13px;padding:20px">Nenhuma ordem aberta para esta operação.</p>';
            return;
        }
        el.innerHTML = ordens.map(o => {
            const prev    = parseFloat(o.QTDE_PREVISTA || 0);
            const apon    = parseFloat(o.QTDE_APONTADA || 0);
            const pct     = prev > 0 ? Math.min((apon / prev) * 100, 100) : 0;
            const pend    = Math.max(prev - apon, 0);
            const scanVal = o.ORDEM_ID || o.NUM_ORDEM;
            return `
<div class="ordem-card" onclick="preencherScan('${scanVal}')">
    <div>
        <span class="ordem-num">${o.ORDEM_ID}</span>
        <span class="pend-badge">Pend: ${pend.toFixed(2)}</span>
    </div>
    <div class="ordem-desc">${o.DESCRICAO || ''}<small style="color:#aaa;margin-left:6px">#${o.NUM_ORDEM}</small></div>
    <div class="ordem-progress">
        <div class="progress-bar-wrap">
            <div class="pb-lido" style="width:${pct}%"></div>
        </div>
        <div class="progress-labels">
            <span>Lido: ${apon.toFixed(2)} / ${prev.toFixed(2)}</span>
            <span>${pct.toFixed(0)}%</span>
        </div>
    </div>
</div>`;
        }).join('');
    }

    function preencherScan(codigo) {
        const el = document.getElementById('scanInput');
        if (el) { el.value = codigo; el.focus(); }
    }

    // ── Lista de leituras (histórico da sessão) ───────────────────────────────

    function renderLeituras() {
        const el = document.getElementById('listaLeituras');
        if (!el) return;
        if (!histLeituras.length) {
            el.innerHTML = '<p style="text-align:center;color:#aaa;font-size:13px;padding:20px">Nenhum apontamento nesta sessão.</p>';
            return;
        }
        el.innerHTML = [...histLeituras].reverse().map(l => `
<div class="leitura-card">
    <div class="lc-status ${l.ok ? 'lc-ok' : 'lc-err'}"></div>
    <div class="lc-info">
        <strong>${l.codigo}</strong> — ${l.ok ? `ID ${l.value}` : l.error}
        ${l.tempo_ms ? `<small style="color:#aaa"> (${l.tempo_ms}ms)</small>` : ''}
    </div>
    <span class="lc-time">${l.hora}</span>
</div>`).join('');
    }

    // ── Popup de máquina ─────────────────────────────────────────────────────

    function abrirPopupMaquina(callback) {
        let modal = document.getElementById('modalMaquina');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'modalMaquina';
            modal.innerHTML = `
<div id="modalMaquinaOverlay" style="
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;
    display:flex;align-items:center;justify-content:center;padding:16px">
  <div style="
      background:#fff;border-radius:12px;padding:24px 20px;width:100%;max-width:360px;
      box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <div style="font-size:15px;font-weight:700;color:#1565c0;margin-bottom:4px">
      <i class="bi bi-cpu"></i> Vincular Máquina
    </div>
    <p style="font-size:13px;color:#555;margin:0 0 14px">
      Nenhuma máquina vinculada à sessão.<br>Scaneie ou digite o código da máquina:
    </p>
    <input id="inputMaquinaModal" type="text" inputmode="numeric"
      placeholder="Código da máquina"
      style="width:100%;box-sizing:border-box;padding:10px 12px;font-size:15px;
             border:2px solid #1565c0;border-radius:8px;outline:none;margin-bottom:12px">
    <div id="erroMaquinaModal" style="color:#c62828;font-size:12px;min-height:16px;margin-bottom:8px"></div>
    <div style="display:flex;gap:10px">
      <button id="btnConfirmarMaquina" style="
          flex:1;padding:10px;background:#1565c0;color:#fff;border:none;
          border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">
        Confirmar
      </button>
      <button id="btnCancelarMaquina" style="
          flex:1;padding:10px;background:#eee;color:#333;border:none;
          border-radius:8px;font-size:14px;cursor:pointer">
        Cancelar
      </button>
    </div>
  </div>
</div>`;
            document.body.appendChild(modal);
        }

        const overlay  = document.getElementById('modalMaquinaOverlay');
        const input    = document.getElementById('inputMaquinaModal');
        const erro     = document.getElementById('erroMaquinaModal');
        const btnConf  = document.getElementById('btnConfirmarMaquina');
        const btnCanc  = document.getElementById('btnCancelarMaquina');

        modal.style.display = 'block';
        erro.textContent = '';
        input.value = '';
        // Mantém foco no campo da máquina enquanto modal estiver aberto
        input.onblur = () => { if (modalAberto()) setTimeout(() => input.focus(), 80); };
        setTimeout(() => input.focus(), 100);

        async function confirmar() {
            const cod = input.value.trim();
            if (!cod) { erro.textContent = 'Digite o código da máquina.'; return; }
            btnConf.disabled = true;
            btnConf.textContent = 'Buscando…';
            try {
                const r = await fetch(`${BASE}apontamento-api-set-maquina`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ codigo: cod }),
                });
                const d = await r.json();
                if (d.error) {
                    erro.textContent = d.error;
                    input.value = '';
                    input.focus();
                } else {
                    maquinaVinculada = true;
                    modal.style.display = 'none';
                    // Atualiza badge de máquina se existir na página
                    const badge = document.getElementById('badgeMaquina');
                    if (badge) badge.textContent = d.maquina.DESCRICAO;
                    if (callback) callback();
                }
            } catch (e) {
                erro.textContent = 'Erro de rede.';
            } finally {
                btnConf.disabled = false;
                btnConf.textContent = 'Confirmar';
            }
        }

        function fecharModal() {
            modal.style.display = 'none';
            focarInput();
        }

        btnConf.onclick = confirmar;
        btnCanc.onclick = fecharModal;
        overlay.onclick = (e) => { if (e.target === overlay) fecharModal(); };
        input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); confirmar(); } };
    }

    // ── Fluxo de scan ────────────────────────────────────────────────────────

    async function processarScan(codigo) {
        if (processando || !codigo.trim()) return;

        // Verifica se há máquina vinculada antes de apontar
        if (!maquinaVinculada) {
            abrirPopupMaquina(() => processarScan(codigo));
            return;
        }

        processando = true;

        const input = document.getElementById('scanInput');
        if (input) input.disabled = true;
        setStatus('&#9203; Processando...', '#e65100');
        setFeedback(`<div class="fb-info">Buscando ordem <strong>${codigo}</strong>…</div>`);

        try {
            // 1. Resolve o código para OrdemRoteiro
            const rBusca = await fetch(`${BASE}apontamento-api-buscar-codigo?tipo=ordem&codigo=${encodeURIComponent(codigo)}`);
            const dBusca = await rBusca.json();

            if (dBusca.error) {
                setFeedback(`<div class="fb-err"><i class="bi bi-x-circle"></i> ${dBusca.error}</div>`);
                histLeituras.push({ codigo, ok: false, error: dBusca.error, hora: hora() });
                renderLeituras();
                return;
            }

            const ordem = dBusca.data;
            setFeedback(`<div class="fb-info">
                <strong>${ordem.NUM_ORDEM}</strong> — ${ordem.DESCRICAO || ''}<br>
                <small>Roteiro ID: ${ordem.ROT_ID} | Apontando…</small>
            </div>`);

            // 2. Faz o apontamento
            const rApont = await fetch(`${BASE}apontamento-api-apontar`, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ ordem_rot_id: ordem.ROT_ID, quantidade: 1, tipo: 'TP', etiq_id: ordem.ETIQ_ID || null, cod_barra: ordem.COD_BARRA_ORD || codigo }),
            });
            const dApont = await rApont.json();

            if (dApont.ok) {
                setFeedback(`<div class="fb-ok">
                    <i class="bi bi-check-circle-fill" style="font-size:18px"></i>
                    <div>
                        <strong>Apontado!</strong> Ordem ${ordem.NUM_ORDEM}<br>
                        <small>ID ${dApont.value} | ${dApont.tempo_ms}ms</small>
                    </div>
                </div>`);
                histLeituras.push({ codigo, ok: true, value: dApont.value, tempo_ms: dApont.tempo_ms, hora: hora() });
            } else {
                // Feedback principal mostra só o ID (se gerado) sem o detalhe do erro
                setFeedback(`<div class="fb-err">
                    <i class="bi bi-exclamation-circle-fill" style="font-size:18px"></i>
                    <div>
                        <strong>Erro no apontamento</strong>${dApont.value ? ` — ID ${dApont.value}` : ''}<br>
                        <small style="color:#888">Veja detalhes em <em>Por Leitura</em></small>
                    </div>
                </div>`);
                histLeituras.push({ codigo, ok: false, error: dApont.error, value: dApont.value, tempo_ms: dApont.tempo_ms, hora: hora() });
            }

            renderLeituras();

            // Atualiza aba de ordens se estiver ativa
            if (document.getElementById('tabOrdens')?.classList.contains('active')) {
                recarregarOrdens();
            }

        } catch (e) {
            setFeedback(`<div class="fb-err"><i class="bi bi-wifi-off"></i> Erro de rede: ${e.message}</div>`);
            histLeituras.push({ codigo, ok: false, error: e.message, hora: hora() });
            renderLeituras();
        } finally {
            processando = false;
            // Se máquina não foi fixada no setup, pede novamente a cada leitura
            if (!maquinaFixa) maquinaVinculada = false;
            if (input) { input.disabled = false; input.value = ''; }
            setStatus('&#128994; Scanner ativo', '#1565c0');
            focarInput();
            setTimeout(focarInput, 150);
            setTimeout(focarInput, 500);
        }
    }

    // ── Encerrar sessão ───────────────────────────────────────────────────────

    window.encerrar = async function () {
        if (!confirm('Encerrar a sessão de apontamento?')) return;
        await fetch(BASE + 'apontamento-api-encerrar-sessao', { method: 'POST' });
        window.location.href = BASE + 'apontamento-producao';
    };

    // ── Init ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', async function () {
        const input = document.getElementById('scanInput');
        if (!input) return;

        // Verifica se sessão já tem máquina vinculada (definida no setup = fixa)
        try {
            const r = await fetch(BASE + 'apontamento-api-sessao');
            const d = await r.json();
            if (d.ativa && d.sessao && d.sessao.maquina_id > 0) {
                maquinaVinculada = true;
                maquinaFixa      = !!d.sessao.maquina_fixa;
            }
        } catch (_) {}

        // Toque/clique direto no campo de scan abre teclado para digitação manual
        input.addEventListener('click', function () {
            input.inputMode = 'text';
        });
        input.addEventListener('blur', function () {
            input.inputMode = 'none';
        });

        // Garante foco (sem teclado) quando toca fora de botões/abas
        document.addEventListener('click', function (e) {
            if (modalAberto()) return;
            if (e.target === input) return; // clicou no próprio campo — já tratado acima
            if (!e.target.closest('button') && !e.target.closest('.ap-tab')) focarInput();
        });
        document.addEventListener('touchend', function (e) {
            if (modalAberto()) return;
            if (e.target === input) return;
            if (!e.target.closest('button') && !e.target.closest('.ap-tab')) focarInput();
        });

        // Captura Enter do scanner
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const codigo = input.value.trim();
                if (codigo) processarScan(codigo);
            }
        });

        // Mantém foco no campo sempre — re-foca mesmo durante processamento
        input.addEventListener('blur', function () {
            setTimeout(focarInput, 120);
        });

        focarInput();
        recarregarOrdens();
    });

})();
