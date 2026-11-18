document.addEventListener('DOMContentLoaded', function () {
    // SweetAlert2 confirmation for any form with class "confirm-form"
    document.querySelectorAll('form.confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            Swal.fire({
                title: form.dataset.title || 'Are you sure?',
                text: form.dataset.text || '',
                icon: form.dataset.icon || 'warning',
                showCancelButton: true,
                confirmButtonColor: form.dataset.icon === 'question' ? '#3b82f6' : '#dc3545',
                confirmButtonText: form.dataset.confirm || 'Yes, continue'
            }).then(function (result) {
                if (result.isConfirmed) { form.submit(); }
            });
        });
    });

    // DataTables on any table with class "datatable"
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('table.datatable').each(function () {
            jQuery(this).DataTable({ pageLength: 10, order: [], autoWidth: false });
        });
    }

    // Fill a modal's inputs from a button's data-fill JSON (add/edit modals)
    document.querySelectorAll('[data-modal-fill]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.querySelector(btn.dataset.modalFill);
            if (!modal) { return; }
            var data = JSON.parse(btn.dataset.fill || '{}');
            Object.keys(data).forEach(function (key) {
                var el = modal.querySelector('[name="' + key + '"]');
                if (el) { el.value = data[key] === null ? '' : data[key]; }
            });
            var title = modal.querySelector('.modal-title-text');
            if (title && btn.dataset.title) { title.textContent = btn.dataset.title; }
        });
    });
});
