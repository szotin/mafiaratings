import { Component, Input, OnInit, SimpleChanges } from '@angular/core';
import { Player, PlayerRole } from 'src/app/services/gamesnapshot.model';

@Component({
  selector: 'player',
  templateUrl: './player.component.html',
  styleUrls: ['./player.component.scss']
})
export class PlayerComponent implements OnInit {
  @Input()
  item!: Player;

  constructor() { }

  ngOnInit(): void {
  }

  ngOnChanges(changes: SimpleChanges) {
    // console.log(changes);
    // changes.prop contains the old and the new value...
    let currentValue: Player = changes['item'].currentValue;
    if (currentValue.id === 0) {
      currentValue.role = PlayerRole.none;
    }
  }

}
