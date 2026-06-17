import { startStimulusApp } from '@symfony/stimulus-bundle';

import CartLineController from './controllers/cart_line_controller.js';
import CookieConsentController from './controllers/cookie_consent_controller.js';
import CropperEditorController from './controllers/cropper_editor_controller.js';
import ImageEditorController from './controllers/image_editor_controller.js';
import NavSelectController from './controllers/nav_select_controller.js';
import ProductController from './controllers/product_controller.js';
import SortableCarouselController from './controllers/sortable-carousel_controller.js';
import StreetTiltController from './controllers/street_tilt_controller.js';
import TranslationsEditorController from './controllers/translations_editor_controller.js';

const app = startStimulusApp();

app.register('cart-line', CartLineController);
app.register('cookie-consent', CookieConsentController);
app.register('cropper-editor', CropperEditorController);
app.register('image-editor', ImageEditorController);
app.register('nav-select', NavSelectController);
app.register('product', ProductController);
app.register('sortable-carousel', SortableCarouselController);
app.register('street-tilt', StreetTiltController);
app.register('translations-editor', TranslationsEditorController);
