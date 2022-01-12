import { Component, OnInit } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { GamePhase, Game, GameState, Player } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';

@Component({
  selector: 'players',
  templateUrl: './players.component.html',
  styleUrls: ['./players.component.scss']
})
export class PlayersComponent implements OnInit {
  game$?: Observable<Game | undefined>;
  showPlayers$?: Observable<boolean>;;

  constructor(private gameSnapshotService: GamesnapshotService) {
  }

  ngOnInit(): void {
    this.game$ = this.gameSnapshotService.getCurrentGame();
    this.showPlayers$ = this.game$.pipe(map((it?: Game) => (it?.state != GameState.notStarted) ?? false));
  }

  trackPlayerById(index:number, player:Player): number {
    return player.id;
  }
}
