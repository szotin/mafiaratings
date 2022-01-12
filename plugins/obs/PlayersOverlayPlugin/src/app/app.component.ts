import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent {
  title = 'PlayersOverlayPlugin';

  constructor(private translate: TranslateService, private activatedRoute: ActivatedRoute) {
    translate.setDefaultLang('en');

    this.activatedRoute.queryParams.subscribe((params: { [x: string]: any; }) => {
      this.processUrlParams(params);
    });
  }

  private processUrlParams(params: { [x: string]: any; }) {
    const locale = params['locale'];

    if (locale) {
      this.translate.use(locale);
    }
  }
}
