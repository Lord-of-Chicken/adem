(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const config = document.getElementById('admin-media-item-form-config');
        if (!config) return;

        const form = document.querySelector('form');
        if (!form) return;

        const uploadUrl = config.dataset.uploadUrl;
        const csrfToken = config.dataset.csrfToken;
        const fileField = form.querySelector('[type="file"]');

        if (fileField && uploadUrl) {
            fileField.addEventListener('change', (event) => {
                const file = event.target.files && event.target.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('file', file);

                fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.path) return;
                        const assetPathField = form.querySelector('[name$="[assetPath]"]');
                        if (assetPathField) assetPathField.value = data.path;
                    })
                    .catch(error => console.error('Upload error:', error));
            });
        }
    });
})();
