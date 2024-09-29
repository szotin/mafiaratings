import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClient, HttpClientModule } from '@angular/common/http';

import { TranslateLoader, TranslateModule } from '@ngx-translate/core';
import { TranslateHttpLoader } from '@ngx-translate/http-loader';

import { GamesnapshotService } from './services/gamesnapshot.service';

import { AppRoutingModule } from './app-routing.module';

import { AppComponent } from './app.component';
import { GamestatsComponent } from './components/gamestats/gamestats.component';
import { RefereeComponent } from './components/referee/referee.component';
import { RoundComponent } from './components/round/round.component';
import { TopBarComponent } from './components/top-bar/top-bar.component';
import { PlayerComponent } from './components/player/player.component';
import { PlayersComponent } from './components/players/players.component';
import { GameOverlayComponent } from './components/game-overlay/game-overlay.component';

import { ObsControlComponent } from './components/obs-control/obs-control.component';
import { ObsControlWebsocketService } from './components/obs-control/obs-control-websocket.service';

@NgModule({
  declarations: [
    AppComponent,
    PlayersComponent,
    PlayerComponent,
    GamestatsComponent,
    ObsControlComponent,
    GameOverlayComponent,
    RefereeComponent,
    TopBarComponent,
    RoundComponent
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    TranslateModule.forRoot({
      loader: {
        provide: TranslateLoader,
        useFactory: HttpLoaderFactory,
        deps: [HttpClient]
      }
    }),
    AppRoutingModule
  ],
  providers: [ GamesnapshotService, ObsControlWebsocketService ],
  bootstrap: [ AppComponent ]
})
export class AppModule { }

// required for AOT compilation
export function HttpLoaderFactory(http: HttpClient): TranslateHttpLoader {
  return new TranslateHttpLoader(http, './assets/i18n/');
}
