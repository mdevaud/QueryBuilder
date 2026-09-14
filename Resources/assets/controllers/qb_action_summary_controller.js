import { Controller } from '@hotwired/stimulus';

import { countRules, groupBlock, parseQuery } from '../summary.js';

/**
 * Popover on the (i) mark next to each action of the rule screen, reading the product
 * selection of the action the way the action screen does above its editor, so the
 * selections can be compared without leaving the rule.
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
            const query = parseQuery(trigger.dataset.tree);
            const isEmpty = countRules(query) === 0;
            trigger.classList.toggle('qb-action-summary-trigger-empty', isEmpty);

            if (!Popover) {
                continue;
            }

            this.popovers.push(new Popover(trigger, {
                html: true,
                sanitize: false,
                title: this.titleValue,
                content: this.buildContent(query, isEmpty),
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

    buildContent(query, isEmpty) {
        const content = document.createElement('div');
        content.className = 'qb-action-summary';

        if (isEmpty) {
            const hint = document.createElement('p');
            hint.className = 'qb-action-summary-empty mb-0';
            hint.textContent = this.emptyHintValue;
            content.appendChild(hint);
            return content;
        }

        content.appendChild(groupBlock(query, this.fieldsValue, this.labelsValue, 0));

        return content;
    }
}
