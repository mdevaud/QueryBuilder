import { Controller } from '@hotwired/stimulus';

const EMPTY_QUERY = { combinator: 'and', rules: [] };
const LIST_OPERATORS = ['in', 'notIn', 'between', 'notBetween'];
const VALUELESS_OPERATORS = ['null', 'notNull'];

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
        return parseJson(this.input?.value, EMPTY_QUERY);
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

// ------------------------------------------------------------------ summary DOM

function parseJson(raw, fallback) {
    if (!raw || raw === 'null') {
        return fallback;
    }
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && Array.isArray(parsed.rules) ? parsed : fallback;
    } catch {
        return fallback;
    }
}

function countRules(group) {
    if (!group || !Array.isArray(group.rules)) {
        return 0;
    }
    return group.rules.reduce(
        (total, rule) => total + (rule && Array.isArray(rule.rules) ? countRules(rule) : (rule && rule.field ? 1 : 0)),
        0,
    );
}

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    // textContent only: field labels and values come from the shop data
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
}

// A stored list is a comma-separated string (in/notIn textareas, between pairs); arrays are
// accepted for trees written by hand
function valueItems(rule) {
    const value = rule.value;
    if (Array.isArray(value)) {
        return value;
    }
    if (LIST_OPERATORS.includes(rule.operator) && typeof value === 'string') {
        return value.split(',').map((item) => item.trim()).filter((item) => item !== '');
    }
    return value === undefined || value === null ? [] : [value];
}

function valueLabels(field, rule, labels) {
    const options = Array.isArray(field?.values) ? field.values : [];
    const words = labels.values || {};

    return valueItems(rule).map((single) => {
        if (single === true || single === 'true') {
            return words.true || 'true';
        }
        if (single === false || single === 'false') {
            return words.false || 'false';
        }
        const known = options.find((candidate) => String(candidate.value ?? candidate.name) === String(single));
        return known ? (known.label ?? known.name) : String(single);
    });
}

function ruleLine(rule, fields, labels) {
    const field = fields.find((candidate) => candidate.name === rule.field);
    const operatorLabel = (labels.operators || {})[rule.operator] || rule.operator;
    const words = labels.values || {};

    const line = element('li', 'qb-summary-rule');
    // An unknown field means the tree was built for another context: make it visible
    const fieldNode = element(
        'span',
        field ? 'qb-summary-field' : 'qb-summary-field qb-summary-field-unknown',
        field ? field.label : rule.field,
    );
    if (!field) {
        fieldNode.title = words.unknownField || '';
    }
    line.appendChild(fieldNode);
    line.appendChild(element('span', 'qb-summary-operator', operatorLabel));

    if (VALUELESS_OPERATORS.includes(rule.operator)) {
        return line;
    }

    const items = valueLabels(field, rule, labels).filter((label) => label !== '');

    if (items.length === 0) {
        line.appendChild(element('span', 'qb-summary-missing', words.missing || ''));
        return line;
    }

    const isRange = rule.operator === 'between' || rule.operator === 'notBetween';
    const join = labels.join || {};

    items.forEach((label, index) => {
        if (index > 0) {
            line.appendChild(element('span', 'qb-summary-join', isRange ? (join.and || 'and') : (join.or || 'or')));
        }
        line.appendChild(element('span', 'qb-summary-value', label));
    });

    return line;
}

// Structured recap of the tree: nesting, fields and values must stay readable
// even with a dozen rules, which a single flat sentence does not allow
function groupBlock(group, fields, labels, depth) {
    const rules = Array.isArray(group.rules) ? group.rules : [];
    const combinator = group.combinator === 'or' ? 'or' : 'and';
    const block = element('div', `qb-summary-group qb-summary-depth-${Math.min(depth, 3)}`);
    const headings = labels.groups || {};
    const combinators = labels.combinators || {};

    if (rules.length > 1 || depth > 0 || group.not) {
        const heading = element('div', 'qb-summary-heading');
        heading.appendChild(element(
            'span',
            `qb-summary-combinator qb-summary-combinator-${combinator}`,
            combinators[combinator] || combinator.toUpperCase(),
        ));
        heading.appendChild(element(
            'span',
            'qb-summary-heading-text',
            headings[group.not ? `${combinator}Not` : combinator] || '',
        ));
        block.appendChild(heading);
    }

    const list = element('ul', 'qb-summary-list');

    for (const rule of rules) {
        if (!rule || typeof rule !== 'object') {
            continue;
        }
        if (Array.isArray(rule.rules)) {
            const nested = element('li', 'qb-summary-nested');
            nested.appendChild(groupBlock(rule, fields, labels, depth + 1));
            list.appendChild(nested);
            continue;
        }
        if (!rule.field) {
            continue;
        }
        list.appendChild(ruleLine(rule, fields, labels));
    }

    block.appendChild(list);

    return block;
}
