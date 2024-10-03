import { Component, Input, OnInit } from '@angular/core';
import { Observable } from 'rxjs';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';


@Component({
  selector: 'round',
  templateUrl: './round.component.html',
  styleUrls: ['./round.component.scss']
})
export class RoundComponent implements OnInit {
  @Input() round!: Observable<number | undefined>;

  constructor(private gameSnapshotService: GamesnapshotService) {
  }

  ngOnInit(): void {
    this.round = this.gameSnapshotService.getRound();
  }
}
