import { HttpClient, HttpParams, HttpResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Observable } from 'rxjs';
import { environment } from 'src/environments/environment';
import { GameSnapshot } from './gamesnapshot.model';
import { retry, share, Subject, Subscription, switchMap, timer } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class GamesnapshotService {
  private configUrl_prod = '/api/get/current_game.php';
  private configUrl_dev = `http://localhost:8010/proxy${this.configUrl_prod}`;
  private configUrl = environment.production ? this.configUrl_prod : this.configUrl_dev;

  private urlParams: HttpParams = new HttpParams();
  private timeInterval: Subscription | undefined;

  constructor(private http: HttpClient, private activatedRoute: ActivatedRoute) {
    this.activatedRoute.queryParams.subscribe((params: { [x: string]: any; }) => {
      this.urlParams = this.getUrlParams(params);
    });
   }

   getGameSnapshot(): Observable<HttpResponse<GameSnapshot>> {
    return this.http
      .get<GameSnapshot>(this.configUrl, { observe: 'response', params: this.urlParams });
  }

  private getUrlParams(params: { [x: string]: any; }) {
    const token = params['token'];
    const moderatorId = params['moderator_id'];
    const gameId = params['game_id'];
    const userId = params['user_id'];
    const apiVersion = params['version'];

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

    return parsedParams;
  }
}
