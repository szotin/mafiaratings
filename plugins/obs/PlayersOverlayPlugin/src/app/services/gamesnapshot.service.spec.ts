import { TestBed } from '@angular/core/testing';

import { GamesnapshotService } from './gamesnapshot.service';

describe('GamesnapshotService', () => {
  let service: GamesnapshotService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(GamesnapshotService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
