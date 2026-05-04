(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const config = document.getElementById('admin-media-item-form-config');
        if (!config) {
            return;
        }

        const form = document.querySelector('form');
        if (!form) {
            return;
        }

        const uploadUrl = config.dataset.uploadUrl;
        const fileField = form.querySelector('[type="file"]');

        if (fileField && uploadUrl) {
            fileField.addEventListener('change', (event) => {
                const file = event.target.files && event.target.files[0];
                if (!file) {
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);

                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.path) {
                            return;
                        }

                        const assetPathField = form.querySelector('[name$="[assetPath]"]');
                        if (assetPathField) {
                            assetPathField.value = data.path;
                        }
                    })
                    .catch((error) => console.error('Upload error:', error));
            });
        }

        window.openImageEditor = () => {
            const modal = document.getElementById('imageEditorModal');
            if (modal) {
                modal.classList.add('image-editor-modal--open');
            }
        };

        window.closeImageEditor = () => {
            const modal = document.getElementById('imageEditorModal');
            if (modal) {
                modal.classList.remove('image-editor-modal--open');
            }
        };

        const contrastSlider = document.getElementById('contrast-slider');
        const brightnessSlider = document.getElementById('brightness-slider');
        const contrastValue = document.getElementById('contrast-value');
        const brightnessValue = document.getElementById('brightness-value');

        if (contrastSlider && contrastValue) {
            contrastSlider.addEventListener('input', function () {
                contrastValue.textContent = `${this.value}%`;
            });
        }

        if (brightnessSlider && brightnessValue) {
            brightnessSlider.addEventListener('input', function () {
                brightnessValue.textContent = `${this.value}%`;
            });
        }
    });
})();
