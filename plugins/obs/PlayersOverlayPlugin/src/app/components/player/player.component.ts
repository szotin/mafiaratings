import { Component, Input, OnInit, SimpleChanges } from '@angular/core';
import { Game, GamePhase, GameState, Player, PlayerRole } from 'src/app/services/gamesnapshot.model';
import { UrlParametersService } from 'src/app/services/url-parameters.service';

@Component({
  selector: 'player',
  templateUrl: './player.component.html',
  styleUrls: ['./player.component.scss']
})
export class PlayerComponent implements OnInit {
  @Input() player!: Player;
  @Input() game!: Game | null | undefined;

  hideRolesUrlParameter: boolean = false;
  showRoles: boolean = false;

  private isDayOccured: boolean = false;

  constructor(urlParameterService: UrlParametersService) {
    urlParameterService.getHideRoles$()
      .subscribe((it: boolean)=> this.hideRolesUrlParameter = it);
   }

  ngOnInit(): void {
  }

  ngOnChanges(changes: SimpleChanges) {
    // changes.prop contains the old and the new value...
    let player: Player = changes['player'].currentValue;
    if (player.id === 0) {
      player.role = PlayerRole.none;
    }

    let game: Game = changes['game'].currentValue;

    // BUG mitigation (remove when fixed) - Remember if we had a day phase to distinguish between 2 states with same API states (moderator selecting roles and blank screen before 1st night)
    if (game.state === GameState.notStarted) {
      this.isDayOccured = false;
    } else {
      this.isDayOccured = this.isDayOccured || game.phase === GamePhase.day;
    }

    this.showRoles = !this.hideRolesUrlParameter && ((
      game
      && game.state != GameState.notStarted
      && (game.state !== GameState.starting
         || (game.phase === GamePhase.night && (game.round > 0 || this.isDayOccured)) // "starting"
         || (game.phase === GamePhase.day && game.round >= 0)
      )) ?? false);
  }
}
