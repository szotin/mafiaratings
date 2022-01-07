import { HttpClient, HttpParams, HttpResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, timer, BehaviorSubject, of } from 'rxjs';
import { retry, share, switchMap, pluck, map, catchError, delay, skip, tap, concat, concatWith, concatMap, retryWhen, delayWhen } from 'rxjs/operators';

import { ActivatedRoute } from '@angular/router';
import { environment } from 'src/environments/environment';
import { GameSnapshot, Game, Player } from './gamesnapshot.model';

@Injectable({
  providedIn: 'root'
})
export class GamesnapshotService {
  private configUrl_prod = '/api/get/current_game.php';
  private configUrl_dev = `http://localhost:8010/proxy${this.configUrl_prod}`;
  private configUrl = environment.production ? this.configUrl_prod : this.configUrl_dev;
  private retryDelay: number = 2000;

  private urlParams: HttpParams = new HttpParams();

  private gameSnapshot$: BehaviorSubject<GameSnapshot> = new BehaviorSubject<GameSnapshot>({ version:0 });
  private timer$: BehaviorSubject<string> = new BehaviorSubject('');

  constructor(private http: HttpClient, private activatedRoute: ActivatedRoute) {

    this.activatedRoute.queryParams.subscribe((params: { [x: string]: any; }) => {
      this.urlParams = this.getUrlParams(params);
    });

    const data$: Observable<GameSnapshot> = this.getGameSnapshotData();

    const whenToRefresh$ = of('').pipe(
      delay(this.retryDelay),
      tap(_ => this.timer$.next('')),
      skip(1),
    );

    const poll$ = data$.pipe(concatWith(whenToRefresh$));

    this.timer$
      .pipe(
        concatMap(_ => poll$),
        retryWhen(errors => errors
                  .pipe(
                      delayWhen(() => timer(2000)),
                      tap(() => console.log('retrying...')))),
        share())
      .subscribe((gameSnapshot: any) => this.setGameSnapshot(gameSnapshot));
   }

  getGameSnapshot() {
     return this.gameSnapshot$;
  }

  getCurrentGame(): Observable<Game | undefined> {
    return this.gameSnapshot$.pipe(
      map((it?: GameSnapshot) => it?.game));
  }

  getPlayers(): Observable<Player[]> {
    return this.getCurrentGame().pipe(
      map((it?: Game) => it?.players ?? []));
  }

  getNominees(): Observable<Player[]> {
    return this.getCurrentGame().pipe(
      map((it?: Game) => it?.nominatedPlayers ?? []));
  }

  getCheckedBySheriff(): Observable<Player[]> {
    return this.getPlayers().pipe(
      map(
        (it: Player[]) => {
          it = it
            .filter((player: Player) => player.checkedBySheriff)
            .sort((left: Player, right: Player) => (left?.checkedBySheriff ?? 0) - (right?.checkedBySheriff ?? 0));

          return it;
        }
          ));
  }

  getCheckedByDon(): Observable<Player[]> {
    return this.getPlayers().pipe(
      map(
        (it: Player[]) => it
          .filter((player: Player) => player.checkedByDon)
          .sort((left: Player, right: Player) => (left?.checkedByDon ?? 0) - (right?.checkedByDon ?? 0))));
  }

  private getGameSnapshotData(): Observable<GameSnapshot> {
    return this.http
      .get<GameSnapshot>(this.configUrl, { observe: 'response', params: this.urlParams })
      .pipe(
        map((it: HttpResponse<GameSnapshot>) => it.body ?? { version:0 }),
        map((it: GameSnapshot) => {
          if (it.game) {
            let players: Player[] = it.game?.players ?? [];
            it.game.nominatedPlayers = it.game?.nominees?.map((nominee: number) => players[nominee-1]) || [];
          }

          return it;
        }));
  }

  private setGameSnapshot(gameSnapshot: GameSnapshot) {
    this.gameSnapshot$.next(gameSnapshot);
  }

  private getUrlParams(params: { [x: string]: any; }) {
    const token = params['token'];
    const moderatorId = params['moderator_id'];
    const gameId = params['game_id'];
    const userId = params['user_id'];
    const apiVersion = params['version'];

    const retryDelay = params['retry_delay'];

    let parsedParams = new HttpParams();

    if (token) {
      parsedParams = parsedParams.append('token', token);
    }

    if (moderatorId) {
      parsedParams = parsedParams.append('moderator_id', moderatorId);
    }

    if (gameId) {
      parsedParams = parsedParams.append('game_id', gameId);
    }

    if (userId) {
      parsedParams = parsedParams.append('user_id', userId);
    }

    if (apiVersion) {
      parsedParams = parsedParams.append('version', apiVersion);
    }

    if (retryDelay) {
      this.retryDelay = retryDelay;
    }

    return parsedParams;
  }
}
