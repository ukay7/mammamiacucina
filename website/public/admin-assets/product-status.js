(() => {
    document.querySelectorAll('[data-product-active]').forEach(button => {
        button.addEventListener('click', async () => {
            const before = button.getAttribute('aria-checked') === 'true';
            button.disabled = true;
            const notice = document.querySelector('[data-product-status-notice]') || (() => {
                const element = document.createElement('div');
                element.dataset.productStatusNotice = '';
                element.setAttribute('role', 'status');
                document.querySelector('[data-product-filters]').before(element);
                return element;
            })();
            try {
                const response = await fetch(button.dataset.productActive, {
                    method: 'PATCH',
                    headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value},
                    body: JSON.stringify({is_active: !before, expected_active: before})
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to change product status.');
                const active = data.is_active;
                const label = active ? 'Active' : 'Inactive';
                button.setAttribute('aria-checked', String(active));
                button.title = `${active ? 'Deactivate' : 'Activate'} product`;
                button.querySelector('small').textContent = label;
                button.closest('tr').querySelector('[data-product-column="status"]').textContent = label;
                notice.className = 'alert alert-success';
                notice.textContent = data.message;
                // Preserve category/search/column selections if the changed row no longer matches the status filter.
                const filter = document.querySelector('[data-product-filters] [name="active"]').value;
                if (filter !== '' && filter !== String(Number(active))) location.reload();
            } catch (error) {
                notice.className = 'alert alert-danger';
                notice.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    });
})();
