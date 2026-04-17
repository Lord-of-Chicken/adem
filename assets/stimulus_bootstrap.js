import { startStimulusApp } from '@symfony/stimulus-bundle';

import StreetTiltController from './controllers/street_tilt_controller.js';

const app = startStimulusApp();
app.register('street-tilt', StreetTiltController);
