import { Component, OnInit } from '@angular/core';
import { retry, share, Subject, Subscription, switchMap, timer } from 'rxjs';
import { GamePhase, GameSnapshot, GameState, Player } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';

@Component({
  selector: 'players',
  templateUrl: './players.component.html',
  styleUrls: ['./players.component.scss']
})
export class PlayersComponent implements OnInit {
  gameSnapshot: GameSnapshot | null | undefined;
  showPlayers: boolean = false;
  private timeInterval: Subscription | undefined;

  constructor(private gameSnapshotService: GamesnapshotService) { }

  ngOnInit(): void {
    this.timeInterval = timer(0, 2000)
      .pipe(
        switchMap(() => this.getGameSnapshot()),
        retry(20),
        share(),
      )
      .subscribe(
        (res: { body: GameSnapshot | null | undefined; }) => {
          let gameSnapshot = res.body;
          let game = gameSnapshot?.game;
          this.gameSnapshot = gameSnapshot;
          this.showPlayers = (game && game.state != GameState.notStarted) ?? false;
        },
        (err: any) => console.log(err)
      );
  }

  private getGameSnapshot() {
    return this.gameSnapshotService.getGameSnapshot();
  }

  trackPlayerById(index:number, player:Player): number {
    return player.id;
  }
}
