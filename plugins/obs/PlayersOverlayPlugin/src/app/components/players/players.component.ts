import { Component, Input, SimpleChanges } from '@angular/core';
import { GameSnapshotService } from '../../services/gamesnapshot/gamesnapshot.service';
import { GameSnapshot, GameState } from '../../services/gamesnapshot/gamesnapshot.model';
import { PlayerComponent } from '../player/player.component';
import { interval, Subject, Subscription, timer } from 'rxjs';
import { retry, share, startWith, switchMap, takeUntil } from 'rxjs/operators';
@Component({
  selector: 'players',
  templateUrl: `./players.component.html`,
  styleUrls: [`./players.component.css`],
})
export class PlayersComponent {
  @Input() name: string;
  gameSnapshot: GameSnapshot | undefined;
  showPlayers: boolean;
  private timeInterval: Subscription;
  private stopPolling = new Subject();

  constructor(private gameSnapshotService: GameSnapshotService) {}

  ngOnInit() {
    this.timeInterval = timer(0, 2000)
      .pipe(
        switchMap(() => this.getGameSnapshot()),
        // retry(2),
        share(),
        // takeUntil(this.stopPolling)
      )
      .subscribe(
        (res) => {
          let gameSnapshot = res.body;
          this.showPlayers = gameSnapshot.game.state != GameState.notStarted;
          this.gameSnapshot = res.body;
          
        },
        (err) => console.log(err)
      );
  }

  ngOnDestroy() {
    this.stopPolling.next(null);
  }

  getGameSnapshot() {
    return this.gameSnapshotService.getGameSnapshot();
  }
}
