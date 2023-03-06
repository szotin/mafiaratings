import { NgModule } from '@angular/core'
import { HelpComponent } from '../views/help/help.component'
import { LoginComponent } from '../views/login/login.component'
import { ObsControllerComponent } from '../views/obs-controller/obs-controller.component'
import { MrControllerComponent } from '../views/mr-controller/mr-controller.component'
import { ObsLeftSideBarComponent } from '../views/left-side-bar/left-side-bar.component'
import { ObsRightSideBarComponent } from '../views/right-side-bar/right-side-bar.component'
import { ObsMaterialUiModule } from './material-ui.module'
import { ObsRoutingModule } from './obs-routing.module'
import { CommonModule } from '@angular/common'
import { FormsModule, ReactiveFormsModule } from '@angular/forms'

@NgModule({
  declarations: [
    HelpComponent,
    LoginComponent,
    ObsControllerComponent,
    MrControllerComponent,
    ObsLeftSideBarComponent,
    ObsRightSideBarComponent
  ],
  imports: [
    CommonModule,
    ObsMaterialUiModule,
    ObsRoutingModule,
    FormsModule,
    ReactiveFormsModule
  ],
  exports: [
    ObsLeftSideBarComponent,
    ObsMaterialUiModule,
    HelpComponent,
    LoginComponent,
    ObsControllerComponent,
    MrControllerComponent,
    ObsLeftSideBarComponent,
    ObsRightSideBarComponent
  ],
  schemas: []
})
export class ObsExportModule {}
