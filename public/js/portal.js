document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-delete-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.getAttribute('data-delete-form');
            const form = formId ? document.getElementById(formId) : null;

            if (!form) {
                return;
            }

            const modal = document.getElementById('deleteConfirmModal');
            const confirmButton = document.getElementById('confirmDeleteButton');
            const recordName = button.getAttribute('data-record-name') || 'this record';
            const target = document.getElementById('deleteRecordName');

            if (target) {
                target.textContent = recordName;
            }

            if (confirmButton) {
                confirmButton.onclick = () => form.submit();
            }

            if (modal && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    });
});
