import { HttpClient, HttpResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, timer, BehaviorSubject, of } from 'rxjs';
import { share, map, delay, skip, tap, concatWith, concatMap, retryWhen, delayWhen } from 'rxjs/operators';

import { environment } from 'src/environments/environment';
import { GameSnapshot, Game, Player } from './gamesnapshot.model';
import { UrlParametersService } from './url-parameters.service';

@Injectable({
  providedIn: 'root'
})
export class GamesnapshotService {
  private configUrl_prod = '/api/get/current_game.php';
  private configUrl_dev = `http://localhost:8010/proxy${this.configUrl_prod}`;
  private configUrl = environment.production ? this.configUrl_prod : this.configUrl_dev;

  private gameSnapshot$: BehaviorSubject<GameSnapshot> = new BehaviorSubject<GameSnapshot>({ version:0 });
  private timer$: BehaviorSubject<string> = new BehaviorSubject('');
  isOffline$: BehaviorSubject<boolean> = new BehaviorSubject<boolean>(false);

  constructor(private http: HttpClient, private urlParameterService: UrlParametersService) {

    // Observable with game snapshot data
    const data$: Observable<GameSnapshot> = this.getGameSnapshotData();

    // Observable to signal when to refresh data from server
    const whenToRefresh$ = of('').pipe(
      delay(this.urlParameterService.retryDelay),
      tap(_ => this.timer$.next('')),
      skip(1),
    );

    // Combining 2 observables
    const poll$ = data$.pipe(concatWith(whenToRefresh$));

    this.timer$
      .pipe(
        concatMap(_ => poll$),
        retryWhen(errors => errors
                  .pipe(
                      delayWhen(() => timer(this.urlParameterService.retryDelay)),
                      tap(() => {
                        console.log('retrying...');
                        this.isOffline$.next(true);
                      }))),
        share())
      .subscribe((gameSnapshot: any) => {
        this.setGameSnapshot(gameSnapshot);
        this.isOffline$.next(false);
      });
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

  getLegacyPlayers(): Observable<Player[]> {
    return this.getCurrentGame().pipe(
      map((it?: Game) => it?.legacyPlayers ?? []));
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
      .get<GameSnapshot>(this.configUrl, { observe: 'response', params: this.urlParameterService.gameSnapshotUrlParams })
      .pipe(
        map((it: HttpResponse<GameSnapshot>) => it.body ?? { version:0 }),
        map((it: GameSnapshot) => {
          if (it.game) {
            let players: Player[] = it.game?.players ?? [];
            it.game.nominatedPlayers = it.game?.nominees?.map((nominee: number) => players[nominee-1]) || [];

            it.game.legacyPlayers = it.game?.legacy?.map((it: number)=> players[it-1]) || [];
          }

          return it;
        }));
  }

  private setGameSnapshot(gameSnapshot: GameSnapshot) {
    this.gameSnapshot$.next(gameSnapshot);
  }
}
