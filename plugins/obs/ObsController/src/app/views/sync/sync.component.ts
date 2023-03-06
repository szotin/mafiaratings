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
  currentRound: number | undefined;


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
          const gameRound = gameSnapshot.game?.round;

          if (this.currentGamePhase !== gamePhase) {
            console.log(`Switching from ${this.currentGamePhase} to ${gamePhase}` )
            this.currentGamePhase = gamePhase;
            this.switchSceneForGamePhase(gamePhase)
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
