import { NgModule } from '@angular/core'
import { BrowserModule } from '@angular/platform-browser'
import { BrowserAnimationsModule } from '@angular/platform-browser/animations'
import { HttpClientModule } from '@angular/common/http';

import { ObsExportModule } from './modules/obs-export.module'

import { ObsComponent } from './app.component'
import { AppRoutingModule } from './app-routing.module';
import { ControllerComponent } from './views/controller/controller.component';
import { SyncComponent } from './views/sync/sync.component'
import { FormsModule } from '@angular/forms';

@NgModule({
  declarations: [ ObsComponent, ControllerComponent, SyncComponent ],
  imports: [
    AppRoutingModule,
    BrowserModule,
    FormsModule,
    BrowserAnimationsModule,
    HttpClientModule,
    ObsExportModule
  ],
  exports: [],
  bootstrap: [ ObsComponent ]
})
export class ObsModule {}
