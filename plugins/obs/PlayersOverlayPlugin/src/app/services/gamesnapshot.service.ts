import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { GameSnapshot } from './gamesnapshot.model';

@Injectable({
  providedIn: 'root'
})
export class GamesnapshotService {
  // configUrl =
  //   'https://mafiaratings.com/api/ops/game.php?op=incomplete_game&user_id=805';

  private configUrl =
    'http://localhost:8010/proxy/api/get/current_game.php';

  private urlParams: HttpParams = new HttpParams();

  constructor(private http: HttpClient, private activatedRoute: ActivatedRoute) {

    this.activatedRoute.queryParams.subscribe(params => {
      let token = params['token'];
      let moderatorId = params['moderator_id'];
      let gameId = params['game_id'];
      let userId = params['user_id'];
      let apiVersion = params['version'];

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

      this.urlParams = parsedParams;
      console.log(parsedParams);
    });
   }

   getGameSnapshot() {

    return this.http.get<GameSnapshot>(this.configUrl, { observe: 'response', params: this.urlParams });
  }
}
