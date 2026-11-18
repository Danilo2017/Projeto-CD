<?php /** Funções JS comuns das telas da Comissão Expedição (incluído por cada tela). */ ?>
<script>
window.EX = (function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const fmt = (n, c = 2) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: c, maximumFractionDigits: c });
    return {
        esc, iso, fmt,
        brl: n => fmt(n, 2),
        val: n => fmt(n, 4).replace(/0{1,2}$/, ''),
        dbr: d => d ? String(d).split('-').reverse().join('/') : '',
        num: v => String(v ?? '').trim().replace(/\./g, '').replace(',', '.'),
        hoje: () => '<?= date('Y-m-d') ?>', inicioMes: () => '<?= date('Y-m-01') ?>',
        tipo: t => t === 'E' ? 'Expedição' : 'Separação',
        tagTipo: t => '<span class="badge ' + (t === 'E' ? 'bg-primary' : 'bg-warning text-dark') + '">' + (t === 'E' ? 'Expedição' : 'Separação') + '</span>',
        status: a => a === 'S' ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>',
        async post(url, dados) {
            const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados || {}) });
            let data;
            try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
            if (data.error) throw new Error(data.error);
            return data;
        },
        aviso(texto, ok) {
            const el = document.getElementById('exMsg'); if (!el) return alert(texto);
            el.className = 'alert alert-' + (ok ? 'success' : 'danger') + ' py-2 mb-2'; el.textContent = texto; el.style.display = texto ? '' : 'none';
            if (ok && texto) setTimeout(() => { if (el.textContent === texto) el.style.display = 'none'; }, 4000);
        },
        async acao(fn) { try { await fn(); } catch (e) { EX.aviso(e.message, false); } },
        modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); },
        /** Select2 de funcionários da empresa; devolve a lista carregada. */
        async funcionarios(sel, modalId) {
            const rows = (await EX.post('/expedicao-api-funcionarios', {})).rows || [];
            sel.innerHTML = '<option value="">Selecione</option>' + rows.map(f => '<option value="' + f.ID + '">' + esc(f.COD_FUNC + ' - ' + f.NOME) + '</option>').join('');
            const o = { width: '100%', theme: 'bootstrap-5', placeholder: 'Selecione' };
            if (modalId) o.dropdownParent = $('#' + modalId + ' .modal-content');
            if ($.fn.select2) $(sel).select2(o);
            return rows;
        },
        async equipes(sel, comQualquer, apenasAtivas = true) {
            const rows = (await EX.post('/expedicao-api-equipes', { apenas_ativas: apenasAtivas })).rows || [];
            sel.innerHTML = (comQualquer ? '<option value="">Qualquer equipe</option>' : '<option value="">Selecione</option>') +
                rows.map(e => '<option value="' + e.ID_EQUIPE + '">' + esc(e.NOME) + ' (' + EX.tipo(e.TIPO) + ')</option>').join('');
            return rows;
        },
    };
})();
</script>
