import { Component, OnInit } from '@angular/core'
import { FormControl, FormGroup, Validators } from '@angular/forms'
import { ObsUrl } from '../../services/obs/obs-api.service'
import { ObsAuthService } from '../../services/obs/obs-auth.service'
import { Router } from '@angular/router';
import { UrlParametersService } from 'src/app/services/mafiaratings/url-parameters.service';

@Component({
  selector: 'obs-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.sass'],
})
export class LoginComponent implements OnInit {
  public connectedColor = 'primary'

  public formulario: any = new FormGroup({});

  constructor(private authService: ObsAuthService, private urlParameterService: UrlParametersService, private router: Router) {}

  ngOnInit() {
    const mrToken = this.urlParameterService.gameSnapshotUrlParams.get('token') ?? '';

    const mrUserid = this.urlParameterService.gameSnapshotUrlParams.get('user_id') ?? '';

    this.formulario = new FormGroup({
      obs: new FormGroup({
        host: new FormControl('127.0.0.1', Validators.required),
        port: new FormControl('4455', []),
        password: new FormControl('', []),
        protocol: new FormControl('ws', []),
      }),
      mafiaRatings: new FormGroup({
        mrUserId: new FormControl(mrUserid, Validators.required),
        mrToken: new FormControl(mrToken, Validators.required)
      })
    })

    this.authService.isLoggedIn$.subscribe({
      next: (data: any) => {
        this.connectedColor = data ? 'accent' : 'primary'
      }
    })
  }
  
  submitForm() {
    if (this.formulario.invalid) {
      return
    }
    let obsUrl: ObsUrl = {
      protocol: this.formulario.get(['obs','protocol'])?.value || '',
      host: this.formulario.get(['obs','host'])?.value || '',
      port: this.formulario.get(['obs','port'])?.value || ''
    }
    let password = this.formulario.get(['obs','password'])?.value
    this.authService.login(obsUrl, password || '').subscribe((data) => {

      this.router.navigate(['obs','controller'], {
         queryParams: {
          token: this.formulario.get(['mafiaRatings','mrToken'])?.value,
          user_id: this.formulario.get(['mafiaRatings','mrUserId'])?.value 
         },
         queryParamsHandling: "merge" 
        })
    })
  }
}
