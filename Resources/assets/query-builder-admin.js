import { Application } from '@hotwired/stimulus';

import 'react-querybuilder/dist/query-builder.css';
import './query-builder-admin.css';

import QueryBuilderController from '@openstudio/query-builder-bundle/controllers/query_builder_controller.js';
import EditorController from './controllers/qb_editor_controller.js';
import ActionFormController from './controllers/qb_action_form_controller.js';
import ActionSummaryController from './controllers/qb_action_summary_controller.js';

// Own Stimulus application: the back-office theme builds its controllers with Encore and
// exposes no registry, so the module registers the editor controller of the bundle itself.
// Both applications scan the same DOM and only act on the identifiers they know.
const application = Application.start();

application.register('query-builder', QueryBuilderController);
application.register('qb-editor', EditorController);
application.register('qb-action-form', ActionFormController);
application.register('qb-action-summary', ActionSummaryController);
