import { Component, Input, OnInit } from '@angular/core';
import { Player } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';



@Component({
  selector: 'gamestats',
  templateUrl: './gamestats.component.html',
  styleUrls: ['./gamestats.component.scss']
})
export class GamestatsComponent implements OnInit {  
  nominees$?: Observable<Player[]>;
  checkedBySheriff$?: Observable<Player[]>;
  checkedByDon$?: Observable<Player[]>;
  players$?: Observable<Player[]>;

  // checkedBySheriffNights$?: Observable<number[]>;
  // checkedByDonNights$?: Observable<number[]>;

  constructor(private gameSnapshotService: GamesnapshotService) {
   }

  ngOnInit(): void {
    this.nominees$ = this.gameSnapshotService.getNominees();
    this.checkedBySheriff$ = this.gameSnapshotService.getCheckedBySheriff();
    this.checkedByDon$ = this.gameSnapshotService.getCheckedByDon();
    this.players$ = this.gameSnapshotService.getPlayers();
  }

  trackPlayerById(index:number, player:Player): number {
    return player.id;
  }
}
