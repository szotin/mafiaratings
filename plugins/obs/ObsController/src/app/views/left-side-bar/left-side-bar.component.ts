import { Component, OnInit } from '@angular/core'
import { Observable } from 'rxjs';
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
    { label: 'Control', href: '/obs/controller' },
    { label: 'Help', href: '/obs/help' }
  ]

  constructor(private loggedInGuardService: LoggedInGuardService) {}

  ngOnInit() {
    this.isLoggedIn = this.loggedInGuardService.canActivate().asObservable()

  }
}
