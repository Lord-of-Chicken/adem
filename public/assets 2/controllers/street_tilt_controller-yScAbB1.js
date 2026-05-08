import { Controller } from '@hotwired/stimulus';

const maxTilt = 9;

export default class extends Controller {
    static targets = ['tilt'];

    connect() {
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    // On utilise les méthodes directement appelées par le HTML (Actions)
    onMove(event) {
        if (this.reducedMotion) return;

        // On utilise requestAnimationFrame pour une fluidité maximale (60fps)
        if (this.ticking) return;
        this.ticking = true;

        requestAnimationFrame(() => {
            const el = event.currentTarget;
            const r = el.getBoundingClientRect();
            const x = (event.clientX - r.left) / r.width - 0.5;
            const y = (event.clientY - r.top) / r.height - 0.5;
            
            el.style.transform = `perspective(880px) 
                                  rotateY(${x * maxTilt * 2}deg) 
                                  rotateX(${-y * maxTilt * 2}deg) 
                                  translateZ(10px)`;
            this.ticking = false;
        });
    }

    onLeave(event) {
        event.currentTarget.style.transform = 'perspective(880px) rotateY(0deg) rotateX(0deg) translateZ(0px)';
    }

    disconnect() {
        // On remet à zéro tous les targets lors du démontage du composant
        this.tiltTargets.forEach(el => el.style.transform = '');
    }
}