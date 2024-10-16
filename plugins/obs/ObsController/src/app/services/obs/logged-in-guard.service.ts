import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { ObsAuthService } from './obs-auth.service';

@Injectable({providedIn: 'root'})
export class LoggedInGuardService implements CanActivate{
  constructor(private obsAuth: ObsAuthService, private router: Router) {}
  
  canActivate() {
 
    return this.obsAuth._isLoggedIn$.pipe(
      map(e => {
        if (e) {
          return true
        } else {
          this.redirectToLogin()
          return false
        }
      }),
      catchError((err) => {
        this.redirectToLogin()
        return of(false)
      })
    )
  }

  redirectToLogin() {
    this.router.navigate(['obs','login'], {
      // queryParams: {
      //  token: this.formulario.get(['mafiaRatings','mrToken'])?.value,
      //  user_id: this.formulario.get(['mafiaRatings','mrUserId'])?.value 
      // },
      queryParamsHandling: "merge" 
     })
  }

}
