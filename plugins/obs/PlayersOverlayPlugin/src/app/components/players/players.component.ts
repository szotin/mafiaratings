import { Component, OnInit } from '@angular/core';
import { retry, share, Subject, Subscription, switchMap, timer } from 'rxjs';
import { GameSnapshot, GameState, Player } from 'src/app/services/gamesnapshot.model';
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
  // private stopPolling = new Subject();

  constructor(private gameSnapshotService: GamesnapshotService) { }

  ngOnInit(): void {
    this.timeInterval = timer(0, 2000)
      .pipe(
        switchMap(() => this.getGameSnapshot()),
        retry(20),
        share(),
        // takeUntil(this.stopPolling)
      )
      .subscribe(
        (res: { body: GameSnapshot | null | undefined; }) => {
          let gameSnapshot = res.body;
          this.showPlayers = gameSnapshot?.game.state != GameState.notStarted ?? false;
          this.gameSnapshot = res.body;

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
