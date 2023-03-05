import { Injectable } from '@angular/core';
import { Observable, Subject } from 'rxjs';

@Injectable({providedIn: 'root'})
export class CommonService {

  private isPropVisible$: Subject<any> = new Subject<any>();
  private rightSidebarData$: Subject<unknown> = new Subject<unknown>;

  getRightSidebarData(): Observable<any> {
    return this.rightSidebarData$.asObservable();
  }
  isPropVisible(): Observable<boolean> {
    return this.isPropVisible$.asObservable();
  }
  setRightSidebarData(data: unknown){
    this.rightSidebarData$.next(data);
    this.setIsPropVisible(true);
  }
  setIsPropVisible(data: boolean): void {
    this.isPropVisible$.next(data)
  }
}
