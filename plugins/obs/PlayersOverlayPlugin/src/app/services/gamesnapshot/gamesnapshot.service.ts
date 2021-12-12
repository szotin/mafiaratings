import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { HttpHeaders } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError, retry } from 'rxjs/operators';
import { NumberValueAccessor } from '@angular/forms';
import { GameSnapshot } from './gamesnapshot.model';

@Injectable()
export class GameSnapshotService {
  // configUrl =
  //   'https://mafiaratings.com/api/ops/game.php?op=incomplete_game&user_id=805';

  private configUrl =
    'http://localhost:8010/proxy/api/ops/game.php?op=incomplete_game&user_id=805';

  httpOptions: any;
  constructor(private http: HttpClient) {
    this.httpOptions = {
      headers: new HttpHeaders({
        'Content-Type': 'application/json',
        Authorization: 'Basic ' + btoa('user:pwd'),
      }),
    };
  }

  getGameSnapshot() {
    return this.http.get<GameSnapshot>(this.configUrl, { observe: 'response', headers: this.httpOptions.headers });
  }
}
