import { Component } from '@angular/core';
import { Observable } from 'rxjs/internal/Observable';
import { Game } from '../../services/mafiaratings/gamesnapshot.model';
import { GamesnapshotService } from '../../services/mafiaratings/gamesnapshot.service';

@Component({
  selector: 'obs-mr-controller',
  templateUrl: './mr-controller.component.html',
  styleUrls: ['./mr-controller.component.sass']
})
export class MrControllerComponent {

  public currentGame: Observable<Game | undefined>;
  constructor(private gameSnapshotService: GamesnapshotService) {
    this.currentGame = this.gameSnapshotService.getCurrentGame();
  }

  ngOnInit() {

  }

  getCurrentPhase() {
    return this.gameSnapshotService.getCurrentPhase();
  }

  getSpeakingPlayer() {
    return this.gameSnapshotService.getSpeakingPlayer();
  }
}
