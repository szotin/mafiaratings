import { Injectable } from '@angular/core';
import { CanActivate } from '@angular/router';
import { ObsAuthService } from './obs-auth.service';

@Injectable({providedIn: 'root'})
export class LoggedInGuardService implements CanActivate{
  constructor(private obsAuth: ObsAuthService) {}
  
  canActivate(){
    return this.obsAuth._isLoggedIn$;
  }
}
