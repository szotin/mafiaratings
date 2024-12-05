import { Component, Input, OnInit, SimpleChanges } from '@angular/core';
import { Game, GamePhase, GameState, Player, PlayerRole } from 'src/app/services/gamesnapshot.model';
import { UrlParametersService } from 'src/app/services/url-parameters.service';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

@Component({
  selector: 'player',
  templateUrl: './player.component.html',
  styleUrls: ['./player.component.scss']
})
export class PlayerComponent implements OnInit {
  @Input() player!: Player;
  @Input() game!: Game | null | undefined;

  flipCards: boolean = false;
  showRoles$?: Observable<boolean>;

  private isDayOccured: boolean = false;

  constructor(urlParameterService: UrlParametersService) {
    this.showRoles$ = urlParameterService.getHideRoles$().pipe(map(hideRoles => !hideRoles));
   }

  ngOnInit(): void {
  }

  ngOnChanges(changes: SimpleChanges) {
    // changes.prop contains the old and the new value...

    let game: Game = changes['game'].currentValue;

    // BUG mitigation (remove when fixed) - Remember if we had a day phase to distinguish between 2 states with same API states (moderator selecting roles and blank screen before 1st night)
    if (game.state === GameState.notStarted) {
      this.isDayOccured = false;
    } else {
      this.isDayOccured = this.isDayOccured || game.phase === GamePhase.day;
    }

    this.flipCards = game && game.state !== GameState.notStarted && !(
      game.state == GameState.starting && game.round == 0 && !this.isDayOccured
    );
  }
}
