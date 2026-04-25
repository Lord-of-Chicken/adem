import { Controller } from '@hotwired/stimulus';
import Cropper from 'cropperjs';

export default class extends Controller {
    static targets = ['image'];
    static values = {
        uploadUrl: String,
    };

    cropper = null;
    contrast = 100;
    brightness = 100;

    connect() {
        this.initCropper();
    }

    initCropper() {
        if (!this.hasImageTarget) return;

        this.cropper = new Cropper(this.imageTarget, {
            aspectRatio: NaN,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.8,
            restore: false,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
    }

    crop() {
        if (!this.cropper) return;

        const canvas = this.cropper.getCroppedCanvas();
        this.applyFilters(canvas);
        
        canvas.toBlob((blob) => {
            this.uploadImage(blob);
        }, 'image/jpeg', 0.9);
    }

    applyFilters(canvas) {
        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;

        for (let i = 0; i < data.length; i += 4) {
            // Luminosité
            const brightnessFactor = (this.brightness - 100) / 100;
            data[i] = data[i] * (1 + brightnessFactor);
            data[i + 1] = data[i + 1] * (1 + brightnessFactor);
            data[i + 2] = data[i + 2] * (1 + brightnessFactor);

            // Contraste
            const contrastFactor = (259 * (this.contrast + 255)) / (255 * (259 - this.contrast));
            data[i] = contrastFactor * (data[i] - 128) + 128;
            data[i + 1] = contrastFactor * (data[i + 1] - 128) + 128;
            data[i + 2] = contrastFactor * (data[i + 2] - 128) + 128;
        }

        ctx.putImageData(imageData, 0, 0);
    }

    uploadImage(blob) {
        const formData = new FormData();
        formData.append('file', blob, 'edited-image.jpg');

        fetch(this.uploadUrlValue, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Image sauvegardée avec succès!');
                window.location.reload();
            } else {
                alert('Erreur lors de l\'upload de l\'image');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'upload de l\'image');
        });
    }

    setContrast(event) {
        this.contrast = parseInt(event.target.value);
        this.previewImage();
    }

    setBrightness(event) {
        this.brightness = parseInt(event.target.value);
        this.previewImage();
    }

    previewImage() {
        if (!this.cropper) return;

        const canvas = this.cropper.getCroppedCanvas();
        this.applyFilters(canvas);
        
        // Mettre à jour l'aperçu
        const previewCanvas = document.createElement('canvas');
        previewCanvas.width = canvas.width;
        previewCanvas.height = canvas.height;
        previewCanvas.getContext('2d').drawImage(canvas, 0, 0);
        
        // Remplacer l'image dans le cropper
        const imageData = previewCanvas.toDataURL();
        this.cropper.replace(imageData);
    }

    reset() {
        this.contrast = 100;
        this.brightness = 100;
        
        // Reset les sliders
        const contrastSlider = document.getElementById('contrast-slider');
        const brightnessSlider = document.getElementById('brightness-slider');
        if (contrastSlider) contrastSlider.value = 100;
        if (brightnessSlider) brightnessSlider.value = 100;
        
        // Reset le cropper
        if (this.cropper) {
            this.cropper.reset();
        }
    }

    rotateLeft() {
        if (this.cropper) {
            this.cropper.rotate(-90);
        }
    }

    rotateRight() {
        if (this.cropper) {
            this.cropper.rotate(90);
        }
    }

    flipHorizontal() {
        if (this.cropper) {
            const data = this.cropper.getData();
            const scaleX = data.scaleX || 1;
            this.cropper.scaleX(scaleX === 1 ? -1 : 1);
        }
    }

    flipVertical() {
        if (this.cropper) {
            const data = this.cropper.getData();
            const scaleY = data.scaleY || 1;
            this.cropper.scaleY(scaleY === 1 ? -1 : 1);
        }
    }
}
