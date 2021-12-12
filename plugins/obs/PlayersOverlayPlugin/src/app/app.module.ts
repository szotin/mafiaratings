import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';
import { FormsModule } from '@angular/forms';

import { AppComponent } from './app.component';
import { PlayersComponent } from './components/players/players.component';
import { PlayerComponent } from './components/player/player.component';
import { GameSnapshotService } from './services/gamesnapshot/gamesnapshot.service';

@NgModule({
  imports: [BrowserModule, FormsModule, HttpClientModule],
  declarations: [AppComponent, PlayersComponent, PlayerComponent],
  bootstrap: [AppComponent],
  providers: [ GameSnapshotService ]
})
export class AppModule {}
