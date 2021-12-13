import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { PlayersComponent } from './components/players/players.component';
import { PlayerComponent } from './components/player/player.component';
import { GamesnapshotService } from './services/gamesnapshot.service';

@NgModule({
  declarations: [
    AppComponent,
    PlayersComponent,
    PlayerComponent
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    AppRoutingModule
  ],
  providers: [ GamesnapshotService ],
  bootstrap: [AppComponent]
})
export class AppModule { }
