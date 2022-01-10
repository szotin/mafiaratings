import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { PlayersComponent } from './components/players/players.component';
import { PlayerComponent } from './components/player/player.component';
import { GamestatsComponent } from './components/gamestats/gamestats.component';
import { ObsControlComponent } from './components/obs-control/obs-control.component';

import { GamesnapshotService } from './services/gamesnapshot.service';
import { ObsControlWebsocketService } from './components/obs-control/obs-control-websocket.service'
@NgModule({
  declarations: [
    AppComponent,
    PlayersComponent,
    PlayerComponent,
    GamestatsComponent,
    ObsControlComponent
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    AppRoutingModule
  ],
  providers: [ GamesnapshotService, ObsControlWebsocketService ],
  bootstrap: [ AppComponent ]
})
export class AppModule { }
