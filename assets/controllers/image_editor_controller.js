import { Controller } from '@hotwired/stimulus';
import { showToast } from '../js/toast.js';

export default class extends Controller {
    static targets = ['modal'];
    static values  = { uploadUrl: String };

    cropper    = null;
    contrast   = 100;
    brightness = 100;

    // ── UX CropperJS events ──────────────────────────────────────────────────

    onConnect(event) {
        this.cropper = event.detail.cropper;
    }

    // ── Modal open / close ───────────────────────────────────────────────────

    open() {
        if (!this.hasModalTarget) return;
        this.modalTarget.classList.add('image-editor-modal--open');
        // Cropper was initialized while modal was hidden (0×0) — resize now
        requestAnimationFrame(() => {
            if (this.cropper) this.cropper.resize();
        });
    }

    close() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.remove('image-editor-modal--open');
        }
    }

    // ── Image operations ─────────────────────────────────────────────────────

    rotateLeft()  { if (this.cropper) this.cropper.rotate(-90); }
    rotateRight() { if (this.cropper) this.cropper.rotate(90);  }

    flipHorizontal() {
        if (!this.cropper) return;
        this.cropper.scaleX((this.cropper.getData().scaleX || 1) * -1);
    }

    flipVertical() {
        if (!this.cropper) return;
        this.cropper.scaleY((this.cropper.getData().scaleY || 1) * -1);
    }

    setContrast(event) {
        this.contrast = parseInt(event.target.value);
        event.target.closest('.control-item')
            ?.querySelector('.slider-value')
            ?.textContent && this._updateLabel(event.target, this.contrast + '%');
    }

    setBrightness(event) {
        this.brightness = parseInt(event.target.value);
        this._updateLabel(event.target, this.brightness + '%');
    }

    _updateLabel(input, text) {
        const label = input.previousElementSibling?.querySelector('.slider-value');
        if (label) label.textContent = text;
    }

    reset() {
        this.contrast   = 100;
        this.brightness = 100;

        this.element.querySelectorAll('input[type="range"]').forEach(slider => {
            slider.value = 100;
            const label = slider.previousElementSibling?.querySelector('.slider-value');
            if (label) label.textContent = '100%';
        });

        if (this.cropper) this.cropper.reset();
    }

    // ── Crop + upload ────────────────────────────────────────────────────────

    crop() {
        if (!this.cropper) return;

        const canvas = this.cropper.getCroppedCanvas();
        if (!canvas) return;

        this.applyFilters(canvas);
        canvas.toBlob((blob) => this.uploadImage(blob), 'image/jpeg', 0.9);
    }

    applyFilters(canvas) {
        const ctx       = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data      = imageData.data;

        const bf = (this.brightness - 100) / 100;
        const cf = (259 * (this.contrast + 255)) / (255 * (259 - this.contrast));

        for (let i = 0; i < data.length; i += 4) {
            data[i]     = cf * (data[i]     * (1 + bf) - 128) + 128;
            data[i + 1] = cf * (data[i + 1] * (1 + bf) - 128) + 128;
            data[i + 2] = cf * (data[i + 2] * (1 + bf) - 128) + 128;
        }

        ctx.putImageData(imageData, 0, 0);
    }

    uploadImage(blob) {
        const translations = this.getUiTranslations();
        const config    = document.getElementById('admin-media-item-form-config');
        const csrfToken = config ? config.dataset.csrfToken : '';
        const formData  = new FormData();
        formData.append('file', blob, 'edited-image.jpg');

        fetch(this.uploadUrlValue, {
            method:  'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body:    formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.path) {
                showToast(translations.image_upload_success || 'Image saved successfully!', 'success');
                window.location.reload();
            } else {
                showToast(translations.image_upload_error || 'Error while uploading image', 'error');
            }
        })
        .catch(() => showToast(translations.image_upload_error || 'Error while uploading image', 'error'));
    }

    getUiTranslations() {
        const script = document.getElementById('cart-translations');
        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            return {};
        }
    }
}
