/* MEPTDI PMAS — small progressive-enhancement script (no framework, no build step). */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // Sidebar (mobile)
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-nav-toggle]')) {
            document.body.classList.toggle('nav-open');
        } else if (e.target.closest('.overlay')) {
            document.body.classList.remove('nav-open');
        }
    });

    // Dropdowns
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-dropdown]');
        $$('.dropdown.open').forEach((d) => { if (!toggle || d !== toggle.closest('.dropdown')) d.classList.remove('open'); });
        if (toggle) {
            e.preventDefault();
            toggle.closest('.dropdown').classList.toggle('open');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            $$('.dropdown.open').forEach((d) => d.classList.remove('open'));
            document.body.classList.remove('nav-open');
        }
    });

    // Live clock (office time as rendered by the server + local ticking)
    const clock = $('[data-clock]');
    if (clock) {
        const tz = clock.dataset.tz || undefined;
        const fmt = new Intl.DateTimeFormat('en-GB', { timeZone: tz, day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        const target = $('[data-clock-text]', clock);
        const tick = () => { try { target.textContent = fmt.format(new Date()).replace(',', ''); } catch (_) { /* keep server value */ } };
        tick();
        setInterval(tick, 1000);
    }

    // Dialogs: <button data-dialog-open="id">
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-dialog-open]');
        if (opener) {
            const dlg = document.getElementById(opener.dataset.dialogOpen);
            if (dlg) {
                // Pre-fill fields from data-fill-* attributes
                Object.keys(opener.dataset).filter((k) => k.startsWith('fill')).forEach((k) => {
                    const name = k.slice(4).replace(/^[A-Z]/, (c) => c.toLowerCase()).replace(/[A-Z]/g, (c) => '_' + c.toLowerCase());
                    const field = dlg.querySelector('[data-field="' + name + '"]');
                    if (!field) return;
                    if ('value' in field && field.tagName !== 'DIV' && field.tagName !== 'SPAN' && field.tagName !== 'B') field.value = opener.dataset[k];
                    else field.textContent = opener.dataset[k];
                });
                if (opener.dataset.action) {
                    const form = dlg.querySelector('form');
                    if (form) form.action = opener.dataset.action;
                }
                dlg.showModal();
                const focusable = dlg.querySelector('textarea, input:not([type=hidden]), select');
                if (focusable) setTimeout(() => focusable.focus(), 30);
            }
        }
        const closer = e.target.closest('[data-dialog-close]');
        if (closer) closer.closest('dialog').close();
    });
    $$('dialog.modal').forEach((dlg) => {
        dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    });

    // Confirmations: <form data-confirm="message">
    const confirmDlg = $('#confirm-dialog');
    let pendingForm = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed && confirmDlg) {
            e.preventDefault();
            pendingForm = form;
            $('[data-confirm-text]', confirmDlg).textContent = form.dataset.confirm;
            const btn = $('[data-confirm-ok]', confirmDlg);
            btn.className = 'btn ' + (form.dataset.confirmTone === 'danger' ? 'btn-danger' : 'btn-primary');
            btn.textContent = form.dataset.confirmButton || 'Confirm';
            confirmDlg.showModal();
            return;
        }
        // Prevent double submission of consequential forms
        const submitter = e.submitter || $('button[type=submit]', form);
        if (submitter && !form.hasAttribute('data-allow-resubmit')) {
            setTimeout(() => { submitter.disabled = true; }, 0);
        }
    });
    if (confirmDlg) {
        $('[data-confirm-ok]', confirmDlg).addEventListener('click', () => {
            if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                confirmDlg.close();
                pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
            }
        });
    }

    // Clickable table rows
    document.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a, button, input, label, select, form')) {
            window.location = row.dataset.href;
        }
    });

    // Auto-submit filter controls
    $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form && el.form.submit()));

    // Select-all checkboxes: <input data-select-all="group">, members data-select="group"
    $$('[data-select-all]').forEach((master) => {
        const group = master.dataset.selectAll;
        const members = () => $$('[data-select="' + group + '"]').filter((m) => !m.disabled);
        const counter = $('[data-select-count="' + group + '"]');
        const sync = () => {
            const all = members();
            const checked = all.filter((m) => m.checked).length;
            master.checked = checked > 0 && checked === all.length;
            master.indeterminate = checked > 0 && checked < all.length;
            if (counter) counter.textContent = checked;
            $$('[data-requires-selection="' + group + '"]').forEach((b) => { b.disabled = checked === 0; });
        };
        master.addEventListener('change', () => { members().forEach((m) => { m.checked = master.checked; }); sync(); });
        document.addEventListener('change', (e) => { if (e.target.matches('[data-select="' + group + '"]')) sync(); });
        sync();
    });

    // Client-side quick filter for long lists: <input data-quickfilter="#tableId">
    $$('[data-quickfilter]').forEach((input) => {
        const table = $(input.dataset.quickfilter);
        if (!table) return;
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            $$('tbody tr', table).forEach((tr) => { tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none'; });
        });
    });

    // Flash auto-dismiss
    $$('[data-autodismiss]').forEach((el) => setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 6000));
    document.addEventListener('click', (e) => { const c = e.target.closest('.alert .close'); if (c) c.closest('.alert').remove(); });

    // Show/hide dependent fields: <select data-toggle-target="#el" data-toggle-values="a,b">
    $$('[data-toggle-target]').forEach((ctrl) => {
        const target = $(ctrl.dataset.toggleTarget);
        const values = (ctrl.dataset.toggleValues || '').split(',');
        const apply = () => { if (target) target.style.display = values.includes(ctrl.value) ? '' : 'none'; };
        ctrl.addEventListener('change', apply);
        apply();
    });

    // Print buttons
    document.addEventListener('click', (e) => { if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); } });
})();
