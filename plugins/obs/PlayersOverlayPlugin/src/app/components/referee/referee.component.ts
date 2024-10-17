import { Component, Input, OnInit } from '@angular/core';
import { Observable } from 'rxjs';
import { Referee } from 'src/app/services/gamesnapshot.model';
import { GamesnapshotService } from 'src/app/services/gamesnapshot.service';

@Component({
  selector: 'referee',
  templateUrl: './referee.component.html',
  styleUrls: ['./referee.component.scss']
})
export class RefereeComponent implements OnInit {
  @Input() referee!: Observable<Referee | undefined>;

  constructor(private gameSnapshotService: GamesnapshotService) {
  }

  ngOnInit(): void {
    this.referee = this.gameSnapshotService.getReferee();
  }

}
