import { Component, Input, OnInit } from '@angular/core';
import { Observable } from 'rxjs';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';


@Component({
  selector: 'round',
  templateUrl: './round.component.html',
  styleUrls: ['./round.component.scss']
})
export class RoundComponent implements OnInit {
  @Input() logo!: Observable<string | undefined>;
  @Input() stage!: Observable<string | undefined>;
  @Input() round!: Observable<number | undefined>;

  constructor(private gameSnapshotService: GamesnapshotService) {
  }

  ngOnInit(): void {
    this.logo = this.gameSnapshotService.getLogo();
    this.stage = this.gameSnapshotService.getStage();
    this.round = this.gameSnapshotService.getRound();
  }
}
