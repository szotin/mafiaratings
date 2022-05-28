import { HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { ActivatedRoute, Params } from '@angular/router';
import { BehaviorSubject, map, Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class UrlParametersService {

  private urlParameters$: BehaviorSubject<Params> = new BehaviorSubject({});
  private defaultRetryDelay: number = 2000;

  gameSnapshotUrlParams: HttpParams = new HttpParams();
  retryDelay: number = 2000;


  constructor(private activatedRoute: ActivatedRoute) {
    this.activatedRoute.queryParams.subscribe((params: Params) => {
      this.urlParameters$.next(params);

      this.retryDelay = this.getRetryDelay(params);
      this.gameSnapshotUrlParams = this.getGameSnapshotUrlParams(params);
    });

    this.urlParameters$
   }

  private getRetryDelay(params: Params): number {
    const retryDelay = params['retry_delay'];

    if (retryDelay) {
      return retryDelay;
    } else {
      return this.defaultRetryDelay;
    }
  }

  private getGameSnapshotUrlParams(params: Params): HttpParams {
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

  getHideRoles$(): Observable<boolean> {
    return this.urlParameters$.pipe(
      map((it: Params) => {
        const hideRoles: string = it['hide_roles'];

        return (typeof hideRoles !== 'undefined') && hideRoles !== "false";
      }));
  }
}
