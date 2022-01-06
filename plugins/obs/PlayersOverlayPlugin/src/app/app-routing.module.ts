import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GamestatsComponent } from './components/gamestats/gamestats.component';
import { PlayersComponent } from './components/players/players.component';

const routes: Routes = [
  { path: 'players', component: PlayersComponent },
  { path: 'gamestats', component: GamestatsComponent }
];

@NgModule({
  imports: [RouterModule.forRoot(routes, { useHash: true })],
  exports: [RouterModule]
})
export class AppRoutingModule { }
