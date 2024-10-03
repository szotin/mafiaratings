import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GamestatsComponent } from './components/gamestats/gamestats.component';
import { RefereeComponent } from './components/referee/referee.component';
import { PlayersComponent } from './components/players/players.component';
import { GameOverlayComponent } from './components/game-overlay/game-overlay.component';
import { ObsControlComponent } from './components/obs-control/obs-control.component';

const routes: Routes = [
  { path: '', component: GameOverlayComponent },
  { path: 'gamestats', component: GamestatsComponent },
  { path: 'referee', component: RefereeComponent },
  { path: 'players', component: PlayersComponent },
  { path: 'game', component: GameOverlayComponent },
  { path: 'obs-control', component: ObsControlComponent }
];

@NgModule({
  imports: [ RouterModule.forRoot(routes, { useHash: true }) ],
  exports: [ RouterModule ]
})
export class AppRoutingModule { }
