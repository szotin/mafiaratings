import { Component, Input, OnInit } from '@angular/core';
import { Player } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';
import { Observable } from 'rxjs';

@Component({
  selector: 'gamestats',
  templateUrl: './gamestats.component.html',
  styleUrls: ['./gamestats.component.scss']
})
export class GamestatsComponent implements OnInit {
  nominees$?: Observable<Player[]>;
  checkedBySheriff$?: Observable<Player[]>;
  checkedByDon$?: Observable<Player[]>;
  isOffline$: Observable<boolean>;

  constructor(private gameSnapshotService: GamesnapshotService) {
    this.isOffline$ = this.gameSnapshotService.isOffline$;
  }

  ngOnInit(): void {
    this.nominees$ = this.gameSnapshotService.getNominees();
    this.checkedBySheriff$ = this.gameSnapshotService.getCheckedBySheriff();
    this.checkedByDon$ = this.gameSnapshotService.getCheckedByDon();
  }

  trackPlayerById(index: number, player: Player): number {
    return player.id;
  }
}
