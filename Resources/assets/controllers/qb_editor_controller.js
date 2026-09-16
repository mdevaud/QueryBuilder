import { Controller } from '@hotwired/stimulus';

import { countRules, groupBlock, parseQuery } from '../summary.js';

/**
 * Feedback around the query-builder-bundle widget of a rule or action form: a
 * condition counter, a readable summary of the stored tree and an empty state,
 * refreshed from the hidden input the bundle controller rewrites on every edit.
 *
 * On the rule screen it also drives the context: switching it hands the fields of
 * the new context to the bundle widget (which prunes the rules they cannot hold)
 * and filters the hook checkboxes.
 *
 * Values:
 *   fieldsByContext  { CONTEXT: [{ name, type, label, values, operators }] }
 *   labels           translated wording (see QueryBuilder\Service\EditorLabels)
 */
export default class extends Controller {
    static targets = [
        'context', 'contextHelp',
        'hookChoice', 'hookCount', 'hookEmpty',
        'counter', 'summary', 'summaryText', 'emptyHint',
    ];

    static values = {
        fieldsByContext: Object,
        labels: Object,
    };

    connect() {
        this.editor = this.element.querySelector('[data-controller~="query-builder"]');
        this.input = this.element.querySelector('[data-query-builder-target="input"]');
        this.onInputChange = () => this.refreshFeedback();
        this.input?.addEventListener('change', this.onInputChange);
        this.previousContext = this.hasContextTarget ? this.contextTarget.value : null;

        this.syncContextHelp();
        this.refreshHooks();
        this.refreshFeedback();
    }

    disconnect() {
        this.input?.removeEventListener('change', this.onInputChange);
    }

    // ------------------------------------------------------------------ context

    changeContext() {
        const context = this.contextTarget.value;
        const confirmMessage = this.labelsValue.confirmContextChange;

        if (countRules(this.currentQuery()) > 0 && confirmMessage && !window.confirm(confirmMessage)) {
            this.contextTarget.value = this.previousContext;
            return;
        }

        this.previousContext = context;

        // The bundle controller rebuilds the editor on this attribute, drops the rules on fields
        // the new list does not declare and rewrites the hidden input (change event: the
        // feedback below is refreshed again with the pruned tree)
        if (this.editor) {
            this.editor.dataset.queryBuilderFieldsValue = JSON.stringify(this.currentFields());
        }

        this.syncContextHelp();
        this.refreshHooks();
        this.refreshFeedback();
    }

    syncContextHelp() {
        if (!this.hasContextTarget || !this.hasContextHelpTarget) {
            return;
        }
        const option = this.contextTarget.options[this.contextTarget.selectedIndex];
        this.contextHelpTarget.textContent = option ? option.dataset.description || '' : '';
    }

    currentContext() {
        if (this.hasContextTarget) {
            return this.contextTarget.value;
        }
        return Object.keys(this.fieldsByContextValue)[0] ?? null;
    }

    currentFields() {
        return this.fieldsByContextValue[this.currentContext()] || [];
    }

    // -------------------------------------------------------------------- hooks

    refreshHooks() {
        const context = this.currentContext();
        const visible = [];

        for (const choice of this.hookChoiceTargets) {
            const matches = choice.dataset.context === context;
            choice.hidden = !matches;
            const checkbox = choice.querySelector('input[type="checkbox"]');
            if (!checkbox) {
                continue;
            }
            if (!matches) {
                checkbox.checked = false;
            } else {
                visible.push(checkbox);
            }
            choice.classList.toggle('qb-hook-checked', matches && checkbox.checked);
        }

        if (this.hasHookCountTarget) {
            const checked = visible.filter((checkbox) => checkbox.checked).length;
            this.hookCountTarget.textContent = (this.labelsValue.hooks?.count || '%checked% / %total%')
                .replace('%checked%', String(checked))
                .replace('%total%', String(visible.length));
        }

        if (this.hasHookEmptyTarget) {
            this.hookEmptyTarget.hidden = visible.length > 0;
        }
    }

    checkAllHooks(event) {
        event.preventDefault();
        this.setVisibleHooks(true);
    }

    uncheckAllHooks(event) {
        event.preventDefault();
        this.setVisibleHooks(false);
    }

    setVisibleHooks(checked) {
        for (const choice of this.hookChoiceTargets) {
            if (choice.hidden) {
                continue;
            }
            const checkbox = choice.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = checked;
            }
        }
        this.refreshHooks();
    }

    // ----------------------------------------------------------------- feedback

    currentQuery() {
        return parseQuery(this.input?.value);
    }

    refreshFeedback() {
        const query = this.currentQuery();
        const total = countRules(query);
        const labels = this.labelsValue;

        if (this.hasCounterTarget) {
            const counter = labels.counter || {};
            this.counterTarget.textContent = total === 0
                ? (counter.none || '')
                : total === 1
                    ? (counter.one || '1')
                    : (counter.many || '%count%').replace('%count%', String(total));
        }

        if (this.hasEmptyHintTarget) {
            this.emptyHintTarget.classList.toggle('qb-empty-hint-visible', total === 0);
        }

        if (this.hasSummaryTarget) {
            this.summaryTarget.classList.toggle('qb-summary-visible', total > 0);
            const target = this.hasSummaryTextTarget ? this.summaryTextTarget : this.summaryTarget;
            target.replaceChildren();
            if (total > 0) {
                target.appendChild(groupBlock(query, this.currentFields(), labels, 0));
            }
        }
    }
}
