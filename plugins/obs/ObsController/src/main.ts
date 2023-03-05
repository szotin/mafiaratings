import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';

import { ObsModule } from './app/app.module';


platformBrowserDynamic().bootstrapModule(ObsModule)
  .catch(err => console.error(err));
