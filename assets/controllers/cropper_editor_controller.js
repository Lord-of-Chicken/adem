import { Controller } from '@hotwired/stimulus';
import Cropper from 'cropperjs';

export default class extends Controller {
    static targets = ['image'];
    static values = {
        uploadUrl: String
    };

    connect() {
        console.log('Cropper editor controller connected');
        
        if (!this.hasImageTarget) {
            console.error('Image target not found');
            return;
        }

        console.log('Image target found:', this.imageTarget);

        // Initialiser Cropper directement (API v1.x)
        this.cropper = new Cropper(this.imageTarget, {
            aspectRatio: NaN,
            viewMode: 0,
            autoCropArea: 1,
            responsive: true,
            checkCrossOrigin: false,
            background: false,
            minContainerWidth: 200,
            minContainerHeight: 200,
            minCanvasWidth: 0,
            minCanvasHeight: 0,
            zoomOnWheel: false,
            ready: () => {
                console.log('Cropper is ready');
                // Accéder au canvas de Cropper via le DOM
                const cropperContainer = this.cropper.container;
                const canvas = cropperContainer.querySelector('canvas');
                if (canvas) {
                    console.log('Canvas found:', canvas);
                    this.canvas = canvas;
                }
            }
        });

        console.log('Cropper instance initialized:', this.cropper);
        
        // Stocker l'effet actuel pour l'appliquer lors du crop
        this.currentFilter = '';
        // Valeurs par défaut
        this.quality = 0.9;
        this.format = 'image/jpeg';
        this.scale = 1.0;
    }

    rotateLeft() {
        console.log('rotateLeft called, cropper:', this.cropper);
        if (this.cropper) {
            // API v1.x: utiliser rotate
            this.cropper.rotate(-90);
        }
    }

    rotateRight() {
        console.log('rotateRight called, cropper:', this.cropper);
        if (this.cropper) {
            // API v1.x: utiliser rotate
            this.cropper.rotate(90);
        }
    }

    flipHorizontal() {
        console.log('flipHorizontal called, cropper:', this.cropper);
        if (this.cropper) {
            const scaleX = this.cropper.getData().scaleX || 1;
            this.cropper.scaleX(scaleX * -1);
        }
    }

    flipVertical() {
        console.log('flipVertical called, cropper:', this.cropper);
        if (this.cropper) {
            const scaleY = this.cropper.getData().scaleY || 1;
            this.cropper.scaleY(scaleY * -1);
        }
    }

    setRatioFree() {
        console.log('setRatioFree called, cropper:', this.cropper);
        if (this.cropper) {
            // API v1.x: utiliser NaN pour ratio libre
            this.cropper.setAspectRatio(NaN);
        }
    }

    setRatioSquare() {
        console.log('setRatioSquare called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.setAspectRatio(1);
        }
    }

    setRatio4_3() {
        console.log('setRatio4_3 called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.setAspectRatio(4 / 3);
        }
    }

    setRatio16_9() {
        console.log('setRatio16_9 called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.setAspectRatio(16 / 9);
        }
    }

    zoomIn() {
        console.log('zoomIn called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.zoom(0.1);
        }
    }

    zoomOut() {
        console.log('zoomOut called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.zoom(-0.1);
        }
    }

    setEffectNone() {
        console.log('setEffectNone called');
        this.currentFilter = '';
        if (this.canvas) {
            this.canvas.style.filter = '';
        }
    }

    setEffectGrayscale() {
        console.log('setEffectGrayscale called');
        this.currentFilter = 'grayscale(100%)';
        if (this.canvas) {
            this.canvas.style.filter = this.currentFilter;
        }
    }

    setEffectSepia() {
        console.log('setEffectSepia called');
        this.currentFilter = 'sepia(100%)';
        if (this.canvas) {
            this.canvas.style.filter = this.currentFilter;
        }
    }

    setEffectBlur() {
        console.log('setEffectBlur called');
        this.currentFilter = 'blur(3px)';
        if (this.canvas) {
            this.canvas.style.filter = this.currentFilter;
        }
    }

    setEffectBrightness() {
        console.log('setEffectBrightness called');
        this.currentFilter = 'brightness(1.3)';
        if (this.canvas) {
            this.canvas.style.filter = this.currentFilter;
        }
    }

    setEffectContrast() {
        console.log('setEffectContrast called');
        this.currentFilter = 'contrast(1.5)';
        if (this.canvas) {
            this.canvas.style.filter = this.currentFilter;
        }
    }

    updateQuality(event) {
        const quality = event.target.value;
        document.getElementById('quality-value').textContent = quality;
        this.quality = parseFloat(quality);
    }

    setFormatJpeg() {
        console.log('setFormatJpeg called');
        this.format = 'image/jpeg';
    }

    setFormatPng() {
        console.log('setFormatPng called');
        this.format = 'image/png';
    }

    updateScale(event) {
        const scale = event.target.value;
        document.getElementById('scale-value').textContent = scale + 'x';
        this.scale = parseFloat(scale);
    }

    crop() {
        console.log('crop called, cropper:', this.cropper);
        if (!this.cropper) {
            console.error('Cropper instance not available');
            alert('Cropper instance not available');
            return;
        }

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
            console.error('Failed to get cropped canvas');
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
        console.log('reset called, cropper:', this.cropper);
        if (this.cropper) {
            this.cropper.reset();
        }
        this.currentFilter = '';
        if (this.canvas) {
            this.canvas.style.filter = '';
        }
    }
}
