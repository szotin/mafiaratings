import { Component, Input, OnInit, SimpleChanges } from '@angular/core';
import { Game, GamePhase, GameState, Player, PlayerRole } from 'src/app/services/gamesnapshot.model';

@Component({
  selector: 'player',
  templateUrl: './player.component.html',
  styleUrls: ['./player.component.scss']
})
export class PlayerComponent implements OnInit {
  @Input() player!: Player;
  @Input() game!: Game | undefined;
  showRoles: boolean = false;

  constructor() { }

  ngOnInit(): void {
  }

  ngOnChanges(changes: SimpleChanges) {
    console.log(changes);
    // changes.prop contains the old and the new value...
    let player: Player = changes['player'].currentValue;
    if (player.id === 0) {
      player.role = PlayerRole.none;
    }
    let game: Game = changes['game'].currentValue;

    this.showRoles = (game && game.state != GameState.notStarted && (game.state !== "starting" || (game.round > 0 && game.phase !== GamePhase.night))) ?? false;
    console.log(this.showRoles);
  }

}
