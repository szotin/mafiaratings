import { Component, OnInit } from '@angular/core';
import { GameSnapshot } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';
import { retry, share, Subject, Subscription, switchMap, timer } from 'rxjs';


@Component({
  selector: 'gamestats',
  templateUrl: './gamestats.component.html',
  styleUrls: ['./gamestats.component.scss']
})
export class GamestatsComponent implements OnInit {
  gameSnapshot: GameSnapshot | null | undefined;
  private timeInterval: Subscription | undefined;

  constructor(private gameSnapshotService: GamesnapshotService) { }

  ngOnInit(): void {
  }

}
