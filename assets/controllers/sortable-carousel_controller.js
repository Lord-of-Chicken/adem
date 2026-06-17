import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static values = {
        updateUrl: String,
        csrfToken: String,
    };

    sortable = null;

    connect() {
        const tbody = this.element.querySelector('tbody');
        if (!tbody) return;

        this.sortable = new Sortable(tbody, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => {
                this.updateOrder();
            }
        });
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy();
            this.sortable = null;
        }
    }

    updateOrder() {
        const rows = this.element.querySelectorAll('tbody tr');
        const orders = [];

        rows.forEach((row, index) => {
            const id = row.dataset.id;
            if (id) {
                orders.push({ id: id, sortOrder: index + 1 });
            }
        });

        if (orders.length === 0) return;

        fetch(this.updateUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfTokenValue,
            },
            body: JSON.stringify({ orders: orders }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the displayed order numbers
                rows.forEach((row, index) => {
                    const orderCell = row.querySelector('td:nth-child(6)');
                    if (orderCell) {
                        orderCell.textContent = index + 1;
                    }
                });
            }
        })
        .catch(error => console.error('Error updating order:', error));
    }
}
