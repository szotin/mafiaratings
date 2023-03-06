import { NgModule } from '@angular/core'
import { RouterModule, Routes } from '@angular/router'
import { HelpComponent } from '../views/help/help.component'
import { LoginComponent } from '../views/login/login.component'
import { ObsControllerComponent } from '../views/obs-controller/obs-controller.component'
import { MrControllerComponent } from '../views/mr-controller/mr-controller.component'
import { LoggedInGuardService } from '../services/obs/logged-in-guard.service'
import { ControllerComponent } from '../views/controller/controller.component'

const obsRoutes: Routes = [
  {
    path: 'obs',
    children: [
      { path: 'help', component: HelpComponent },
      {
        path: 'controller',
        component: ControllerComponent,
        canActivate: [LoggedInGuardService]
      },
      {
        path: 'obscontroller',
        component: ObsControllerComponent,
        canActivate: [LoggedInGuardService]
      },
      {
        path: 'mrcontroller',
        component: MrControllerComponent,
        canActivate: [LoggedInGuardService]
      },
      { 
        path: 'login',
         component: LoginComponent 
      }
    ]
  }
]

const routes: Routes = [...obsRoutes]
@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ObsRoutingModule {}
