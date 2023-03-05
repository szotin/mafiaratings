import { Component, OnInit } from '@angular/core';

import { Scene } from '../../models/scene.model';

import { ObsApiService } from '../../services/obs/obs-api.service';
import { OBSRequestTypes } from 'obs-websocket-js';
import { OBSRequest } from '../../services/obs/constants';
import { Source } from 'src/app/models/source.model';
import { CommonService } from '../../services/common.service';

@Component({
  selector: 'obs-controller',
  templateUrl: './controller.component.html',
  styleUrls: ['./controller.component.sass'],
})
export class ControllerComponent implements OnInit {
  public scenes: Scene[] = [];
  public currentScene: Scene = { sceneName: '' };
  constructor(
    private obsApi: ObsApiService,
    private commonService: CommonService
  ) {
  }

  ngOnInit() {
    // obtener lista de escenas
    this.obsApi.getLiveScenes().subscribe({
      next: (data) => {
        console.log(data);
        this.currentScene.sceneName = data.currentProgramSceneName;
        if (data.scenes) {
          this.scenes = data.scenes.reverse();
        } 
      },
      error: (error) => console.log(error),
      complete: () => {
        this.scenes.forEach((scene) => {
          // TODO update screenshots
        });
      },
    });
  }

  public toggleSource($event: any, sceneName: string, sceneItemId: number) {
    this.obsApi.sendCommand(OBSRequest.SetSceneItemEnabled, {
      sceneName: sceneName,
      sceneItemId: sceneItemId,
      sceneItemEnabled: $event.checked,
    });
  }
  
  public getIsActive(sceneName: string) {
    return this.currentScene.sceneName === sceneName;
  }

  public getActiveColor(sceneName: string) {
    return this.currentScene.sceneName === sceneName ? 'accent' : 'primary';
  }

  public setCurrentScene($event: any, sceneName: string) {
    $event.stopPropagation();
    this.obsApi
      .sendCommand(OBSRequest.SetCurrentProgramScene, {
        sceneName: sceneName,
      })
      .subscribe({
        next: () => (this.currentScene.sceneName = sceneName),
      });
    console.log('setCurrentScene');
  }

  public showProperties(scene: any, sceneItem: Source) {
    console.log()
    let request: OBSRequestTypes["GetSceneItemTransform"] = {
      sceneName: scene.sceneName,
      sceneItemId: sceneItem.sceneItemId,
    };
    this.obsApi.sendCommand("GetSceneItemTransform", request).subscribe({
      next: (data) => {
        this.commonService.setRightSidebarData({
          sourceName: sceneItem.sourceName,
          transformData: data
        })
      }
    })
    this.commonService.setIsPropVisible(true);
  }
}
