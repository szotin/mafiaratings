import { Component, OnInit } from '@angular/core'
import { FormControl, FormGroup, Validators } from '@angular/forms'
import { ObsUrl } from '../../services/obs/obs-api.service'
import { ObsAuthService } from '../../services/obs/obs-auth.service'
import { Router } from '@angular/router';

@Component({
  selector: 'obs-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.sass'],
})
export class LoginComponent implements OnInit {
  public connectedColor = 'primary'

  public formulario = new FormGroup({
    host: new FormControl('127.0.0.1', Validators.required),
    port: new FormControl('4455', []),
    password: new FormControl('', []),
    protocol: new FormControl('ws', [])
  })

  constructor(private authService: ObsAuthService, private router: Router) {}

  ngOnInit() {
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
      protocol: this.formulario.get('protocol')?.value || '',
      host: this.formulario.get('host')?.value || '',
      port: this.formulario.get('port')?.value || ''
    }
    let password = this.formulario.get('password')?.value
    this.authService.login(obsUrl, password || '').subscribe((data)=>{
      this.router.navigate(['obs','controller'])
    })
  }
}
