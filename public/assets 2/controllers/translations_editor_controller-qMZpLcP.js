import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'textarea',
        'searchInput',
        'searchStatus',
        'sectionSelect',
        'sectionStatus',
    ];

    connect() {
        this.searchMatches = [];
        this.currentMatchIndex = -1;

        this.rebuildSections();
        this.updateSearchMatches();
    }

    onSearchInput() {
        this.updateSearchMatches();

        if (this.searchMatches.length > 0) {
            this.focusFirstMatchFromCaret();
        }
    }

    onSearchKeydown(event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        this.focusMatch(event.shiftKey ? -1 : 1);
    }

    previousMatch() {
        this.focusMatch(-1);
    }

    nextMatch() {
        this.focusMatch(1);
    }

    onSectionChange() {
        if (!this.hasSectionSelectTarget || !this.hasTextareaTarget) {
            return;
        }

        const value = this.sectionSelectTarget.value;
        if (value === '') {
            return;
        }

        const position = parseInt(value, 10);
        this.focusSelection(position, position);
    }

    rebuildSections() {
        if (!this.hasTextareaTarget || !this.hasSectionSelectTarget) {
            return;
        }

        const lines = this.textareaTarget.value.split('\n');
        const sections = [];
        let charPos = 0;

        for (let i = 0; i < lines.length; i += 1) {
            const line = lines[i];
            const match = line.match(/^\s*#\s+(.+)$/);
            if (match) {
                sections.push({
                    label: match[1].trim(),
                    lineNumber: i + 1,
                    position: charPos,
                });
            }
            charPos += line.length + 1;
        }

        this.sectionSelectTarget.innerHTML = '<option value="">Aller à une section…</option>';
        sections.forEach((section) => {
            const option = document.createElement('option');
            option.value = String(section.position);
            option.textContent = `L${section.lineNumber} — ${section.label}`;
            this.sectionSelectTarget.appendChild(option);
        });

        if (this.hasSectionStatusTarget) {
            this.sectionStatusTarget.textContent = `${sections.length} section(s) détectée(s)`;
        }
    }

    onTextareaInput() {
        this.rebuildSections();

        if (this.hasSearchInputTarget && this.searchInputTarget.value.trim() !== '') {
            this.updateSearchMatches();
        }
    }

    updateSearchMatches() {
        if (!this.hasTextareaTarget || !this.hasSearchInputTarget) {
            return;
        }

        const query = this.searchInputTarget.value.trim().toLowerCase();
        const text = this.textareaTarget.value;
        const haystack = text.toLowerCase();

        this.searchMatches = [];
        this.currentMatchIndex = -1;

        if (query === '') {
            if (this.hasSearchStatusTarget) {
                this.searchStatusTarget.textContent = '';
            }
            return;
        }

        let from = 0;
        while (from < haystack.length) {
            const idx = haystack.indexOf(query, from);
            if (idx === -1) {
                break;
            }

            this.searchMatches.push({ start: idx, end: idx + query.length });
            from = idx + query.length;
        }

        if (this.hasSearchStatusTarget) {
            this.searchStatusTarget.textContent = this.searchMatches.length > 0
                ? `${this.searchMatches.length} résultat(s)`
                : 'Aucun résultat';
        }
    }

    focusMatch(direction) {
        if (!this.hasTextareaTarget || this.searchMatches.length === 0) {
            return;
        }

        if (this.currentMatchIndex === -1) {
            this.currentMatchIndex = direction >= 0 ? 0 : this.searchMatches.length - 1;
        } else {
            this.currentMatchIndex =
                (this.currentMatchIndex + direction + this.searchMatches.length) % this.searchMatches.length;
        }

        const match = this.searchMatches[this.currentMatchIndex];
        this.focusSelection(match.start, match.end);

        if (this.hasSearchStatusTarget) {
            this.searchStatusTarget.textContent = `${this.currentMatchIndex + 1}/${this.searchMatches.length} résultat(s)`;
        }
    }

    focusSelection(start, end) {
        this.textareaTarget.focus();
        this.textareaTarget.setSelectionRange(start, end);
        this.scrollToPosition(start);
        this.flashJumpTarget();
    }

    focusFirstMatchFromCaret() {
        const caret = this.textareaTarget.selectionStart || 0;
        let index = this.searchMatches.findIndex((match) => match.start >= caret);

        if (index === -1) {
            index = 0;
        }

        this.currentMatchIndex = index;
        const match = this.searchMatches[index];
        this.focusSelection(match.start, match.end);

        if (this.hasSearchStatusTarget) {
            this.searchStatusTarget.textContent = `${this.currentMatchIndex + 1}/${this.searchMatches.length} résultat(s)`;
        }
    }

    scrollToPosition(position) {
        const before = this.textareaTarget.value.slice(0, position);
        const lineCount = before.split('\n').length;
        const lineHeight = parseFloat(window.getComputedStyle(this.textareaTarget).lineHeight) || 20;
        this.textareaTarget.scrollTop = Math.max(0, (lineCount - 3) * lineHeight);
    }

    flashJumpTarget() {
        this.textareaTarget.classList.remove('translations-editor__textarea--jump-flash');

        requestAnimationFrame(() => {
            this.textareaTarget.classList.add('translations-editor__textarea--jump-flash');
            window.setTimeout(() => {
                this.textareaTarget.classList.remove('translations-editor__textarea--jump-flash');
            }, 380);
        });
    }
}
