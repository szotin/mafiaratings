import { Component, OnInit } from '@angular/core'
import { Observable } from 'rxjs';
import { ObsAuthService } from '../../services/obs/obs-auth.service';
import { LoggedInGuardService } from '../../services/obs/logged-in-guard.service'

@Component({
  selector: 'obs-left-side-bar',
  templateUrl: './left-side-bar.component.html',
  styleUrls: ['./left-side-bar.component.sass']
})
export class ObsLeftSideBarComponent implements OnInit {
  public isLoggedIn: Observable<boolean> = new Observable<boolean>()
  public obsLinks$: Observable<any> = new Observable<any>()

  public obsLinks: { label: string; href: string; class?: string; checkmarkWhenLoggedIn?:boolean }[] = [
    { label: 'Connect', href: '/obs/login', checkmarkWhenLoggedIn: true },
    { label: 'Controller', href: '/obs/controller' },
    { label: 'Help', href: '/obs/help' }
  ]

  constructor(private obsAuth: ObsAuthService) {}

  ngOnInit() {
    this.isLoggedIn = this.obsAuth.isLoggedIn$
  }
}
