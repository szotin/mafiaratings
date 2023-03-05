import { Injectable } from '@angular/core'
import OBSWebSocket from 'obs-websocket-js'
import { BehaviorSubject, Observable, ReplaySubject } from 'rxjs'
import { OBSEvents, OBSRequest } from './constants'

export interface ObsUrl {
  protocol?: string
  host: string
  port?: string
}

@Injectable({providedIn: 'root'})
export class ObsApiService {
  private idParams = { rpcVersion: 1 }
  private obsWS: OBSWebSocket
  private scenes$: BehaviorSubject<any> = new BehaviorSubject<any>({ });

  constructor() {
    this.obsWS = new OBSWebSocket()
  }

  login(obsUrl: ObsUrl, password: string): Observable<any> {
    const action = new ReplaySubject(1)
    let url = this._genUrl(obsUrl)
    this.obsWS
      .connect(url, password, this.idParams)
      .then((data: any) => {
         this.getScenes().subscribe(scenes => this.scenes$.next(scenes));
         this.obsWS.on(OBSEvents.CurrentProgramSceneChanged, (result) => {
           console.log(result)
           this.getScenes().subscribe(scenes => this.scenes$.next(scenes))
          })
         return action.next(data)
        })
      .catch((err: any) => action.error(err))
      .finally(() => action.complete())
    return action
  }

  public sendCommand(command: any, params?: any): Observable<any> {
    const action = new ReplaySubject(1)

    this.obsWS
      .call(command, params || {})
      .then((data: any) => action.next(data))
      .catch((error: any) => action.error(error))
      .finally(() => action.complete())

    return action
  }

  private _genUrl(obsUrl: ObsUrl): string {
    let { protocol, host, port } = obsUrl
    let url: ObsUrl = {
      protocol: this._chkString(protocol) ? protocol : '',
      host: this._chkString(host) ? host : '',
      port: this._chkString(port) ? port : ''
    }
    return url.protocol + '://' + url.host + ':' + url.port
  }
  private _chkString(val: string | null | undefined) {
    return val !== '' && val !== undefined && val !== null
  }

  public getScenes(): Observable<any> {
    return this.sendCommand(OBSRequest.GetSceneList)
  }

  public getLiveScenes(): Observable<any> {
    return this.scenes$.asObservable();

  }

  public onConnectionClosed(): Observable<any> {
    const action = new ReplaySubject(1)
    this.obsWS.on(OBSEvents.ConnectionClosed, () => {
      action.next('saliendo')
      action.complete()
    })
    return action
  }
}
