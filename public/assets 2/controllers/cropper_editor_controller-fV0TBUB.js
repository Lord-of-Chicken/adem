import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        uploadUrl: String
    };

    connect() {
        this.currentFilter = '';
        this.quality = 0.9;
        this.format = 'image/jpeg';
        this.scale = 1.0;
        this.cropper = null;
        this.canvas = null;
        this.imageElement = null;
    }

    onPreConnect(event) {
        const { img } = event.detail;

        img.id = 'cropper-image';
        img.alt = img.alt || 'Image à éditer';
    }

    onConnect(event) {
        this.cropper = event.detail.cropper;
        this.imageElement = event.detail.img;
        this.canvas = this.cropper?.container?.querySelector('canvas') || null;
    }

    ensureCropperReady() {
        return !!this.cropper;
    }

    rotateLeft() {
        if (!this.ensureCropperReady()) return;
        this.cropper.rotate(-90);
    }

    rotateRight() {
        if (!this.ensureCropperReady()) return;
        this.cropper.rotate(90);
    }

    flipHorizontal() {
        if (!this.ensureCropperReady()) return;
        const scaleX = this.cropper.getData().scaleX || 1;
        this.cropper.scaleX(scaleX * -1);
    }

    flipVertical() {
        if (!this.ensureCropperReady()) return;
        const scaleY = this.cropper.getData().scaleY || 1;
        this.cropper.scaleY(scaleY * -1);
    }

    setRatioFree() {
        if (!this.ensureCropperReady()) return;
        this.cropper.setAspectRatio(NaN);
    }

    setRatioSquare() {
        if (!this.ensureCropperReady()) return;
        this.cropper.setAspectRatio(1);
    }

    setRatio4_3() {
        if (!this.ensureCropperReady()) return;
        this.cropper.setAspectRatio(4 / 3);
    }

    setRatio16_9() {
        if (!this.ensureCropperReady()) return;
        this.cropper.setAspectRatio(16 / 9);
    }

    zoomIn() {
        if (!this.ensureCropperReady()) return;
        this.cropper.zoom(0.1);
    }

    zoomOut() {
        if (!this.ensureCropperReady()) return;
        this.cropper.zoom(-0.1);
    }

    setEffectNone() {
        this.currentFilter = '';
        if (this.canvas) this.canvas.style.filter = '';
        if (this.imageElement) this.imageElement.style.filter = '';
    }

    setEffectGrayscale() {
        this.currentFilter = 'grayscale(100%)';
        if (this.canvas) this.canvas.style.filter = this.currentFilter;
        if (this.imageElement) this.imageElement.style.filter = this.currentFilter;
    }

    setEffectSepia() {
        this.currentFilter = 'sepia(100%)';
        if (this.canvas) this.canvas.style.filter = this.currentFilter;
        if (this.imageElement) this.imageElement.style.filter = this.currentFilter;
    }

    setEffectBlur() {
        this.currentFilter = 'blur(3px)';
        if (this.canvas) this.canvas.style.filter = this.currentFilter;
        if (this.imageElement) this.imageElement.style.filter = this.currentFilter;
    }

    setEffectBrightness() {
        this.currentFilter = 'brightness(1.3)';
        if (this.canvas) this.canvas.style.filter = this.currentFilter;
        if (this.imageElement) this.imageElement.style.filter = this.currentFilter;
    }

    setEffectContrast() {
        this.currentFilter = 'contrast(1.5)';
        if (this.canvas) this.canvas.style.filter = this.currentFilter;
        if (this.imageElement) this.imageElement.style.filter = this.currentFilter;
    }

    updateQuality(event) {
        const quality = event.target.value;
        document.getElementById('quality-value').textContent = quality;
        this.quality = parseFloat(quality);
    }

    setFormatJpeg() {
        this.format = 'image/jpeg';
    }

    setFormatPng() {
        this.format = 'image/png';
    }

    updateScale(event) {
        const scale = event.target.value;
        document.getElementById('scale-value').textContent = scale + 'x';
        this.scale = parseFloat(scale);
    }

    crop() {
        if (!this.ensureCropperReady()) return;

        const quality = this.quality || 0.9;
        const format = this.format || 'image/jpeg';
        const scale = this.scale || 1.0;
        
        const croppedCanvas = this.cropper.getCroppedCanvas({
            maxWidth: 4096 * scale,
            maxHeight: 4096 * scale,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!croppedCanvas) {
            return;
        }

        // Appliquer l'effet actuel s'il y en a un
        if (this.currentFilter) {
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = croppedCanvas.width;
            tempCanvas.height = croppedCanvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.drawImage(croppedCanvas, 0, 0);

            const ctx = croppedCanvas.getContext('2d');
            ctx.clearRect(0, 0, croppedCanvas.width, croppedCanvas.height);
            ctx.filter = this.currentFilter;
            ctx.drawImage(tempCanvas, 0, 0);
        }

        const extension = format === 'image/png' ? 'png' : 'jpeg';
        croppedCanvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = `cropped-image.${extension}`;
            link.href = url;
            link.click();
            URL.revokeObjectURL(url);
        }, format, quality);
    }

    reset() {
        if (!this.ensureCropperReady()) return;
        this.cropper.reset();
        this.currentFilter = '';
        if (this.canvas) this.canvas.style.filter = '';
        if (this.imageElement) this.imageElement.style.filter = '';
    }
}
