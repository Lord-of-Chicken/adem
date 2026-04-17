import { Controller } from '@hotwired/stimulus';

const maxTilt = 9;

export default class extends Controller {
    static targets = ['tilt'];

    connect() {
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (this.reducedMotion) {
            return;
        }
        this.onMove = this.onMove.bind(this);
        this.onLeave = this.onLeave.bind(this);
        this.tiltTargets.forEach((el) => {
            el.addEventListener('mousemove', this.onMove);
            el.addEventListener('mouseleave', this.onLeave);
        });
    }

    disconnect() {
        this.tiltTargets.forEach((el) => {
            el.removeEventListener('mousemove', this.onMove);
            el.removeEventListener('mouseleave', this.onLeave);
            el.style.transform = '';
        });
    }

    onMove(event) {
        const el = event.currentTarget;
        const r = el.getBoundingClientRect();
        const x = (event.clientX - r.left) / r.width - 0.5;
        const y = (event.clientY - r.top) / r.height - 0.5;
        el.style.transform =
            `perspective(880px) rotateY(${x * maxTilt * 2}deg) rotateX(${-y * maxTilt * 2}deg) translateZ(6px)`;
    }

    onLeave(event) {
        event.currentTarget.style.transform = '';
    }
}
