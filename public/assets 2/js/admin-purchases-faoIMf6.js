(() => {
    const currencyRates = { EUR: 1, USD: 1.08, GBP: 0.85, CHF: 0.95 };
    const currencySymbols = { EUR: '€', USD: '$', GBP: '£', CHF: 'CHF' };

    function filterPurchases() {
        const statusFilter = document.getElementById('status-filter').value;
        document.querySelectorAll('tbody tr[data-status]').forEach(row => {
            row.style.display = (statusFilter === '' || row.dataset.status === statusFilter) ? '' : 'none';
        });
    }

    function updateCurrency() {
        const currency = document.getElementById('currency-filter').value;
        const rate = currencyRates[currency];
        const symbol = currencySymbols[currency];

        document.getElementById('currency-symbol').textContent = symbol;

        document.querySelectorAll('.amount-cell').forEach(cell => {
            const converted = ((parseInt(cell.dataset.cents) / 100) * rate).toFixed(2);
            cell.textContent = converted.replace('.', ',') + ' ' + symbol;
        });
    }

    window.filterPurchases = filterPurchases;
    window.updateCurrency = updateCurrency;
})();
