import { Controller } from '@hotwired/stimulus';

import { countRules, groupBlock, parseQuery } from '../summary.js';

/**
 * Popover on the (i) mark next to each action of the rule screen, reading the product
 * selection of the action the way the action screen does above its editor, followed by
 * the parameters worth a glance (discount rate, free shipping, stackable), so the
 * actions can be compared without leaving the rule.
 *
 * Each trigger carries the stored tree in data-tree (absent when the action has no
 * product selection) and the translated detail lines in data-details.
 *
 * Relies on the Bootstrap 5 build the back-office theme exposes as window.bootstrap;
 * without it the marks stay inert.
 *
 * Values:
 *   fields     fields of the rule context, as handed to the editor
 *   labels     translated wording (see QueryBuilder\Service\EditorLabels)
 *   title      heading of the popover
 *   emptyHint  wording shown when the action has no criterion
 */
export default class extends Controller {
    static targets = ['trigger'];

    static values = {
        fields: Array,
        labels: Object,
        title: String,
        emptyHint: String,
    };

    connect() {
        const Popover = window.bootstrap?.Popover;
        this.popovers = [];

        for (const trigger of this.triggerTargets) {
            const hasSelection = 'tree' in trigger.dataset;
            const query = parseQuery(trigger.dataset.tree);
            const details = parseDetails(trigger.dataset.details);
            const isEmpty = (!hasSelection || countRules(query) === 0) && details.length === 0;
            trigger.classList.toggle('qb-action-summary-trigger-empty', isEmpty);

            if (!Popover) {
                continue;
            }

            this.popovers.push(new Popover(trigger, {
                html: true,
                sanitize: false,
                title: this.titleValue,
                content: this.buildContent(hasSelection ? query : null, details),
                trigger: 'hover focus',
                placement: 'right',
                container: 'body',
                customClass: 'qb-action-summary-popover',
            }));
        }
    }

    disconnect() {
        this.popovers.forEach((popover) => popover.dispose());
        this.popovers = [];
    }

    buildContent(query, details) {
        const content = document.createElement('div');
        content.className = 'qb-action-summary';

        if (query !== null) {
            if (countRules(query) === 0) {
                const hint = document.createElement('p');
                hint.className = 'qb-action-summary-empty mb-0';
                hint.textContent = this.emptyHintValue;
                content.appendChild(hint);
            } else {
                content.appendChild(groupBlock(query, this.fieldsValue, this.labelsValue, 0));
            }
        }

        if (details.length > 0) {
            const list = document.createElement('ul');
            list.className = 'qb-action-summary-details';
            for (const detail of details) {
                const item = document.createElement('li');
                item.textContent = detail;
                list.appendChild(item);
            }
            content.appendChild(list);
        }

        return content;
    }
}

function parseDetails(raw) {
    if (!raw) {
        return [];
    }
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter((line) => typeof line === 'string' && line !== '') : [];
    } catch {
        return [];
    }
}
