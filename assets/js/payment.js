// Payment recording: fetch the selected tenant's balance and preview the new balance.
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('tenantSelect');
    var amount = document.getElementById('amountInput');
    var panel = document.getElementById('balancePanel');
    var data = window.TENANT_DATA || {};
    var cur = window.CUR || '';

    function fmt(n) {
        return cur + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function label(n) {
        if (n > 0) { return '<span class="badge bg-danger">OWING ' + fmt(n) + '</span>'; }
        if (n < 0) { return '<span class="badge bg-primary">IN CREDIT ' + fmt(n) + '</span>'; }
        return '<span class="badge bg-success">CLEAR ' + fmt(0) + '</span>';
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function render() {
        var t = data[select.value];
        if (!t) {
            panel.innerHTML = '<p class="text-muted mb-0">Select a tenant to see their current balance.</p>';
            return;
        }
        var paid = parseFloat(amount.value) || 0;
        var after = Math.round((t.balance - paid) * 100) / 100;
        var html = '<div class="mb-2 text-muted small">' + esc(t.unit) + '</div>' +
            '<div class="d-flex justify-content-between mb-2"><span>Monthly rent</span><strong>' + fmt(t.rent) + '</strong></div>' +
            '<div class="d-flex justify-content-between mb-2"><span>Next due date</span><strong>' + esc(t.next) + '</strong></div>' +
            '<div class="d-flex justify-content-between mb-2"><span>Current balance</span>' + label(t.balance) + '</div>';
        if (t.overdue > 0) {
            html += '<div class="alert alert-danger py-2 small">Overdue by ' + t.overdue + ' day(s).</div>';
        }
        if (paid > 0) {
            html += '<hr><div class="d-flex justify-content-between mb-2"><span>This payment</span><strong>' + fmt(paid) + '</strong></div>' +
                '<div class="d-flex justify-content-between"><span>Balance after payment</span>' + label(after) + '</div>';
        }
        panel.innerHTML = html;
    }

    select.addEventListener('change', render);
    amount.addEventListener('input', render);
    render();
});
