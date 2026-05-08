import { startStimulusApp } from '@symfony/stimulus-bundle';

import CropperEditorController from './controllers/cropper_editor_controller.js';
import ImageEditorController from './controllers/image_editor_controller.js';
import SortableCarouselController from './controllers/sortable-carousel_controller.js';
import StreetTiltController from './controllers/street_tilt_controller.js';
import TranslationsEditorController from './controllers/translations_editor_controller.js';

const app = startStimulusApp();

app.register('cropper-editor', CropperEditorController);
app.register('image-editor', ImageEditorController);
app.register('sortable-carousel', SortableCarouselController);
app.register('street-tilt', StreetTiltController);
app.register('translations-editor', TranslationsEditorController);
