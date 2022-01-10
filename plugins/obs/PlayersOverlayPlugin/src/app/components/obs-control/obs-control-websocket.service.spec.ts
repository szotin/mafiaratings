import { TestBed } from '@angular/core/testing';

import { ObsControlWebsocketService } from './obs-control-websocket.service';

describe('ObsControlWebsocketService', () => {
  let service: ObsControlWebsocketService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ObsControlWebsocketService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
