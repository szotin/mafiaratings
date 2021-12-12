import { Component, Input, SimpleChanges } from '@angular/core';
import { Player, PlayerRole } from '../../services/gamesnapshot/gamesnapshot.model';

@Component({
  selector: 'player',
  templateUrl: `./player.component.html`,
  styleUrls: [`./player.component.css`],
})
export class PlayerComponent {
  @Input() item: Player;

  ngOnChanges(changes: SimpleChanges) {
    console.log(changes);
    // changes.prop contains the old and the new value...
    let currentValue: Player = changes.item.currentValue;
    if (currentValue.id === 0) {
      currentValue.role = PlayerRole.none;
    }
  }
}
