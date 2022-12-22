import { Component, Input, OnInit } from '@angular/core';
import { Player } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';
import { UrlParametersService } from 'src/app/services/url-parameters.service';
import { retry, share, switchMap, pluck, map, catchError, delay, skip, tap, concat, concatWith, concatMap, retryWhen, delayWhen } from 'rxjs/operators';

import { Observable } from 'rxjs';

@Component({
  selector: 'gamestats',
  templateUrl: './gamestats.component.html',
  styleUrls: ['./gamestats.component.scss']
})
export class GamestatsComponent implements OnInit {
  nominees$?: Observable<Player[]>;
  legacyPlayers$?: Observable<Player[]>;
  checkedBySheriff$?: Observable<Player[]>;
  checkedByDon$?: Observable<Player[]>;

  isLegacyPlayers$?: Observable<boolean>;

  hideRoles$?: Observable<boolean>;

  isOffline$: Observable<boolean>;

  constructor(
    private gameSnapshotService: GamesnapshotService,
    private urlParameterService: UrlParametersService) {

    this.isOffline$ = this.gameSnapshotService.isOffline$;
  }

  ngOnInit(): void {
    this.nominees$ = this.gameSnapshotService.getNominees();
    this.checkedBySheriff$ = this.gameSnapshotService.getCheckedBySheriff();
    this.checkedByDon$ = this.gameSnapshotService.getCheckedByDon();
    this.legacyPlayers$ = this.gameSnapshotService.getLegacyPlayers();

    this.isLegacyPlayers$ = this.legacyPlayers$.pipe(
      map((it: Player[])=> {
        console.log(it?.length);
        return it.length > 0 ?? false;
      }));
    
    this.hideRoles$ = this.urlParameterService.getHideRoles$();
  }

  trackPlayerById(index: number, player: Player): number {
    return player.id;
  }
}
