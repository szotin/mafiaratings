import { Component } from '@angular/core';
import { filter } from 'rxjs';
import { GamePhase, GameSnapshot, GameState } from 'src/app/services/mafiaratings/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/mafiaratings/gamesnapshot.service';
import { OBSRequest } from 'src/app/services/obs/constants';
import { ObsApiService } from 'src/app/services/obs/obs-api.service';

enum SyncState {
  on = 'on',
  off = 'off',
  paused = 'paused'
}

@Component({
  selector: 'sync',
  templateUrl: './sync.component.html',
  styleUrls: ['./sync.component.sass']
})
export class SyncComponent {
  isSyncEnabled: SyncState = SyncState.off;
  fontStyle?: any;

  currentGamePhase: GamePhase | undefined;
  currentGameState: GameState | undefined;
  maxSeenRound: number = 0;


  phaseSceneMap: {[key in GamePhase]: string} = {
    "day" : "Day",
    "night": "Night"
  }

  phaseStateMap: any = {
    "notStarted": "Break"
  }

  currentScene: any;
  currentSelectedScene: string | undefined;
  gameObservable: any;

  constructor(private gameSnapshotService: GamesnapshotService, private obsService: ObsApiService) {

  }

  ngOnInit() {
    this.gameObservable = this.gameSnapshotService.getGameSnapshot()
    .pipe(filter((snapshot) => this.isSyncEnabled === SyncState.on))
      .subscribe(
        (gameSnapshot: GameSnapshot) => {
          const gamePhase = gameSnapshot.game?.phase;
          const gameState = gameSnapshot.game?.state;
          const gameRound = gameSnapshot.game?.round ?? 0;

          if (this.currentGamePhase !== gamePhase) {
            if (this.maxSeenRound > gameRound) {
              console.log(`Game rollback detected from round ${this.maxSeenRound} to round ${gameRound}, skipping scene switch`)
            } else if (this.maxSeenRound === gameRound && this.currentGamePhase == GamePhase.night && gamePhase == GamePhase.day) {
              console.log(`Game rollback detected from ${this.currentGamePhase} round ${this.maxSeenRound} to ${gamePhase} round ${gameRound}, skipping scene switch`)
            } else {
              console.log(`Switching from ${this.currentGamePhase} round ${this.maxSeenRound} to ${gamePhase} round ${gameRound}`)

              this.currentGamePhase = gamePhase
              this.maxSeenRound = gameRound
              this.switchSceneForGamePhase(gamePhase)
            }
          }
        }
      )
  }

  switchSceneForGamePhase(phase: GamePhase | undefined) {
    if (phase) {
      const newSceneName = this.phaseSceneMap[phase];
      this.setCurrentScene(newSceneName);
    }
  }

  public setCurrentScene(sceneName: string) {
    this.obsService
      .sendCommand(OBSRequest.SetCurrentProgramScene, {
        sceneName: sceneName,
      })
      .subscribe(() => (this.currentScene = sceneName),
      );
  }
}
