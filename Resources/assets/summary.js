/**
 * Readable summary of a stored condition tree (react-querybuilder structure), shared
 * by the editor feedback of the rule and action forms and by the action popovers of
 * the rule screen. Builds DOM with textContent only: labels and values come from the
 * shop data.
 */

const EMPTY_QUERY = { combinator: 'and', rules: [] };
const LIST_OPERATORS = ['in', 'notIn', 'between', 'notBetween'];
const VALUELESS_OPERATORS = ['null', 'notNull'];

// Stored tree read from a hidden input or a data attribute: anything that is not a
// group falls back to the empty query
export function parseQuery(raw) {
    if (!raw || raw === 'null') {
        return EMPTY_QUERY;
    }
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && Array.isArray(parsed.rules) ? parsed : EMPTY_QUERY;
    } catch {
        return EMPTY_QUERY;
    }
}

export function countRules(group) {
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
export function groupBlock(group, fields, labels, depth) {
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
