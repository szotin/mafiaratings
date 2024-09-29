import { Component, OnInit } from '@angular/core';
import { Observable } from 'rxjs';
import { Game } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';

@Component({
  selector: 'top-bar',
  templateUrl: './top-bar.component.html',
  styleUrls: ['./top-bar.component.scss']
})
export class TopBarComponent implements OnInit {
  game$?: Observable<Game | undefined>;

  constructor(private gameSnapshotService: GamesnapshotService) {
  }

  ngOnInit(): void {
    this.game$ = this.gameSnapshotService.getCurrentGame();
  }

}
