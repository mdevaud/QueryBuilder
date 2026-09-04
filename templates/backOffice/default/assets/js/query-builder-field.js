import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryBuilder } from 'react-querybuilder';

const EMPTY_QUERY = { combinator: 'and', rules: [] };

//Bootstrap 3 classes of the back-office, applied on top of the library ones
const CONTROL_CLASSNAMES = {
    queryBuilder: 'qb-tree',
    fields: 'form-control',
    operators: 'form-control',
    value: 'form-control',
    valueSource: 'form-control',
    combinators: 'form-control',
    addRule: 'btn btn-xs btn-default',
    addGroup: 'btn btn-xs btn-default',
    cloneRule: 'btn btn-xs btn-link',
    cloneGroup: 'btn btn-xs btn-link',
    removeRule: 'btn btn-xs btn-link qb-btn-remove',
    removeGroup: 'btn btn-xs btn-link qb-btn-remove',
    notToggle: 'qb-not-toggle',
};

//Names must stay "and"/"or": SqlBuilder whitelists them. Only the labels are localized.
const COMBINATORS = [
    { name: 'and', value: 'and', label: 'ET' },
    { name: 'or', value: 'or', label: 'OU' },
];

const TRANSLATIONS = {
    addRule: { label: '+ Condition', title: 'Ajouter une condition' },
    addGroup: { label: '+ Groupe', title: 'Ajouter un groupe de conditions' },
    cloneRule: { label: '⧉', title: 'Dupliquer la condition' },
    cloneGroup: { label: '⧉', title: 'Dupliquer le groupe' },
    removeRule: { label: '✕', title: 'Supprimer la condition' },
    removeGroup: { label: '✕', title: 'Supprimer le groupe' },
    combinators: { title: 'Toutes les conditions (ET) ou au moins une (OU)' },
    notToggle: { label: 'Inverser (NON)', title: 'Inverser le résultat du groupe' },
    fields: { title: 'Champ à tester' },
    operators: { title: 'Opérateur de comparaison' },
    value: { title: 'Valeur comparée' },
};

function parseJson(raw, fallback) {
    if (!raw || raw === 'null') return fallback;
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch {
        return fallback;
    }
}

function countRules(group) {
    if (!group || !Array.isArray(group.rules)) return 0;
    return group.rules.reduce(
        (total, rule) => total + (Array.isArray(rule.rules) ? countRules(rule) : 1),
        0
    );
}

function normalizeBooleanValues(group) {
    if (!group || !Array.isArray(group.rules)) return group;
    for (const rule of group.rules) {
        if (Array.isArray(rule.rules)) {
            normalizeBooleanValues(rule);
        } else if (typeof rule.value === 'boolean') {
            rule.value = rule.value ? 'true' : 'false';
        }
    }
    return group;
}

function collectFieldNames(group, names = new Set()) {
    if (!group || !Array.isArray(group.rules)) return names;
    for (const rule of group.rules) {
        if (Array.isArray(rule.rules)) {
            collectFieldNames(rule, names);
        } else if (rule.field) {
            names.add(rule.field);
        }
    }
    return names;
}

//Operator phrasing of the recap: full sentences read better than the "=" / "≠" of the selects
const OPERATOR_PHRASES = {
    '=': 'est égal à',
    '!=': "n'est pas égal à",
    '<': 'est inférieur à',
    '<=': 'est inférieur ou égal à',
    '>': 'est supérieur à',
    '>=': 'est supérieur ou égal à',
    contains: 'contient',
    doesNotContain: 'ne contient pas',
    beginsWith: 'commence par',
    endsWith: 'finit par',
    between: 'est entre',
    notBetween: "n'est pas entre",
    in: 'est parmi',
    notIn: "n'est pas parmi",
    null: 'est vide',
    notNull: "n'est pas vide",
};

//Operators comparing nothing: the value must not be rendered
const VALUELESS_OPERATORS = ['null', 'notNull'];

const GROUP_HEADINGS = {
    and: 'Toutes les conditions suivantes',
    or: 'Au moins une des conditions suivantes',
    andNot: 'Aucune des conditions suivantes',
    orNot: 'Pas « au moins une » des conditions suivantes',
};

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    //textContent only: field labels and values come from the shop data
    if (text !== undefined) node.textContent = text;
    return node;
}

//field.values is either a flat option list or <optgroup> sections ({label, options})
function flattenValues(field) {
    if (!field || !Array.isArray(field.values)) return [];
    return field.values.flatMap((entry) => (Array.isArray(entry.options) ? entry.options : [entry]));
}

function valueLabels(field, value) {
    const values = Array.isArray(value) ? value : [value];
    const options = flattenValues(field);

    return values.map((single) => {
        if (single === true) return 'vrai';
        if (single === false) return 'faux';
        const known = options.find((candidate) => String(candidate.name) === String(single));
        return known ? known.label : String(single ?? '');
    });
}

function ruleLine(rule, fields) {
    const field = fields.find((candidate) => candidate.name === rule.field);
    const operatorLabel = OPERATOR_PHRASES[rule.operator]
        || (field?.operators || []).find((operator) => operator.name === rule.operator)?.label
        || rule.operator;

    const line = element('li', 'qb-summary-rule');
    //An unknown field means the tree was built for another context: make it visible
    const fieldNode = element(
        'span',
        field ? 'qb-summary-field' : 'qb-summary-field qb-summary-field-unknown',
        field ? field.label : rule.field
    );
    if (!field) fieldNode.title = 'Champ indisponible dans ce contexte';
    line.appendChild(fieldNode);
    line.appendChild(element('span', 'qb-summary-operator', operatorLabel));

    if (VALUELESS_OPERATORS.includes(rule.operator)) {
        return line;
    }

    const labels = valueLabels(field, rule.value).filter((label) => label !== '');

    if (labels.length === 0) {
        line.appendChild(element('span', 'qb-summary-missing', 'valeur à renseigner'));

        return line;
    }

    const isRange = rule.operator === 'between' || rule.operator === 'notBetween';

    labels.forEach((label, index) => {
        if (index > 0) {
            line.appendChild(element('span', 'qb-summary-join', isRange ? 'et' : 'ou'));
        }
        line.appendChild(element('span', 'qb-summary-value', label));
    });

    return line;
}

//Structured recap of the tree: nesting, fields and values must stay readable
//even with a dozen rules, which a single flat sentence does not allow
function groupBlock(group, fields, depth) {
    const rules = Array.isArray(group.rules) ? group.rules : [];
    const combinator = group.combinator === 'or' ? 'or' : 'and';
    const block = element('div', `qb-summary-group qb-summary-depth-${Math.min(depth, 3)}`);

    if (rules.length > 1 || depth > 0 || group.not) {
        const heading = element('div', 'qb-summary-heading');
        heading.appendChild(element(
            'span',
            `qb-summary-combinator qb-summary-combinator-${combinator}`,
            combinator === 'or' ? 'OU' : 'ET'
        ));
        heading.appendChild(element(
            'span',
            'qb-summary-heading-text',
            GROUP_HEADINGS[group.not ? `${combinator}Not` : combinator]
        ));
        block.appendChild(heading);
    }

    const list = element('ul', 'qb-summary-list');

    for (const rule of rules) {
        if (Array.isArray(rule.rules)) {
            const nested = element('li', 'qb-summary-nested');
            nested.appendChild(groupBlock(rule, fields, depth + 1));
            list.appendChild(nested);
            continue;
        }

        if (!rule.field) continue;

        list.appendChild(ruleLine(rule, fields));
    }

    block.appendChild(list);

    return block;
}

function mountEditor(mount) {
    const input = document.querySelector(mount.dataset.input);
    if (!input) return;

    const fieldsByContext = parseJson(mount.dataset.fieldsByContext, null);
    const contextSelect = mount.dataset.contextSelect
        ? document.querySelector(mount.dataset.contextSelect)
        : null;
    const contextHelp = mount.dataset.contextHelp ? document.querySelector(mount.dataset.contextHelp) : null;
    const summary = mount.dataset.summary ? document.querySelector(mount.dataset.summary) : null;
    const emptyHint = mount.dataset.emptyHint ? document.querySelector(mount.dataset.emptyHint) : null;
    const counter = mount.dataset.counter ? document.querySelector(mount.dataset.counter) : null;

    const currentFields = () => {
        if (fieldsByContext && contextSelect) {
            return fieldsByContext[contextSelect.value] || [];
        }
        return parseJson(mount.dataset.fields, []);
    };

    const root = createRoot(mount);

    //Fields carrying a "values" list (values_query) get a select — multiselect for in/notIn.
    //Fields with a static valueEditorType (booleans) keep it: field-level wins in react-querybuilder.
    const valueEditorTypeFor = (fieldName, operator) => {
        const field = currentFields().find((candidate) => candidate.name === fieldName);
        if (!field || !Array.isArray(field.values) || !field.values.length) return undefined;
        return operator === 'in' || operator === 'notIn' ? 'multiselect' : 'select';
    };

    const refreshFeedback = (query) => {
        const total = countRules(query);

        if (counter) {
            counter.textContent = total === 0
                ? 'aucune condition'
                : `${total} condition${total > 1 ? 's' : ''}`;
        }

        if (emptyHint) {
            emptyHint.classList.toggle('qb-empty-hint-visible', total === 0);
        }

        if (summary) {
            summary.classList.toggle('qb-summary-visible', total > 0);
            const target = summary.querySelector('[data-qb-summary-text]') || summary;
            target.replaceChildren();
            if (total > 0) {
                target.appendChild(groupBlock(query, currentFields(), 0));
            }
        }
    };

    const render = () => {
        const query = normalizeBooleanValues(parseJson(input.value, EMPTY_QUERY));
        const fields = currentFields();
        //Fields stored in the tree but missing from the current context list are
        //materialized as stubs (appended so a real field stays the default of new
        //rules): without them the library select silently falls back to the first
        //option, which would replace the stored field on save and corrupt the rule
        const knownNames = new Set(fields.map((field) => field.name));
        const stubs = [...collectFieldNames(query)]
            .filter((name) => !knownNames.has(name))
            .map((name) => ({ name, label: `${name} — champ indisponible dans ce contexte` }));

        root.render(createElement(QueryBuilder, {
            fields: [...fields, ...stubs],
            defaultQuery: query,
            getValueEditorType: valueEditorTypeFor,
            controlClassnames: CONTROL_CLASSNAMES,
            translations: TRANSLATIONS,
            combinators: COMBINATORS,
            showCombinatorsBetweenRules: true,
            showNotToggle: true,
            showCloneButtons: true,
            //Arrays for in/notIn values: a comma inside a value (ex: "Appetizers, finger food")
            //would break the comma-joined string format at SQL compile time
            listsAsArrays: true,
            onQueryChange: (updatedQuery) => {
                input.value = updatedQuery.rules.length ? JSON.stringify(updatedQuery) : '';
                refreshFeedback(updatedQuery);
            },
        }));

        refreshFeedback(query);
    };

    render();

    if (contextSelect && fieldsByContext) {
        let previousContext = contextSelect.value;

        const syncContextHelp = () => {
            if (!contextHelp) return;
            const option = contextSelect.options[contextSelect.selectedIndex];
            contextHelp.textContent = option ? option.dataset.description || '' : '';
        };

        contextSelect.addEventListener('change', () => {
            //Switching context invalidates the current tree: warn before dropping it
            if (input.value !== '' && !window.confirm(
                'Changer de contexte réinitialise les conditions déjà saisies. Continuer ?'
            )) {
                contextSelect.value = previousContext;
                return;
            }

            previousContext = contextSelect.value;
            input.value = '';
            render();
            syncHookChoices(contextSelect);
            syncContextHelp();
        });

        syncHookChoices(contextSelect);
        syncContextHelp();
    }
}

function hookChoices() {
    return Array.from(document.querySelectorAll('.qb-hook-choice'));
}

function visibleHookCheckboxes() {
    return hookChoices()
        .filter((choice) => choice.style.display !== 'none')
        .map((choice) => choice.querySelector('input[type="checkbox"]'))
        .filter(Boolean);
}

function refreshHookFeedback() {
    const visible = visibleHookCheckboxes();
    const checked = visible.filter((checkbox) => checkbox.checked).length;

    for (const checkbox of visible) {
        checkbox.closest('.qb-hook-choice').classList.toggle('qb-hook-checked', checkbox.checked);
    }

    const counter = document.querySelector('[data-qb-hook-count]');
    if (counter) {
        counter.textContent = `${checked} coché${checked > 1 ? 's' : ''} sur ${visible.length}`;
    }

    const emptyState = document.querySelector('[data-qb-hook-empty]');
    if (emptyState) {
        emptyState.style.display = visible.length === 0 ? '' : 'none';
    }
}

function syncHookChoices(contextSelect) {
    for (const choice of hookChoices()) {
        //The GLOBAL context already carries a choice for every hook (DataDictionary::getHooks)
        const matches = choice.dataset.context === contextSelect.value;
        choice.style.display = matches ? '' : 'none';
        if (!matches) {
            const checkbox = choice.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        }
    }

    refreshHookFeedback();
}

function bindHookToolbar() {
    for (const choice of hookChoices()) {
        choice.querySelector('input[type="checkbox"]')?.addEventListener('change', refreshHookFeedback);
    }

    const setAllVisible = (checked) => {
        for (const checkbox of visibleHookCheckboxes()) {
            checkbox.checked = checked;
        }
        refreshHookFeedback();
    };

    document.querySelector('[data-qb-hook-all]')?.addEventListener('click', (event) => {
        event.preventDefault();
        setAllVisible(true);
    });

    document.querySelector('[data-qb-hook-none]')?.addEventListener('click', (event) => {
        event.preventDefault();
        setAllVisible(false);
    });

    refreshHookFeedback();
}

bindHookToolbar();

for (const mount of document.querySelectorAll('[data-query-builder]')) {
    mountEditor(mount);
}
